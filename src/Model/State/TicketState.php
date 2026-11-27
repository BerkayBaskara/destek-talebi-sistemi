<?php

declare(strict_types=1);

namespace App\Model\State;

use App\Model\Enum\TicketStatus;
use App\Model\Ticket;

/**
 * STATE DESENİ (FR-06): her durum kendi sınıfında, hangi geçişe izin verdiğini kendisi bilir.
 * Metot izinli geçişte hedef durumu döner; izinsizse InvalidTransitionException atar.
 * Böylece TicketService'te dev bir if/switch yığını olmaz.
 */
interface TicketState
{
    /** Müşteri talebini iptal eder (yalnız Bekleniyor'da). */
    public function cancel(Ticket $ticket): TicketStatus;

    /** Personel talebi havuzdan üstlenir. */
    public function take(Ticket $ticket): TicketStatus;

    /** Personel incelemeye başlar. */
    public function startReview(Ticket $ticket): TicketStatus;

    /** Personel çözüm gönderir. */
    public function resolve(Ticket $ticket): TicketStatus;

    /** Müşteri çözümü reddeder; talep incelemeye döner. */
    public function reject(Ticket $ticket): TicketStatus;

    /** Müşteri çözümü onaylar; talep kapanır. */
    public function close(Ticket $ticket): TicketStatus;
}
