<?php

declare(strict_types=1);

namespace App\Repository;

/** Personel izinleri (FR-20): izinli personel o günlerde otomatik atamaya girmez. Tarihler 'Y-m-d', iki uç dahil. */
interface LeaveRepositoryInterface
{
    public function add(int $userId, string $start, string $end, string $note): int;

    public function remove(int $id): void;

    /** @return list<array{id: int, user_id: int, start: string, end: string, note: string}> başlangıç tarihine göre */
    public function all(): array;

    public function isOnLeave(int $userId, string $date): bool;
}
