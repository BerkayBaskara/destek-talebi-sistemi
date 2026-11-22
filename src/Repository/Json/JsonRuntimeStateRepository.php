<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Repository\RuntimeStateRepositoryInterface;

/** runtime_state.json: anahtar → değer satırları. */
final class JsonRuntimeStateRepository implements RuntimeStateRepositoryInterface
{
    private const TABLE = 'runtime_state';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function claim(string $key, int $now, int $interval): bool
    {
        return (bool) $this->store->transaction([self::TABLE], function (JsonStore $store) use ($key, $now, $interval): bool {
            foreach ($store->all(self::TABLE) as $row) {
                if (($row['key'] ?? null) === $key) {
                    if ($now - (int) ($row['value'] ?? 0) < $interval) {
                        return false;
                    }
                    $store->update(self::TABLE, (int) $row['id'], ['key' => $key, 'value' => $now]);

                    return true;
                }
            }
            $store->insert(self::TABLE, ['key' => $key, 'value' => $now]);

            return true;
        });
    }
}
