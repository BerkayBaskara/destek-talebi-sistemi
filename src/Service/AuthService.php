<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Text;
use App\Exception\ValidationException;
use App\Model\Enum\Role;
use App\Model\User;
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

    private const MIN_PASSWORD = 8;

    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function register(
        string $name,
        string $surname,
        #[\SensitiveParameter] string $password,
        string $secretQuestion,
        #[\SensitiveParameter] string $secretAnswer,
    ): User
    {
        $name = trim($name);
        $surname = trim($surname);
        $errors = [];
        if (Text::length($name) < 2 || Text::length($name) > 100) {
            $errors['name'] = 'Ad en az 2 karakter olmalı.';
        }
        if (Text::length($surname) < 2 || Text::length($surname) > 100) {
            $errors['surname'] = 'Soyad en az 2 karakter olmalı.';
        }
        if (Text::length($password) < self::MIN_PASSWORD) {
            $errors['password'] = 'Şifre en az 8 karakter olmalı.';
        }
        if (!in_array($secretQuestion, self::SECRET_QUESTIONS, true)) {
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
        $user = $this->users->findByCustomerNo(self::normalizeNumber($customerNo));
        if ($user === null || !password_verify($password, $user->passwordHash)) {
            return null;
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
        if (Text::length($newPassword) < self::MIN_PASSWORD) {
            throw new ValidationException(['password' => 'Şifre en az 8 karakter olmalı.']);
        }

        $user = $this->users->findByCustomerNo(self::normalizeNumber($customerNo));
        if ($user === null || !password_verify(self::normalizeAnswer($secretAnswer), $user->secretAnswerHash)) {
            return false;
        }

        $user->passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->users->save($user);

        return true;
    }

    /** Personel/yönetici hesabı (örnek veri için). */
    public function createStaff(Role $role, string $name, string $surname, string $password): User
    {
        return $this->users->save(new User(
            null,
            $this->newCustomerNumber($role),
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            $name,
            $surname,
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
