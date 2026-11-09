<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Clock\FixedClock;
use App\Model\Enum\Priority;
use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Model\User;
use App\Repository\Json\JsonCalendarRepository;
use App\Repository\Json\JsonTicketRepository;
use App\Repository\Json\JsonUserRepository;
use App\Service\ReportService;
use App\Service\SlaCalculatorService;
use App\Service\SlaStatusService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

/** Aşama 2 özet raporu (hocanın "en az bir özet/istatistik ekranı" şartı). */
final class ReportServiceTest extends TestCase
{
    use TempStoreTrait;

    private JsonTicketRepository $tickets;
    private JsonUserRepository $users;
    private ReportService $service;
    private int $p1;
    private int $p2;

    protected function setUp(): void
    {
        $store = $this->makeStore();
        $this->tickets = new JsonTicketRepository($store);
        $this->users = new JsonUserRepository($store);
        $clock = new FixedClock(self::at('2026-11-10 09:30')); // Salı
        $calculator = new SlaCalculatorService(new JsonCalendarRepository(__DIR__ . '/../../fixtures/calendar.json'));
        $this->service = new ReportService($this->tickets, $this->users, new SlaStatusService($calculator, $clock), $calculator);

        $this->p1 = (int) $this->users->save(new User(null, 'P000001', 'x', Role::Agent, 'Zeynep', 'Kaya', 'S', 'C'))->id;
        $this->p2 = (int) $this->users->save(new User(null, 'P000002', 'x', Role::Agent, 'Murat', 'Demir', 'S', 'C'))->id;
    }

    private static function at(string $s): DateTimeImmutable
    {
        return new DateTimeImmutable($s, new DateTimeZone('Europe/Istanbul'));
    }

    private function add(TicketStatus $status, ?int $agent, string $created, string $deadline, ?string $resolved = null, Priority $priority = Priority::Normal): void
    {
        $this->tickets->save(new Ticket(null, 99, $agent, 1, 'T', $status, $priority, $priority, 0,
            self::at($created), self::at($deadline), $resolved === null ? null : self::at($resolved)));
    }

    public function test_summary_counts_and_average(): void
    {
        $this->add(TicketStatus::Closed, $this->p1, '2026-11-09 09:00', '2026-11-09 09:30', '2026-11-09 10:00');            // 60 dk, geç çözüldü
        $this->add(TicketStatus::Resolved, $this->p2, '2026-11-09 09:00', '2026-11-09 17:00', '2026-11-09 12:00');          // 180 dk
        $this->add(TicketStatus::Taken, $this->p1, '2026-11-09 09:00', '2026-11-10 11:00', null, Priority::High);           // %85 sarı
        $this->add(TicketStatus::InReview, $this->p1, '2026-11-09 09:00', '2026-11-09 12:00', null, Priority::Urgent);      // aşıldı
        $this->add(TicketStatus::Pending, null, '2026-11-10 09:00', '2026-11-11 09:00');                                     // normal
        $this->add(TicketStatus::Cancelled, null, '2026-11-09 09:00', '2026-11-10 09:00');

        $summary = $this->service->summary();

        self::assertSame(['pending' => 1, 'taken' => 1, 'in_review' => 1, 'resolved' => 1, 'closed' => 1, 'cancelled' => 1], $summary['byStatus']);
        self::assertSame([1 => 0, 2 => 4, 3 => 1, 4 => 1], $summary['byPriority']);
        self::assertSame([3], array_map(static fn (Ticket $t): ?int => $t->id, $summary['warning']));
        self::assertSame([4], array_map(static fn (Ticket $t): ?int => $t->id, $summary['breached']));
        self::assertSame(120, $summary['avgResolutionMinutes']);
        self::assertSame(2, $summary['resolvedCount']);
        self::assertSame(50, $summary['metPercent'], 'Çözülen 2 talebin 1i süresinde çözüldü.');
        self::assertSame(
            [['Zeynep Kaya', 2], ['Murat Demir', 0]],
            array_map(static fn (array $w): array => [$w['agent']->fullName(), $w['active']], $summary['workload']),
        );
        self::assertSame(6, $summary['total']);
    }

    public function test_summary_with_no_resolved_has_null_average(): void
    {
        $this->add(TicketStatus::Pending, null, '2026-11-10 09:00', '2026-11-11 09:00');

        $summary = $this->service->summary();

        self::assertNull($summary['avgResolutionMinutes']);
        self::assertNull($summary['metPercent']);
        self::assertSame(0, $summary['resolvedCount']);
    }
}
