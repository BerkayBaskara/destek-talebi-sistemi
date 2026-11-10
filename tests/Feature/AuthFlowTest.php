<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Service\AuthService;

final class AuthFlowTest extends FeatureTestCase
{
    private function registerCustomer(string $answer = 'Pamuk'): string
    {
        return $this->kernel->authService()
            ->register('Ahmet', 'Yılmaz', 'gizliSifre1', AuthService::SECRET_QUESTIONS[0], $answer)
            ->customerNo;
    }

    public function test_register_shows_customer_number_then_login_works(): void
    {
        $response = $this->post('/kayit', [
            'name' => 'Ayşe',
            'surname' => 'Demir',
            'password' => 'gizliSifre1',
            'password_confirm' => 'gizliSifre1',
            'secret_question' => AuthService::SECRET_QUESTIONS[1],
            'secret_answer' => 'Trabzon',
        ]);
        self::assertSame(302, $response->status);
        self::assertSame('/kayit/tamam', $response->headers['Location']);

        $done = $this->get('/kayit/tamam');
        self::assertSame(200, $done->status);
        self::assertSame(1, preg_match('/M\d{6}/', $done->body, $m));

        $login = $this->post('/giris', ['customer_no' => $m[0], 'password' => 'gizliSifre1']);
        self::assertSame(302, $login->status);
        self::assertSame('/', $login->headers['Location']);
        self::assertSame('/talepler', $this->get('/')->headers['Location'] ?? null);
    }

    public function test_register_password_mismatch_keeps_values(): void
    {
        $response = $this->post('/kayit', [
            'name' => 'Ayşe',
            'surname' => 'Demir',
            'password' => 'gizliSifre1',
            'password_confirm' => 'baskaSifre2',
            'secret_question' => AuthService::SECRET_QUESTIONS[1],
            'secret_answer' => 'Trabzon',
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Şifreler aynı değil.', $response->body);
        self::assertStringContainsString('value="Ayşe"', $response->body);
        self::assertStringNotContainsString('gizliSifre1', $response->body);
    }

    public function test_wrong_password_shows_error_and_keeps_number(): void
    {
        $number = $this->registerCustomer();

        $response = $this->post('/giris', ['customer_no' => $number, 'password' => 'yanlisSifre']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Müşteri numarası veya şifre hatalı.', $response->body);
        self::assertStringContainsString('value="' . $number . '"', $response->body);
    }

    public function test_session_id_regenerated_on_login(): void
    {
        $number = $this->registerCustomer();

        $this->post('/giris', ['customer_no' => $number, 'password' => 'gizliSifre1']);

        self::assertTrue($this->session->regenerated);
    }

    public function test_customer_cannot_open_admin_page(): void
    {
        $this->loginAs($this->registerCustomer(), 'gizliSifre1');

        self::assertSame(403, $this->get('/yonetici')->status);
        self::assertSame(403, $this->get('/kanban')->status);
    }

    public function test_guest_redirected_to_login(): void
    {
        $response = $this->get('/talepler');

        self::assertSame(302, $response->status);
        self::assertSame('/giris', $response->headers['Location']);
    }

    public function test_idle_30_minutes_logs_out(): void
    {
        $this->loginAs($this->registerCustomer(), 'gizliSifre1');
        self::assertSame(200, $this->get('/talepler')->status);

        $this->clock->advance('+29 minutes');
        self::assertSame(200, $this->get('/talepler')->status, '29 dakika sonra hâlâ oturum açık olmalı.');

        $this->clock->advance('+31 minutes');
        $response = $this->get('/talepler');
        self::assertSame(302, $response->status);
        self::assertStringContainsString('30 dakika', $this->get('/giris')->body);
    }

    public function test_logout_ends_session(): void
    {
        $this->loginAs($this->registerCustomer(), 'gizliSifre1');

        $this->post('/cikis');

        self::assertSame(302, $this->get('/talepler')->status);
    }

    public function test_forgot_password_flow(): void
    {
        $number = $this->registerCustomer('Pamuk');

        $step1 = $this->post('/sifre-sifirla', ['customer_no' => $number]);
        self::assertSame(200, $step1->status);
        self::assertStringContainsString(e(AuthService::SECRET_QUESTIONS[0]), $step1->body);

        $wrong = $this->post('/sifre-sifirla/yeni', ['customer_no' => $number, 'secret_answer' => 'Tekir', 'password' => 'yeniSifre99']);
        self::assertSame(422, $wrong->status);
        self::assertStringContainsString('Gizli sorunun cevabı hatalı.', $wrong->body);

        $ok = $this->post('/sifre-sifirla/yeni', ['customer_no' => $number, 'secret_answer' => 'pamuk', 'password' => 'yeniSifre99']);
        self::assertSame('/giris', $ok->headers['Location'] ?? null);
        self::assertSame(302, $this->post('/giris', ['customer_no' => $number, 'password' => 'yeniSifre99'])->status);
    }

    public function test_unknown_number_in_forgot_password(): void
    {
        $response = $this->post('/sifre-sifirla', ['customer_no' => 'M999999']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Bu müşteri numarası bulunamadı.', $response->body);
    }

    public function test_staff_home_redirects_by_role(): void
    {
        $auth = $this->kernel->authService();
        $agent = $auth->createStaff(Role::Agent, 'Zeynep', 'Kaya', 'Destek2026!');
        $this->loginAs($agent->customerNo, 'Destek2026!');
        self::assertSame('/kanban', $this->get('/')->headers['Location'] ?? null);

        $this->post('/cikis');
        $admin = $auth->createStaff(Role::Admin, 'Yönetici', 'Bey', 'Destek2026!');
        $this->loginAs($admin->customerNo, 'Destek2026!');
        self::assertSame('/yonetici', $this->get('/')->headers['Location'] ?? null);
    }
}
