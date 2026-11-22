<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Clock\FixedClock;
use App\Exception\ValidationException;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\Json\JsonAuditLogRepository;
use App\Repository\Json\JsonWorkCalendarRepository;
use App\Service\AuditLogger;
use App\Service\CalendarService;
use App\Service\SlaCalculatorService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

/** Yönetilebilir çalışma takvimi: mesai saatleri ve tatiller (SLA hesabının girdisi). */
final class CalendarServiceTest extends TestCase
{
    use TempStoreTrait;

    private JsonWorkCalendarRepository $repo;
    private CalendarService $service;
    private User $admin;
    /** @var list<string> */
    private array $logged = [];

    protected function setUp(): void
    {
        $store = $this->makeStore();
        $this->repo = new JsonWorkCalendarRepository($store, function (string $m): void {
            $this->logged[] = $m;
        });
        $this->service = new CalendarService($this->repo, new AuditLogger(new JsonAuditLogRepository($store), new FixedClock(new DateTimeImmutable('2026-11-24 10:00'))));
        $this->admin = new User(1, 'Y000001', 'x', Role::Admin, 'Selin', 'Arslan', 'S', 'C');
    }

    private static function at(string $s): DateTimeImmutable
    {
        return new DateTimeImmutable($s, new DateTimeZone('Europe/Istanbul'));
    }

    private function importFixture(): void
    {
        ['days' => $days, 'holidays' => $holidays] = CalendarService::readLegacyFile(__DIR__ . '/../../fixtures/calendar.json');
        $this->repo->replaceAll($days, $holidays);
    }

    /** @return array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}> */
    private function weekdays(): array
    {
        $days = [];
        for ($d = 1; $d <= 7; $d++) {
            $days[$d] = ['works' => $d <= 5, 'start' => '09:00', 'end' => '18:00', 'break_start' => '12:00', 'break_end' => '13:00'];
        }

        return $days;
    }

    public function test_reads_same_intervals_as_file_calendar(): void
    {
        $this->importFixture();

        $config = $this->repo->getConfig();
        self::assertSame([[540, 720], [780, 1080]], $config->intervalsFor(self::at('2026-11-09')));
        self::assertSame([], $config->intervalsFor(self::at('2026-11-14'))); // Cumartesi
        self::assertSame([], $config->intervalsFor(self::at('2026-10-29'))); // tatil
        self::assertSame(['2026-10-29'], $this->repo->getHolidays());
        self::assertSame([], $this->logged);
    }

    public function test_empty_tables_fall_back_to_default_and_log(): void
    {
        self::assertSame([[540, 720], [780, 1080]], $this->repo->getConfig()->intervalsFor(self::at('2026-11-09')));
        self::assertCount(1, $this->logged);
        self::assertStringContainsString('Takvim', $this->logged[0]);
    }

    public function test_empty_tables_use_legacy_file_until_admin_saves(): void
    {
        $legacy = new \App\Repository\Json\JsonCalendarRepository(__DIR__ . '/../../fixtures/calendar.json');
        $repo = new JsonWorkCalendarRepository(new \App\Core\Storage\JsonStore($this->tempDir), fn (string $m) => $this->logged[] = $m, $legacy);

        self::assertSame([], $repo->getConfig()->intervalsFor(self::at('2026-10-29'))); // dosyadaki tatil
        self::assertSame([], $this->logged);
    }

    public function test_save_rejects_calendar_without_working_days_and_keeps_old(): void
    {
        $this->importFixture();
        $none = array_map(static fn (array $d): array => ['works' => false] + $d, $this->weekdays());

        try {
            $this->service->saveWorkHours($this->admin, $none);
            self::fail('Hiç çalışma günü olmayan takvim kabul edilmemeli.');
        } catch (ValidationException $e) {
            self::assertSame('En az bir çalışma günü olmalı.', $e->errors()['days']);
        }
        self::assertTrue($this->repo->workHours()[1]['works']);
    }

    public function test_save_rejects_break_outside_day(): void
    {
        $days = $this->weekdays();
        $days[1]['break_start'] = '19:00';
        $days[1]['break_end'] = '20:00';

        $this->expectException(ValidationException::class);
        $this->service->saveWorkHours($this->admin, $days);
    }

    public function test_saved_hours_change_sla(): void
    {
        $days = $this->weekdays();
        $days[6]['works'] = true; // Cumartesi de çalışılsın
        $this->service->saveWorkHours($this->admin, $days);

        $calc = new SlaCalculatorService($this->repo);
        self::assertSame('2026-11-14 10:00', $calc->calculateWithJson(self::at('2026-11-13 17:00'), 120)->format('Y-m-d H:i'));
    }

    public function test_holiday_add_remove_and_affects_sla(): void
    {
        $this->service->saveWorkHours($this->admin, $this->weekdays());
        $this->service->addHoliday($this->admin, '2026-11-25', 'Örnek tatil');

        $calc = new SlaCalculatorService($this->repo);
        self::assertSame('2026-11-26 10:00', $calc->calculateWithJson(self::at('2026-11-24 17:00'), 120)->format('Y-m-d H:i'));
        self::assertSame([['date' => '2026-11-25', 'description' => 'Örnek tatil']], $this->repo->holidays());

        $this->service->removeHoliday($this->admin, '2026-11-25');
        self::assertSame([], $this->repo->holidays());
    }

    public function test_duplicate_or_invalid_holiday_rejected(): void
    {
        $this->service->addHoliday($this->admin, '2026-11-25', 'Örnek');

        foreach (['2026-11-25', '25.11.2026', '2026-02-30'] as $date) {
            try {
                $this->service->addHoliday($this->admin, $date, 'Tekrar');
                self::fail($date . ' kabul edilmemeliydi.');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('date', $e->errors());
            }
        }
    }
}
