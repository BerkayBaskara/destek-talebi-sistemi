<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Notification;
use DateTimeImmutable;

interface NotificationRepositoryInterface
{
    public function add(Notification $notification): Notification;

    /** @return list<Notification> en yeni önce */
    public function forUser(int $userId, int $limit = 50): array;

    public function unreadCount(int $userId): int;

    /** Bildirim bu kullanıcıya aitse okundu yapar ve döner; değilse null. */
    public function markRead(int $userId, int $id, DateTimeImmutable $at): ?Notification;

    public function markAllRead(int $userId, DateTimeImmutable $at): void;
}
