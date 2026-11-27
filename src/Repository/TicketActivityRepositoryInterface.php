<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\TicketActivity;

/** Talep işlem geçmişi (yalnız ekleme; güncelleme ve silme yok). */
interface TicketActivityRepositoryInterface
{
    public function add(TicketActivity $activity): TicketActivity;

    /** @return list<TicketActivity> eklenme sırasıyla */
    public function findByTicket(int $ticketId): array;
}
