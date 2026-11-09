<?php

declare(strict_types=1);

namespace App\Service\Observer;

use App\Model\Ticket;

/**
 * OBSERVER DESENİ: TicketService bir olay olduğunda (talep açıldı, atandı, durum değişti, yanıt yazıldı)
 * kayıtlı gözlemcilere haber verir. Gözlemciler birbirinden ve TicketService'ten bağımsızdır.
 * Aşama 2: TicketLogObserver (durum geçmişi). Aşama 3: EscalationObserver, SlaExtensionObserver.
 */
interface TicketObserver
{
    /** @param array<string, mixed> $context olaya özgü bilgiler (ör. old, new, actor_id) */
    public function update(Ticket $ticket, string $eventType, array $context = []): void;
}
