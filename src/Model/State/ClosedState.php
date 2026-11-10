<?php

declare(strict_types=1);

namespace App\Model\State;

/** Kapalı: son durum, hiçbir geçiş yok (kapatılmış bilet yeniden İşlemde'ye alınamaz). */
final class ClosedState extends AbstractTicketState
{
}
