<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Response;
use App\Exception\ConflictException;
use App\Kernel;
use App\Model\Enum\Role;
use App\Model\State\InvalidTransitionException;
use App\Model\Ticket;
use App\Service\TicketService;

/** Personel ekranları: ortak havuz ("üzerime al") ve bana atanan talepler listesi. */
final class AgentController
{
    public function __construct(private readonly Kernel $kernel, private readonly TicketService $tickets)
    {
    }

    public function pool(): Response
    {
        $this->kernel->requireRole(Role::Agent, Role::Admin);

        return $this->kernel->render('agent/pool', [
            'title' => 'Ortak Havuz',
            'rows' => $this->rows($this->kernel->ticketRepository()->getPoolTickets()),
        ]);
    }

    /** NFR-04: aynı talebi iki personel aynı anda alamaz; ikinciye anlaşılır mesaj gösterilir. */
    public function take(int $id): Response
    {
        $agent = $this->kernel->requireRole(Role::Agent, Role::Admin);

        try {
            $this->tickets->take($id, $agent);
        } catch (ConflictException | InvalidTransitionException $e) {
            $this->kernel->session()->flash('error', $e->getMessage());

            return Response::redirect('/havuz');
        }
        $this->kernel->session()->flash('success', 'Talep #' . $id . ' üzerinize alındı.');

        return Response::redirect('/talepler/' . $id);
    }

    /** Wireframe: "Bana Atanan Talepler" (aktif olanlar önce, SLA'sı en yakın olan en üstte). */
    public function myTickets(): Response
    {
        $agent = $this->kernel->requireRole(Role::Agent);
        $tickets = $this->kernel->ticketRepository()->findByAgent((int) $agent->id);
        usort($tickets, static fn (Ticket $a, Ticket $b): int => [$a->status->isFinal(), $a->deadline] <=> [$b->status->isFinal(), $b->deadline]);

        return $this->kernel->render('agent/tickets', ['title' => 'Bana Atanan Talepler', 'rows' => $this->rows($tickets)]);
    }

    /**
     * @param list<Ticket> $tickets
     * @return list<array{ticket: Ticket, sla: \App\Model\SlaStatus}>
     */
    private function rows(array $tickets): array
    {
        $sla = $this->kernel->slaStatusService();

        return array_map(static fn (Ticket $t): array => ['ticket' => $t, 'sla' => $sla->forTicket($t)], $tickets);
    }
}
