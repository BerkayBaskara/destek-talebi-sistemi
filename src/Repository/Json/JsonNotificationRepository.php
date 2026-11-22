<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\Notification;
use App\Repository\NotificationRepositoryInterface;
use DateTimeImmutable;

/** notifications.json üzerinde bildirim deposu. */
final class JsonNotificationRepository implements NotificationRepositoryInterface
{
    private const TABLE = 'notifications';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function add(Notification $notification): Notification
    {
        $data = $notification->toArray();
        unset($data['id']);
        $notification->id = $this->store->insert(self::TABLE, $data);

        return $notification;
    }

    public function forUser(int $userId, int $limit = 50): array
    {
        $list = [];
        foreach ($this->store->all(self::TABLE) as $row) {
            if (($row['user_id'] ?? null) === $userId) {
                $list[] = Notification::fromArray($row);
            }
        }
        usort($list, static fn (Notification $a, Notification $b): int => [$b->createdAt, $b->id] <=> [$a->createdAt, $a->id]);

        return array_slice($list, 0, $limit);
    }

    public function unreadCount(int $userId): int
    {
        $count = 0;
        foreach ($this->store->all(self::TABLE) as $row) {
            if (($row['user_id'] ?? null) === $userId && ($row['read_at'] ?? null) === null) {
                $count++;
            }
        }

        return $count;
    }

    public function markRead(int $userId, int $id, DateTimeImmutable $at): ?Notification
    {
        $row = $this->store->find(self::TABLE, $id);
        if ($row === null || ($row['user_id'] ?? null) !== $userId) {
            return null;
        }
        if (($row['read_at'] ?? null) === null) {
            $row['read_at'] = $at->format(DATE_ATOM);
            $this->store->update(self::TABLE, $id, $row);
        }

        return Notification::fromArray($row);
    }

    public function markUnread(int $userId, int $id): bool
    {
        $row = $this->store->find(self::TABLE, $id);
        if ($row === null || ($row['user_id'] ?? null) !== $userId) {
            return false;
        }
        $row['read_at'] = null;
        $this->store->update(self::TABLE, $id, $row);

        return true;
    }

    public function markAllRead(int $userId, DateTimeImmutable $at): void
    {
        $this->store->transaction([self::TABLE], function (JsonStore $store) use ($userId, $at): void {
            foreach ($store->all(self::TABLE) as $row) {
                if (($row['user_id'] ?? null) === $userId && ($row['read_at'] ?? null) === null) {
                    $row['read_at'] = $at->format(DATE_ATOM);
                    $store->update(self::TABLE, (int) $row['id'], $row);
                }
            }
        });
    }
}
