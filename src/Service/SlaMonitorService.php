<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Model\Enum\TicketStatus;
use App\Model\TicketLog;
use App\Repository\RuntimeStateRepositoryInterface;
use App\Repository\TicketRepositoryInterface;

/**
 * Zamana bağlı kuralların denetimi (cron yok): her HTTP isteğinde en fazla dakikada bir ve
 * `php bin/sla-monitor.php` ile çalışır. Aynı işi iki kez yapmaz: aşım bitiş zamanıyla işaretlenir,
 * bekleme bitince talep beklemeden çıkar.
 */
final class SlaMonitorService
{
    private const KEY = 'sla_monitor';
    private const INTERVAL_SECONDS = 60;

    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly EscalationService $escalation,
        private readonly TicketService $ticketService,
        private readonly RuntimeStateRepositoryInterface $state,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Son çalışmanın üzerinden bir dakika geçtiyse çalışır (aynı anda gelen isteklerden yalnız biri). */
    public function runIfDue(): void
    {
        if ($this->state->claim(self::KEY, $this->clock->now()->getTimestamp(), self::INTERVAL_SECONDS)) {
            $this->run();
        }
    }

    /** @return array{breaches: int, escalations: int, completed: int} */
    public function run(): array
    {
        $now = $this->clock->now();
        $result = ['breaches' => 0, 'escalations' => 0, 'completed' => 0];

        foreach ($this->tickets->findAll() as $ticket) {
            if ($ticket->holdUntil !== null && $now >= $ticket->holdUntil
                && $ticket->status === TicketStatus::Pending && $ticket->agentId === null) {
                $this->escalation->escalate($ticket, EscalationService::REASON_NO_RESPONSE, TicketLog::SYSTEM_USER, 'SLA aşımında müşteri yanıt vermedi');
                $result['escalations']++;
            } elseif ($this->escalation->handleBreach($ticket)) {
                $result['breaches']++;
            } elseif ($this->escalation->autoComplete($ticket)) {
                $result['completed']++;
            }
        }

        if ($result['breaches'] + $result['escalations'] + $result['completed'] > 0) {
            $this->ticketService->fillFromPool(); // personelden alınan talepler yer açtı
        }

        return $result;
    }
}
