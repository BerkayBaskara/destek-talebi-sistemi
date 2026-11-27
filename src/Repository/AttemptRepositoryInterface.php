<?php

declare(strict_types=1);

namespace App\Repository;

/** Hatalı deneme kayıtları (ör. şifre sıfırlamada yanlış gizli soru cevabı, FR-31). */
interface AttemptRepositoryInterface
{
    public function record(string $key, int $at): void;

    public function countSince(string $key, int $since): int;

    public function clear(string $key): void;
}
