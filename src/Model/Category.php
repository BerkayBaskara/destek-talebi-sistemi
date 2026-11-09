<?php

declare(strict_types=1);

namespace App\Model;

/** Destek kategorisi (ER: categories). */
final class Category
{
    public function __construct(public ?int $id, public string $name)
    {
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(isset($row['id']) ? (int) $row['id'] : null, (string) ($row['name'] ?? ''));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
