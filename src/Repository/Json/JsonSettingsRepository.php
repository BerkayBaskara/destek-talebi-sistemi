<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Repository\SettingsRepositoryInterface;

/** system_settings.json üzerinde ayar deposu (her satır: id, key, value). */
final class JsonSettingsRepository implements SettingsRepositoryInterface
{
    private const TABLE = 'system_settings';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        $this->store->transaction([self::TABLE], function (JsonStore $store) use ($key, $value): void {
            foreach ($store->all(self::TABLE) as $row) {
                if (($row['key'] ?? null) === $key) {
                    $store->update(self::TABLE, (int) $row['id'], ['value' => $value]);

                    return;
                }
            }
            $store->insert(self::TABLE, ['key' => $key, 'value' => $value]);
        });
    }

    public function all(): array
    {
        $values = [];
        foreach ($this->store->all(self::TABLE) as $row) {
            $values[(string) ($row['key'] ?? '')] = (string) ($row['value'] ?? '');
        }

        return $values;
    }
}
