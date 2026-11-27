<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Talebe eklenen dosya (ER: attachments). file_path: storage/uploads altındaki rastgele ad,
 * file_type: içerikten okunan MIME türü. Dosyanın asıl adı saklanmaz (güvenlik).
 */
final class Attachment
{
    public function __construct(
        public ?int $id,
        public int $ticketId,
        public int $userId,
        public string $filePath,
        public string $fileType,
        public DateTimeImmutable $uploadedAt,
    ) {
    }

    public function isImage(): bool
    {
        return str_starts_with($this->fileType, 'image/');
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['ticket_id'] ?? 0),
            (int) ($row['user_id'] ?? 0),
            (string) ($row['file_path'] ?? ''),
            (string) ($row['file_type'] ?? ''),
            Ticket::date($row['uploaded_at'] ?? null) ?? new DateTimeImmutable('@0'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticketId,
            'user_id' => $this->userId,
            'file_path' => $this->filePath,
            'file_type' => $this->fileType,
            'uploaded_at' => $this->uploadedAt->format(DATE_ATOM),
        ];
    }
}
