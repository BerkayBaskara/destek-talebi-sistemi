<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use App\Model\CalendarConfig;
use App\Repository\Json\JsonCalendarRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JsonCalendarRepositoryTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../fixtures';

    private static function day(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('Europe/Istanbul'));
    }

    public function test_reads_intervals_with_break_removed(): void
    {
        $config = (new JsonCalendarRepository(self::FIXTURES . '/calendar.json'))->getConfig();

        self::assertSame([[540, 720], [780, 1080]], $config->intervalsFor(self::day('2026-11-09')));
    }

    public function test_weekend_and_holiday_have_no_intervals(): void
    {
        $config = (new JsonCalendarRepository(self::FIXTURES . '/calendar.json'))->getConfig();

        self::assertSame([], $config->intervalsFor(self::day('2026-11-14')));
        self::assertSame([], $config->intervalsFor(self::day('2026-10-29')));
    }

    public function test_business_hours_and_holidays_accessors(): void
    {
        $repo = new JsonCalendarRepository(self::FIXTURES . '/calendar.json');

        self::assertSame(['2026-10-29'], $repo->getHolidays());
        self::assertSame([[540, 720], [780, 1080]], $repo->getBusinessHours()[1]);
        self::assertArrayNotHasKey(6, $repo->getBusinessHours());
    }

    public function test_missing_file_falls_back_to_default_and_logs(): void
    {
        $messages = [];
        $repo = new JsonCalendarRepository(self::FIXTURES . '/yok.json', function (string $m) use (&$messages): void {
            $messages[] = $m;
        });

        $config = $repo->getConfig();

        self::assertEquals(CalendarConfig::default(), $config);
        self::assertCount(1, $messages);
        self::assertStringContainsString('Takvim dosyası okunamadı', $messages[0]);
    }

    public function test_corrupt_file_falls_back_to_default_and_logs(): void
    {
        $messages = [];
        $repo = new JsonCalendarRepository(self::FIXTURES . '/calendar-bozuk.json', function (string $m) use (&$messages): void {
            $messages[] = $m;
        });

        self::assertSame([[540, 720], [780, 1080]], $repo->getConfig()->intervalsFor(self::day('2026-11-09')));
        $repo->getConfig();
        self::assertCount(1, $messages, 'Sonuç önbelleğe alınmalı, uyarı bir kez yazılmalı.');
        self::assertStringContainsString('Takvim dosyası okunamadı', $messages[0]);
    }

    public function test_calendar_without_any_working_hours_falls_back_to_default(): void
    {
        $path = sys_get_temp_dir() . '/cal_' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, '{"work_days": {}, "holidays": []}');
        $messages = [];

        $config = (new JsonCalendarRepository($path, function (string $m) use (&$messages): void {
            $messages[] = $m;
        }))->getConfig();
        unlink($path);

        self::assertEquals(CalendarConfig::default(), $config);
        self::assertCount(1, $messages);
    }

    public function test_bom_prefixed_file_is_read(): void
    {
        $path = sys_get_temp_dir() . '/cal_' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, "\xEF\xBB\xBF" . file_get_contents(self::FIXTURES . '/calendar.json'));

        $holidays = (new JsonCalendarRepository($path))->getHolidays();
        unlink($path);

        self::assertSame(['2026-10-29'], $holidays, 'BOM ile kaydedilmiş takvim (PowerShell/Notepad) de okunmalı.');
    }

    public function test_break_outside_day_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CalendarConfig::fromArray([
            'work_days' => ['1' => ['start' => '09:00', 'end' => '18:00', 'breaks' => [['start' => '19:00', 'end' => '20:00']]]],
            'holidays' => [],
        ]);
    }

    public function test_bad_time_format_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CalendarConfig::fromArray(['work_days' => ['1' => ['start' => '9', 'end' => '18:00']], 'holidays' => []]);
    }
}
