<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;
use App\Model\CalendarConfig;
use App\Model\User;
use App\Repository\Json\JsonWorkCalendarRepository;
use App\Repository\WorkCalendarRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Yöneticinin çalışma takvimini düzenlemesi. Kaydetmeden önce takvim, SLA hesabının kullandığı
 * CalendarConfig kurallarıyla doğrulanır; geçersiz takvim yazılmaz, eski takvim korunur.
 */
final class CalendarService
{
    public const DAY_NAMES = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];

    public function __construct(
        private readonly WorkCalendarRepositoryInterface $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}> $days
     * @throws ValidationException
     */
    public function saveWorkHours(User $admin, array $days): void
    {
        if (array_filter($days, static fn (array $d): bool => $d['works']) === []) {
            throw new ValidationException(['days' => 'En az bir çalışma günü olmalı.']);
        }
        foreach ($days as $day => $d) {
            if ($d['works'] && ($d['break_start'] === null) !== ($d['break_end'] === null)) {
                throw new ValidationException(['days' => 'Öğle arasının başlangıç ve bitiş saati birlikte girilmeli (' . (self::DAY_NAMES[$day] ?? (string) $day) . ').']);
            }
        }
        try {
            CalendarConfig::fromArray(JsonWorkCalendarRepository::toSchema($days, []));
        } catch (InvalidArgumentException $e) {
            throw new ValidationException(['days' => $e->getMessage()]);
        }

        $old = $this->repo->workHours();
        $this->repo->saveWorkHours($days);
        $this->audit->record($admin, 'update', 'work_hours', null, ['days' => $old], ['days' => $days]);
    }

    /** @throws ValidationException */
    public function addHoliday(User $admin, string $date, string $description): void
    {
        $date = trim($date);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new ValidationException(['date' => 'Tarih YYYY-AA-GG biçiminde geçerli bir gün olmalı.']);
        }
        if (in_array($date, array_column($this->repo->holidays(), 'date'), true)) {
            throw new ValidationException(['date' => 'Bu tarih zaten tatil listesinde.']);
        }
        $description = mb_substr(trim($description), 0, 100);

        $this->repo->addHoliday($date, $description);
        $this->audit->record($admin, 'create', 'holiday', null, null, ['date' => $date, 'description' => $description]);
    }

    public function removeHoliday(User $admin, string $date): void
    {
        $this->repo->removeHoliday($date);
        $this->audit->record($admin, 'delete_holiday', 'holiday', null, ['date' => $date], null);
    }

    /**
     * Eski calendar.json dosyasını gün kayıtlarına ve tatil listesine çevirir (örnek veri aktarımı).
     *
     * @return array{days: array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}>, holidays: list<array{date: string, description: string}>}
     */
    public static function readLegacyFile(string $path): array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $workDays = is_array($data) && is_array($data['work_days'] ?? null) ? $data['work_days'] : [];

        $days = [];
        for ($day = 1; $day <= 7; $day++) {
            $d = $workDays[(string) $day] ?? null;
            $break = is_array($d) && is_array($d['breaks'][0] ?? null) ? $d['breaks'][0] : null;
            $days[$day] = [
                'works' => is_array($d),
                'start' => is_array($d) ? (string) ($d['start'] ?? '09:00') : '09:00',
                'end' => is_array($d) ? (string) ($d['end'] ?? '18:00') : '18:00',
                'break_start' => $break !== null ? (string) ($break['start'] ?? '') : (is_array($d) ? null : '12:00'),
                'break_end' => $break !== null ? (string) ($break['end'] ?? '') : (is_array($d) ? null : '13:00'),
            ];
        }

        $holidays = [];
        foreach (is_array($data) && is_array($data['holidays'] ?? null) ? $data['holidays'] : [] as $date) {
            if (is_string($date)) {
                $holidays[] = ['date' => $date, 'description' => 'Resmî tatil'];
            }
        }

        return ['days' => $days, 'holidays' => $holidays];
    }
}
