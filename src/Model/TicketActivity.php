<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Talep işlem geçmişinin durum değişikliği dışındaki satırı (FR-19): atama, yanıt, eskalasyon,
 * SLA uzatma, kapanış nedeni gibi. Durum değişiklikleri ticket_logs'ta kalır; Günlük ikisini birleştirir.
 * Kayıtlar yalnız eklenir, değiştirilmez ve silinmez.
 */
final class TicketActivity
{
    public const ASSIGNED = 'assigned';
    public const REPLIED = 'replied';

    public function __construct(
        public ?int $id,
        public int $ticketId,
        public int $userId,
        public string $action,
        public ?int $targetUserId,
        public ?string $note,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['ticket_id'] ?? 0),
            (int) ($row['user_id'] ?? 0),
            (string) ($row['action'] ?? ''),
            isset($row['target_user_id']) ? (int) $row['target_user_id'] : null,
            isset($row['note']) ? (string) $row['note'] : null,
            Ticket::date($row['created_at'] ?? null) ?? new DateTimeImmutable('@0'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticketId,
            'user_id' => $this->userId,
            'action' => $this->action,
            'target_user_id' => $this->targetUserId,
            'note' => $this->note,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
