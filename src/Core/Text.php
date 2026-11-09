<?php

declare(strict_types=1);

namespace App\Core;

/** Türkçe metin yardımcıları. */
final class Text
{
    /**
     * Türkçeye uygun küçük harfe çevirir. mb_strtolower 'İ'yi 'i̇' (noktalı) yapar ve 'I'yı 'i' yapar;
     * Türkçede doğrusu 'İ'→'i', 'I'→'ı'. Bu yüzden önce bu iki harf elle çevrilir.
     */
    public static function lower(string $text): string
    {
        return mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], $text), 'UTF-8');
    }

    public static function length(string $text): int
    {
        return mb_strlen($text, 'UTF-8');
    }
}
