<?php

declare(strict_types=1);

namespace App\Model\State;

use App\Model\Enum\TicketStatus;
use App\Model\Ticket;

/**
 * Varsayılan: hiçbir geçişe izin verme. Alt sınıflar yalnız kendi izinli geçişlerini ezer.
 */
abstract class AbstractTicketState implements TicketState
{
    public function cancel(Ticket $ticket): TicketStatus
    {
        throw $this->denied($ticket, 'iptal edilemez');
    }

    public function take(Ticket $ticket): TicketStatus
    {
        throw $this->denied($ticket, 'üstlenilemez');
    }

    public function startReview(Ticket $ticket): TicketStatus
    {
        throw $this->denied($ticket, 'incelemeye alınamaz');
    }

    public function resolve(Ticket $ticket): TicketStatus
    {
        throw $this->denied($ticket, 'çözüldü olarak işaretlenemez');
    }

    public function reject(Ticket $ticket): TicketStatus
    {
        throw $this->denied($ticket, 'reddedilemez');
    }

    public function close(Ticket $ticket): TicketStatus
    {
        throw $this->denied($ticket, 'kapatılamaz');
    }

    private function denied(Ticket $ticket, string $what): InvalidTransitionException
    {
        return new InvalidTransitionException(sprintf("'%s' durumundaki talep %s.", $ticket->status->label(), $what));
    }
}
