<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\Category;
use App\Repository\CategoryRepositoryInterface;

/** categories.json üzerinde kategori deposu. */
final class JsonCategoryRepository implements CategoryRepositoryInterface
{
    private const TABLE = 'categories';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function findAll(): array
    {
        return array_map(static fn (array $row): Category => Category::fromArray($row), $this->store->all(self::TABLE));
    }

    public function findById(int $id): ?Category
    {
        $row = $this->store->find(self::TABLE, $id);

        return $row === null ? null : Category::fromArray($row);
    }

    public function save(Category $category): Category
    {
        $data = $category->toArray();
        unset($data['id']);
        if ($category->id === null) {
            $category->id = $this->store->insert(self::TABLE, $data);
        } else {
            $this->store->update(self::TABLE, $category->id, $data);
        }

        return $category;
    }
}
