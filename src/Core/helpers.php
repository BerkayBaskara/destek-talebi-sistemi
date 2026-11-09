<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /** HTML çıktısını kaçırır (XSS önlemi). Şablonlarda her değişken bununla yazdırılır. */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
