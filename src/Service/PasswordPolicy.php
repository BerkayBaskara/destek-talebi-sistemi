<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Şifre kuralı (tek yerde): en az 8 karakter, en az bir harf, bir rakam ve bir noktalama işareti.
 * Kayıt, şifre sıfırlama ve personel oluşturmada sunucuda zorunludur; tarayıcıdaki canlı kontrol yalnız kolaylıktır.
 */
final class PasswordPolicy
{
    public const MESSAGE = 'Şifre en az 8 karakter olmalı ve en az bir harf, bir rakam ve bir noktalama işareti içermeli.';

    /** Hata metni; şifre kurala uyuyorsa null. */
    public static function validate(#[\SensitiveParameter] string $password): ?string
    {
        $ok = mb_strlen($password) >= 8
            && preg_match('/\p{L}/u', $password) === 1
            && preg_match('/\p{N}/u', $password) === 1
            && preg_match('/[^\p{L}\p{N}\s]/u', $password) === 1;

        return $ok ? null : self::MESSAGE;
    }
}
