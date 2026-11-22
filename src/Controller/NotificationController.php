<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Response;
use App\Exception\NotFoundException;
use App\Kernel;

/** Bildirimler: liste, tek bildirimi açma (okundu + talebe gitme), tümünü okundu yapma, çan sayacı (JSON). */
final class NotificationController
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function index(): Response
    {
        $user = $this->kernel->requireRole();

        return $this->kernel->render('notifications/index', [
            'title' => 'Bildirimler',
            'notifications' => $this->kernel->notificationRepository()->forUser((int) $user->id),
        ]);
    }

    public function open(int $id): Response
    {
        $user = $this->kernel->requireRole();
        $notification = $this->kernel->notificationRepository()->markRead((int) $user->id, $id, $this->kernel->clock()->now());
        if ($notification === null) {
            throw new NotFoundException('Bildirim bulunamadı.');
        }

        return Response::redirect($notification->ticketId !== null ? '/talepler/' . $notification->ticketId : '/bildirimler');
    }

    public function markAllRead(): Response
    {
        $user = $this->kernel->requireRole();
        $this->kernel->notificationRepository()->markAllRead((int) $user->id, $this->kernel->clock()->now());

        return Response::redirect('/bildirimler');
    }

    /** GET /api/bildirimler/sayi — okunmamış bildirim sayısı (oturum süresini uzatmaz). */
    public function count(): Response
    {
        $user = $this->kernel->requireRole();

        return Response::json(['count' => $this->kernel->notificationRepository()->unreadCount((int) $user->id)]);
    }
}
