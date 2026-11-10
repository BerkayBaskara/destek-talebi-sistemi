<?php

declare(strict_types=1);

namespace App\Model\State;

use App\Model\Enum\TicketStatus;
use App\Model\Ticket;

/** Talep Alındı: personel incelemeye başlayabilir. */
final class TakenState extends AbstractTicketState
{
    public function startReview(Ticket $ticket): TicketStatus
    {
        return TicketStatus::InReview;
    }
}
