<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\User;

/** FR-33: yönetici müşteri hesaplarını listeler, arar, aktif ya da pasif yapar; pasif müşteri giriş yapamaz. */
final class CustomerAccountsTest extends FeatureTestCase
{
    private User $admin;
    private string $customerNo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->kernel->authService()->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->customerNo = $this->registerCustomer('Ayşe');
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
    }

    private function customer(): User
    {
        $user = $this->kernel->userRepository()->findByCustomerNo($this->customerNo);
        self::assertNotNull($user);

        return $user;
    }

    public function test_admin_lists_and_searches_customers(): void
    {
        $this->registerCustomer('Mehmet');

        $page = $this->get('/yonetici/musteriler')->body;
        self::assertStringContainsString($this->customerNo, $page);
        self::assertStringContainsString('Ayşe Yılmaz', $page);
        self::assertStringContainsString('Mehmet Yılmaz', $page);

        $found = $this->get('/yonetici/musteriler?q=AYŞE')->body;
        self::assertStringContainsString('Ayşe Yılmaz', $found);
        self::assertStringNotContainsString('Mehmet Yılmaz', $found);
    }

    public function test_deactivated_customer_cannot_log_in_until_reactivated(): void
    {
        $id = (int) $this->customer()->id;

        self::assertSame(302, $this->post('/yonetici/musteriler/' . $id . '/durum', ['active' => '0'])->status);
        self::assertFalse($this->customer()->active);
        self::assertStringContainsString('Pasif', $this->get('/yonetici/musteriler')->body);

        $this->post('/cikis');
        $login = $this->post('/giris', ['customer_no' => $this->customerNo, 'password' => self::PASSWORD]);
        self::assertStringContainsString('Hesabınız pasif durumda', $login->body);

        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        $this->post('/yonetici/musteriler/' . $id . '/durum', ['active' => '1']);
        self::assertTrue($this->customer()->active);
        $audit = $this->kernel->auditLogRepository()->latest(5);
        self::assertSame(['activate', 'deactivate'], array_slice(array_map(static fn ($e): string => $e->action, $audit), 0, 2));
    }

    public function test_only_customers_can_be_changed_here(): void
    {
        $response = $this->post('/yonetici/musteriler/' . $this->admin->id . '/durum', ['active' => '0']);

        self::assertSame(404, $response->status);
        self::assertTrue($this->kernel->userRepository()->findById((int) $this->admin->id)?->active);
    }

    public function test_non_admin_gets_403(): void
    {
        $this->post('/cikis');
        $this->loginAs($this->customerNo, self::PASSWORD);

        self::assertSame(403, $this->get('/yonetici/musteriler')->status);
        self::assertSame(403, $this->post('/yonetici/musteriler/' . $this->customer()->id . '/durum', ['active' => '0'])->status);
    }

    /** NFR-33: bir sayfada en fazla 50 kayıt. */
    public function test_list_is_paginated_by_fifty(): void
    {
        $repo = $this->kernel->userRepository();
        for ($i = 1; $i <= 55; $i++) {
            $repo->save(new User(null, sprintf('M9%05d', $i), 'x', Role::Customer, 'Toplu' . $i, 'Müşteri', '', ''));
        }

        $first = $this->get('/yonetici/musteriler')->body;
        $second = $this->get('/yonetici/musteriler?sayfa=2')->body;

        self::assertSame(50, substr_count($first, 'data-customer-row'));
        self::assertSame(6, substr_count($second, 'data-customer-row'));
    }
}
