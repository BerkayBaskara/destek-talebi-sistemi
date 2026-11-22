<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\CalendarConfig;
use App\Repository\CalendarRepositoryInterface;
use App\Repository\WorkCalendarRepositoryInterface;
use InvalidArgumentException;

/**
 * work_hours.json ve holidays.json üzerinde çalışma takvimi.
 * Tablolar boşken eski calendar.json okunur: yönetici ekranı da SLA hesabı da aynı takvimi görür. Yönetici
 * takvimde ilk değişikliği yaptığında eski takvim önce tablolara aktarılır, değişiklik onun üzerine yazılır
 * (dosyadaki tatiller kaybolmaz). Takvim geçersizse sistem çökmez: kod içindeki varsayılan mesaiye döner ve
 * durumu hata günlüğüne yazar (NFR-07).
 */
final class JsonWorkCalendarRepository implements WorkCalendarRepositoryInterface
{
    private const HOURS = 'work_hours';
    private const HOLIDAYS = 'holidays';

    /** @var callable(string): void */
    private $logger;

    private ?CalendarConfig $config = null;

    /**
     * @param (callable(string): void)|null $logger
     * @param CalendarRepositoryInterface|null $legacy yönetici takvimi kaydedene kadar okunan eski calendar.json
     */
    public function __construct(
        private readonly JsonStore $store,
        ?callable $logger = null,
        private readonly ?CalendarRepositoryInterface $legacy = null,
    ) {
        $this->logger = $logger ?? static function (string $message): void {
            error_log($message);
        };
    }

    public function getConfig(): CalendarConfig
    {
        return $this->config ??= $this->load();
    }

    public function getBusinessHours(): array
    {
        return $this->getConfig()->workDays;
    }

    public function getHolidays(): array
    {
        $holidays = array_keys($this->getConfig()->holidays);
        sort($holidays);

        return $holidays;
    }

    public function workHours(): array
    {
        $days = $this->storedWorkHours();

        return $days === [] && $this->legacy !== null ? self::fromConfig($this->legacy->getConfig())['days'] : $days;
    }

    /** @return array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}> */
    private function storedWorkHours(): array
    {
        $days = [];
        foreach ($this->store->all(self::HOURS) as $row) {
            $day = (int) ($row['day'] ?? 0);
            if ($day < 1 || $day > 7) {
                continue;
            }
            $days[$day] = [
                'works' => (bool) ($row['works'] ?? false),
                'start' => (string) ($row['start'] ?? '09:00'),
                'end' => (string) ($row['end'] ?? '18:00'),
                'break_start' => isset($row['break_start']) && $row['break_start'] !== '' ? (string) $row['break_start'] : null,
                'break_end' => isset($row['break_end']) && $row['break_end'] !== '' ? (string) $row['break_end'] : null,
            ];
        }
        ksort($days);

        return $days;
    }

    public function saveWorkHours(array $days): void
    {
        $this->importLegacyIfEmpty();
        $this->writeWorkHours($days);
    }

    /** @param array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}> $days */
    private function writeWorkHours(array $days): void
    {
        $this->store->transaction([self::HOURS], function (JsonStore $store) use ($days): void {
            $existing = [];
            foreach ($store->all(self::HOURS) as $row) {
                $existing[(int) ($row['day'] ?? 0)] = (int) $row['id'];
            }
            foreach ($days as $day => $data) {
                $row = ['day' => $day] + $data;
                if (isset($existing[$day])) {
                    $store->update(self::HOURS, $existing[$day], $row);
                } else {
                    $store->insert(self::HOURS, $row);
                }
            }
        });
        $this->config = null;
    }

    public function holidays(): array
    {
        if ($this->legacy !== null && $this->storedWorkHours() === []) {
            return self::mergeHolidays(self::fromConfig($this->legacy->getConfig())['holidays'], $this->storedHolidays());
        }

        return $this->storedHolidays();
    }

    /** @return list<array{date: string, description: string}> */
    private function storedHolidays(): array
    {
        $list = [];
        foreach ($this->store->all(self::HOLIDAYS) as $row) {
            $list[] = ['date' => (string) ($row['date'] ?? ''), 'description' => (string) ($row['description'] ?? '')];
        }
        usort($list, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $list;
    }

    public function addHoliday(string $date, string $description): void
    {
        $this->importLegacyIfEmpty();
        $this->store->insert(self::HOLIDAYS, ['date' => $date, 'description' => $description]);
        $this->config = null;
    }

    public function removeHoliday(string $date): void
    {
        $this->importLegacyIfEmpty();
        $this->deleteHoliday($date);
    }

    private function deleteHoliday(string $date): void
    {
        foreach ($this->store->all(self::HOLIDAYS) as $row) {
            if (($row['date'] ?? null) === $date) {
                $this->store->delete(self::HOLIDAYS, (int) $row['id']);
            }
        }
        $this->config = null;
    }

    public function replaceAll(array $days, array $holidays): void
    {
        foreach ($this->storedHolidays() as $holiday) {
            $this->deleteHoliday($holiday['date']);
        }
        $this->writeWorkHours($days);
        foreach ($holidays as $holiday) {
            $this->store->insert(self::HOLIDAYS, ['date' => $holiday['date'], 'description' => $holiday['description']]);
        }
        $this->config = null;
    }

    /** Tablolar boşken yapılan ilk değişiklikten önce eski takvimi (ve o ana kadar eklenmiş tatilleri) tablolara yazar. */
    private function importLegacyIfEmpty(): void
    {
        if ($this->legacy === null || $this->storedWorkHours() !== []) {
            return;
        }
        $legacy = self::fromConfig($this->legacy->getConfig());
        $this->replaceAll($legacy['days'], self::mergeHolidays($legacy['holidays'], $this->storedHolidays()));
    }

    /**
     * CalendarConfig'i gün kayıtlarına ve tatil listesine çevirir (toSchema'nın tersi).
     *
     * @return array{days: array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}>, holidays: list<array{date: string, description: string}>}
     */
    private static function fromConfig(CalendarConfig $config): array
    {
        $time = static fn (int $minutes): string => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        $days = [];
        for ($day = 1; $day <= 7; $day++) {
            $intervals = $config->workDays[$day] ?? [];
            if ($intervals === []) {
                $days[$day] = ['works' => false, 'start' => '09:00', 'end' => '18:00', 'break_start' => '12:00', 'break_end' => '13:00'];
                continue;
            }
            $hasBreak = count($intervals) >= 2;
            $days[$day] = [
                'works' => true,
                'start' => $time($intervals[0][0]),
                'end' => $time($intervals[count($intervals) - 1][1]),
                'break_start' => $hasBreak ? $time($intervals[0][1]) : null,
                'break_end' => $hasBreak ? $time($intervals[1][0]) : null,
            ];
        }
        $holidays = array_map(static fn (string $date): array => ['date' => $date, 'description' => ''], array_map('strval', array_keys($config->holidays)));

        return ['days' => $days, 'holidays' => $holidays];
    }

    /**
     * @param list<array{date: string, description: string}> $first
     * @param list<array{date: string, description: string}> $second açıklaması dolu olan kazanır
     * @return list<array{date: string, description: string}>
     */
    private static function mergeHolidays(array $first, array $second): array
    {
        $byDate = [];
        foreach (array_merge($first, $second) as $holiday) {
            if (!isset($byDate[$holiday['date']]) || $holiday['description'] !== '') {
                $byDate[$holiday['date']] = $holiday;
            }
        }
        ksort($byDate);

        return array_values($byDate);
    }

    /**
     * Gün kayıtlarını CalendarConfig şemasına çevirir (çalışılmayan gün şemaya girmez).
     *
     * @param array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}> $days
     * @param list<string> $holidays
     * @return array<string, mixed>
     */
    public static function toSchema(array $days, array $holidays): array
    {
        $workDays = [];
        foreach ($days as $day => $data) {
            if (!$data['works']) {
                continue;
            }
            $breaks = $data['break_start'] !== null && $data['break_end'] !== null
                ? [['start' => $data['break_start'], 'end' => $data['break_end']]]
                : [];
            $workDays[(string) $day] = ['start' => $data['start'], 'end' => $data['end'], 'breaks' => $breaks];
        }

        return ['timezone' => 'Europe/Istanbul', 'work_days' => $workDays, 'holidays' => $holidays];
    }

    private function load(): CalendarConfig
    {
        try {
            $days = $this->storedWorkHours();
            if ($days === [] && $this->legacy !== null) {
                return $this->legacy->getConfig();
            }
            if ($days === []) {
                throw new InvalidArgumentException('mesai saatleri tanımlanmamış');
            }

            return CalendarConfig::fromArray(self::toSchema($days, array_column($this->storedHolidays(), 'date')));
        } catch (InvalidArgumentException $e) {
            ($this->logger)('Takvim okunamadı, varsayılan mesai kullanılıyor: ' . $e->getMessage());

            return CalendarConfig::default();
        }
    }
}
