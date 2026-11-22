<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Repository\HierarchyRepositoryInterface;

/** category_staff.json: kategori, personel ve seviye satırları. */
final class JsonHierarchyRepository implements HierarchyRepositoryInterface
{
    private const TABLE = 'category_staff';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function levelsFor(int $categoryId): array
    {
        $levels = [];
        foreach ($this->store->all(self::TABLE) as $row) {
            if (($row['category_id'] ?? null) === $categoryId) {
                $levels[(int) $row['user_id']] = (int) $row['level'];
            }
        }
        ksort($levels);

        return $levels;
    }

    public function setLevel(int $categoryId, int $userId, ?int $level): void
    {
        $this->store->transaction([self::TABLE], function (JsonStore $store) use ($categoryId, $userId, $level): void {
            foreach ($store->all(self::TABLE) as $row) {
                if (($row['category_id'] ?? null) === $categoryId && ($row['user_id'] ?? null) === $userId) {
                    if ($level === null) {
                        $store->delete(self::TABLE, (int) $row['id']);
                    } else {
                        $store->update(self::TABLE, (int) $row['id'], ['level' => $level] + $row);
                    }

                    return;
                }
            }
            if ($level !== null) {
                $store->insert(self::TABLE, ['category_id' => $categoryId, 'user_id' => $userId, 'level' => $level]);
            }
        });
    }

    public function replaceLevels(int $categoryId, array $levels): void
    {
        $this->store->transaction([self::TABLE], function (JsonStore $store) use ($categoryId, $levels): void {
            foreach ($store->all(self::TABLE) as $row) {
                if (($row['category_id'] ?? null) === $categoryId) {
                    $store->delete(self::TABLE, (int) $row['id']);
                }
            }
            foreach ($levels as $userId => $level) {
                $store->insert(self::TABLE, ['category_id' => $categoryId, 'user_id' => $userId, 'level' => $level]);
            }
        });
    }
}
