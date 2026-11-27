<?php

declare(strict_types=1);

namespace App\Model;

/** Kategori ve önceliğe göre SLA süresi, iş saati cinsinden (ER: sla_policies). */
final class SlaPolicy
{
    public function __construct(
        public ?int $id,
        public int $categoryId,
        public int $priorityId,
        public int $durationHours,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (int) ($row['category_id'] ?? 0),
            (int) ($row['priority'] ?? 2),
            (int) ($row['duration_hours'] ?? 0),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->categoryId,
            'priority' => $this->priorityId,
            'duration_hours' => $this->durationHours,
        ];
    }
}
