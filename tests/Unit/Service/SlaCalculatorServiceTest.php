<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Model\CalendarConfig;
use App\Repository\CalendarRepositoryInterface;
use App\Repository\Json\JsonCalendarRepository;
use App\Service\SlaCalculatorService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Çekirdek problem: mesai saatine, öğle arasına ve resmi tatile duyarlı SLA bitiş zamanı.
 * Takvim (tests/fixtures/calendar.json): Pzt–Cum 09:00–18:00, öğle 12:00–13:00, tatil 29 Ekim 2026 (Perşembe).
 * 9 Kasım 2026 Pazartesi'dir.
 */
final class SlaCalculatorServiceTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures';

    private static function at(string $datetime): DateTimeImmutable
    {
        return new DateTimeImmutable($datetime, new DateTimeZone('Europe/Istanbul'));
    }

    private static function service(string $calendarFile = 'calendar.json'): SlaCalculatorService
    {
        return new SlaCalculatorService(new JsonCalendarRepository(self::FIXTURES . '/' . $calendarFile, static function (): void {
        }));
    }

    /** @return array<string, array{string, int, string}> */
    public static function cases(): array
    {
        return [
            'mola_oncesi' => ['2026-11-09 10:00', 2, '2026-11-09 12:00'],
            'ogle_arasi_atlanir' => ['2026-11-09 11:00', 2, '2026-11-09 14:00'],
            'molada_acilan' => ['2026-11-09 12:30', 1, '2026-11-09 14:00'],
            'gun_sonu_devri' => ['2026-11-09 17:00', 2, '2026-11-10 10:00'],
            'cuma_1730_hocanin_ornegi' => ['2026-11-13 17:30', 4, '2026-11-16 13:30'],
            'hafta_sonu_acilan' => ['2026-11-14 10:00', 1, '2026-11-16 10:00'],
            'mesai_sonrasi_acilan' => ['2026-11-09 20:00', 1, '2026-11-10 10:00'],
            'tatil_oncesi_gun' => ['2026-10-28 17:00', 2, '2026-10-30 10:00'],
            'tatilde_acilan' => ['2026-10-29 11:00', 1, '2026-10-30 10:00'],
            'tam_gun_mesai_sonunda_biter' => ['2026-11-09 09:00', 8, '2026-11-09 18:00'],
            'cok_gunlu' => ['2026-11-13 16:00', 24, '2026-11-18 16:00'],
            'mesai_oncesi_acilan' => ['2026-11-09 07:00', 1, '2026-11-09 10:00'],
        ];
    }

    #[DataProvider('cases')]
    public function test_deadline_matches_case_table(string $start, int $hours, string $expected): void
    {
        $deadline = self::service()->calculateWithJson(self::at($start), $hours * 60);

        self::assertSame($expected, $deadline->format('Y-m-d H:i'));
    }

    public function test_zero_duration_outside_hours_moves_to_next_working_moment(): void
    {
        self::assertSame('2026-11-16 09:00', self::service()->calculateWithJson(self::at('2026-11-14 10:00'), 0)->format('Y-m-d H:i'));
        self::assertSame('2026-11-09 10:15', self::service()->calculateWithJson(self::at('2026-11-09 10:15'), 0)->format('Y-m-d H:i'));
    }

    public function test_corrupt_calendar_uses_default_hours(): void
    {
        $deadline = self::service('calendar-bozuk.json')->calculateWithJson(self::at('2026-11-09 11:00'), 120);

        self::assertSame('2026-11-09 14:00', $deadline->format('Y-m-d H:i'));
    }

    public function test_business_minutes_between_skips_break_and_weekend(): void
    {
        $minutes = self::service()->businessMinutesBetween(self::at('2026-11-13 17:30'), self::at('2026-11-16 13:30'));

        self::assertSame(240, $minutes);
    }

    public function test_business_minutes_reverse_order_is_zero(): void
    {
        self::assertSame(0, self::service()->businessMinutesBetween(self::at('2026-11-10 10:00'), self::at('2026-11-09 10:00')));
    }

    public function test_calendar_without_workdays_throws(): void
    {
        $service = new SlaCalculatorService(self::fixedCalendar(new CalendarConfig([], [])));

        $this->expectException(RuntimeException::class);
        $service->calculateWithJson(self::at('2026-11-09 10:00'), 60);
    }

    public function test_five_years_of_holidays_is_fast(): void
    {
        $holidays = [];
        for ($d = self::at('2026-01-02'); $d < self::at('2031-01-01'); $d = $d->modify('+7 days')) {
            $holidays[$d->format('Y-m-d')] = true;
        }
        $config = CalendarConfig::default();
        $service = new SlaCalculatorService(self::fixedCalendar(new CalendarConfig($config->workDays, $holidays)));

        $started = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $service->calculateWithJson(self::at('2026-11-13 17:30'), 24 * 60);
        }
        $elapsedMs = (hrtime(true) - $started) / 1_000_000;

        self::assertLessThan(500, $elapsedMs, 'NFR-01: 1000 hesap 500 ms altında olmalı.');
    }

    private static function fixedCalendar(CalendarConfig $config): CalendarRepositoryInterface
    {
        return new class ($config) implements CalendarRepositoryInterface {
            public function __construct(private readonly CalendarConfig $config)
            {
            }

            public function getConfig(): CalendarConfig
            {
                return $this->config;
            }

            public function getBusinessHours(): array
            {
                return $this->config->workDays;
            }

            public function getHolidays(): array
            {
                return array_keys($this->config->holidays);
            }
        };
    }
}
