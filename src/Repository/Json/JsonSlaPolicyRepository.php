<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\SlaPolicy;
use App\Repository\SlaPolicyRepositoryInterface;

/** sla_policies.json üzerinde SLA kuralı deposu. */
final class JsonSlaPolicyRepository implements SlaPolicyRepositoryInterface
{
    private const TABLE = 'sla_policies';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function findFor(int $categoryId, int $priorityId): ?SlaPolicy
    {
        foreach ($this->findAll() as $policy) {
            if ($policy->categoryId === $categoryId && $policy->priorityId === $priorityId) {
                return $policy;
            }
        }

        return null;
    }

    public function remove(int $categoryId, int $priorityId): void
    {
        $policy = $this->findFor($categoryId, $priorityId);
        if ($policy !== null && $policy->id !== null) {
            $this->store->delete(self::TABLE, $policy->id);
        }
    }

    public function findAll(): array
    {
        return array_map(static fn (array $row): SlaPolicy => SlaPolicy::fromArray($row), $this->store->all(self::TABLE));
    }

    public function save(SlaPolicy $policy): SlaPolicy
    {
        $data = $policy->toArray();
        unset($data['id']);
        if ($policy->id === null) {
            $policy->id = $this->store->insert(self::TABLE, $data);
        } else {
            $this->store->update(self::TABLE, $policy->id, $data);
        }

        return $policy;
    }
}
