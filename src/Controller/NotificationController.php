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

    /** NFR-33: sayfa başına en fazla 50 bildirim, en yeni önce. */
    public const PER_PAGE = 50;

    public function index(\App\Core\Http\Request $request): Response
    {
        $user = $this->kernel->requireRole();
        $all = $this->kernel->notificationRepository()->forUser((int) $user->id, PHP_INT_MAX);
        $page = \App\Service\TicketService::paginate($all, max(1, (int) $request->string('sayfa')), self::PER_PAGE);

        return $this->kernel->render('notifications/index', [
            'title' => 'Bildirimler',
            'notifications' => $page['items'],
            'page' => $page['page'],
            'pages' => $page['pages'],
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

    public function markUnread(int $id): Response
    {
        $user = $this->kernel->requireRole();
        if (!$this->kernel->notificationRepository()->markUnread((int) $user->id, $id)) {
            throw new NotFoundException('Bildirim bulunamadı.');
        }

        return Response::redirect('/bildirimler');
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
