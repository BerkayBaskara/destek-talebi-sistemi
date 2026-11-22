<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Repository\AttemptRepositoryInterface;

/** attempts.json: anahtar (ör. "reset:M100001") ve zaman damgası. */
final class JsonAttemptRepository implements AttemptRepositoryInterface
{
    private const TABLE = 'attempts';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function record(string $key, int $at): void
    {
        $this->store->insert(self::TABLE, ['key' => $key, 'at' => $at]);
    }

    public function countSince(string $key, int $since): int
    {
        $count = 0;
        foreach ($this->store->all(self::TABLE) as $row) {
            if (($row['key'] ?? null) === $key && (int) ($row['at'] ?? 0) >= $since) {
                $count++;
            }
        }

        return $count;
    }

    public function clear(string $key): void
    {
        $this->store->transaction([self::TABLE], function (JsonStore $store) use ($key): void {
            foreach ($store->all(self::TABLE) as $row) {
                if (($row['key'] ?? null) === $key) {
                    $store->delete(self::TABLE, (int) $row['id']);
                }
            }
        });
    }
}
