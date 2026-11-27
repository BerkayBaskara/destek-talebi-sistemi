<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;

final class AdminPagesTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $auth = $this->kernel->authService();
        $auth->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']);
        $this->post('/talepler', ['category_id' => '3', 'priority' => '4', 'title' => 'VPN bağlantısı yok', 'description' => 'Uzaktan bağlanamıyoruz.']);
        $this->post('/cikis');
        $admin = $auth->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->loginAs($admin->customerNo, self::PASSWORD);
    }

    public function test_admin_pages_render(): void
    {
        $list = $this->get('/yonetici');
        self::assertSame(200, $list->status);
        self::assertStringContainsString('Tüm Talepler', $list->body);
        self::assertStringContainsString('Yazıcı arızası', $list->body);
        self::assertStringContainsString('Zeynep Kaya', $list->body);

        $summary = $this->get('/yonetici/ozet');
        self::assertSame(200, $summary->status);
        self::assertStringContainsString('Özet Rapor', $summary->body);
        self::assertStringContainsString('Ortalama Çözüm Süresi', $summary->body);
        self::assertStringContainsString('Personel İş Yükü', $summary->body);
    }

    public function test_admin_list_filters(): void
    {
        self::assertStringNotContainsString('Yazıcı arızası', $this->get('/yonetici?oncelik=4')->body);
        self::assertStringContainsString('VPN bağlantısı yok', $this->get('/yonetici?oncelik=4')->body);
        self::assertStringContainsString('VPN bağlantısı yok', $this->get('/yonetici?q=vpn')->body);
        self::assertStringNotContainsString('VPN bağlantısı yok', $this->get('/yonetici?durum=cancelled')->body);
    }

    public function test_agent_and_customer_cannot_open_admin_pages(): void
    {
        $this->post('/cikis');
        $agent = $this->kernel->authService()->createStaff(Role::Agent, 'Murat', 'Demir', self::PASSWORD);
        $this->loginAs($agent->customerNo, self::PASSWORD);

        self::assertSame(403, $this->get('/yonetici/ozet')->status);
        self::assertSame(403, $this->get('/yonetici')->status);
    }
}
