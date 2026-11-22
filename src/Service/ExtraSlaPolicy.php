<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Ticket;
use DateTimeImmutable;

/** Ek SLA (FR-14): ilk SLA süresinin ayardaki oranı kadar iş dakikası, verilen andan itibaren. */
final class ExtraSlaPolicy
{
    public function __construct(
        private readonly SlaCalculatorService $sla,
        private readonly SettingsService $settings,
    ) {
    }

    public function extendedDeadline(Ticket $ticket, DateTimeImmutable $from): DateTimeImmutable
    {
        $first = $this->sla->businessMinutesBetween($ticket->createdAt, $ticket->firstDeadline());
        $extra = (int) ceil($first * $this->settings->int('sla.extra_percent') / 100);

        return $this->sla->calculateWithJson($from, max(1, $extra));
    }
}
