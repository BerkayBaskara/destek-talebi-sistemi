<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\User;

/** Yöneticinin kategori hiyerarşisi ve personel izni ekranları; atamaya etkisi. */
final class HierarchyAndLeaveTest extends FeatureTestCase
{
    private User $admin;
    private User $senior;
    private User $junior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $auth = $this->kernel->authService();
        $this->admin = $auth->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->senior = $auth->createStaff(Role::Agent, 'Murat', 'Demir', self::PASSWORD);
        $this->junior = $auth->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
    }

    private function openTicket(string $category = '1'): void
    {
        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        $this->post('/talepler', ['category_id' => $category, 'priority' => '2', 'title' => 'Hiyerarşi talebi', 'description' => 'Hiyerarşi denemesi için talep.']);
        $this->post('/cikis');
    }

    public function test_admin_sets_levels_and_new_ticket_goes_to_lowest_level(): void
    {
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        self::assertSame(200, $this->get('/yonetici/hiyerarsi')->status);
        $response = $this->post('/yonetici/hiyerarsi/1', ['level' => [(string) $this->senior->id => '2', (string) $this->junior->id => '1']]);
        self::assertSame(302, $response->status);
        self::assertStringContainsString('Değişiklikler kaydedildi.', $this->get('/yonetici/hiyerarsi')->body);
        $this->post('/cikis');

        $this->openTicket();

        self::assertSame($this->junior->id, $this->kernel->ticketRepository()->findById(1)?->agentId);
    }

    public function test_invalid_level_is_rejected_and_nothing_saved(): void
    {
        $this->loginAs($this->admin->customerNo, self::PASSWORD);

        $response = $this->post('/yonetici/hiyerarsi/1', ['level' => [(string) $this->senior->id => '0', (string) $this->junior->id => '1']]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Seviye 1 ile 9 arasında olmalı.', $response->body);
        self::assertSame([], $this->kernel->hierarchyRepository()->levelsFor(1));
    }

    public function test_leave_page_adds_and_removes_and_agent_on_leave_gets_no_ticket(): void
    {
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        self::assertSame(200, $this->get('/yonetici/izinler')->status);
        $this->post('/yonetici/izinler', ['user_id' => (string) $this->senior->id, 'start' => '2026-11-09', 'end' => '2026-11-10', 'note' => 'Rapor']);
        self::assertStringContainsString('Murat Demir', $this->get('/yonetici/izinler')->body);

        $bad = $this->post('/yonetici/izinler', ['user_id' => (string) $this->junior->id, 'start' => '2026-11-12', 'end' => '2026-11-11', 'note' => '']);
        self::assertSame(422, $bad->status);
        self::assertStringContainsString('Bitiş tarihi başlangıçtan önce olamaz.', $bad->body);
        $this->post('/cikis');

        $this->openTicket(); // saat 2026-11-09 10:00: Murat izinde

        self::assertSame($this->junior->id, $this->kernel->ticketRepository()->findById(1)?->agentId);

        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        $leave = $this->kernel->leaveRepository()->all()[0];
        $this->post('/yonetici/izinler/' . $leave['id'] . '/sil');
        self::assertSame([], $this->kernel->leaveRepository()->all());
    }

    public function test_non_admin_cannot_open_hierarchy_or_leave_pages(): void
    {
        $this->loginAs($this->junior->customerNo, self::PASSWORD);

        self::assertSame(403, $this->get('/yonetici/hiyerarsi')->status);
        self::assertSame(403, $this->get('/yonetici/izinler')->status);
    }

    public function test_agent_outside_category_hierarchy_cannot_take_pool_ticket(): void
    {
        $this->kernel->hierarchyRepository()->setLevel(1, (int) $this->junior->id, 1);
        $this->junior->active = false;
        $this->kernel->userRepository()->save($this->junior);
        $this->openTicket(); // Zeynep pasif → havuza düşer
        self::assertNull($this->kernel->ticketRepository()->findById(1)?->agentId);

        $this->loginAs($this->senior->customerNo, self::PASSWORD);
        $response = $this->post('/havuz/1/al');

        self::assertSame(403, $response->status);
        self::assertNull($this->kernel->ticketRepository()->findById(1)?->agentId);
    }
}
