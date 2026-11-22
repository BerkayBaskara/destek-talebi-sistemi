<?php

declare(strict_types=1);

namespace App\Service\Observer;

use App\Core\Clock\ClockInterface;
use App\Model\Ticket;
use App\Model\TicketActivity;
use App\Model\TicketLog;
use App\Repository\TicketActivityRepositoryInterface;
use App\Service\TicketService;

/**
 * Talep olaylarını işlem geçmişine yazar (atama, yanıt ve sonraki aşamalarda eskalasyon, SLA uzatma,
 * kapanış). Olay bağlamında "activity" anahtarı varsa o eylem olduğu gibi kaydedilir.
 */
final class TicketActivityObserver implements TicketObserver
{
    public function __construct(
        private readonly TicketActivityRepositoryInterface $activities,
        private readonly ClockInterface $clock,
    ) {
    }

    public function update(Ticket $ticket, string $eventType, array $context = []): void
    {
        $actor = is_int($context['actor_id'] ?? null) ? $context['actor_id'] : TicketLog::SYSTEM_USER;
        $target = is_int($context['agent_id'] ?? null) ? $context['agent_id'] : null;
        $note = is_string($context['note'] ?? null) ? $context['note'] : null;

        $action = match ($eventType) {
            TicketService::EVENT_ASSIGNED => TicketActivity::ASSIGNED,
            TicketService::EVENT_CUSTOMER_REPLIED, TicketService::EVENT_AGENT_REPLIED => TicketActivity::REPLIED,
            default => is_string($context['activity'] ?? null) ? $context['activity'] : null,
        };
        if ($action === null) {
            return;
        }

        $this->activities->add(new TicketActivity(null, (int) $ticket->id, $actor, $action, $target, $note, $this->clock->now()));
    }
}
