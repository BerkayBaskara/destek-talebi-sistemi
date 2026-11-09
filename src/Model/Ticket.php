<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\Priority;
use App\Model\Enum\TicketStatus;
use App\Model\State\TicketState;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Destek talebi. Alan adları ER diyagramındaki tickets tablosuyla aynı; Aşama 2'de eklenenler:
 * title, customer_priority (müşterinin seçtiği öncelik), resolved_at (son çözüm zamanı).
 * `priority` sistemin etkin önceliğidir (anahtar kelimeyle yükseltilmiş olabilir, FR-03).
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
        public Priority $priority,
        public Priority $customerPriority,
        public int $attemptCount,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $deadline,
        public ?DateTimeImmutable $resolvedAt,
        public int $earnedPoints = 0,
        public int $agentQuestionCount = 0,
    ) {
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
        return $this->priority->value > $this->customerPriority->value;
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
            Priority::from((int) ($row['priority'] ?? 2)),
            Priority::from((int) ($row['customer_priority'] ?? $row['priority'] ?? 2)),
            (int) ($row['attempt_count'] ?? 0),
            self::date($row['created_at'] ?? null) ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul')),
            self::date($row['deadline'] ?? null) ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul')),
            self::date($row['resolved_at'] ?? null),
            (int) ($row['earned_points'] ?? 0),
            (int) ($row['agent_question_count'] ?? 0),
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
            'priority' => $this->priority->value,
            'customer_priority' => $this->customerPriority->value,
            'attempt_count' => $this->attemptCount,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'deadline' => $this->deadline->format(DATE_ATOM),
            'resolved_at' => $this->resolvedAt?->format(DATE_ATOM),
            'earned_points' => $this->earnedPoints,
            'agent_question_count' => $this->agentQuestionCount,
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
