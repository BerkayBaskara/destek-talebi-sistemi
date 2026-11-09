<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Enum\Priority;
use App\Model\SlaPolicy;

interface SlaPolicyRepositoryInterface
{
    public function findFor(int $categoryId, Priority $priority): ?SlaPolicy;

    /** @return list<SlaPolicy> */
    public function findAll(): array;

    public function save(SlaPolicy $policy): SlaPolicy;
}
