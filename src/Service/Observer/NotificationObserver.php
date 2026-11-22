<?php

declare(strict_types=1);

namespace App\Service\Observer;

use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Service\NotificationService;
use App\Service\TicketService;

/**
 * Talep olaylarından bildirim üretir (FR-18): atama personele, personel yanıtı ve durum değişikliği müşteriye,
 * müşteri yanıtı ve müşterinin kararı personele. İşlemi yapan kişiye kendi işlemi bildirilmez.
 * Olay bağlamında "notify" listesi ([kullanıcı id, mesaj]) varsa onlar da gönderilir.
 */
final class NotificationObserver implements TicketObserver
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function update(Ticket $ticket, string $eventType, array $context = []): void
    {
        $actor = is_int($context['actor_id'] ?? null) ? $context['actor_id'] : 0;
        $code = $ticket->displayCode();
        $id = (int) $ticket->id;

        switch ($eventType) {
            case TicketService::EVENT_ASSIGNED:
                $agentId = is_int($context['agent_id'] ?? null) ? $context['agent_id'] : 0;
                if ($agentId !== $actor) {
                    $this->notifications->notify($agentId, 'assigned', $id, 'Size yeni bir talep atandı: ' . $code . ' ' . $ticket->title);
                }
                break;
            case TicketService::EVENT_AGENT_REPLIED:
                $this->send($ticket->creatorId, $actor, 'reply', $id, $code . ' talebinize yeni yanıt var.');
                break;
            case TicketService::EVENT_CUSTOMER_REPLIED:
                $this->send((int) $ticket->agentId, $actor, 'reply', $id, $code . ' talebinde müşteriden yeni yanıt var.');
                break;
            case TicketService::EVENT_STATUS_CHANGED:
                $this->statusChanged($ticket, $context, $actor);
                break;
        }

        foreach (is_array($context['notify'] ?? null) ? $context['notify'] : [] as $item) {
            if (is_array($item) && is_int($item[0] ?? null) && is_string($item[1] ?? null)) {
                $this->notifications->notify($item[0], is_string($item[2] ?? null) ? $item[2] : 'info', $id, $item[1]);
            }
        }
    }

    /** @param array<string, mixed> $context */
    private function statusChanged(Ticket $ticket, array $context, int $actor): void
    {
        $old = $context['old'] ?? null;
        $new = $context['new'] ?? $ticket->status;
        if (!$new instanceof TicketStatus || $old === null) {
            return;
        }
        $code = $ticket->displayCode();
        $id = (int) $ticket->id;

        if ($new === TicketStatus::Resolved) {
            $this->send($ticket->creatorId, $actor, 'resolved', $id, $code . ' talebiniz çözüldü olarak işaretlendi. Lütfen çözümün işe yarayıp yaramadığını bildirin.');
        } elseif ($old === TicketStatus::Resolved && $new === TicketStatus::InReview) {
            $this->send((int) $ticket->agentId, $actor, 'rejected', $id, $code . ' talebinde müşteri çözümün işe yaramadığını bildirdi.');
        } elseif ($old === TicketStatus::Resolved && $new === TicketStatus::Closed) {
            $this->send((int) $ticket->agentId, $actor, 'approved', $id, $code . ' talebinde müşteri çözümün işe yaradığını bildirdi.');
        } elseif ($new !== TicketStatus::Taken) {
            $this->send($ticket->creatorId, $actor, 'status', $id, $code . ' talebinizin durumu: ' . $new->label() . '.');
        }
    }

    private function send(int $userId, int $actor, string $type, int $ticketId, string $message): void
    {
        if ($userId !== $actor) {
            $this->notifications->notify($userId, $type, $ticketId, $message);
        }
    }
}
