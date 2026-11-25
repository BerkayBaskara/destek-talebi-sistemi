<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\AuthorizationException;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Kernel;
use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\State\InvalidTransitionException;
use App\Model\Ticket;
use App\Service\TicketService;

/**
 * FR-09: personelin AJAX Kanban panosu ve FR-07 canlı SLA göstergesi için JSON uç noktaları.
 * Tarayıcıdan gelen hiçbir durum bilgisine güvenilmez: her taşıma sunucuda State deseniyle denetlenir.
 */
final class KanbanController
{
    /** Kanban sütunları (yalnız ileri geçişler sürüklenebilir: Alındı → İnceleniyor → Çözüldü). */
    public const COLUMNS = [TicketStatus::Taken, TicketStatus::InReview, TicketStatus::Resolved];

    public function __construct(private readonly Kernel $kernel, private readonly TicketService $tickets)
    {
    }

    /**
     * Panodaki talepler: personelin kendi talepleri (yönetici için hepsi), yalnız Kanban sütunlarındaki durumlar.
     *
     * @return list<Ticket>
     */
    private function boardTickets(\App\Model\User $user): array
    {
        $tickets = $user->role === Role::Admin
            ? $this->kernel->ticketRepository()->findAll()
            : $this->kernel->ticketRepository()->findByAgent((int) $user->id);

        return array_values(array_filter($tickets, static fn (Ticket $t): bool => in_array($t->status, self::COLUMNS, true)));
    }

    /**
     * Panonun parmak izi: kartların kimliği ve durumu değişmedikçe aynı kalır.
     *
     * @param list<Ticket> $tickets
     */
    private static function version(array $tickets): string
    {
        $parts = array_map(static fn (Ticket $t): string => $t->id . ':' . $t->status->value, $tickets);
        sort($parts);

        return sha1(implode(',', $parts));
    }

    /**
     * GET /api/kanban/surum — sayfa birkaç saniyede bir sorar; sürüm değişince pano yenilenir (yeni atanan talep
     * personel sayfayı yenilemeden görünür). Oturum süresini uzatmaz (Kernel::PASSIVE_POLLS).
     */
    public function liveVersion(): Response
    {
        $tickets = $this->boardTickets($this->kernel->requireRole(Role::Agent, Role::Admin));
        $ids = array_map(static fn (Ticket $t): int => (int) $t->id, $tickets);
        sort($ids);

        return Response::json(['version' => self::version($tickets), 'ids' => $ids]);
    }

    public function board(): Response
    {
        $user = $this->kernel->requireRole(Role::Agent, Role::Admin);
        $tickets = $this->boardTickets($user);

        $columns = [];
        foreach (self::COLUMNS as $status) {
            $columns[$status->value] = ['status' => $status, 'cards' => []];
        }
        $sla = $this->kernel->slaStatusService();
        foreach ($tickets as $ticket) {
            if (isset($columns[$ticket->status->value])) {
                $columns[$ticket->status->value]['cards'][] = ['ticket' => $ticket, 'sla' => $sla->forTicket($ticket)];
            }
        }
        foreach ($columns as &$column) {
            usort($column['cards'], static fn (array $a, array $b): int => $a['ticket']->deadline <=> $b['ticket']->deadline);
        }
        unset($column);

        return $this->kernel->render('agent/kanban', ['title' => 'Kanban', 'columns' => $columns, 'version' => self::version($tickets)]);
    }

    /** POST /api/talepler/{id}/durum  gövde: {"status": "in_review"} */
    public function move(Request $request, int $id): Response
    {
        $user = $this->kernel->requireRole(Role::Agent, Role::Admin);
        $target = TicketStatus::tryFrom(is_string($request->json()['status'] ?? null) ? $request->json()['status'] : '');
        if ($target === null) {
            return Response::json(['ok' => false, 'error' => 'Geçersiz durum.'], 422);
        }

        try {
            $ticket = $this->tickets->moveOnKanban($id, $user, $target);
        } catch (InvalidTransitionException $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (ConflictException $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return Response::json([
            'ok' => true,
            'status' => $ticket->status->value,
            'label' => $ticket->status->label(),
            'sla' => $this->slaJson($ticket),
        ]);
    }

    /** GET /api/sla?ids=1,2,3 — yalnız kullanıcının görebildiği taleplerin SLA durumları. */
    public function sla(Request $request): Response
    {
        $user = $this->kernel->requireRole();
        $result = [];
        foreach (array_slice(array_unique(array_filter(array_map('intval', explode(',', $request->string('ids'))))), 0, 200) as $id) {
            try {
                $result[(string) $id] = $this->slaJson($this->tickets->findVisible($id, $user));
            } catch (AuthorizationException | NotFoundException) {
                // görmeye yetkisi olmayan veya olmayan talep yanıtta yer almaz
            }
        }

        return Response::json((object) $result);
    }

    /** @return array{level: string, label: string, css: string, percent: int} */
    private function slaJson(Ticket $ticket): array
    {
        $sla = $this->kernel->slaStatusService()->forTicket($ticket);

        return ['level' => $sla->level, 'label' => $sla->label, 'css' => $sla->cssClass(), 'percent' => $sla->percent];
    }
}
