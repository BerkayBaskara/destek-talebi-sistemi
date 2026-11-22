<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Text;
use App\Exception\AuthorizationException;
use App\Exception\ValidationException;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\AttemptRepositoryInterface;
use App\Core\Clock\ClockInterface;
use App\Repository\UserRepositoryInterface;

/**
 * Kayıt, giriş ve gizli soruyla şifre sıfırlama (US-06). E-posta veya SMS kullanılmaz.
 * Kayıtta sistem bir müşteri numarası üretir; kullanıcı bu numara ve şifresiyle giriş yapar.
 * Şifre ve gizli soru cevabı yalnız password_hash ile saklanır, düz metin hiçbir yerde tutulmaz.
 */
final class AuthService
{
    public const SECRET_QUESTIONS = [
        'İlk evcil hayvanınızın adı nedir?',
        'Doğduğunuz şehir neresidir?',
        'İlkokul öğretmeninizin soyadı nedir?',
        'En sevdiğiniz yemek nedir?',
    ];

    /** Giriş ve şifre sıfırlamada hatalı deneme sınırı (FR-31, NFR-19); değerler yönetici ayarından. */
    private function attemptLimit(): int
    {
        return $this->settings?->int('auth.max_attempts') ?? 5;
    }

    private function lockMinutes(): int
    {
        return $this->settings?->int('auth.lock_minutes') ?? 5;
    }

    /** Bu anahtarda engel varsa nedenini atar. @throws AuthorizationException */
    private function assertNotLocked(string $key, int $now): void
    {
        if ($this->attempts !== null && $this->attempts->countSince($key, $now - $this->lockMinutes() * 60) >= $this->attemptLimit()) {
            throw new AuthorizationException('Çok fazla hatalı deneme yapıldı. ' . $this->lockMinutes() . ' dakika sonra tekrar deneyin.');
        }
    }

    private function now(): int
    {
        return $this->clock?->now()->getTimestamp() ?? time();
    }

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ?SettingsService $settings = null,
        private readonly ?AttemptRepositoryInterface $attempts = null,
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    /**
     * Kayıtta seçilebilen güvenlik soruları (yönetici düzenler; ayar yoksa varsayılan liste).
     *
     * @return list<string>
     */
    public function questions(): array
    {
        $list = $this->settings?->lines('auth.security_questions') ?? [];

        return $list !== [] ? $list : self::SECRET_QUESTIONS;
    }

    public function register(
        string $name,
        string $surname,
        #[\SensitiveParameter] string $password,
        string $secretQuestion,
        #[\SensitiveParameter] string $secretAnswer,
        bool $ownQuestion = false,
    ): User
    {
        $secretQuestion = trim($secretQuestion);
        $name = trim($name);
        $surname = trim($surname);
        $errors = [];
        if (Text::length($name) < 2 || Text::length($name) > 100) {
            $errors['name'] = 'Ad en az 2 karakter olmalı.';
        }
        if (Text::length($surname) < 2 || Text::length($surname) > 100) {
            $errors['surname'] = 'Soyad en az 2 karakter olmalı.';
        }
        $passwordError = PasswordPolicy::validate($password);
        if ($passwordError !== null) {
            $errors['password'] = $passwordError;
        }
        if ($ownQuestion) {
            if (Text::length($secretQuestion) < 5 || Text::length($secretQuestion) > 150) {
                $errors['custom_question'] = 'Kendi sorunuz 5-150 karakter olmalı.';
            }
        } elseif (!in_array($secretQuestion, $this->questions(), true)) {
            $errors['secret_question'] = 'Listeden bir gizli soru seçin.';
        }
        if (Text::length(trim($secretAnswer)) < 2) {
            $errors['secret_answer'] = 'Gizli sorunun cevabı en az 2 karakter olmalı.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->users->save(new User(
            null,
            $this->newCustomerNumber(Role::Customer),
            password_hash($password, PASSWORD_DEFAULT),
            Role::Customer,
            $name,
            $surname,
            $secretQuestion,
            password_hash(self::normalizeAnswer($secretAnswer), PASSWORD_DEFAULT),
        ));
    }

    public function login(string $customerNo, #[\SensitiveParameter] string $password): ?User
    {
        $number = self::normalizeNumber($customerNo);
        $key = 'login:' . $number;
        $now = $this->now();
        $this->assertNotLocked($key, $now);

        $user = $this->users->findByCustomerNo($number);
        if ($user === null || !password_verify($password, $user->passwordHash)) {
            $this->attempts?->record($key, $now);

            return null;
        }
        $this->attempts?->clear($key);
        if (!$user->active) {
            throw new AuthorizationException('Hesabınız pasif durumda. Lütfen yöneticinize başvurun.');
        }
        if ($this->clock !== null) {
            $user->lastLoginAt = $this->clock->now();
            $this->users->save($user);
        }

        return $user;
    }

    public function secretQuestionFor(string $customerNo): ?string
    {
        return $this->users->findByCustomerNo(self::normalizeNumber($customerNo))?->secretQuestion;
    }

    /** Cevap doğruysa şifreyi değiştirir ve true döner; yanlışsa hiçbir şey değişmez. */
    public function resetPassword(
        string $customerNo,
        #[\SensitiveParameter] string $secretAnswer,
        #[\SensitiveParameter] string $newPassword,
    ): bool
    {
        $passwordError = PasswordPolicy::validate($newPassword);
        if ($passwordError !== null) {
            throw new ValidationException(['password' => $passwordError]);
        }

        $number = self::normalizeNumber($customerNo);
        $key = 'reset:' . $number;
        $now = $this->now();
        try {
            $this->assertNotLocked($key, $now);
        } catch (AuthorizationException $e) {
            throw new ValidationException(['secret_answer' => $e->getMessage()]);
        }

        $user = $this->users->findByCustomerNo($number);
        if ($user === null || !password_verify(self::normalizeAnswer($secretAnswer), $user->secretAnswerHash)) {
            $this->attempts?->record($key, $now);

            return false;
        }

        $user->passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->users->save($user);
        $this->attempts?->clear($key);

        return true;
    }

    /** Personel/yönetici hesabı (yönetici ekranı, yönetici komutu ve örnek veri). */
    public function createStaff(Role $role, string $name, string $surname, #[\SensitiveParameter] string $password): User
    {
        $errors = [];
        if (Text::length(trim($name)) < 2 || Text::length(trim($name)) > 100) {
            $errors['name'] = 'Ad en az 2 karakter olmalı.';
        }
        if (Text::length(trim($surname)) < 2 || Text::length(trim($surname)) > 100) {
            $errors['surname'] = 'Soyad en az 2 karakter olmalı.';
        }
        $passwordError = PasswordPolicy::validate($password);
        if ($passwordError !== null) {
            $errors['password'] = $passwordError;
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->users->save(new User(
            null,
            $this->newCustomerNumber($role),
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            trim($name),
            trim($surname),
            self::SECRET_QUESTIONS[0],
            password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
        ));
    }

    /** Ör. M + 6 hane; çakışırsa yeniden üretilir. */
    private function newCustomerNumber(Role $role): string
    {
        do {
            $number = $role->prefix() . random_int(100000, 999999);
        } while ($this->users->findByCustomerNo($number) !== null);

        return $number;
    }

    private static function normalizeNumber(string $customerNo): string
    {
        return strtoupper(trim($customerNo));
    }

    /** "  Pamuk " ve "pamuk", "Istanbul" ve "İSTANBUL" aynı cevap sayılır. */
    private static function normalizeAnswer(string $answer): string
    {
        return Text::fold(trim($answer));
    }
}
