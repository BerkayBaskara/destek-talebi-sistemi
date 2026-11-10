<?php

declare(strict_types=1);

namespace App\Model\Enum;

use App\Model\State\CancelledState;
use App\Model\State\ClosedState;
use App\Model\State\InReviewState;
use App\Model\State\PendingState;
use App\Model\State\ResolvedState;
use App\Model\State\TakenState;
use App\Model\State\TicketState;

/**
 * Talep durumları (SRS akışı). Hocanın kartındaki adlar hocaLabel() ile eşlenir:
 * Bekleniyor → Açık, Alındı/İnceleniyor → İşlemde, Çözüldü → Çözüldü, Kapalı → Kapalı.
 */
enum TicketStatus: string
{
    case Pending = 'pending';
    case Taken = 'taken';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Talep Bekleniyor',
            self::Taken => 'Talep Alındı',
            self::InReview => 'Talep İnceleniyor',
            self::Resolved => 'Talep Çözüldü',
            self::Closed => 'Kapalı',
            self::Cancelled => 'İptal Edildi',
        };
    }

    public function hocaLabel(): string
    {
        return match ($this) {
            self::Pending => 'Açık',
            self::Taken, self::InReview => 'İşlemde',
            self::Resolved => 'Çözüldü',
            self::Closed, self::Cancelled => 'Kapalı',
        };
    }

    /** Son durumlar: hiçbir geçişe izin vermez. */
    public function isFinal(): bool
    {
        return $this === self::Closed || $this === self::Cancelled;
    }

    /** Bu durumun kurallarını taşıyan State nesnesi. */
    public function state(): TicketState
    {
        return match ($this) {
            self::Pending => new PendingState(),
            self::Taken => new TakenState(),
            self::InReview => new InReviewState(),
            self::Resolved => new ResolvedState(),
            self::Closed => new ClosedState(),
            self::Cancelled => new CancelledState(),
        };
    }
}
