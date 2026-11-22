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
 * Tablolar boşsa ya da geçersiz bir takvim içeriyorsa sistem çökmez: kod içindeki varsayılan
 * mesaiye döner ve durumu hata günlüğüne yazar (NFR-07).
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
        $list = [];
        foreach ($this->store->all(self::HOLIDAYS) as $row) {
            $list[] = ['date' => (string) ($row['date'] ?? ''), 'description' => (string) ($row['description'] ?? '')];
        }
        usort($list, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $list;
    }

    public function addHoliday(string $date, string $description): void
    {
        $this->store->insert(self::HOLIDAYS, ['date' => $date, 'description' => $description]);
        $this->config = null;
    }

    public function removeHoliday(string $date): void
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
        foreach ($this->holidays() as $holiday) {
            $this->removeHoliday($holiday['date']);
        }
        $this->saveWorkHours($days);
        foreach ($holidays as $holiday) {
            $this->addHoliday($holiday['date'], $holiday['description']);
        }
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
            $days = $this->workHours();
            if ($days === [] && $this->legacy !== null) {
                return $this->legacy->getConfig();
            }
            if ($days === []) {
                throw new InvalidArgumentException('mesai saatleri tanımlanmamış');
            }

            return CalendarConfig::fromArray(self::toSchema($days, array_column($this->holidays(), 'date')));
        } catch (InvalidArgumentException $e) {
            ($this->logger)('Takvim okunamadı, varsayılan mesai kullanılıyor: ' . $e->getMessage());

            return CalendarConfig::default();
        }
    }
}
