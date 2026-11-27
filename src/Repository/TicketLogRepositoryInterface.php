<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\TicketLog;

interface TicketLogRepositoryInterface
{
    public function save(TicketLog $log): TicketLog;

    /** @return list<TicketLog> kayıt sırasıyla (kronolojik) */
    public function findByTicket(int $ticketId): array;
}
