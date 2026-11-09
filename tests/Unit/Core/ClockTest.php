<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Clock\FixedClock;
use App\Core\Clock\SystemClock;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    public function test_system_clock_uses_istanbul_timezone(): void
    {
        $clock = new SystemClock();

        self::assertSame('Europe/Istanbul', $clock->now()->getTimezone()->getName());
    }

    public function test_fixed_clock_returns_and_advances(): void
    {
        $tz = new DateTimeZone('Europe/Istanbul');
        $clock = new FixedClock(new DateTimeImmutable('2026-11-13 17:30', $tz));

        self::assertSame('2026-11-13 17:30', $clock->now()->format('Y-m-d H:i'));

        $clock->advance('+90 minutes');
        self::assertSame('2026-11-13 19:00', $clock->now()->format('Y-m-d H:i'));

        $clock->set(new DateTimeImmutable('2026-11-16 09:00', $tz));
        self::assertSame('2026-11-16 09:00', $clock->now()->format('Y-m-d H:i'));
    }
}
