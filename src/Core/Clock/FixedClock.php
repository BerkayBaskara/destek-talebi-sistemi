<?php

declare(strict_types=1);

namespace App\Core\Clock;

use DateTimeImmutable;

/** Elle ayarlanan saat; testlerde ve örnek veri üretiminde kullanılır. */
final class FixedClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    /** Saati ileri/geri alır, ör. '+2 hours' veya '-3 days'. */
    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
