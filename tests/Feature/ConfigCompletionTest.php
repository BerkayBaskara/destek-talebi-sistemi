<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\User;

/**
 * SRS'te "yönetici değiştirebilir" denen ayarlar: giriş/sıfırlama deneme sınırı (FR-31, NFR-19), oturum süresi (NFR-10),
 * bildirim türleri (US-45), personel bilgisi düzenleme (US-26), yöneticinin talep açması (FR-16, US-39),
 * ana sayfa görseli (FR-76), hareketsiz personelin pasifleştirilmesi (FR-49).
 */
final class ConfigCompletionTest extends FeatureTestCase
{
    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->admin = $this->kernel->authService()->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->agent = $this->kernel->authService()->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
    }

    private function adminSaves(array $data): \App\Core\Http\Response
    {
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        $response = $this->post('/yonetici/ayarlar/guvenlik', $data);
        $this->post('/cikis');

        return $response;
    }

    public function test_login_is_blocked_after_configured_failed_attempts(): void
    {
        $customer = $this->registerCustomer();
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(422, $this->post('/giris', ['customer_no' => $customer, 'password' => 'yanlis1!'])->status);
        }

        $blocked = $this->post('/giris', ['customer_no' => $customer, 'password' => self::PASSWORD]);
        self::assertSame(422, $blocked->status);
        self::assertStringContainsString('Çok fazla hatalı deneme yapıldı. 5 dakika sonra tekrar deneyin.', $blocked->body);

        $this->clock->advance('+6 minutes');
        $this->loginAs($customer, self::PASSWORD);
    }

    public function test_admin_changes_attempt_limit_and_idle_timeout(): void
    {
        $bad = $this->adminSaves(['max_attempts' => '1', 'lock_minutes' => '0', 'idle_minutes' => '2']);
        self::assertSame(422, $bad->status);
        self::assertStringContainsString('Deneme sınırı 3 ile 20 arasında olmalı.', $bad->body);
        self::assertStringContainsString('Engel süresi 1 ile 120 dakika arasında olmalı.', $bad->body);
        self::assertStringContainsString('Oturum süresi 5 ile 480 dakika arasında olmalı.', $bad->body);

        self::assertSame(302, $this->adminSaves(['max_attempts' => '3', 'lock_minutes' => '10', 'idle_minutes' => '10'])->status);

        $customer = $this->registerCustomer();
        for ($i = 0; $i < 3; $i++) {
            $this->post('/giris', ['customer_no' => $customer, 'password' => 'yanlis1!']);
        }
        self::assertStringContainsString('10 dakika sonra tekrar deneyin.', $this->post('/giris', ['customer_no' => $customer, 'password' => self::PASSWORD])->body);

        $this->clock->advance('+11 minutes');
        $this->loginAs($customer, self::PASSWORD);
        $this->clock->advance('+11 minutes');
        self::assertSame('/giris', $this->get('/talepler')->headers['Location'] ?? null, '10 dakikalık oturum süresi uygulanır.');
    }

    public function test_disabled_notification_type_is_not_sent(): void
    {
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        self::assertSame(302, $this->post('/yonetici/ayarlar/bildirimler', ['enabled' => ['reply', 'status']])->status);
        $this->post('/cikis');

        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']);

        self::assertSame(0, $this->kernel->notificationRepository()->unreadCount((int) $this->agent->id), 'Atama bildirimi kapalı.');
    }

    public function test_admin_edits_staff_name(): void
    {
        $this->loginAs($this->admin->customerNo, self::PASSWORD);

        $this->post('/yonetici/personel/' . $this->agent->id, ['name' => 'Zeynep Nur', 'surname' => 'Kaya', 'active' => '1', 'capacity' => '']);
        self::assertSame('Zeynep Nur Kaya', $this->kernel->userRepository()->findById((int) $this->agent->id)?->fullName());

        $bad = $this->post('/yonetici/personel/' . $this->agent->id, ['name' => 'Z', 'surname' => 'Kaya', 'active' => '1', 'capacity' => '']);
        self::assertSame(422, $bad->status);
        self::assertStringContainsString('Ad en az 2 karakter olmalı.', $bad->body);
    }

    public function test_admin_opens_ticket_for_customer_and_for_self(): void
    {
        $customerNo = $this->registerCustomer();
        $customer = $this->kernel->userRepository()->findByCustomerNo($customerNo);
        self::assertNotNull($customer);
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        self::assertStringContainsString('Müşteri adına', $this->get('/talepler/yeni')->body);

        $onBehalf = $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Telefonla bildirilen arıza', 'description' => 'Müşteri telefonla aradı.', 'customer_id' => (string) $customer->id]);
        self::assertSame('/talepler/1', $onBehalf->headers['Location'] ?? null);
        self::assertSame($customer->id, $this->kernel->ticketRepository()->findById(1)?->creatorId);

        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Ofis yazıcısı', 'description' => 'İşletme içi sorun bildirimi.', 'customer_id' => '']);
        self::assertSame($this->admin->id, $this->kernel->ticketRepository()->findById(2)?->creatorId);
        $this->post('/cikis');

        $this->loginAs($customerNo, self::PASSWORD);
        self::assertStringContainsString('Telefonla bildirilen arıza', $this->get('/talepler')->body);
    }

    public function test_agent_cannot_open_ticket(): void
    {
        $this->loginAs($this->agent->customerNo, self::PASSWORD);

        self::assertSame(403, $this->get('/talepler/yeni')->status);
    }

    public function test_home_image_upload_shows_on_home_page(): void
    {
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $tmp = (string) tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, $png);
        $this->kernel->brandingService()->storeImage($this->admin, ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($png)], 'site.home_image', static fn (string $from, string $to): bool => rename($from, $to));

        $path = $this->kernel->settingsService()->string('site.home_image');
        self::assertMatchesRegularExpression('#^/branding/home-[0-9a-f]{16}\.png$#', $path);
        self::assertStringContainsString('src="' . $path . '"', $this->get('/')->body);
        @unlink($this->root . '/public' . $path);
    }

    public function test_inactive_staff_is_deactivated_after_configured_days(): void
    {
        $this->kernel->settingsService()->update($this->admin, ['staff.inactivity_days' => '30']);
        $this->loginAs($this->agent->customerNo, self::PASSWORD); // son giriş 2026-11-09
        $this->post('/cikis');

        $this->clock->advance('+31 days');
        $this->kernel->slaMonitor()->run();

        self::assertFalse($this->kernel->userRepository()->findById((int) $this->agent->id)?->active);
        self::assertTrue($this->kernel->userRepository()->findById((int) $this->admin->id)?->active, 'Yönetici etkilenmez.');
    }
}
