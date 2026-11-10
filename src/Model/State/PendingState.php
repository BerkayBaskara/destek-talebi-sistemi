<?php

declare(strict_types=1);

namespace App\Model\State;

use App\Model\Enum\TicketStatus;
use App\Model\Ticket;

/** Talep Bekleniyor: personel üstlenebilir veya müşteri iptal edebilir. */
final class PendingState extends AbstractTicketState
{
    public function take(Ticket $ticket): TicketStatus
    {
        return TicketStatus::Taken;
    }

    public function cancel(Ticket $ticket): TicketStatus
    {
        return TicketStatus::Cancelled;
    }
}
