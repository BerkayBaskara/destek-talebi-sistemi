<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Clock\FixedClock;
use App\Exception\AuthorizationException;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Model\Category;
use App\Model\Enum\Priority;
use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\SlaPolicy;
use App\Model\Ticket;
use App\Model\TicketLog;
use App\Model\User;
use App\Repository\Json\JsonCalendarRepository;
use App\Repository\Json\JsonCategoryRepository;
use App\Repository\Json\JsonReplyRepository;
use App\Repository\Json\JsonSlaPolicyRepository;
use App\Repository\Json\JsonTicketLogRepository;
use App\Repository\Json\JsonTicketRepository;
use App\Repository\Json\JsonUserRepository;
use App\Service\AssignmentService;
use App\Service\Observer\TicketLogObserver;
use App\Service\Observer\TicketObserver;
use App\Service\PriorityDetector;
use App\Service\SlaCalculatorService;
use App\Service\TicketService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

final class TicketServiceTest extends TestCase
{
    use TempStoreTrait;

    private FixedClock $clock;
    private JsonUserRepository $users;
    private JsonTicketRepository $tickets;
    private JsonTicketLogRepository $logs;
    private JsonReplyRepository $replies;
    private TicketService $service;
    private User $customer;
    private User $otherCustomer;
    private User $admin;

    protected function setUp(): void
    {
        $store = $this->makeStore();
        $this->clock = new FixedClock(self::at('2026-11-13 17:30'));
        $this->users = new JsonUserRepository($store);
        $this->tickets = new JsonTicketRepository($store);
        $this->logs = new JsonTicketLogRepository($store);
        $this->replies = new JsonReplyRepository($store);
        $categories = new JsonCategoryRepository($store);
        $policies = new JsonSlaPolicyRepository($store);

        $categories->save(new Category(null, 'Donanım'));
        $categories->save(new Category(null, 'Ağ / Erişim'));
        $policies->save(new SlaPolicy(null, 1, Priority::Urgent, 4));
        $policies->save(new SlaPolicy(null, 1, Priority::Normal, 16));

        $this->service = new TicketService(
            $this->tickets,
            $this->replies,
            $categories,
            $policies,
            new SlaCalculatorService(new JsonCalendarRepository(__DIR__ . '/../../fixtures/calendar.json')),
            new PriorityDetector([4 => ['çöktü', 'çalışmıyor'], 3 => ['yavaş']]),
            new AssignmentService($this->users, $this->tickets),
            $this->clock,
        );
        $this->service->attachObserver(new TicketLogObserver($this->logs, $this->clock));

        $this->customer = $this->user('M100001', Role::Customer);
        $this->otherCustomer = $this->user('M100002', Role::Customer);
        $this->admin = $this->user('Y000001', Role::Admin);
    }

    private function tempStore(): \App\Core\Storage\JsonStore
    {
        return new \App\Core\Storage\JsonStore($this->tempDir);
    }

    private static function at(string $s): DateTimeImmutable
    {
        return new DateTimeImmutable($s, new DateTimeZone('Europe/Istanbul'));
    }

    private function user(string $no, Role $role): User
    {
        return $this->users->save(new User(null, $no, 'x', $role, 'Ad', $no, 'S', 'C'));
    }

    /** @param array<string, mixed> $override */
    private function create(array $override = [], ?User $creator = null): Ticket
    {
        return $this->service->createTicket($creator ?? $this->customer, $override + [
            'category_id' => 1,
            'priority' => Priority::Normal->value,
            'title' => 'Yazıcıdan çıktı alamıyorum',
            'description' => 'Muhasebe yazıcısı kağıt sıkıştırıyor.',
        ]);
    }

    public function test_create_computes_deadline_with_policy(): void
    {
        $ticket = $this->create(['priority' => Priority::Urgent->value]);

        self::assertSame('2026-11-16 13:30', $ticket->deadline->format('Y-m-d H:i'));
        self::assertSame('2026-11-13 17:30', $ticket->createdAt->format('Y-m-d H:i'));
    }

    public function test_create_without_policy_uses_priority_default(): void
    {
        $ticket = $this->create(['category_id' => 2, 'priority' => Priority::Urgent->value]);

        // Kategori 2 için kural yok → Acil varsayılanı 4 sa
        self::assertSame('2026-11-16 13:30', $ticket->deadline->format('Y-m-d H:i'));
    }

    public function test_create_uses_keyword_priority(): void
    {
        $ticket = $this->create(['title' => 'Muhasebe sunucusu çöktü']);

        self::assertSame(Priority::Urgent, $ticket->priority);
        self::assertSame(Priority::Normal, $ticket->customerPriority);
        self::assertTrue($ticket->priorityRaised());
    }

    public function test_description_is_saved_as_first_reply(): void
    {
        $ticket = $this->create();

        $replies = $this->replies->findByTicket((int) $ticket->id);
        self::assertCount(1, $replies);
        self::assertSame('Muhasebe yazıcısı kağıt sıkıştırıyor.', $replies[0]->message);
        self::assertSame($this->customer->id, $replies[0]->userId);
    }

    public function test_create_auto_assigns_least_loaded_and_logs(): void
    {
        $p1 = $this->user('P000001', Role::Agent);
        $p2 = $this->user('P000002', Role::Agent);
        $this->create(); // P1'e gider

        $ticket = $this->create();

        self::assertSame(TicketStatus::Taken, $ticket->status);
        self::assertSame($p2->id, $ticket->agentId);
        $logs = $this->logs->findByTicket((int) $ticket->id);
        self::assertSame(
            [[null, 'pending', $this->customer->id], ['pending', 'taken', TicketLog::SYSTEM_USER]],
            array_map(static fn (TicketLog $l): array => [$l->oldStatus?->value, $l->newStatus->value, $l->userId], $logs),
        );
        self::assertNotNull($p1->id);
    }

    public function test_create_without_agents_stays_in_pool(): void
    {
        $ticket = $this->create();

        self::assertSame(TicketStatus::Pending, $ticket->status);
        self::assertNull($ticket->agentId);
        self::assertCount(1, $this->tickets->getPoolTickets());
    }

    public function test_create_validation_errors(): void
    {
        try {
            $this->create(['title' => ' ', 'category_id' => 99, 'priority' => 9, 'description' => 'kısa']);
            self::fail('Doğrulama hatası bekleniyordu.');
        } catch (ValidationException $e) {
            self::assertSame('Konu başlığı 3-150 karakter olmalı.', $e->errors()['title']);
            self::assertSame('Geçerli bir kategori seçin.', $e->errors()['category_id']);
            self::assertSame('Geçerli bir öncelik seçin.', $e->errors()['priority']);
            self::assertSame('Açıklama 10-5000 karakter olmalı.', $e->errors()['description']);
        }
        self::assertSame([], $this->tickets->findAll());
    }

    public function test_agent_cannot_create_ticket(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->create([], $this->user('P000001', Role::Agent));
    }

    public function test_take_twice_second_conflicts(): void
    {
        $ticket = $this->create();
        $p1 = $this->user('P000001', Role::Agent);
        $p2 = $this->user('P000002', Role::Agent);

        $taken = $this->service->take((int) $ticket->id, $p1);
        self::assertSame(TicketStatus::Taken, $taken->status);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Bu talep az önce başka bir personel tarafından alındı.');
        $this->service->take((int) $ticket->id, $p2);
    }

    public function test_take_missing_ticket_is_not_found(): void
    {
        $this->expectException(NotFoundException::class);
        $this->service->take(404, $this->user('P000001', Role::Agent));
    }

    public function test_customer_cannot_resolve(): void
    {
        $this->user('P000001', Role::Agent);
        $ticket = $this->create();

        $this->expectException(AuthorizationException::class);
        $this->service->perform((int) $ticket->id, $this->customer, 'resolve');
    }

    public function test_other_agent_cannot_start_review(): void
    {
        $this->user('P000001', Role::Agent);
        $ticket = $this->create();

        $this->expectException(AuthorizationException::class);
        $this->service->perform((int) $ticket->id, $this->user('P000002', Role::Agent), 'start_review');
    }

    public function test_full_flow_resolve_sets_resolved_at_and_approve_closes(): void
    {
        $agent = $this->user('P000001', Role::Agent);
        $id = (int) $this->create()->id;

        $this->service->perform($id, $agent, 'start_review');
        $this->clock->advance('+1 hour');
        $resolved = $this->service->perform($id, $agent, 'resolve');
        self::assertSame(TicketStatus::Resolved, $resolved->status);
        self::assertSame('2026-11-13 18:30', $resolved->resolvedAt?->format('Y-m-d H:i'));

        $closed = $this->service->perform($id, $this->customer, 'approve');
        self::assertSame(TicketStatus::Closed, $closed->status);
        self::assertCount(5, $this->logs->findByTicket($id));
    }

    public function test_reject_increments_attempt_and_returns_to_review(): void
    {
        $agent = $this->user('P000001', Role::Agent);
        $id = (int) $this->create()->id;
        $this->service->perform($id, $agent, 'start_review');
        $this->service->perform($id, $agent, 'resolve');

        $rejected = $this->service->perform($id, $this->customer, 'reject');

        self::assertSame(TicketStatus::InReview, $rejected->status);
        self::assertSame(1, $rejected->attemptCount);
        self::assertNull($rejected->resolvedAt);
    }

    public function test_customer_cancels_pending_but_not_others_ticket(): void
    {
        $id = (int) $this->create()->id;

        try {
            $this->service->perform($id, $this->otherCustomer, 'cancel');
            self::fail('Başka müşteri iptal edememeli.');
        } catch (AuthorizationException) {
        }

        self::assertSame(TicketStatus::Cancelled, $this->service->perform($id, $this->customer, 'cancel')->status);
    }

    public function test_unknown_action_is_rejected(): void
    {
        $id = (int) $this->create()->id;

        $this->expectException(ValidationException::class);
        $this->service->perform($id, $this->customer, 'sil');
    }

    public function test_move_on_kanban(): void
    {
        $agent = $this->user('P000001', Role::Agent);
        $id = (int) $this->create()->id;

        self::assertSame(TicketStatus::InReview, $this->service->moveOnKanban($id, $agent, TicketStatus::InReview)->status);
        self::assertSame(TicketStatus::Resolved, $this->service->moveOnKanban($id, $agent, TicketStatus::Resolved)->status);

        $this->expectException(\App\Model\State\InvalidTransitionException::class);
        $this->service->moveOnKanban($id, $agent, TicketStatus::Taken);
    }

    public function test_reply_rules(): void
    {
        $agent = $this->user('P000001', Role::Agent);
        $id = (int) $this->create()->id;

        $reply = $this->service->addReply($id, $agent, '  Merhaba, inceliyorum.  ');
        self::assertSame('Merhaba, inceliyorum.', $reply->message);

        try {
            $this->service->addReply($id, $this->otherCustomer, 'Ben de yazayım');
            self::fail('Başka müşteri yazamamalı.');
        } catch (AuthorizationException) {
        }

        $this->service->perform($id, $agent, 'start_review');
        $this->service->perform($id, $agent, 'resolve');
        $this->service->perform($id, $this->customer, 'approve');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Kapalı veya iptal edilmiş talebe yanıt yazılamaz.');
        $this->service->addReply($id, $this->customer, 'Bir şey daha');
    }

    public function test_find_visible_rules(): void
    {
        $poolTicket = $this->create();
        $agent = $this->user('P000001', Role::Agent);
        $assigned = $this->create();
        $otherAgent = $this->user('P000002', Role::Agent);

        self::assertSame($poolTicket->id, $this->service->findVisible((int) $poolTicket->id, $otherAgent)->id, 'Havuzdaki talebi her personel görür.');
        self::assertSame($assigned->id, $this->service->findVisible((int) $assigned->id, $agent)->id);
        self::assertSame($assigned->id, $this->service->findVisible((int) $assigned->id, $this->admin)->id);

        try {
            $this->service->findVisible((int) $assigned->id, $otherAgent);
            self::fail('Başkasına atanmış talep görünmemeli.');
        } catch (AuthorizationException) {
        }
        try {
            $this->service->findVisible((int) $assigned->id, $this->otherCustomer);
            self::fail('Başka müşterinin talebi görünmemeli.');
        } catch (AuthorizationException) {
        }

        $this->expectException(NotFoundException::class);
        $this->service->findVisible(999, $this->admin);
    }

    public function test_search_filters_by_status_and_text_and_paginates(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->clock->advance('+1 minute');
            $this->create(['title' => $i % 2 === 0 ? "İNTERNET sorunu $i" : "Yazıcı $i"]);
        }
        $this->create(['title' => 'Başkasının talebi'], $this->otherCustomer);
        $this->service->perform(1, $this->customer, 'cancel');

        $page1 = $this->service->searchForCustomer($this->customer, null, '', 1);
        self::assertSame(12, $page1['total']);
        self::assertSame(2, $page1['pages']);
        self::assertCount(10, $page1['items']);
        self::assertSame(12, $page1['items'][0]->id, 'Yeniden eskiye sıralı olmalı.');

        self::assertCount(2, $this->service->searchForCustomer($this->customer, null, '', 2)['items']);
        self::assertSame(6, $this->service->searchForCustomer($this->customer, null, 'internet', 1)['total']);
        self::assertSame(1, $this->service->searchForCustomer($this->customer, TicketStatus::Cancelled, '', 1)['total']);
        self::assertSame([7], array_map(static fn (Ticket $t): ?int => $t->id, $this->service->searchForCustomer($this->customer, null, '#7', 1)['items']));
    }

    public function test_search_folds_turkish_and_ascii_i(): void
    {
        $this->create(['title' => 'Internet yok']);
        $this->create(['title' => 'IP adresi çakışıyor']);

        self::assertSame(1, $this->service->searchForCustomer($this->customer, null, 'internet', 1)['total']);
        self::assertSame(1, $this->service->searchForCustomer($this->customer, null, 'İNTERNET', 1)['total']);
        self::assertSame(1, $this->service->searchForCustomer($this->customer, null, 'ip', 1)['total']);
    }

    public function test_perform_on_stale_ticket_conflicts_instead_of_overwriting(): void
    {
        $agent = $this->user('P000001', Role::Agent);
        $this->tickets->save(new Ticket(null, (int) $this->customer->id, null, 1, 'Havuzdaki talep', TicketStatus::Pending,
            Priority::Normal, Priority::Normal, 0, self::at('2026-11-13 10:00'), self::at('2026-11-14 10:00'), null));

        // Müşteri iptal ekranını açtığında talep Bekleniyor'du; bu arada personel talebi aldı.
        $stale = new class ($this->tickets) implements \App\Repository\TicketRepositoryInterface {
            private ?Ticket $snapshot;

            public function __construct(private readonly JsonTicketRepository $inner)
            {
                $this->snapshot = $inner->findById(1);
            }

            public function findById(int $id): ?Ticket
            {
                return $this->snapshot !== null ? clone $this->snapshot : null; // eski kopya
            }

            public function save(Ticket $ticket): Ticket { return $this->inner->save($ticket); }
            public function saveIfStatus(Ticket $ticket, TicketStatus $expectedOld): bool { return $this->inner->saveIfStatus($ticket, $expectedOld); }
            public function getPoolTickets(): array { return $this->inner->getPoolTickets(); }
            public function findByCreator(int $creatorId): array { return $this->inner->findByCreator($creatorId); }
            public function findByAgent(int $agentId): array { return $this->inner->findByAgent($agentId); }
            public function findAll(): array { return $this->inner->findAll(); }
            public function countActiveByAgent(): array { return $this->inner->countActiveByAgent(); }
            public function assignIfUnassigned(int $ticketId, int $agentId): bool { return $this->inner->assignIfUnassigned($ticketId, $agentId); }
        };
        $this->tickets->assignIfUnassigned(1, (int) $agent->id);

        $service = new TicketService($stale, $this->replies, new JsonCategoryRepository($this->tempStore()), new JsonSlaPolicyRepository($this->tempStore()),
            new SlaCalculatorService(new JsonCalendarRepository(__DIR__ . '/../../fixtures/calendar.json')), new PriorityDetector([]),
            new AssignmentService($this->users, $this->tickets), $this->clock);

        try {
            $service->perform(1, $this->customer, 'cancel');
            self::fail('Çakışma bekleniyordu.');
        } catch (ConflictException $e) {
            self::assertSame('Talep bu arada değişti. Sayfayı yenileyip tekrar deneyin.', $e->getMessage());
        }

        $ticket = $this->tickets->findById(1);
        self::assertSame(TicketStatus::Taken, $ticket?->status, 'Personelin alması silinmemeli.');
        self::assertSame($agent->id, $ticket?->agentId);
    }

    public function test_observer_receives_events(): void
    {
        $events = [];
        $this->service->attachObserver(new class ($events) implements TicketObserver {
            /** @param list<string> $events */
            public function __construct(private array &$events)
            {
            }

            public function update(Ticket $ticket, string $eventType, array $context = []): void
            {
                $this->events[] = $eventType;
            }
        });
        $this->user('P000001', Role::Agent);

        $this->create();

        self::assertSame([TicketService::EVENT_CREATED, TicketService::EVENT_ASSIGNED, TicketService::EVENT_STATUS_CHANGED], $events);
    }
}
