<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Category;

interface CategoryRepositoryInterface
{
    /** @return list<Category> */
    public function findAll(): array;

    /** @return list<Category> yalnız aktif kategoriler (yeni talep formu) */
    public function findActive(): array;

    public function findById(int $id): ?Category;

    public function save(Category $category): Category;
}
