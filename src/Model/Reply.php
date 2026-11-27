<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Talepteki bir mesaj (ER: replies). Talebin açıklaması da müşterinin ilk mesajı olarak burada tutulur.
 * is_question: personelin "soru" olarak işaretlediği yanıt (FR-22, Aşama 3); Aşama 2'de hep false.
 */
final class Reply
{
    public function __construct(
        public ?int $id,
        public int $ticketId,
        public int $userId,
        public string $message,
        public DateTimeImmutable $createdAt,
        public bool $isQuestion = false,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['ticket_id'] ?? 0),
            (int) ($row['user_id'] ?? 0),
            (string) ($row['message'] ?? ''),
            Ticket::date($row['created_at'] ?? null) ?? new DateTimeImmutable('@0'),
            (bool) ($row['is_question'] ?? false),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticketId,
            'user_id' => $this->userId,
            'message' => $this->message,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'is_question' => $this->isQuestion,
        ];
    }
}
