<?php

declare(strict_types=1);

namespace App\Repository;

/** Kategoriye özel ek talep alanları (FR-01): etiket, tür (text, number, date), zorunlu mu. */
interface CategoryFieldRepositoryInterface
{
    /** @return list<array{id: int, category_id: int, label: string, type: string, required: bool}> eklenme sırasıyla */
    public function forCategory(int $categoryId): array;

    /** @return list<array{id: int, category_id: int, label: string, type: string, required: bool}> */
    public function all(): array;

    public function add(int $categoryId, string $label, string $type, bool $required): int;

    public function remove(int $id): void;
}
