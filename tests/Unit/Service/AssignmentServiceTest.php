<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Model\User;
use App\Repository\Json\JsonTicketRepository;
use App\Repository\Json\JsonUserRepository;
use App\Service\AssignmentService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

/** FR-04: yeni talep, aktif iş yükü en az olan personele atanır. */
final class AssignmentServiceTest extends TestCase
{
    use TempStoreTrait;

    private JsonUserRepository $users;
    private JsonTicketRepository $tickets;
    private AssignmentService $service;
    private \App\Core\Storage\JsonStore $store;

    protected function setUp(): void
    {
        $store = $this->makeStore();
        $this->store = $store;
        $this->users = new JsonUserRepository($store);
        $this->tickets = new JsonTicketRepository($store);
        $this->service = new AssignmentService($this->users, $this->tickets);
    }

    private function agent(string $no): int
    {
        return (int) $this->users->save(new User(null, $no, 'x', Role::Agent, 'Ad', 'Soyad', 'S', 'C'))->id;
    }

    private function giveTickets(int $agentId, int $count, TicketStatus $status = TicketStatus::Taken): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->tickets->save(new Ticket(null, 99, $agentId, 1, 'T', $status, 2, 2, 0,
                new DateTimeImmutable('2026-11-09 10:00'), new DateTimeImmutable('2026-11-09 12:00'), null));
        }
    }

    public function test_picks_least_loaded(): void
    {
        $p1 = $this->agent('P000001');
        $p2 = $this->agent('P000002');
        $p3 = $this->agent('P000003');
        $this->giveTickets($p1, 3);
        $this->giveTickets($p2, 1);
        $this->giveTickets($p3, 2, TicketStatus::InReview);

        self::assertSame($p2, $this->service->pickAgent()?->id);
    }

    public function test_tie_picks_lowest_id(): void
    {
        $p1 = $this->agent('P000001');
        $p2 = $this->agent('P000002');
        $this->giveTickets($p1, 1);
        $this->giveTickets($p2, 1);

        self::assertSame($p1, $this->service->pickAgent()?->id);
    }

    public function test_agent_without_tickets_counts_zero(): void
    {
        $p1 = $this->agent('P000001');
        $p2 = $this->agent('P000002');
        $this->giveTickets($p1, 1);

        self::assertSame($p2, $this->service->pickAgent()?->id);
    }

    public function test_no_agents_returns_null(): void
    {
        $this->users->save(new User(null, 'M100001', 'x', Role::Customer, 'A', 'B', 'S', 'C'));

        self::assertNull($this->service->pickAgent());
    }

    public function test_resolved_and_closed_do_not_count(): void
    {
        $p1 = $this->agent('P000001');
        $p2 = $this->agent('P000002');
        $this->giveTickets($p1, 5, TicketStatus::Resolved);
        $this->giveTickets($p1, 5, TicketStatus::Closed);
        $this->giveTickets($p2, 1);

        self::assertSame($p1, $this->service->pickAgent()?->id);
    }

    private function capacityService(): AssignmentService
    {
        $logger = new \App\Service\AuditLogger(new \App\Repository\Json\JsonAuditLogRepository($this->store), new \App\Core\Clock\FixedClock(new DateTimeImmutable('2026-11-24 10:00')));
        $settings = new \App\Service\SettingsService(new \App\Repository\Json\JsonSettingsRepository($this->store), $logger);
        $staff = new \App\Service\StaffService($this->users, new \App\Service\AuthService($this->users), $settings, $logger);

        return new AssignmentService($this->users, $this->tickets, $staff);
    }

    public function test_inactive_and_full_agents_are_skipped(): void
    {
        $p1 = $this->agent('P000001');
        $p2 = $this->agent('P000002');
        $p3 = $this->agent('P000003');
        $inactive = $this->users->findById($p1);
        self::assertNotNull($inactive);
        $inactive->active = false;
        $this->users->save($inactive);
        $full = $this->users->findById($p2);
        self::assertNotNull($full);
        $full->capacity = 1;
        $this->users->save($full);
        $this->giveTickets($p2, 1);
        $this->giveTickets($p3, 3);

        self::assertSame($p3, $this->capacityService()->pickAgent()?->id);
    }

    public function test_all_full_leaves_ticket_in_pool(): void
    {
        $p1 = $this->agent('P000001');
        $agent = $this->users->findById($p1);
        self::assertNotNull($agent);
        $agent->capacity = 2;
        $this->users->save($agent);
        $this->giveTickets($p1, 2, TicketStatus::InReview);

        self::assertNull($this->capacityService()->pickAgent());
        self::assertSame(2, $this->capacityService()->capacityOf($agent));
    }
}
