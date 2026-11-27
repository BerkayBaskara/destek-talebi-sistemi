<?php

declare(strict_types=1);

namespace App\Core\Clock;

use DateTimeImmutable;

/**
 * Sistemin "şu an"ını veren soyutlama.
 * Servisler doğrudan new DateTime() çağırmaz; böylece testlerde zaman sabitlenebilir.
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
