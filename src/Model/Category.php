<?php

declare(strict_types=1);

namespace App\Model;

/** Destek kategorisi (ER: categories). */
final class Category
{
    public function __construct(public ?int $id, public string $name, public bool $active = true)
    {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(isset($row['id']) ? (int) $row['id'] : null, (string) ($row['name'] ?? ''), (bool) ($row['active'] ?? true));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'active' => $this->active];
    }
}
