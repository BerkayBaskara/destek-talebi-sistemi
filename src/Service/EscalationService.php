<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Exception\AuthorizationException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Model\TicketLog;
use App\Model\User;
use App\Repository\EscalationRepositoryInterface;
use App\Repository\TicketRepositoryInterface;
use DateTimeImmutable;

/**
 * SLA aşımı kuralı ve eskalasyon (FR-08, FR-14, FR-55–58):
 * - Aşımda talep personelden alınır, Görev Havuzu'nda müşteri kararını bekler (varsayılan 1 iş günü; 0 = hemen eskalasyon).
 * - Müşteri "aynı personelle devam" derse talep o personele ek SLA ile döner; "sorun çözüldü" derse kapanır.
 * - Yanıt gelmezse çözemeyen personelin bir üstündeki en alt uygun seviyeye eskale edilir (ek SLA ile).
 *   Uygun kimse yoksa talep yönetici müdahalesi için havuzda kalır ve yöneticilere bildirilir.
 */
final class EscalationService
{
    public const REASON_BREACH = 'sla_breach';
    public const REASON_NO_RESPONSE = 'sla_no_response';
    public const REASON_MANUAL = 'manual';
    public const REASON_REJECTED = 'rejected_limit';

    public const CLOSED_BY_CUSTOMER = 'customer_resolved';

    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly TicketService $ticketService,
        private readonly AssignmentService $assignment,
        private readonly SlaCalculatorService $sla,
        private readonly SettingsService $settings,
        private readonly EscalationRepositoryInterface $escalations,
        private readonly NotificationService $notifications,
        private readonly ClockInterface $clock,
    ) {
    }

    /** SLA'sı aşılmış etkin talebi işler; bu aşım daha önce işlendiyse ya da koşul yoksa false. */
    public function handleBreach(Ticket $ticket): bool
    {
        $now = $this->clock->now();
        if (!in_array($ticket->status, [TicketStatus::Taken, TicketStatus::InReview], true)
            || $ticket->agentId === null || $ticket->isOnHold() || $now <= $ticket->deadline
            || $ticket->breachHandledAt?->getTimestamp() === $ticket->deadline->getTimestamp()) {
            return false;
        }

        $old = $ticket->status;
        $agentId = $ticket->agentId;
        $ticket->breachHandledAt = $ticket->deadline;
        $wait = $this->settings->int('sla.breach_wait_days');
        if ($wait === 0) {
            $this->escalate($ticket, self::REASON_BREACH, TicketLog::SYSTEM_USER, 'SLA süresi aşıldı');

            return true;
        }

        $ticket->previousAgentId = $agentId;
        $ticket->agentId = null;
        $ticket->status = TicketStatus::Pending;
        $ticket->holdUntil = $this->sla->addBusinessDays($now, $wait);
        if (!$this->tickets->saveIfUnchanged($ticket, $old, $agentId)) {
            return false; // aynı anda başka bir işlem talebi değiştirdi
        }

        $code = $ticket->displayCode();
        $until = $ticket->holdUntil->format('d.m.Y H:i');
        $this->statusChanged($ticket, $old, TicketLog::SYSTEM_USER);
        $this->ticketService->notifyObservers($ticket, 'sla_breached', [
            'activity' => 'returned_to_pool',
            'note' => 'SLA aşıldı; müşteri kararı bekleniyor',
            'notify' => [
                [$ticket->creatorId, $code . ' talebinizin çözüm süresi aşıldı. Aynı personelle devam etmek mi istersiniz, yoksa sorun çözüldü mü? Yanıt vermezseniz talep ' . $until . ' tarihinde üst seviye personele aktarılır.', 'sla_breach'],
                [$agentId, $code . ' talebinin SLA süresi aşıldı; talep müşterinin kararı için Görev Havuzu\'na alındı.', 'sla_breach'],
            ],
        ]);

        return true;
    }

    /**
     * Müşterinin aşım sonrası kararı: "devam" (aynı personel, ek SLA) ya da "cozuldu" (kapanış).
     *
     * @throws ValidationException
     */
    public function decide(int $ticketId, User $customer, string $decision): Ticket
    {
        $ticket = $this->tickets->findById($ticketId) ?? throw new NotFoundException('Talep bulunamadı.');
        if ($ticket->creatorId !== $customer->id) {
            throw new AuthorizationException('Bu talep için karar veremezsiniz.');
        }
        if ($ticket->holdUntil === null) {
            throw new ValidationException(['karar' => 'Bu talep için şu anda karar beklenmiyor.']);
        }

        $now = $this->clock->now();
        $previous = $ticket->previousAgentId;
        $code = $ticket->displayCode();

        if ($decision === 'devam' && $previous !== null) {
            $ticket->agentId = $previous;
            $ticket->previousAgentId = null;
            $ticket->holdUntil = null;
            $ticket->status = TicketStatus::Taken;
            $ticket->deadline = $this->extendedDeadline($ticket, $now);
            if (!$this->tickets->saveIfUnchanged($ticket, TicketStatus::Pending, null)) {
                throw new ValidationException(['karar' => 'Talep az önce değişti. Sayfayı yenileyip tekrar deneyin.']);
            }
            $this->statusChanged($ticket, TicketStatus::Pending, (int) $customer->id);
            $this->deadlineExtended($ticket, (int) $customer->id, [
                [$previous, 'Müşteri ' . $code . ' talebinde sizinle devam etmek istedi. Ek SLA ile yeni bitiş: ' . $ticket->deadline->format('d.m.Y H:i') . '.', 'continue'],
            ]);

            return $ticket;
        }

        if ($decision === 'cozuldu') {
            $ticket->holdUntil = null;
            $ticket->status = TicketStatus::Closed;
            $ticket->closureReason = self::CLOSED_BY_CUSTOMER;
            $ticket->resolvedAt = $now;
            if (!$this->tickets->saveIfUnchanged($ticket, TicketStatus::Pending, null)) {
                throw new ValidationException(['karar' => 'Talep az önce değişti. Sayfayı yenileyip tekrar deneyin.']);
            }
            $this->statusChanged($ticket, TicketStatus::Pending, (int) $customer->id);
            $this->ticketService->notifyObservers($ticket, 'closed', [
                'actor_id' => (int) $customer->id,
                'activity' => 'closed',
                'note' => 'Müşteri tarafından çözüldü',
                'notify' => $previous !== null ? [[$previous, $code . ' talebini müşteri "sorun çözüldü" diyerek kapattı.', 'closed']] : [],
            ]);
            $this->ticketService->fillFromPool();

            return $ticket;
        }

        throw new ValidationException(['karar' => 'Geçerli bir seçim yapın.']);
    }

    /**
     * Talebi bir üst seviyeye aktarır. Uygun personel yoksa talep yönetici müdahalesi için havuzda kalır.
     * Yeni personelin kimliğini döner (yoksa null).
     */
    public function escalate(Ticket $ticket, string $reason, int $actorId, string $note): ?int
    {
        $now = $this->clock->now();
        $old = $ticket->status;
        $oldAgent = $ticket->agentId;
        $failing = $ticket->agentId ?? $ticket->previousAgentId;
        $target = $this->assignment->pickEscalationTarget($ticket->categoryId, $failing);
        $code = $ticket->displayCode();

        $ticket->holdUntil = null;
        if ($target === null) {
            $ticket->agentId = null;
            $ticket->previousAgentId = $failing;
            $ticket->status = TicketStatus::Pending;
            $ticket->awaitingAdmin = true;
            if (!$this->tickets->saveIfUnchanged($ticket, $old, $oldAgent)) {
                return null;
            }
            $this->escalations->add((int) $ticket->id, $failing, null, $reason, $note, $now);
            if ($old !== TicketStatus::Pending) {
                $this->statusChanged($ticket, $old, $actorId);
            }
            $this->ticketService->notifyObservers($ticket, 'escalation_failed', [
                'actor_id' => $actorId,
                'activity' => 'returned_to_pool',
                'note' => 'Uygun üst seviye personel yok; yönetici müdahalesi bekleniyor',
            ]);
            $this->notifications->notifyAdmins('escalation_failed', (int) $ticket->id, $code . ' talebi için uygun üst seviye personel bulunamadı; talep Görev Havuzu\'nda yönetici müdahalesi bekliyor.');

            return null;
        }

        $ticket->agentId = (int) $target->id;
        $ticket->previousAgentId = null;
        $ticket->awaitingAdmin = false;
        $ticket->status = TicketStatus::Taken;
        $ticket->deadline = $this->extendedDeadline($ticket, $now);
        if (!$this->tickets->saveIfUnchanged($ticket, $old, $oldAgent)) {
            return null;
        }
        $this->escalations->add((int) $ticket->id, $failing, (int) $target->id, $reason, $note, $now);
        if ($old !== TicketStatus::Taken) {
            $this->statusChanged($ticket, $old, $actorId);
        }
        $this->ticketService->notifyObservers($ticket, 'escalated', [
            'actor_id' => $actorId,
            'agent_id' => (int) $target->id,
            'activity' => 'escalated',
            'note' => $note,
            'notify' => [
                [(int) $target->id, 'Size eskale edilen bir talep atandı: ' . $code . ' ' . $ticket->title . ' (' . $note . ').', 'escalated'],
                [$ticket->creatorId, $code . ' talebiniz üst seviye personele aktarıldı.', 'escalated'],
                [(int) $failing, $code . ' talebi üst seviyeye aktarıldı.', 'escalated'],
            ],
        ]);
        $this->deadlineExtended($ticket, $actorId, []);

        return (int) $target->id;
    }

    /** Ek SLA (FR-14): ilk SLA süresinin ayardaki oranı kadar iş dakikası, şu andan itibaren. */
    public function extendedDeadline(Ticket $ticket, DateTimeImmutable $from): DateTimeImmutable
    {
        $first = $this->sla->businessMinutesBetween($ticket->createdAt, $ticket->firstDeadline());
        $extra = (int) ceil($first * $this->settings->int('sla.extra_percent') / 100);

        return $this->sla->calculateWithJson($from, max(1, $extra));
    }

    private function statusChanged(Ticket $ticket, TicketStatus $old, int $actorId): void
    {
        // "quiet": bu durum değişikliği için genel bildirim gönderilmez; özel bildirim ayrıca gider.
        $this->ticketService->notifyObservers($ticket, TicketService::EVENT_STATUS_CHANGED, [
            'old' => $old, 'new' => $ticket->status, 'actor_id' => $actorId, 'quiet' => true,
        ]);
    }

    /** @param list<array{0: int, 1: string, 2: string}> $notify */
    private function deadlineExtended(Ticket $ticket, int $actorId, array $notify): void
    {
        $this->ticketService->notifyObservers($ticket, 'sla_extended', [
            'actor_id' => $actorId,
            'activity' => 'sla_extended',
            'note' => $ticket->deadline->format('d.m.Y H:i'),
            'notify' => $notify,
        ]);
    }
}
