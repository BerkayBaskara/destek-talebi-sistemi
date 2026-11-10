<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Kernel;
use App\Model\Enum\Priority;
use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Service\ReportService;
use App\Service\TicketService;

/** Yönetici ekranları: tüm talepler (filtre + arama) ve Aşama 2 özet raporu. */
final class AdminController
{
    public function __construct(
        private readonly Kernel $kernel,
        private readonly TicketService $tickets,
        private readonly ReportService $reports,
    ) {
    }

    public function tickets(Request $request): Response
    {
        $this->kernel->requireRole(Role::Admin);
        $status = TicketStatus::tryFrom($request->string('durum'));
        $priority = Priority::tryFrom((int) $request->string('oncelik'));
        $agentId = (int) $request->string('personel');
        $query = $request->string('q');

        $result = $this->tickets->searchAll($status, $priority, $agentId > 0 ? $agentId : null, $query, max(1, (int) $request->string('sayfa')));

        $sla = $this->kernel->slaStatusService();
        $users = $this->kernel->userRepository();
        $names = [];
        foreach ($result['items'] as $ticket) {
            foreach ([$ticket->creatorId, (int) $ticket->agentId] as $id) {
                if ($id > 0 && !isset($names[$id])) {
                    $names[$id] = $users->findById($id)?->fullName() ?? '-';
                }
            }
        }

        return $this->kernel->render('admin/tickets', [
            'title' => 'Tüm Talepler',
            'result' => $result,
            'slas' => array_map(static fn (Ticket $t) => $sla->forTicket($t), $result['items']),
            'names' => $names,
            'agents' => $users->findByRole(Role::Agent),
            'filters' => [
                'durum' => $status->value ?? '',
                'oncelik' => $priority !== null ? (string) $priority->value : '',
                'personel' => $agentId > 0 ? (string) $agentId : '',
                'q' => $query,
            ],
        ]);
    }

    public function summary(): Response
    {
        $this->kernel->requireRole(Role::Admin);

        return $this->kernel->render('admin/summary', [
            'title' => 'Özet Rapor',
            'summary' => $this->reports->summary(),
            'format' => fn (int $minutes): string => $this->kernel->slaStatusService()->formatMinutes($minutes),
        ]);
    }
}
