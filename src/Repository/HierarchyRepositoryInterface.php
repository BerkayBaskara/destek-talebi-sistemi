<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Kategori bazlı personel hiyerarşisi (FR-43): her kategoride personelin seviyesi. Seviye 1 ilk basamaktır;
 * sayı büyüdükçe kıdem artar. Kategoride hiç kayıt yoksa o kategoride hiyerarşi tanımsızdır.
 */
interface HierarchyRepositoryInterface
{
    /** @return array<int, int> personel id → seviye */
    public function levelsFor(int $categoryId): array;

    /** Seviyeyi yazar; null personeli kategoriden çıkarır. */
    public function setLevel(int $categoryId, int $userId, ?int $level): void;

    /**
     * Kategorinin hiyerarşisini tek seferde değiştirir.
     *
     * @param array<int, int> $levels personel id → seviye
     */
    public function replaceLevels(int $categoryId, array $levels): void;
}
