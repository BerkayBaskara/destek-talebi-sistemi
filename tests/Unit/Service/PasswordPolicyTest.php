<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Şifre kuralı: en az 8 karakter, en az bir harf, bir rakam ve bir noktalama işareti. */
final class PasswordPolicyTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function cases(): array
    {
        return [
            'örnek veri şifresi' => ['Destek2026!', true],
            'Türkçe harfli' => ['Şifre12.', true],
            'tam 8 karakter' => ['abcdef1?', true],
            'noktalama yok' => ['destek2026', false],
            'rakam yok' => ['Destek!!!', false],
            'harf yok' => ['12345678!', false],
            'kısa' => ['Ab1!', false],
            'boşluk noktalama sayılmaz' => ['destek 2026', false],
        ];
    }

    #[DataProvider('cases')]
    public function test_policy(string $password, bool $valid): void
    {
        $error = PasswordPolicy::validate($password);

        if ($valid) {
            self::assertNull($error);
        } else {
            self::assertSame(PasswordPolicy::MESSAGE, $error);
        }
    }

    public function test_message_text(): void
    {
        self::assertSame('Şifre en az 8 karakter olmalı ve en az bir harf, bir rakam ve bir noktalama işareti içermeli.', PasswordPolicy::MESSAGE);
    }
}
