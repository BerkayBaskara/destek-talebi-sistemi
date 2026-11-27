<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;

/** Yeni arayüz: herkese açık ana sayfa, durum simgeleri, terimler ve liste düzeni. */
final class UiTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    private function openTicket(): string
    {
        $customer = $this->registerCustomer();
        $this->loginAs($customer, self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']);

        return $customer;
    }

    public function test_guest_sees_public_home_with_admin_text(): void
    {
        $admin = $this->kernel->authService()->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->kernel->settingsService()->update($admin, [
            'site.home_heading' => 'Kampüs BT destek merkezi',
            'site.home_text' => 'Sorununuzu iletin, en kısa sürede dönelim.',
        ]);

        $home = $this->get('/');

        self::assertSame(200, $home->status);
        self::assertStringContainsString('<h1>Kampüs BT destek merkezi</h1>', $home->body);
        self::assertStringContainsString('Sorununuzu iletin, en kısa sürede dönelim.', $home->body);
        self::assertStringContainsString('href="/giris"', $home->body);
        self::assertStringContainsString('href="/kayit"', $home->body);
    }

    public function test_status_badges_use_new_labels_and_icons(): void
    {
        self::assertSame(
            ['Beklemede', 'Atandı', 'İnceleniyor', 'Çözüldü', 'Tamamlandı', 'İptal Edildi'],
            array_map(static fn (TicketStatus $s): string => $s->label(), TicketStatus::cases()),
        );
        self::assertSame(
            ['Açık', 'İşlemde', 'İşlemde', 'Çözüldü', 'Kapalı', 'Kapalı'],
            array_map(static fn (TicketStatus $s): string => $s->hocaLabel(), TicketStatus::cases()),
        );

        $this->openTicket();
        $list = $this->get('/talepler')->body;

        self::assertMatchesRegularExpression('#<span class="badge status-pending"[^>]*><svg class="status-icon"[^>]*aria-hidden="true"#', $list);
        self::assertStringContainsString('Beklemede</span>', $list);
    }

    public function test_new_ticket_button_class(): void
    {
        $this->openTicket();

        self::assertStringContainsString('class="btn btn-new" href="/talepler/yeni"', $this->get('/talepler')->body);
    }

    public function test_pool_and_tasks_terminology(): void
    {
        $agent = $this->kernel->authService()->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->loginAs($agent->customerNo, self::PASSWORD);

        $tasks = $this->get('/personel/talepler')->body;
        self::assertStringContainsString('<a href="/havuz">Görev Havuzu</a>', $tasks);
        self::assertStringContainsString('<a href="/personel/talepler">Görevlerim</a>', $tasks);
        self::assertStringContainsString('<h1>Görevlerim</h1>', $tasks);
        self::assertStringNotContainsString('Bana Atanan', $tasks);
        self::assertStringNotContainsString('Ortak Havuz', $tasks);

        self::assertStringContainsString('<h1>Görev Havuzu</h1>', $this->get('/havuz')->body);
        self::assertStringNotContainsString('Kartı sürükleyip', $this->get('/kanban')->body);
    }

    public function test_logout_shows_no_message(): void
    {
        $this->openTicket();

        $this->post('/cikis');

        self::assertStringNotContainsString('Çıkış yaptınız', $this->get('/giris')->body);
    }

    public function test_no_action_column_on_tasks_page(): void
    {
        $agent = $this->kernel->authService()->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->openTicket();
        $this->post('/cikis');
        $this->loginAs($agent->customerNo, self::PASSWORD);

        $tasks = $this->get('/personel/talepler')->body;

        self::assertStringNotContainsString('<th>İşlem</th>', $tasks);
        self::assertStringContainsString('data-href="/talepler/1"', $tasks);
        self::assertStringContainsString('<a href="/talepler/1">Yazıcı arızası</a>', $tasks);
    }

    public function test_search_placeholder(): void
    {
        $this->openTicket();

        self::assertStringContainsString('placeholder="Konu başlığı ya da talep numarası giriniz"', $this->get('/talepler')->body);
    }
}
