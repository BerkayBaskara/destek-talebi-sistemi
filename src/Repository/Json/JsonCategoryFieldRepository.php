<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Repository\CategoryFieldRepositoryInterface;

/** category_fields.json: kategoriye özel ek alanlar. */
final class JsonCategoryFieldRepository implements CategoryFieldRepositoryInterface
{
    private const TABLE = 'category_fields';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function forCategory(int $categoryId): array
    {
        return array_values(array_filter($this->all(), static fn (array $f): bool => $f['category_id'] === $categoryId));
    }

    public function all(): array
    {
        $list = [];
        foreach ($this->store->all(self::TABLE) as $row) {
            $list[] = [
                'id' => (int) $row['id'],
                'category_id' => (int) ($row['category_id'] ?? 0),
                'label' => (string) ($row['label'] ?? ''),
                'type' => (string) ($row['type'] ?? 'text'),
                'required' => (bool) ($row['required'] ?? false),
            ];
        }

        return $list;
    }

    public function add(int $categoryId, string $label, string $type, bool $required): int
    {
        return $this->store->insert(self::TABLE, ['category_id' => $categoryId, 'label' => $label, 'type' => $type, 'required' => $required]);
    }

    public function remove(int $id): void
    {
        if ($this->store->find(self::TABLE, $id) !== null) {
            $this->store->delete(self::TABLE, $id);
        }
    }
}
