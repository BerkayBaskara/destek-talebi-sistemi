<?php

declare(strict_types=1);

namespace App\Service\Observer;

use App\Core\Clock\ClockInterface;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Model\TicketLog;
use App\Repository\TicketLogRepositoryInterface;
use App\Service\TicketService;

/** FR-19: her durum değişikliğini ticket_logs'a yazar (talep detayındaki zaman tüneli). */
final class TicketLogObserver implements TicketObserver
{
    public function __construct(
        private readonly TicketLogRepositoryInterface $logs,
        private readonly ClockInterface $clock,
    ) {
    }

    public function update(Ticket $ticket, string $eventType, array $context = []): void
    {
        $actor = is_int($context['actor_id'] ?? null) ? $context['actor_id'] : TicketLog::SYSTEM_USER;

        if ($eventType === TicketService::EVENT_CREATED) {
            $this->write($ticket, $actor, null, TicketStatus::Pending);

            return;
        }

        if ($eventType === TicketService::EVENT_STATUS_CHANGED) {
            $old = $context['old'] ?? null;
            $new = $context['new'] ?? $ticket->status;
            $this->write($ticket, $actor, $old instanceof TicketStatus ? $old : null, $new instanceof TicketStatus ? $new : $ticket->status);
        }
    }

    private function write(Ticket $ticket, int $actor, ?TicketStatus $old, TicketStatus $new): void
    {
        $this->logs->save(new TicketLog(null, (int) $ticket->id, $actor, $old, $new, $this->clock->now()));
    }
}
