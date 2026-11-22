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
    /** Yöneticinin açıp kapatabildiği bildirim türleri (US-45). */
    public const TYPES = [
        'assigned' => 'Personele talep atandı',
        'reply' => 'Talebe yeni yanıt yazıldı',
        'status' => 'Talebin durumu değişti',
        'resolved' => 'Talep çözüldü (müşteriden onay istenir)',
        'approved' => 'Müşteri çözümü onayladı',
        'rejected' => 'Müşteri çözümü reddetti',
        'sla_breach' => 'SLA aşıldı',
        'escalated' => 'Talep üst seviyeye aktarıldı',
        'escalation_failed' => 'Uygun üst seviye yok (yöneticiye)',
        'continue' => 'Müşteri aynı personelle devam etti',
        'closed' => 'Talep kapatıldı',
        'staff_deactivated' => 'Hareketsiz personel pasif yapıldı (yöneticiye)',
    ];

    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly UserRepositoryInterface $users,
        private readonly ClockInterface $clock,
        private readonly ?SettingsService $settings = null,
    ) {
    }

    public function isEnabled(string $type): bool
    {
        $disabled = array_filter(explode(',', $this->settings?->string('notify.disabled') ?? ''));

        return !in_array($type, $disabled, true);
    }

    public function notify(int $userId, string $type, ?int $ticketId, string $message): void
    {
        if ($userId <= 0 || !$this->isEnabled($type)) {
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
