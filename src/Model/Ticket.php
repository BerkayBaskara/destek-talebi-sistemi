<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\TicketStatus;
use App\Model\State\TicketState;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Destek talebi. Alan adları ER diyagramındaki tickets tablosuyla aynı; Aşama 2'de eklenenler:
 * title, customer_priority (müşterinin seçtiği öncelik), resolved_at (son çözüm zamanı).
 * `priority` sistemin etkin önceliğinin id'sidir (anahtar kelimeyle yükseltilmiş olabilir, FR-03).
 */
final class Ticket
{
    public function __construct(
        public ?int $id,
        public int $creatorId,
        public ?int $agentId,
        public int $categoryId,
        public string $title,
        public TicketStatus $status,
        public int $priorityId,
        public int $customerPriorityId,
        public int $attemptCount,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $deadline,
        public ?DateTimeImmutable $resolvedAt,
        public int $earnedPoints = 0,
        public int $agentQuestionCount = 0,
        /** Kullanıcıya gösterilen 6 karakterli kod (FR-34); eski kayıtlarda boş olabilir. */
        public ?string $code = null,
        /** İlk SLA bitişi (FR-05: ek SLA'dan ayrı saklanır); boşsa deadline ilk bitiştir. */
        public ?DateTimeImmutable $originalDeadline = null,
        /** SLA aşımında müşteri kararı beklenirken talebi bırakan personel. */
        public ?int $previousAgentId = null,
        /** Doluysa talep bu ana kadar müşteri kararı bekler; otomatik atamaya ve "Üzerime al"a girmez. */
        public ?DateTimeImmutable $holdUntil = null,
        /** Kapanış nedeni: customer_resolved, auto_closed, unresolvable (boş = müşteri onayı). */
        public ?string $closureReason = null,
        /** Aşımı işlenmiş son bitiş zamanı (aynı aşım iki kez işlenmesin). */
        public ?DateTimeImmutable $breachHandledAt = null,
    ) {
    }

    /** Ekranda gösterilen numara: kod varsa "#AB23CD", eski kayıtta "#12". */
    public function displayCode(): string
    {
        return '#' . ($this->code ?? (string) $this->id);
    }

    public function firstDeadline(): DateTimeImmutable
    {
        return $this->originalDeadline ?? $this->deadline;
    }

    public function isOnHold(): bool
    {
        return $this->holdUntil !== null;
    }

    /** Mevcut durumun kurallarını taşıyan State nesnesi (sınıf diyagramı: currentState). */
    public function state(): TicketState
    {
        return $this->status->state();
    }

    public function setStatus(TicketStatus $status): void
    {
        $this->status = $status;
    }

    /** Müşteri çözümü reddettiğinde çözüm deneme sayacı artar (3 hak kuralı Aşama 3'te). */
    public function incrementAttempt(): void
    {
        $this->attemptCount++;
    }

    /** Sistem önceliği müşterinin seçtiğinden yüksek mi (ekranda "Sistem önceliği yükseltti" notu). */
    public function priorityRaised(): bool
    {
        return $this->priorityId !== $this->customerPriorityId;
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['creator_id'] ?? 0),
            isset($row['agent_id']) ? (int) $row['agent_id'] : null,
            (int) ($row['category_id'] ?? 0),
            (string) ($row['title'] ?? ''),
            TicketStatus::from((string) ($row['status'] ?? 'pending')),
            (int) ($row['priority'] ?? 2),
            (int) ($row['customer_priority'] ?? $row['priority'] ?? 2),
            (int) ($row['attempt_count'] ?? 0),
            self::date($row['created_at'] ?? null) ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul')),
            self::date($row['deadline'] ?? null) ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul')),
            self::date($row['resolved_at'] ?? null),
            (int) ($row['earned_points'] ?? 0),
            (int) ($row['agent_question_count'] ?? 0),
            isset($row['code']) && $row['code'] !== '' ? (string) $row['code'] : null,
            self::date($row['original_deadline'] ?? null),
            isset($row['previous_agent_id']) ? (int) $row['previous_agent_id'] : null,
            self::date($row['hold_until'] ?? null),
            isset($row['closure_reason']) && $row['closure_reason'] !== '' ? (string) $row['closure_reason'] : null,
            self::date($row['breach_handled_at'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'creator_id' => $this->creatorId,
            'agent_id' => $this->agentId,
            'category_id' => $this->categoryId,
            'title' => $this->title,
            'status' => $this->status->value,
            'priority' => $this->priorityId,
            'customer_priority' => $this->customerPriorityId,
            'attempt_count' => $this->attemptCount,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'deadline' => $this->deadline->format(DATE_ATOM),
            'resolved_at' => $this->resolvedAt?->format(DATE_ATOM),
            'earned_points' => $this->earnedPoints,
            'agent_question_count' => $this->agentQuestionCount,
            'code' => $this->code,
            'original_deadline' => $this->originalDeadline?->format(DATE_ATOM),
            'previous_agent_id' => $this->previousAgentId,
            'hold_until' => $this->holdUntil?->format(DATE_ATOM),
            'closure_reason' => $this->closureReason,
            'breach_handled_at' => $this->breachHandledAt?->format(DATE_ATOM),
        ];
    }

    public static function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);

        return $date === false ? null : $date->setTimezone(new DateTimeZone('Europe/Istanbul'));
    }
}
