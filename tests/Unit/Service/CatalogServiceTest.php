<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Clock\FixedClock;
use App\Exception\ValidationException;
use App\Model\Category;
use App\Model\Enum\Role;
use App\Model\SlaPolicy;
use App\Model\User;
use App\Repository\Json\JsonAuditLogRepository;
use App\Repository\Json\JsonCalendarRepository;
use App\Repository\Json\JsonCategoryRepository;
use App\Repository\Json\JsonPriorityRepository;
use App\Repository\Json\JsonReplyRepository;
use App\Repository\Json\JsonSlaPolicyRepository;
use App\Repository\Json\JsonTicketRepository;
use App\Repository\Json\JsonUserRepository;
use App\Service\AssignmentService;
use App\Service\AuditLogger;
use App\Service\CatalogService;
use App\Service\PriorityService;
use App\Service\SlaCalculatorService;
use App\Service\TicketService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

/** Kategori aktifliği ve kategori × öncelik SLA tablosu. */
final class CatalogServiceTest extends TestCase
{
    use TempStoreTrait;

    private JsonCategoryRepository $categories;
    private JsonSlaPolicyRepository $policies;
    private CatalogService $catalog;
    private TicketService $tickets;
    private FixedClock $clock;
    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        $store = $this->makeStore();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-11-13 17:30', new DateTimeZone('Europe/Istanbul')));
        $this->categories = new JsonCategoryRepository($store);
        $this->policies = new JsonSlaPolicyRepository($store);
        $priorities = new JsonPriorityRepository($store);
        foreach (PriorityService::defaults() as $priority) {
            $priorities->save($priority);
        }
        $this->categories->save(new Category(null, 'Donanım'));
        $this->categories->save(new Category(null, 'Yazılım'));
        $this->policies->save(new SlaPolicy(null, 1, 4, 4));

        $audit = new AuditLogger(new JsonAuditLogRepository($store), $this->clock);
        $this->catalog = new CatalogService($this->categories, $priorities, $this->policies, $audit);

        $users = new JsonUserRepository($store);
        $ticketRepo = new JsonTicketRepository($store);
        $this->tickets = new TicketService($ticketRepo, new JsonReplyRepository($store), $this->categories, $this->policies,
            new SlaCalculatorService(new JsonCalendarRepository(__DIR__ . '/../../fixtures/calendar.json')), $priorities,
            new AssignmentService($users, $ticketRepo), $this->clock);

        $this->admin = $users->save(new User(null, 'Y000001', 'x', Role::Admin, 'Selin', 'Arslan', 'S', 'C'));
        $this->customer = $users->save(new User(null, 'M100001', 'x', Role::Customer, 'Ahmet', 'Yılmaz', 'S', 'C'));
    }

    /** @param array<string, mixed> $override */
    private function open(array $override = []): \App\Model\Ticket
    {
        return $this->tickets->createTicket($this->customer, $override + [
            'category_id' => 1, 'priority' => 4, 'title' => 'Sunucu sorunu', 'description' => 'Sunucuya bağlanırken sorun var.',
        ]);
    }

    public function test_inactive_category_hidden_from_form_and_rejected_on_create(): void
    {
        $this->catalog->updateCategory($this->admin, 2, 'Yazılım', false);

        self::assertSame(['Donanım'], array_map(static fn (Category $c): string => $c->name, $this->categories->findActive()));
        self::assertCount(2, $this->categories->findAll());
        try {
            $this->open(['category_id' => 2]);
            self::fail('Pasif kategoride talep açılmamalı.');
        } catch (ValidationException $e) {
            self::assertSame('Geçerli bir kategori seçin.', $e->errors()['category_id']);
        }
    }

    public function test_cannot_deactivate_last_active_category(): void
    {
        $this->catalog->updateCategory($this->admin, 2, 'Yazılım', false);

        try {
            $this->catalog->updateCategory($this->admin, 1, 'Donanım', false);
            self::fail('Son aktif kategori pasif yapılamamalı.');
        } catch (ValidationException $e) {
            self::assertSame('En az bir aktif kategori olmalı.', $e->errors()['active']);
        }
        self::assertTrue($this->categories->findById(1)?->active);
    }

    public function test_category_name_is_required_and_unique(): void
    {
        foreach (['', 'DONANIM'] as $name) {
            try {
                $this->catalog->createCategory($this->admin, $name);
                self::fail('"' . $name . '" kabul edilmemeliydi.');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('name', $e->errors());
            }
        }
        self::assertSame('Ağ / Erişim', $this->catalog->createCategory($this->admin, ' Ağ / Erişim ')->name);
    }

    public function test_set_sla_overrides_default_for_new_tickets_only(): void
    {
        $old = $this->open();
        self::assertSame('2026-11-16 13:30', $old->deadline->format('Y-m-d H:i')); // Cuma 17:30 + 4 sa

        $this->catalog->setSla($this->admin, 1, 4, 2);
        $new = $this->open();

        self::assertSame('2026-11-16 10:30', $new->deadline->format('Y-m-d H:i')); // Cuma 30 dk + Pzt 1,5 sa
        self::assertSame('2026-11-16 13:30', $this->tickets->mustFind((int) $old->id)->deadline->format('Y-m-d H:i'));
        self::assertSame(2, $this->catalog->slaGrid()[1][4]);
    }

    public function test_clearing_sla_falls_back_to_priority_default(): void
    {
        $this->catalog->setSla($this->admin, 1, 4, null);

        self::assertNull($this->catalog->slaGrid()[1][4]);
        self::assertSame('2026-11-16 13:30', $this->open()->deadline->format('Y-m-d H:i')); // Çok Yüksek varsayılanı 4 sa
    }

    public function test_sla_hours_out_of_range_rejected(): void
    {
        foreach ([0, 721] as $hours) {
            try {
                $this->catalog->setSla($this->admin, 1, 2, $hours);
                self::fail($hours . ' kabul edilmemeliydi.');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('hours', $e->errors());
            }
        }
    }
}
