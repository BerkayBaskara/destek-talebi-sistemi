<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Model\Enum\Role;
use App\Model\Notification;
use App\Repository\NotificationRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/** Bildirim oluşturma (FR-18). E-posta yok; bildirimler uygulama içinde, çan simgesinde görünür. */
final class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly UserRepositoryInterface $users,
        private readonly ClockInterface $clock,
    ) {
    }

    public function notify(int $userId, string $type, ?int $ticketId, string $message): void
    {
        if ($userId <= 0) {
            return;
        }
        $this->notifications->add(new Notification(null, $userId, $type, $ticketId, $message, $this->clock->now()));
    }

    /** Tüm yöneticilere (ör. uygun personel bulunamayan eskalasyon, FR-57). */
    public function notifyAdmins(string $type, ?int $ticketId, string $message): void
    {
        foreach ($this->users->findByRole(Role::Admin) as $admin) {
            $this->notify((int) $admin->id, $type, $ticketId, $message);
        }
    }
}
