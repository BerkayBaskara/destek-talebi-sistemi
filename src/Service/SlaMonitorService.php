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
        private readonly StaffService $staff,
        private readonly NotificationService $notifications,
        private readonly SlaStatusService $slaStatus,
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

    /** NOT-010, FR-07: SLA'nın %80'i dolan etkin talep için personele bir kez uyarı bildirimi. */
    private function warn(\App\Model\Ticket $ticket): bool
    {
        if (!in_array($ticket->status, [TicketStatus::Taken, TicketStatus::InReview], true) || $ticket->agentId === null
            || $ticket->isOnHold() || $ticket->pausedAt !== null
            || $ticket->warnedAt?->getTimestamp() === $ticket->deadline->getTimestamp()) {
            return false;
        }
        $status = $this->slaStatus->forTicket($ticket);
        if ($status->level !== 'warning') {
            return false;
        }
        $ticket->warnedAt = $ticket->deadline;
        if (!$this->tickets->updateIfUnchanged($ticket, $ticket->status, $ticket->agentId, ['warned_at'])) {
            return false;
        }
        $this->notifications->notify($ticket->agentId, 'sla_warning', (int) $ticket->id, $ticket->displayCode() . " talebinde SLA süresinin %80'i doldu; kalan süre: " . $this->slaStatus->formatMinutes($status->remainingMinutes) . '.');

        return true;
    }

    /** @return array{breaches: int, escalations: int, completed: int} */
    public function run(): array
    {
        $now = $this->clock->now();
        $result = ['breaches' => 0, 'escalations' => 0, 'completed' => 0];

        foreach ($this->tickets->findAll() as $ticket) {
            if ($ticket->pausedAt !== null && $ticket->questionDueAt !== null && $now >= $ticket->questionDueAt
                && in_array($ticket->status, [TicketStatus::Taken, TicketStatus::InReview], true)) {
                // Müşteri soruyu süresinde yanıtlamadı: SLA, yanıt süresinin bittiği andan kaldığı yerden devam eder.
                $this->ticketService->resumeSla($ticket, TicketLog::SYSTEM_USER, $ticket->questionDueAt);
                $this->notifications->notify((int) $ticket->agentId, 'status', (int) $ticket->id, $ticket->displayCode() . ' talebinde müşteri soruyu süresinde yanıtlamadı; SLA kaldığı yerden devam ediyor.');
                continue;
            }
            if ($ticket->holdUntil !== null && $now >= $ticket->holdUntil
                && $ticket->status === TicketStatus::Pending && $ticket->agentId === null) {
                $this->escalation->escalate($ticket, EscalationService::REASON_NO_RESPONSE, TicketLog::SYSTEM_USER, 'SLA aşımında müşteri yanıt vermedi');
                $result['escalations']++;
            } elseif ($this->warn($ticket)) {
                // %80 uyarısı gönderildi
            } elseif ($this->escalation->handleBreach($ticket)) {
                $result['breaches']++;
            } elseif ($this->escalation->autoComplete($ticket)) {
                $result['completed']++;
            }
        }

        // FR-49: uzun süre giriş yapmayan personel pasif yapılır; yöneticiye bildirilir.
        foreach ($this->staff->deactivateInactive($now) as $agent) {
            $this->notifications->notifyAdmins('staff_deactivated', null, $agent->fullName() . ' (' . $agent->customerNo . ') uzun süre giriş yapmadığı için pasif yapıldı.');
        }

        if ($result['breaches'] + $result['escalations'] + $result['completed'] > 0) {
            $this->ticketService->fillFromPool(); // personelden alınan talepler yer açtı
        }

        return $result;
    }
}
