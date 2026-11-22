<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Repository\LeaveRepositoryInterface;

/** staff_leaves.json: personel izin aralıkları. */
final class JsonLeaveRepository implements LeaveRepositoryInterface
{
    private const TABLE = 'staff_leaves';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function add(int $userId, string $start, string $end, string $note): int
    {
        return $this->store->insert(self::TABLE, ['user_id' => $userId, 'start' => $start, 'end' => $end, 'note' => $note]);
    }

    public function remove(int $id): void
    {
        if ($this->store->find(self::TABLE, $id) !== null) {
            $this->store->delete(self::TABLE, $id);
        }
    }

    public function all(): array
    {
        $list = [];
        foreach ($this->store->all(self::TABLE) as $row) {
            $list[] = [
                'id' => (int) $row['id'],
                'user_id' => (int) ($row['user_id'] ?? 0),
                'start' => (string) ($row['start'] ?? ''),
                'end' => (string) ($row['end'] ?? ''),
                'note' => (string) ($row['note'] ?? ''),
            ];
        }
        usort($list, static fn (array $a, array $b): int => [$a['start'], $a['id']] <=> [$b['start'], $b['id']]);

        return $list;
    }

    public function isOnLeave(int $userId, string $date): bool
    {
        foreach ($this->all() as $leave) {
            if ($leave['user_id'] === $userId && $leave['start'] <= $date && $date <= $leave['end']) {
                return true;
            }
        }

        return false;
    }
}
