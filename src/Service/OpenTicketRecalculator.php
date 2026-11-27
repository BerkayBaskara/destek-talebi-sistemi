<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Model\Enum\TicketStatus;
use App\Repository\TicketRepositoryInterface;

/**
 * FR-13.4: takvim değişikliği açık taleplere de uygulansın mı (yönetici ayarı, varsayılan hayır).
 * Uygulanırsa her açık talebin değişiklikten önceki kalan iş süresi yeni takvime göre şimdiden itibaren yeniden
 * hesaplanır; ilk SLA bitişi (original_deadline) korunur.
 */
final class OpenTicketRecalculator
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly SlaCalculatorService $sla,
        private readonly SettingsService $settings,
        private readonly ClockInterface $clock,
    ) {
    }

    public function enabled(): bool
    {
        return $this->settings->string('calendar.recalculate_open') === '1';
    }

    /** @return array<int, int> talep id → kalan iş dakikası (değişiklikten önceki takvimle) */
    public function snapshot(): array
    {
        if (!$this->enabled()) {
            return [];
        }
        $now = $this->clock->now();
        $remaining = [];
        foreach ($this->tickets->findAll() as $ticket) {
            if (in_array($ticket->status, [TicketStatus::Pending, TicketStatus::Taken, TicketStatus::InReview], true)
                && $ticket->pausedAt === null && $ticket->deadline > $now) {
                $remaining[(int) $ticket->id] = $this->sla->businessMinutesBetween($now, $ticket->deadline);
            }
        }

        return $remaining;
    }

    /** @param array<int, int> $snapshot */
    public function apply(array $snapshot): int
    {
        $now = $this->clock->now();
        $changed = 0;
        foreach ($snapshot as $id => $minutes) {
            $ticket = $this->tickets->findById($id);
            if ($ticket === null || $ticket->status->isFinal() || $ticket->status === TicketStatus::Resolved) {
                continue;
            }
            $ticket->deadline = $this->sla->calculateWithJson($now, $minutes);
            if ($this->tickets->saveIfUnchanged($ticket, $ticket->status, $ticket->agentId)) {
                $changed++;
            }
        }

        return $changed;
    }
}
