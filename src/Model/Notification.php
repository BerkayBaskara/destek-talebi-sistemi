<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/** Uygulama içi bildirim (FR-18). Yalnız sahibi görür; okununca read_at dolar. */
final class Notification
{
    public function __construct(
        public ?int $id,
        public int $userId,
        public string $type,
        public ?int $ticketId,
        public string $message,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $readAt = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['user_id'] ?? 0),
            (string) ($row['type'] ?? ''),
            isset($row['ticket_id']) ? (int) $row['ticket_id'] : null,
            (string) ($row['message'] ?? ''),
            Ticket::date($row['created_at'] ?? null) ?? new DateTimeImmutable('@0'),
            Ticket::date($row['read_at'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'type' => $this->type,
            'ticket_id' => $this->ticketId,
            'message' => $this->message,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'read_at' => $this->readAt?->format(DATE_ATOM),
        ];
    }
}
