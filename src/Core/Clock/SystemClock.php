<?php

declare(strict_types=1);

namespace App\Core\Clock;

use DateTimeImmutable;
use DateTimeZone;

/** Gerçek sistem saati; uygulama çalışırken kullanılır. */
final class SystemClock implements ClockInterface
{
    private DateTimeZone $timezone;

    public function __construct(string $timezone = 'Europe/Istanbul')
    {
        $this->timezone = new DateTimeZone($timezone);
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
