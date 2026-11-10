<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Clock\FixedClock;
use App\Repository\Json\JsonCalendarRepository;
use App\Service\SlaCalculatorService;
use App\Service\SlaStatusService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * FR-07: SLA'nın %80'i dolunca sarı uyarı, %100'ü dolunca kırmızı ihlal.
 * Talep Pzt 2026-11-09 09:00'da açılıyor, 10 iş saati → bitiş Sal 2026-11-10 11:00.
 */
final class SlaStatusServiceTest extends TestCase
{
    private FixedClock $clock;
    private SlaStatusService $service;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(self::at('2026-11-09 09:00'));
        $calculator = new SlaCalculatorService(new JsonCalendarRepository(__DIR__ . '/../../fixtures/calendar.json'));
        $this->service = new SlaStatusService($calculator, $this->clock);
    }

    private static function at(string $datetime): DateTimeImmutable
    {
        return new DateTimeImmutable($datetime, new DateTimeZone('Europe/Istanbul'));
    }

    private function evaluateAt(string $now, ?string $resolvedAt = null, bool $hidden = false): \App\Model\SlaStatus
    {
        $this->clock->set(self::at($now));

        return $this->service->evaluate(
            self::at('2026-11-09 09:00'),
            self::at('2026-11-10 11:00'),
            $resolvedAt === null ? null : self::at($resolvedAt),
            $hidden,
        );
    }

    public function test_normal_below_80_percent(): void
    {
        $status = $this->evaluateAt('2026-11-09 14:00');

        self::assertSame('normal', $status->level);
        self::assertSame(40, $status->percent);
        self::assertSame(360, $status->remainingMinutes);
        self::assertSame('6 sa kaldı', $status->label);
        self::assertSame('sla-normal', $status->cssClass());
    }

    public function test_warning_at_exactly_80_percent(): void
    {
        $status = $this->evaluateAt('2026-11-10 09:00');

        self::assertSame('warning', $status->level);
        self::assertSame(80, $status->percent);
        self::assertSame('2 sa kaldı', $status->label);
        self::assertSame('sla-warning', $status->cssClass());
    }

    public function test_just_below_80_percent_is_normal(): void
    {
        self::assertSame('normal', $this->evaluateAt('2026-11-09 17:59')->level);
    }

    public function test_breached_after_deadline(): void
    {
        $status = $this->evaluateAt('2026-11-10 12:00');

        self::assertSame('breached', $status->level);
        self::assertGreaterThanOrEqual(100, $status->percent);
        self::assertSame(0, $status->remainingMinutes);
        self::assertSame('Süre aşıldı', $status->label);
        self::assertSame('sla-breached', $status->cssClass());
    }

    public function test_breached_exactly_at_deadline(): void
    {
        self::assertSame('breached', $this->evaluateAt('2026-11-10 11:00')->level);
    }

    public function test_resolved_in_time_is_met(): void
    {
        $status = $this->evaluateAt('2026-11-12 10:00', resolvedAt: '2026-11-10 10:30');

        self::assertSame('met', $status->level);
        self::assertSame('Süre içinde çözüldü', $status->label);
    }

    public function test_resolved_late_is_missed(): void
    {
        $status = $this->evaluateAt('2026-11-12 10:00', resolvedAt: '2026-11-10 15:00');

        self::assertSame('missed', $status->level);
        self::assertSame('Süre aşılarak çözüldü', $status->label);
    }

    public function test_hidden_status_shows_none(): void
    {
        $status = $this->evaluateAt('2026-11-10 12:00', hidden: true);

        self::assertSame('none', $status->level);
        self::assertSame('—', $status->label);
        self::assertSame('sla-none', $status->cssClass());
    }

    public function test_remaining_excludes_weekend(): void
    {
        $this->clock->set(self::at('2026-11-13 18:00'));

        $status = $this->service->evaluate(self::at('2026-11-13 09:00'), self::at('2026-11-16 13:30'), null, false);

        self::assertSame(210, $status->remainingMinutes);
        self::assertSame('3 sa 30 dk kaldı', $status->label);
    }

    public function test_format_minutes(): void
    {
        self::assertSame('45 dk', $this->service->formatMinutes(45));
        self::assertSame('2 sa', $this->service->formatMinutes(120));
        self::assertSame('3 sa 20 dk', $this->service->formatMinutes(200));
        self::assertSame('0 dk', $this->service->formatMinutes(0));
    }
}
