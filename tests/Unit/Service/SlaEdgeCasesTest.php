<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Storage\JsonStore;
use App\Repository\Json\JsonCalendarRepository;
use App\Repository\Json\JsonRuntimeStateRepository;
use App\Service\SlaCalculatorService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/** SLA-008 yıl değişimi, SLA-009 artık yıl, SLA-010 saat dilimi; SEC-033/034 zamanlanmış görev kilidi. */
final class SlaEdgeCasesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sla_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $day = ['start' => '09:00', 'end' => '18:00', 'breaks' => [['start' => '12:00', 'end' => '13:00']]];
        file_put_contents($this->dir . '/calendar.json', (string) json_encode([
            'work_days' => ['1' => $day, '2' => $day, '3' => $day, '4' => $day, '5' => $day],
            'holidays' => ['2027-01-01'],
        ]));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function sla(): SlaCalculatorService
    {
        return new SlaCalculatorService(new JsonCalendarRepository($this->dir . '/calendar.json'));
    }

    private static function at(string $s, string $tz = 'Europe/Istanbul'): DateTimeImmutable
    {
        return new DateTimeImmutable($s, new DateTimeZone($tz));
    }

    public function test_year_change_skips_new_year_holiday(): void
    {
        // Per 2026-12-31 17:00 + 2 sa: Per 1 sa + Cuma 1 Ocak tatil + hafta sonu → Pzt 2027-01-04 10:00
        self::assertSame('2027-01-04 10:00', $this->sla()->calculateWithJson(self::at('2026-12-31 17:00'), 120)->format('Y-m-d H:i'));
    }

    public function test_leap_day_is_a_normal_working_day(): void
    {
        // 2028-02-28 Pzt 17:00 + 2 sa → 2028-02-29 Sal 10:00
        self::assertSame('2028-02-29 10:00', $this->sla()->calculateWithJson(self::at('2028-02-28 17:00'), 120)->format('Y-m-d H:i'));
    }

    public function test_utc_input_gives_same_moment_as_local_input(): void
    {
        $local = $this->sla()->calculateWithJson(self::at('2026-11-09 10:00'), 120);
        $utc = $this->sla()->calculateWithJson(self::at('2026-11-09 07:00', 'UTC'), 120);

        self::assertSame($local->getTimestamp(), $utc->getTimestamp());
        self::assertSame('12:00', $utc->setTimezone(new DateTimeZone('Europe/Istanbul'))->format('H:i'));
    }

    public function test_job_lock_allows_one_run_per_interval_and_expires(): void
    {
        $state = new JsonRuntimeStateRepository(new JsonStore($this->dir));

        self::assertTrue($state->claim('sla_monitor', 1000, 60));
        self::assertFalse($state->claim('sla_monitor', 1030, 60), 'Aynı dakikada ikinci çalışma engellenir.');
        self::assertTrue($state->claim('sla_monitor', 1061, 60), 'Yarıda kalan çalışma sonraki aralıkta tekrar başlar.');
    }
}
