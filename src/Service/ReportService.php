<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Enum\Priority;
use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Model\User;
use App\Repository\TicketRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/**
 * Aşama 2 özet raporu: durum ve öncelik dağılımı, şu an SLA uyarısındaki ve aşılmış talepler,
 * personel iş yükü, ortalama çözüm süresi (iş saatiyle) ve süresinde çözülme oranı.
 * (Personel bazlı ayrıntılı SLA raporu FR-12, Aşama 3.)
 */
final class ReportService
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly UserRepositoryInterface $users,
        private readonly SlaStatusService $slaStatus,
        private readonly SlaCalculatorService $calculator,
    ) {
    }

    /**
     * @return array{
     *     total: int,
     *     byStatus: array<string, int>,
     *     byPriority: array<int, int>,
     *     warning: list<Ticket>,
     *     breached: list<Ticket>,
     *     workload: list<array{agent: User, active: int}>,
     *     avgResolutionMinutes: ?int,
     *     resolvedCount: int,
     *     metPercent: ?int
     * }
     */
    public function summary(): array
    {
        $all = $this->tickets->findAll();

        $byStatus = [];
        foreach (TicketStatus::cases() as $status) {
            $byStatus[$status->value] = 0;
        }
        $byPriority = [];
        foreach (Priority::cases() as $priority) {
            $byPriority[$priority->value] = 0;
        }

        $warning = [];
        $breached = [];
        $resolutionMinutes = [];
        $met = 0;
        foreach ($all as $ticket) {
            $byStatus[$ticket->status->value]++;
            $byPriority[$ticket->priority->value]++;

            $level = $this->slaStatus->forTicket($ticket)->level;
            if ($level === 'warning') {
                $warning[] = $ticket;
            } elseif ($level === 'breached') {
                $breached[] = $ticket;
            }

            if ($ticket->resolvedAt !== null) {
                $resolutionMinutes[] = $this->calculator->businessMinutesBetween($ticket->createdAt, $ticket->resolvedAt);
                $met += $ticket->resolvedAt <= $ticket->deadline ? 1 : 0;
            }
        }

        // En acil olan en üstte
        usort($warning, static fn (Ticket $a, Ticket $b): int => $a->deadline <=> $b->deadline);
        usort($breached, static fn (Ticket $a, Ticket $b): int => $a->deadline <=> $b->deadline);

        $load = $this->tickets->countActiveByAgent();
        $workload = array_map(
            static fn (User $agent): array => ['agent' => $agent, 'active' => $load[(int) $agent->id] ?? 0],
            $this->users->findByRole(Role::Agent),
        );
        usort($workload, static fn (array $a, array $b): int => $b['active'] <=> $a['active']);

        $resolvedCount = count($resolutionMinutes);

        return [
            'total' => count($all),
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'warning' => $warning,
            'breached' => $breached,
            'workload' => $workload,
            'avgResolutionMinutes' => $resolvedCount > 0 ? (int) round(array_sum($resolutionMinutes) / $resolvedCount) : null,
            'resolvedCount' => $resolvedCount,
            'metPercent' => $resolvedCount > 0 ? (int) round($met * 100 / $resolvedCount) : null,
        ];
    }
}
