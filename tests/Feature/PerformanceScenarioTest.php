<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Model\User;

/**
 * PERF-003…007, PERF-012, PERF-013: süre ölçümleri (geliştirme bilgisayarında, uygulama içinden).
 * Sınırlar SRS'teki değerlerdir; ağ gecikmesi dahil değildir.
 */
final class PerformanceScenarioTest extends FeatureTestCase
{
    private User $agent;
    private User $other;
    private string $customerNo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $auth = $this->kernel->authService();
        $this->customerNo = $this->registerCustomer();
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Havuz talebi', 'description' => 'Personel yokken açılan talep.']);
        $this->post('/cikis');
        $this->agent = $auth->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->other = $auth->createStaff(Role::Agent, 'Murat', 'Demir', self::PASSWORD);
    }

    private static function ms(callable $fn): float
    {
        $start = hrtime(true);
        $fn();

        return (hrtime(true) - $start) / 1e6;
    }

    /** PERF-005: aynı talebi ikinci alma isteği 100 ms içinde reddedilir. */
    public function test_second_take_is_rejected_quickly(): void
    {
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
        $this->post('/havuz/1/al');
        $this->post('/cikis');
        $this->loginAs($this->other->customerNo, self::PASSWORD);

        $elapsed = self::ms(fn () => $this->post('/havuz/1/al'));

        self::assertSame($this->agent->id, $this->kernel->ticketRepository()->findById(1)?->agentId);
        self::assertLessThan(100, $elapsed);
    }

    /** PERF-003: Kanban taşıma isteği 200 ms altında. */
    public function test_kanban_move_is_fast(): void
    {
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
        $this->post('/havuz/1/al');

        $elapsed = self::ms(fn () => self::assertSame(200, $this->postJson('/api/talepler/1/durum', ['status' => 'in_review'])->status));

        self::assertLessThan(200, $elapsed);
    }

    /** PERF-006: geçersiz CSRF anahtarı 50 ms içinde reddedilir. */
    public function test_invalid_csrf_is_rejected_quickly(): void
    {
        $elapsed = self::ms(fn () => self::assertSame(403, $this->postWithoutCsrf('/talepler', ['title' => 'x'])->status));

        self::assertLessThan(50, $elapsed);
    }

    /** PERF-007: bozuk takvim dosyasında varsayılana geçiş 100 ms içinde. */
    public function test_corrupt_calendar_falls_back_quickly(): void
    {
        $path = $this->root . '/storage/config/bozuk.json';
        file_put_contents($path, '{"work_days": "x"');
        $repo = new \App\Repository\Json\JsonCalendarRepository($path, static fn (string $m): bool => true);

        $elapsed = self::ms(fn () => $repo->getConfig());

        self::assertLessThan(100, $elapsed);
    }

    /** PERF-012: 10.000 talepte rapor süzme ve personel istatistiği ölçülür (5 sn sınırı). */
    public function test_report_with_ten_thousand_tickets(): void
    {
        $repo = $this->kernel->ticketRepository();
        $now = $this->clock->now();
        // Veri tek seferde yazılır: 10.000 ayrı ekleme dosyayı her seferinde baştan yazar ve kurulum ölçümden uzun sürer.
        $rows = array_map(static fn (Ticket $t): array => $t->toArray(), $repo->findAll());
        for ($i = 0; $i < 10000; $i++) {
            $t = new Ticket(count($rows) + 1, 99, $i % 2 === 0 ? (int) $this->agent->id : (int) $this->other->id, 1 + $i % 4, 'Talep ' . $i,
                TicketStatus::Closed, 2, 2, $i % 3, $now, $now->modify('+1 day'), $now->modify('+2 hours'));
            $rows[] = $t->toArray();
        }
        file_put_contents($this->root . '/storage/data/tickets.json', (string) json_encode($rows, JSON_UNESCAPED_UNICODE));
        self::assertGreaterThan(10000, count($repo->findAll()));
        $report = $this->kernel->detailedReportService();

        $elapsed = self::ms(fn () => $report->perAgent($report->filter(['category' => 1])));

        self::assertLessThan(5000, $elapsed);
    }

    /** PERF-013: 100 ardışık kullanıcı isteğinde hata olmaz (uygulama içinden; gerçek eşzamanlı yük JMeter/k6 işi). */
    public function test_hundred_requests_without_errors(): void
    {
        $errors = 0;
        for ($i = 0; $i < 100; $i++) {
            $this->loginAs($this->customerNo, self::PASSWORD);
            $status = $this->get('/talepler')->status;
            $errors += $status === 200 ? 0 : 1;
            $this->post('/cikis');
        }

        self::assertSame(0, $errors);
    }
}
