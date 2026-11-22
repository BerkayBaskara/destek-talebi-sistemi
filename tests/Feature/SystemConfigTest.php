<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\User;

/** Yöneticinin Sistem Yapılandırması ekranları: site, kategori, öncelik, SLA, takvim, personel, denetim kaydı. */
final class SystemConfigTest extends FeatureTestCase
{
    private const CONFIG_PAGES = [
        '/yonetici/ayarlar', '/yonetici/ayarlar/site', '/yonetici/kategoriler', '/yonetici/oncelikler',
        '/yonetici/sla', '/yonetici/takvim', '/yonetici/personel', '/yonetici/denetim',
    ];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->admin = $this->kernel->authService()->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
    }

    private function loginAdmin(): void
    {
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
    }

    /** Müşteri olarak talep açar ve oturumu kapatır. */
    private function openTicketAsCustomer(string $title): void
    {
        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => $title, 'description' => $title . ' için açıklama.']);
        $this->post('/cikis');
    }

    public function test_non_admin_gets_403_on_every_config_page(): void
    {
        $agent = $this->kernel->authService()->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $customer = $this->registerCustomer();

        foreach ([$agent->customerNo, $customer] as $no) {
            $this->loginAs($no, self::PASSWORD);
            foreach (self::CONFIG_PAGES as $page) {
                self::assertSame(403, $this->get($page)->status, $no . ' ' . $page);
            }
            self::assertSame(403, $this->post('/yonetici/ayarlar/site', ['site_title' => 'Ele geçirildi'])->status);
            $this->post('/cikis');
        }

        $this->loginAdmin();
        foreach (self::CONFIG_PAGES as $page) {
            self::assertSame(200, $this->get($page)->status, $page);
        }
    }

    public function test_site_title_change_appears_in_header(): void
    {
        $this->loginAdmin();

        $response = $this->post('/yonetici/ayarlar/site', [
            'site_title' => 'Kampüs Destek Merkezi', 'home_heading' => 'Size nasıl yardımcı olabiliriz?',
            'home_text' => 'Talebinizi açın.', 'default_capacity' => '5',
        ]);
        self::assertSame(302, $response->status);

        $page = $this->get('/yonetici/ayarlar/site')->body;
        self::assertStringContainsString('Değişiklikler kaydedildi.', $page);
        self::assertStringContainsString('<a class="brand" href="/">Kampüs Destek Merkezi</a>', $page);
        self::assertStringContainsString('<title>Kampüs Destek Merkezi', $page);
    }

    public function test_invalid_site_form_keeps_values_and_shows_error(): void
    {
        $this->loginAdmin();

        $response = $this->post('/yonetici/ayarlar/site', [
            'site_title' => '', 'home_heading' => 'Korunan başlık', 'home_text' => '', 'default_capacity' => '0',
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Site başlığı 1-80 karakter olmalı.', $response->body);
        self::assertStringContainsString('Kapasite 1 ile 100 arasında bir tam sayı olmalı.', $response->body);
        self::assertStringContainsString('Korunan başlık', $response->body);
        self::assertSame('Destek Talebi Sistemi', $this->kernel->settingsService()->string('site.title'));
    }

    public function test_category_create_and_deactivate_hides_from_form(): void
    {
        $this->loginAdmin();
        self::assertSame(302, $this->post('/yonetici/kategoriler', ['name' => 'Yazıcı'])->status);
        $id = 5;
        self::assertSame('Yazıcı', $this->kernel->categoryRepository()->findById($id)?->name);

        $duplicate = $this->post('/yonetici/kategoriler', ['name' => 'yazıcı']);
        self::assertSame(422, $duplicate->status);
        self::assertStringContainsString('value="yazıcı"', $duplicate->body);

        $this->post('/yonetici/kategoriler/' . $id, ['name' => 'Yazıcı']); // aktif kutusu işaretsiz → pasif
        self::assertFalse($this->kernel->categoryRepository()->findById($id)?->active);
        $this->post('/cikis');

        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        $form = $this->get('/talepler/yeni')->body;
        self::assertStringNotContainsString('Yazıcı', $form);
        self::assertStringContainsString('Donanım', $form);
    }

    public function test_priority_create_appears_in_ticket_form(): void
    {
        $this->loginAdmin();
        $response = $this->post('/yonetici/oncelikler', [
            'name' => 'Kritik', 'rank' => '6', 'default_sla_hours' => '2', 'active' => '1', 'keywords' => 'sunucu düştü, tamamen durdu',
        ]);
        self::assertSame(302, $response->status);
        $priority = $this->kernel->priorityRepository()->findById(6);
        self::assertSame(['sunucu düştü', 'tamamen durdu'], $priority?->keywords);

        $bad = $this->post('/yonetici/oncelikler', ['name' => 'Başka', 'rank' => '6', 'default_sla_hours' => '2', 'active' => '1']);
        self::assertSame(422, $bad->status);
        self::assertStringContainsString('Bu sıra numarası başka bir öncelikte kullanılıyor.', $bad->body);
        $this->post('/cikis');

        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        self::assertStringContainsString('>Kritik</option>', $this->get('/talepler/yeni')->body);
    }

    public function test_sla_grid_saves_and_clears(): void
    {
        $this->loginAdmin();

        $response = $this->post('/yonetici/sla', ['sla' => ['1' => ['1' => '30', '2' => ''], '2' => ['1' => '24']]]);
        self::assertSame(302, $response->status);
        $policies = $this->kernel->slaPolicyRepository();
        self::assertSame(30, $policies->findFor(1, 1)?->durationHours);
        self::assertNull($policies->findFor(1, 2));

        $bad = $this->post('/yonetici/sla', ['sla' => ['1' => ['1' => '999'], '2' => ['1' => '10']]]);
        self::assertSame(422, $bad->status);
        self::assertStringContainsString('SLA süresi 1 ile 720 iş saati arasında olmalı.', $bad->body);
        self::assertSame(30, $policies->findFor(1, 1)?->durationHours, 'Hatalı formda hiçbir hücre yazılmamalı.');
        self::assertSame(24, $policies->findFor(2, 1)?->durationHours);
    }

    public function test_calendar_rejects_no_working_day_and_keeps_old(): void
    {
        $this->loginAdmin();
        $before = $this->kernel->workCalendarRepository()->workHours();
        $days = [];
        for ($d = 1; $d <= 7; $d++) {
            $days[(string) $d] = ['start' => '09:00', 'end' => '18:00', 'break_start' => '12:00', 'break_end' => '13:00'];
        }

        $response = $this->post('/yonetici/takvim/mesai', ['days' => $days]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('En az bir çalışma günü olmalı.', $response->body);
        self::assertSame($before, $this->kernel->workCalendarRepository()->workHours());

        $days['6']['works'] = '1';
        self::assertSame(302, $this->post('/yonetici/takvim/mesai', ['days' => $days])->status);
        $saved = $this->kernel->workCalendarRepository()->workHours();
        self::assertTrue($saved[6]['works']);
        self::assertFalse($saved[1]['works']);

        self::assertSame(302, $this->post('/yonetici/takvim/tatil', ['date' => '2026-12-31', 'description' => 'Yılbaşı arifesi'])->status);
        self::assertStringContainsString('Yılbaşı arifesi', $this->get('/yonetici/takvim')->body);
        $this->post('/yonetici/takvim/tatil/sil', ['date' => '2026-12-31']);
        self::assertStringNotContainsString('Yılbaşı arifesi', $this->get('/yonetici/takvim')->body);
    }

    public function test_staff_create_shows_P_number(): void
    {
        $this->loginAdmin();

        $response = $this->post('/yonetici/personel', ['name' => 'Murat', 'surname' => 'Demir', 'password' => 'Personel1!']);

        self::assertSame(302, $response->status);
        $page = $this->get('/yonetici/personel')->body;
        self::assertMatchesRegularExpression('/Personel numarası: P\d{6}/', $page);
        self::assertStringContainsString('Murat Demir', $page);

        $weak = $this->post('/yonetici/personel', ['name' => 'Ayşe', 'surname' => 'Kurt', 'password' => 'zayif']);
        self::assertSame(422, $weak->status);
        self::assertStringContainsString('value="Ayşe"', $weak->body);
    }

    public function test_activating_agent_from_staff_page_assigns_pool_ticket(): void
    {
        $agent = $this->kernel->authService()->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $agent->active = false;
        $this->kernel->userRepository()->save($agent);
        $this->openTicketAsCustomer('Havuzda bekleyen talep');
        self::assertNull($this->kernel->ticketRepository()->findById(1)?->agentId);

        $this->loginAdmin();
        $response = $this->post('/yonetici/personel/' . $agent->id, ['active' => '1', 'capacity' => '']);

        self::assertSame(302, $response->status);
        $ticket = $this->kernel->ticketRepository()->findById(1);
        self::assertSame($agent->id, $ticket?->agentId);
        self::assertSame(TicketStatus::Taken, $ticket?->status);
        self::assertStringContainsString('Havuzda bekleyen 1 talep atandı.', $this->get('/yonetici/personel')->body);
    }

    public function test_inactive_agent_with_open_tickets_shows_warning(): void
    {
        $agent = $this->kernel->authService()->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->openTicketAsCustomer('Birinci talep');
        $this->openTicketAsCustomer('İkinci talep');

        $this->loginAdmin();
        $this->post('/yonetici/personel/' . $agent->id, ['capacity' => '']); // aktif kutusu işaretsiz → pasif

        self::assertFalse($this->kernel->userRepository()->findById((int) $agent->id)?->active);
        $page = $this->get('/yonetici/personel')->body;
        self::assertStringContainsString('Pasif', $page);
        self::assertStringContainsString('Bu personelin 2 açık talebi var; talepler şimdilik onun üzerinde kalıyor.', $page);
    }

    public function test_audit_page_lists_changes(): void
    {
        $this->loginAdmin();
        $this->post('/yonetici/kategoriler', ['name' => 'Yazıcı']);

        $page = $this->get('/yonetici/denetim')->body;

        self::assertStringContainsString('Selin Arslan', $page);
        self::assertStringContainsString('Kategori', $page);
        self::assertStringContainsString('Yazıcı', $page);
    }
}
