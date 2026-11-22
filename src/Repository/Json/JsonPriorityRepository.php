<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\Priority;
use App\Repository\PriorityRepositoryInterface;

/** priorities.json üzerinde öncelik deposu. */
final class JsonPriorityRepository implements PriorityRepositoryInterface
{
    private const TABLE = 'priorities';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function findAll(): array
    {
        $priorities = array_map(static fn (array $row): Priority => Priority::fromArray($row), $this->store->all(self::TABLE));
        usort($priorities, static fn (Priority $a, Priority $b): int => [$a->rank, $a->id] <=> [$b->rank, $b->id]);

        return $priorities;
    }

    public function findActive(): array
    {
        return array_values(array_filter($this->findAll(), static fn (Priority $p): bool => $p->active));
    }

    public function findById(int $id): ?Priority
    {
        $row = $this->store->find(self::TABLE, $id);

        return $row === null ? null : Priority::fromArray($row);
    }

    public function save(Priority $priority): Priority
    {
        $data = $priority->toArray();
        unset($data['id']);
        if ($priority->id !== null && $this->store->find(self::TABLE, $priority->id) !== null) {
            $this->store->update(self::TABLE, $priority->id, $data);
        } else {
            $priority->id = $this->store->insert(self::TABLE, $data);
        }

        return $priority;
    }
}
