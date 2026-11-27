<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\TicketStatus;
use DateTimeImmutable;

/**
 * Talebin durum geçmişindeki bir satır (ER: ticket_logs, FR-19).
 * user_id = 0 ise değişikliği sistem yaptı (ör. otomatik atama).
 */
final class TicketLog
{
    public const SYSTEM_USER = 0;

    public function __construct(
        public ?int $id,
        public int $ticketId,
        public int $userId,
        public ?TicketStatus $oldStatus,
        public TicketStatus $newStatus,
        public DateTimeImmutable $changedAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['ticket_id'] ?? 0),
            (int) ($row['user_id'] ?? 0),
            isset($row['old_status']) ? TicketStatus::from((string) $row['old_status']) : null,
            TicketStatus::from((string) ($row['new_status'] ?? 'pending')),
            Ticket::date($row['changed_at'] ?? null) ?? new DateTimeImmutable('@0'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticketId,
            'user_id' => $this->userId,
            'old_status' => $this->oldStatus?->value,
            'new_status' => $this->newStatus->value,
            'changed_at' => $this->changedAt->format(DATE_ATOM),
        ];
    }
}
