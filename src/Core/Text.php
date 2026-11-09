<?php

declare(strict_types=1);

namespace App\Core;

/** Türkçe metin yardımcıları. */
final class Text
{
    /**
     * Türkçeye uygun küçük harfe çevirir (ekranda gösterim için). mb_strtolower 'İ'yi 'i̇' (noktalı) yapar
     * ve 'I'yı 'i' yapar; Türkçede doğrusu 'İ'→'i', 'I'→'ı'. Bu yüzden önce bu iki harf elle çevrilir.
     */
    public static function lower(string $text): string
    {
        return mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], $text), 'UTF-8');
    }

    /**
     * KARŞILAŞTIRMA için katlama: küçük harf + 'ı' ile 'i' aynı sayılır. Böylece "Internet", "İNTERNET",
     * "internet"; "ACIL" ile "acil"; "Istanbul" ile "istanbul" eşleşir (arama, anahtar kelime, gizli cevap).
     */
    public static function fold(string $text): string
    {
        return str_replace('ı', 'i', self::lower($text));
    }

    public static function length(string $text): int
    {
        return mb_strlen($text, 'UTF-8');
    }
}
