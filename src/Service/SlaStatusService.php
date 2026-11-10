<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Model\Enum\TicketStatus;
use App\Model\SlaStatus;
use App\Model\Ticket;
use DateTimeImmutable;

/**
 * FR-07: SLA'nın ne kadarının dolduğunu iş saatine göre hesaplar ve uyarı seviyesini belirler.
 * %80 ve üstü sarı uyarı, bitiş anı geçtiyse kırmızı ihlal.
 */
final class SlaStatusService
{
    public const WARNING_PERCENT = 80;

    public function __construct(
        private readonly SlaCalculatorService $calculator,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param DateTimeImmutable|null $resolvedAt Talep çözüldüyse çözüm anı (sayaç durur)
     * @param bool $hidden Kapalı veya iptal edilmiş, hiç çözülmemiş talep (gösterge yok)
     */
    public function evaluate(
        DateTimeImmutable $createdAt,
        DateTimeImmutable $deadline,
        ?DateTimeImmutable $resolvedAt,
        bool $hidden,
    ): SlaStatus {
        if ($hidden) {
            return new SlaStatus('none', 0, 0, '—');
        }

        if ($resolvedAt !== null) {
            return $resolvedAt <= $deadline
                ? new SlaStatus('met', 100, 0, 'Süre içinde çözüldü')
                : new SlaStatus('missed', 100, 0, 'Süre aşılarak çözüldü');
        }

        $now = $this->clock->now();
        if ($now >= $deadline) {
            $total = $this->calculator->businessMinutesBetween($createdAt, $deadline);
            $elapsed = $this->calculator->businessMinutesBetween($createdAt, $now);
            $percent = $total > 0 ? max(100, intdiv($elapsed * 100, $total)) : 100;

            return new SlaStatus('breached', $percent, 0, 'Süre aşıldı');
        }

        $total = $this->calculator->businessMinutesBetween($createdAt, $deadline);
        $elapsed = $this->calculator->businessMinutesBetween($createdAt, $now);
        $percent = $total > 0 ? intdiv($elapsed * 100, $total) : 0;
        $remaining = $this->calculator->businessMinutesBetween($now, $deadline);
        $level = $percent >= self::WARNING_PERCENT ? 'warning' : 'normal';

        return new SlaStatus($level, $percent, $remaining, $this->formatMinutes($remaining) . ' kaldı');
    }

    /** Talebin kendi alanlarından gösterge: çözülmüş/kapalıysa sonucu, iptalse gösterge yok. */
    public function forTicket(Ticket $ticket): SlaStatus
    {
        $resolved = in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true);

        return $this->evaluate(
            $ticket->createdAt,
            $ticket->deadline,
            $resolved ? $ticket->resolvedAt : null,
            $ticket->status->isFinal() && $ticket->resolvedAt === null,
        );
    }

    /** 45 → "45 dk", 120 → "2 sa", 200 → "3 sa 20 dk" */
    public function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return $rest . ' dk';
        }

        return $rest === 0 ? $hours . ' sa' : $hours . ' sa ' . $rest . ' dk';
    }
}
