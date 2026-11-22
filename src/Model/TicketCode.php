<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Kullanıcıya gösterilen 6 karakterli talep kodu (FR-34). İç işlemler sayısal kimlikle yürür (FR-35).
 * Karıştırılan karakterler (0/O, 1/I) kullanılmaz; kod büyük harf ve rakamdan oluşur.
 */
final class TicketCode
{
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    public const LENGTH = 6;

    public static function generate(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    /** Kullanıcının yazdığı kodu ("#ab23cd") karşılaştırılabilir biçime çevirir; kod değilse null. */
    public static function normalize(string $text): ?string
    {
        $code = strtoupper(ltrim(trim($text), '#'));
        $pattern = '/^[' . self::ALPHABET . ']{' . self::LENGTH . '}$/';

        return preg_match($pattern, $code) === 1 ? $code : null;
    }
}
