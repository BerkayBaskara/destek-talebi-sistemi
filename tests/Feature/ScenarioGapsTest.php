<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\User;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Test senaryosu listesinde (docs/test-senaryolari.md) karşılığı eksik çıkan davranışlar:
 * FRM-002/005/007/025, NOT-002/006/007/010, RPT-011.
 */
final class ScenarioGapsTest extends FeatureTestCase
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

    private function openTicket(array $extra = []): void
    {
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.', 'extra' => $extra]);
        $this->post('/cikis');
    }

    /** FRM-002, FRM-005, FRM-007, FRM-025 */
    public function test_duplicate_field_is_rejected_and_removed_field_keeps_old_values(): void
    {
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        $this->post('/yonetici/kategoriler/1/alanlar', ['label' => 'Seri no', 'type' => 'text', 'required' => '']);
        $dup = $this->post('/yonetici/kategoriler/1/alanlar', ['label' => 'seri NO', 'type' => 'text', 'required' => '']);
        self::assertSame(422, $dup->status);
        self::assertStringContainsString('Bu kategoride aynı adlı bir alan zaten var.', $dup->body);
        $this->post('/cikis');
        $field = $this->kernel->categoryFieldRepository()->forCategory(1)[0];

        $this->openTicket([$field['id'] => 'SN-1']);
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        $this->post('/yonetici/kategoriler/alanlar/' . $field['id'] . '/sil');

        self::assertSame([], $this->kernel->categoryFieldRepository()->forCategory(1));
        self::assertStringContainsString('SN-1', $this->get('/talepler/1')->body, 'Eski talepteki değer korunur.');
    }

    /** NOT-002 */
    public function test_notification_to_unknown_user_is_ignored(): void
    {
        $this->kernel->notificationService()->notify(9999, 'status', null, 'Kimseye');

        self::assertSame(0, $this->kernel->notificationRepository()->unreadCount(9999));
    }

    /** NOT-006, NOT-007 */
    public function test_notification_can_be_marked_unread_and_reading_twice_is_consistent(): void
    {
        $this->openTicket();
        $id = $this->kernel->notificationRepository()->forUser((int) $this->agent->id)[0]->id;
        $this->loginAs($this->agent->customerNo, self::PASSWORD);

        $this->get('/bildirimler/' . $id);
        $this->get('/bildirimler/' . $id);
        self::assertSame(0, $this->kernel->notificationRepository()->unreadCount((int) $this->agent->id));

        self::assertSame(302, $this->post('/bildirimler/' . $id . '/okunmadi')->status);
        self::assertSame(1, $this->kernel->notificationRepository()->unreadCount((int) $this->agent->id));

        $this->post('/cikis');
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/bildirimler/' . $id . '/okunmadi');
        self::assertSame(1, $this->kernel->notificationRepository()->unreadCount((int) $this->agent->id), 'Başkasının bildirimi değiştirilemez.');
    }

    /** NOT-010: SLA'nın %80'i dolunca personele bir kez uyarı. */
    public function test_sla_warning_notification_is_sent_once(): void
    {
        $this->openTicket(); // Pzt 10:00, bitiş Çar 10:00; %80 = Sal 15:48
        $this->clock->set(new DateTimeImmutable('2026-11-10 15:00', new DateTimeZone('Europe/Istanbul')));
        $this->kernel->slaMonitor()->run();
        $warnings = fn (): int => count(array_filter($this->kernel->notificationRepository()->forUser((int) $this->agent->id), static fn ($n): bool => $n->type === 'sla_warning'));
        self::assertSame(0, $warnings());

        $this->clock->set(new DateTimeImmutable('2026-11-10 16:00', new DateTimeZone('Europe/Istanbul')));
        $this->kernel->slaMonitor()->run();
        $this->clock->advance('+5 minutes');
        $this->kernel->slaMonitor()->run();

        self::assertSame(1, $warnings());
        self::assertStringContainsString("SLA süresinin %80'i doldu", $this->kernel->notificationRepository()->forUser((int) $this->agent->id)[0]->message);
    }

    /** RPT-011 */
    public function test_report_lists_escalations(): void
    {
        $this->openTicket();
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
        $this->post('/talepler/1/eskale', ['gerekce' => 'Yetki gerekiyor']);
        $this->post('/cikis');

        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        $page = $this->get('/yonetici/rapor')->body;

        self::assertStringContainsString('Eskalasyon Kayıtları', $page);
        self::assertStringContainsString('Zeynep Kaya → Murat Demir', $page);
        self::assertStringContainsString('Manuel (personel)', $page);
    }
}
