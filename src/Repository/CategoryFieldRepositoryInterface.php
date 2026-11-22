<?php

declare(strict_types=1);

namespace App\Repository;

/** Kategoriye özel ek talep alanları (FR-01, FR-37): etiket, tür (text, number, date, select, email), zorunlu mu, seçenekler, en fazla uzunluk. */
interface CategoryFieldRepositoryInterface
{
    /** @return list<array{id: int, category_id: int, label: string, type: string, required: bool, options: list<string>, max_length: ?int}> eklenme sırasıyla */
    public function forCategory(int $categoryId): array;

    /** @return list<array{id: int, category_id: int, label: string, type: string, required: bool, options: list<string>, max_length: ?int}> */
    public function all(): array;

    /** @param list<string> $options */
    public function add(int $categoryId, string $label, string $type, bool $required, array $options = [], ?int $maxLength = null): int;

    public function remove(int $id): void;
}
