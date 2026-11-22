<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Priority;

/** Öncelik listesine erişim. Aşama 2: JsonPriorityRepository, Aşama 3: MySqlPriorityRepository. */
interface PriorityRepositoryInterface
{
    /** @return list<Priority> küçükten büyüğe (rank) */
    public function findAll(): array;

    /** @return list<Priority> yalnız aktifler, küçükten büyüğe */
    public function findActive(): array;

    public function findById(int $id): ?Priority;

    /** id yoksa ekler (verilen id varsa onu korur), varsa günceller. */
    public function save(Priority $priority): Priority;
}
