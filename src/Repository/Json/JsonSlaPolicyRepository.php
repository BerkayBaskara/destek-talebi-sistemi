<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\Enum\Priority;
use App\Model\SlaPolicy;
use App\Repository\SlaPolicyRepositoryInterface;

/** sla_policies.json üzerinde SLA kuralı deposu. */
final class JsonSlaPolicyRepository implements SlaPolicyRepositoryInterface
{
    private const TABLE = 'sla_policies';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function findFor(int $categoryId, Priority $priority): ?SlaPolicy
    {
        foreach ($this->findAll() as $policy) {
            if ($policy->categoryId === $categoryId && $policy->priority === $priority) {
                return $policy;
            }
        }

        return null;
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
