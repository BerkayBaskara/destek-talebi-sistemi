<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\SlaPolicy;

interface SlaPolicyRepositoryInterface
{
    public function findFor(int $categoryId, int $priorityId): ?SlaPolicy;

    /** Kategori × öncelik kuralını kaldırır (öncelik varsayılanına döner). */
    public function remove(int $categoryId, int $priorityId): void;

    /** @return list<SlaPolicy> */
    public function findAll(): array;

    public function save(SlaPolicy $policy): SlaPolicy;
}
