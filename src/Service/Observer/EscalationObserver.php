<?php

declare(strict_types=1);

namespace App\Service\Observer;

use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Service\EscalationService;
use App\Service\TicketService;

/**
 * Sınıf diyagramındaki EscalationObserver: müşteri çözümü reddettiğinde (Çözüldü → İnceleniyor) ek SLA uygular,
 * 3. retten sonra talebi üst seviyeye aktarır. Zamana bağlı eskalasyon SlaMonitorService'te.
 */
final class EscalationObserver implements TicketObserver
{
    public function __construct(private readonly EscalationService $escalation)
    {
    }

    public function update(Ticket $ticket, string $eventType, array $context = []): void
    {
        if ($eventType !== TicketService::EVENT_STATUS_CHANGED || ($context['quiet'] ?? false) === true) {
            return;
        }
        if (($context['old'] ?? null) === TicketStatus::Resolved && ($context['new'] ?? null) === TicketStatus::InReview) {
            $this->escalation->onRejected($ticket, is_int($context['actor_id'] ?? null) ? $context['actor_id'] : 0);
        }
    }
}
