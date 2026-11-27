<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

/**
 * Yönetici işleminin denetim kaydı (ER: admin_audit_logs). Kayıt bir kez yazılır, değiştirilmez.
 * old_value / new_value: değişen alanların eski ve yeni hâli.
 */
final class AuditLog
{
    /**
     * @param array<string, mixed>|null $oldValue
     * @param array<string, mixed>|null $newValue
     */
    public function __construct(
        public ?int $id,
        public int $userId,
        public string $action,
        public string $entity,
        public ?int $entityId,
        public ?array $oldValue,
        public ?array $newValue,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['user_id'] ?? 0),
            (string) ($row['action'] ?? ''),
            (string) ($row['entity'] ?? ''),
            isset($row['entity_id']) ? (int) $row['entity_id'] : null,
            is_array($row['old_value'] ?? null) ? $row['old_value'] : null,
            is_array($row['new_value'] ?? null) ? $row['new_value'] : null,
            new DateTimeImmutable((string) ($row['created_at'] ?? 'now')),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'action' => $this->action,
            'entity' => $this->entity,
            'entity_id' => $this->entityId,
            'old_value' => $this->oldValue,
            'new_value' => $this->newValue,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
