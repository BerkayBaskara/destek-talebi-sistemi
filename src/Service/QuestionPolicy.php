<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Ticket;
use DateTimeImmutable;

/**
 * FR-22: personelin müşteriye soru sorma kuralları. Soru sınırı ve müşterinin yanıt süresi yönetici ayarıdır.
 * Soru beklerken SLA durur; devam edince kalan iş süresi (duraklama anından bitişe) yeniden işlemeye başlar.
 */
final class QuestionPolicy
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SlaCalculatorService $sla,
    ) {
    }

    public function maxQuestions(): int
    {
        return $this->settings->int('tickets.max_questions');
    }

    public function replyDue(DateTimeImmutable $from): DateTimeImmutable
    {
        return $from->modify('+' . $this->settings->int('tickets.question_reply_hours') . ' hours');
    }

    /**
     * Soru duraklamasını verilen anda kapatır: duran iş süresi uyum bitişine eklenir (SLA uyumunda personelin aleyhine
     * sayılmaz). $resume ise kalan süre o andan işlemeye başlar; talep personelden çıkıyorsa (çözüm, aktarma) bitiş değişmez.
     */
    public function endPause(Ticket $ticket, DateTimeImmutable $at, bool $resume): void
    {
        if ($ticket->pausedAt === null) {
            return;
        }
        $paused = $at > $ticket->pausedAt ? $this->sla->businessMinutesBetween($ticket->pausedAt, $at) : 0;
        if ($paused > 0) {
            $ticket->complianceDeadline = $this->sla->calculateWithJson($ticket->complianceDeadline ?? $ticket->firstDeadline(), $paused);
        }
        if ($resume) {
            $ticket->deadline = $this->resumedDeadline($ticket, $at);
        }
        $ticket->pausedAt = null;
        $ticket->questionDueAt = null;
    }

    /** Duraklama anında kalan iş dakikası kadar, verilen andan itibaren yeni bitiş. */
    public function resumedDeadline(Ticket $ticket, DateTimeImmutable $now): DateTimeImmutable
    {
        $pausedAt = $ticket->pausedAt ?? $now;
        $remaining = $pausedAt < $ticket->deadline ? $this->sla->businessMinutesBetween($pausedAt, $ticket->deadline) : 0;

        return $this->sla->calculateWithJson($now, $remaining);
    }
}
