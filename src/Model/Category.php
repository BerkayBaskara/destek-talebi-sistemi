<?php

declare(strict_types=1);

namespace App\Model;

/** Destek kategorisi (ER: categories). */
final class Category
{
    /** @param int|null $parentId alt kategoriyse ana kategorisi (tek seviye, US-28) */
    public function __construct(public ?int $id, public string $name, public bool $active = true, public ?int $parentId = null)
    {
    }

    /**
     * Ekranda gösterilen ad: alt kategoride "Ana › Alt".
     *
     * @param array<int, Category> $byId
     */
    public function pathLabel(array $byId): string
    {
        $parent = $this->parentId !== null ? ($byId[$this->parentId] ?? null) : null;

        return $parent !== null ? $parent->name . ' › ' . $this->name : $this->name;
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(isset($row['id']) ? (int) $row['id'] : null, (string) ($row['name'] ?? ''), (bool) ($row['active'] ?? true), isset($row['parent_id']) ? (int) $row['parent_id'] : null);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'active' => $this->active, 'parent_id' => $this->parentId];
    }
}
