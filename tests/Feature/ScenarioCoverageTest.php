<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\User;
use DateTimeImmutable;
use DateTimeZone;

/** docs/test-senaryolari.md listesinde ayrı testi olmayan senaryolar (her testin üstünde senaryo numarası). */
final class ScenarioCoverageTest extends FeatureTestCase
{
    private User $admin;
    private User $agent;
    private User $senior;
    private string $customerNo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $auth = $this->kernel->authService();
        $this->admin = $auth->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->agent = $auth->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->senior = $auth->createStaff(Role::Agent, 'Murat', 'Demir', self::PASSWORD);
        $this->kernel->hierarchyRepository()->setLevel(1, (int) $this->agent->id, 1);
        $this->kernel->hierarchyRepository()->setLevel(1, (int) $this->senior->id, 2);
        $this->customerNo = $this->registerCustomer();
    }

    private function as(string $number): void
    {
        $this->post('/cikis');
        $this->loginAs($number, self::PASSWORD);
    }

    private function openTicket(): void
    {
        $this->as($this->customerNo);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']);
    }

    private function resolve(): void
    {
        $this->as($this->agent->customerNo);
        $this->post('/talepler/1/islem', ['islem' => 'start_review']);
        $this->post('/talepler/1/islem', ['islem' => 'resolve']);
    }

    /** AUTH-004 */
    public function test_empty_login_fields_are_rejected(): void
    {
        $response = $this->post('/giris', ['customer_no' => '', 'password' => '']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Müşteri numarası veya şifre hatalı.', $response->body);
    }

    /** AUTH-011, AUTH-013, AUTH-028 */
    public function test_generated_numbers_are_unique_and_prefixed(): void
    {
        $numbers = [];
        for ($i = 0; $i < 30; $i++) {
            $numbers[] = $this->registerCustomer('Ad' . $i);
        }

        self::assertCount(30, array_unique($numbers));
        foreach ($numbers as $number) {
            self::assertMatchesRegularExpression('/^M\d{6}$/', $number);
        }
    }

    /** AUTH-023, AUTH-024, AUTH-025, AUTH-026, AUTH-027 */
    public function test_role_prefixes(): void
    {
        self::assertSame(['M', 'P', 'Y'], [Role::Customer->prefix(), Role::Agent->prefix(), Role::Admin->prefix()]);
        self::assertMatchesRegularExpression('/^P\d{6}$/', $this->agent->customerNo);
        self::assertMatchesRegularExpression('/^Y\d{6}$/', $this->admin->customerNo);
    }

    /** AUTH-032, FRM-013, ASN-029, SEC-002, SEC-003 */
    public function test_non_admin_cannot_post_admin_actions(): void
    {
        $this->openTicket();
        foreach ([$this->agent->customerNo, $this->customerNo] as $number) {
            $this->as($number);
            self::assertSame(403, $this->post('/yonetici/ayarlar/sorular', ['questions' => 'Soru nedir?'])->status);
            self::assertSame(403, $this->post('/yonetici/kategoriler/1/alanlar', ['label' => 'Alan', 'type' => 'text'])->status);
            self::assertSame(403, $this->post('/talepler/1/ata', ['personel' => (string) $this->senior->id, 'gerekce' => 'x'])->status);
            self::assertSame(403, $this->post('/yonetici/ayarlar/guvenlik', ['max_attempts' => '3'])->status);
            self::assertSame(403, $this->post('/yonetici/yedek/indir')->status);
        }
    }

    /** FRM-020 */
    public function test_number_field_rejects_text(): void
    {
        $this->as($this->admin->customerNo);
        $this->post('/yonetici/kategoriler/1/alanlar', ['label' => 'Adet', 'type' => 'number', 'required' => '1']);
        $field = $this->kernel->categoryFieldRepository()->forCategory(1)[0];
        $this->as($this->customerNo);

        $response = $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı', 'description' => 'Açıklama metni.', 'extra' => [$field['id'] => 'üç']]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Adet alanı sayı olmalı.', $response->body);
    }

    /** ASN-023 */
    public function test_recently_active_staff_is_not_deactivated(): void
    {
        $this->kernel->settingsService()->update($this->admin, ['staff.inactivity_days' => '30']);
        $this->as($this->agent->customerNo);
        $this->clock->advance('+10 days');
        $this->as($this->agent->customerNo);
        $this->clock->advance('+25 days');
        $this->kernel->slaMonitor()->run();

        self::assertTrue($this->kernel->userRepository()->findById((int) $this->agent->id)?->active);
    }

    /** SLA-031, SLA-032, SLA-033 */
    public function test_extra_sla_accumulates_and_first_deadline_is_kept(): void
    {
        $this->openTicket();
        $deadlines = [];
        for ($i = 0; $i < 2; $i++) {
            $this->resolve();
            $this->as($this->customerNo);
            $this->post('/talepler/1/islem', ['islem' => 'reject']);
            $deadlines[] = $this->kernel->ticketRepository()->findById(1)?->deadline->format('Y-m-d H:i');
            $this->clock->advance('+2 hours');
        }

        self::assertNotSame($deadlines[0], $deadlines[1], 'Her itiraz yeni ek SLA verir.');
        self::assertSame('2026-11-11 10:00', $this->kernel->ticketRepository()->findById(1)?->firstDeadline()->format('Y-m-d H:i'));
        $extended = array_filter($this->kernel->ticketActivityRepository()->findByTicket(1), static fn ($a): bool => $a->action === 'sla_extended');
        self::assertCount(2, $extended, 'Her ek SLA geçmişte ayrı kayıt.');
    }

    /** RES-005, RES-008 */
    public function test_other_customer_cannot_approve_and_double_approval_is_refused(): void
    {
        $this->openTicket();
        $this->resolve();

        $this->as($this->registerCustomer('Ayşe'));
        self::assertSame(403, $this->post('/talepler/1/islem', ['islem' => 'approve'])->status);

        $this->as($this->customerNo);
        $this->post('/talepler/1/islem', ['islem' => 'approve']);
        $this->post('/talepler/1/islem', ['islem' => 'approve']);
        self::assertSame(TicketStatus::Closed, $this->kernel->ticketRepository()->findById(1)?->status);
        $closings = array_filter($this->kernel->ticketLogRepository()->findByTicket(1), static fn ($l): bool => $l->newStatus === TicketStatus::Closed);
        self::assertCount(1, $closings, 'İkinci onay işlem yapmaz.');
    }

    /** RES-017 */
    public function test_objection_stops_auto_close(): void
    {
        $this->openTicket();
        $this->resolve();
        $this->clock->advance('+1 day');
        $this->as($this->customerNo); // saat ilerledikten sonra giriş (eski oturum boşta kalma süresini aşar)
        $this->post('/talepler/1/islem', ['islem' => 'reject']);
        self::assertSame(TicketStatus::InReview, $this->kernel->ticketRepository()->findById(1)?->status);

        $this->clock->set(new DateTimeImmutable('2026-11-16 10:00', new DateTimeZone('Europe/Istanbul')));
        $this->kernel->slaMonitor()->run();
        self::assertNotSame(TicketStatus::Closed, $this->kernel->ticketRepository()->findById(1)?->status, 'İtiraz edilen talep otomatik kapanmaz.');
    }

    /** RES-018 */
    public function test_auto_complete_runs_once(): void
    {
        $this->openTicket();
        $this->resolve();
        $this->clock->set(new DateTimeImmutable('2026-11-13 10:00', new DateTimeZone('Europe/Istanbul')));
        $this->kernel->slaMonitor()->run();
        $this->kernel->slaMonitor()->run();

        $closed = array_filter($this->kernel->ticketActivityRepository()->findByTicket(1), static fn ($a): bool => $a->action === 'closed');
        self::assertCount(1, $closed);
    }

    /** RES-023, RES-024 */
    public function test_unresolvable_close_needs_reason_of_at_least_five_characters(): void
    {
        $this->openTicket();
        $this->as($this->admin->customerNo);

        $this->post('/talepler/1/cozulemedi', ['gerekce' => '']);
        $this->post('/talepler/1/cozulemedi', ['gerekce' => 'abc']);

        self::assertNotSame(TicketStatus::Closed, $this->kernel->ticketRepository()->findById(1)?->status);
        self::assertStringContainsString('Gerekçe 5-300 karakter olmalı.', $this->get('/talepler/1')->body);
    }

    /** ADM-009, SEC-008, SEC-009 */
    public function test_script_and_event_attributes_are_shown_harmless(): void
    {
        $this->kernel->settingsService()->update($this->admin, ['site.home_text' => '<script>alert(1)</script><img src=x onerror=alert(2)>']);
        $this->post('/cikis');
        $home = $this->get('/')->body;
        self::assertStringNotContainsString('<script>alert(1)', $home);
        self::assertStringNotContainsString('<img src=x onerror', $home);
        self::assertStringContainsString('&lt;img src=x onerror=alert(2)&gt;', $home);

        $this->openTicket();
        $this->post('/talepler/1/yanit', ['mesaj' => '<b onmouseover="alert(3)">kalın</b>']);
        self::assertStringNotContainsString('<b onmouseover', $this->get('/talepler/1')->body);
    }

    /** RPT-012, RPT-015 */
    public function test_report_date_and_combined_filters(): void
    {
        $this->openTicket(); // 2026-11-09
        $this->clock->set(new DateTimeImmutable('2026-11-20 10:00', new DateTimeZone('Europe/Istanbul')));
        $this->as($this->customerNo);
        $this->post('/talepler', ['category_id' => '2', 'priority' => '2', 'title' => 'Excel açılmıyor', 'description' => 'Excel kapanıyor.']);

        $this->as($this->admin->customerNo);
        $page = $this->get('/yonetici/rapor?baslangic=2026-11-15&bitis=2026-11-30')->body;
        self::assertStringContainsString('Excel açılmıyor', $page);
        self::assertStringNotContainsString('Yazıcı arızası', $page);

        $none = $this->get('/yonetici/rapor?baslangic=2026-11-15&bitis=2026-11-30&kategori=1')->body;
        self::assertStringContainsString('Bu süzgece uyan talep yok.', $none);
    }
}
