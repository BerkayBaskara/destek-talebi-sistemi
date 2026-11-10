<?php

declare(strict_types=1);

namespace App\Model\State;

use App\Model\Enum\TicketStatus;
use App\Model\Ticket;

/** Talep İnceleniyor: personel çözüm gönderebilir. */
final class InReviewState extends AbstractTicketState
{
    public function resolve(Ticket $ticket): TicketStatus
    {
        return TicketStatus::Resolved;
    }
}
