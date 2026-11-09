<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Exception\ValidationException;
use App\Model\Enum\Role;
use App\Repository\Json\JsonUserRepository;
use App\Service\AuthService;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

final class AuthServiceTest extends TestCase
{
    use TempStoreTrait;

    private AuthService $auth;

    protected function setUp(): void
    {
        $this->auth = new AuthService(new JsonUserRepository($this->makeStore()));
    }

    private function registerDefault(): \App\Model\User
    {
        return $this->auth->register('Ahmet', 'Yılmaz', 'gizliSifre1', AuthService::SECRET_QUESTIONS[0], 'Pamuk');
    }

    public function test_register_creates_customer_with_M_number(): void
    {
        $user = $this->registerDefault();

        self::assertMatchesRegularExpression('/^M\d{6}$/', $user->customerNo);
        self::assertSame(Role::Customer, $user->role);
        self::assertNotSame('gizliSifre1', $user->passwordHash);
        self::assertTrue(password_verify('gizliSifre1', $user->passwordHash));
        self::assertNotSame('Pamuk', $user->secretAnswerHash);
    }

    public function test_register_validation_messages(): void
    {
        try {
            $this->auth->register('A', '', 'kisa', 'Uydurma soru?', '');
            self::fail('Doğrulama hatası bekleniyordu.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            self::assertSame('Şifre en az 8 karakter olmalı.', $errors['password']);
            self::assertSame('Ad en az 2 karakter olmalı.', $errors['name']);
            self::assertSame('Soyad en az 2 karakter olmalı.', $errors['surname']);
            self::assertSame('Listeden bir gizli soru seçin.', $errors['secret_question']);
            self::assertSame('Gizli sorunun cevabı en az 2 karakter olmalı.', $errors['secret_answer']);
        }
    }

    public function test_login_success_and_wrong_password(): void
    {
        $user = $this->registerDefault();

        self::assertSame($user->id, $this->auth->login($user->customerNo, 'gizliSifre1')?->id);
        self::assertNull($this->auth->login($user->customerNo, 'yanlis'));
        self::assertNull($this->auth->login('M000000', 'gizliSifre1'));
    }

    public function test_login_accepts_lowercase_and_spaces(): void
    {
        $user = $this->registerDefault();

        self::assertNotNull($this->auth->login(' ' . strtolower($user->customerNo) . ' ', 'gizliSifre1'));
    }

    public function test_secret_question_lookup(): void
    {
        $user = $this->registerDefault();

        self::assertSame(AuthService::SECRET_QUESTIONS[0], $this->auth->secretQuestionFor($user->customerNo));
        self::assertNull($this->auth->secretQuestionFor('M000000'));
    }

    public function test_reset_password_with_answer_case_insensitive(): void
    {
        $user = $this->registerDefault();

        self::assertTrue($this->auth->resetPassword($user->customerNo, '  pamuk ', 'yeniSifre99'));
        self::assertNull($this->auth->login($user->customerNo, 'gizliSifre1'));
        self::assertNotNull($this->auth->login($user->customerNo, 'yeniSifre99'));
    }

    public function test_reset_password_turkish_uppercase_answer(): void
    {
        $user = $this->auth->register('Ali', 'Kaya', 'gizliSifre1', AuthService::SECRET_QUESTIONS[1], 'İzmir');

        self::assertTrue($this->auth->resetPassword($user->customerNo, 'izmir', 'yeniSifre99'));
    }

    public function test_reset_password_ascii_i_answer(): void
    {
        $user = $this->auth->register('Ali', 'Kaya', 'gizliSifre1', AuthService::SECRET_QUESTIONS[1], 'Istanbul');

        self::assertTrue($this->auth->resetPassword($user->customerNo, 'istanbul', 'yeniSifre99'));
        self::assertTrue($this->auth->resetPassword($user->customerNo, 'İSTANBUL', 'yeniSifre98'));
    }

    public function test_reset_password_wrong_answer_returns_false(): void
    {
        $user = $this->registerDefault();

        self::assertFalse($this->auth->resetPassword($user->customerNo, 'Tekir', 'yeniSifre99'));
        self::assertNotNull($this->auth->login($user->customerNo, 'gizliSifre1'));
    }

    public function test_reset_password_short_new_password_rejected(): void
    {
        $user = $this->registerDefault();

        $this->expectException(ValidationException::class);
        $this->auth->resetPassword($user->customerNo, 'Pamuk', 'kisa');
    }
}
