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

    /** Duraklama anında kalan iş dakikası kadar, verilen andan itibaren yeni bitiş. */
    public function resumedDeadline(Ticket $ticket, DateTimeImmutable $now): DateTimeImmutable
    {
        $pausedAt = $ticket->pausedAt ?? $now;
        $remaining = $pausedAt < $ticket->deadline ? $this->sla->businessMinutesBetween($pausedAt, $ticket->deadline) : 0;

        return $this->sla->calculateWithJson($now, $remaining);
    }
}
