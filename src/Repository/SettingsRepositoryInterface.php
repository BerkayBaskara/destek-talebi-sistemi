<?php

declare(strict_types=1);

namespace App\Repository;

/** Genel sistem ayarları (ER: system_settings). Anahtar → metin değer. */
interface SettingsRepositoryInterface
{
    public function get(string $key): ?string;

    public function set(string $key, string $value): void;

    /** @return array<string, string> */
    public function all(): array;
}
