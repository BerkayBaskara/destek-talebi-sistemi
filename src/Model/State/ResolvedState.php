<?php

declare(strict_types=1);

namespace App\Model\State;

use App\Model\Enum\TicketStatus;
use App\Model\Ticket;

/** Talep Çözüldü: müşteri onaylar (Kapalı) veya reddeder (tekrar İnceleniyor). */
final class ResolvedState extends AbstractTicketState
{
    public function close(Ticket $ticket): TicketStatus
    {
        return TicketStatus::Closed;
    }

    public function reject(Ticket $ticket): TicketStatus
    {
        return TicketStatus::InReview;
    }
}
