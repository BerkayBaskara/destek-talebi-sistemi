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
        private readonly ?OpenTicketRecalculator $recalculator = null,
    ) {
    }

    /** Takvimi değiştiren işlemi çalıştırır; ayar açıksa açık taleplerin bitişini yeni takvime göre günceller (FR-13.4). */
    private function changing(callable $write): void
    {
        $snapshot = $this->recalculator?->snapshot() ?? [];
        $write();
        if ($snapshot !== []) {
            $this->recalculator->apply($snapshot); // boş olmayan anlık görüntü yalnız hesaplayıcı varken oluşur
        }
    }

    /** @throws ValidationException */
    public function addSpecialDay(User $admin, string $date, string $start, string $end, string $description): void
    {
        $date = trim($date);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = [];
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            $errors['special_date'] = 'Tarih YYYY-AA-GG biçiminde geçerli bir gün olmalı.';
        } elseif (in_array($date, array_column($this->repo->specialDays(), 'date'), true)) {
            $errors['special_date'] = 'Bu tarih için zaten özel gün var.';
        } elseif (in_array($date, array_column($this->repo->holidays(), 'date'), true)) {
            $errors['special_date'] = 'Bu tarih tatil olarak tanımlı; önce tatili kaldırın.';
        }
        $valid = static fn (string $t): bool => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) === 1;
        if (!$valid($start) || !$valid($end)) {
            $errors['special_time'] = 'Saatler SS:DD biçiminde olmalı.';
        } elseif ($end <= $start) {
            $errors['special_time'] = 'Bitiş saati başlangıçtan sonra olmalı.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $description = mb_substr(trim($description), 0, 100);
        $this->changing(fn () => $this->repo->addSpecialDay($date, $start, $end, $description));
        $this->audit->record($admin, 'create', 'special_day', null, null, ['date' => $date, 'start' => $start, 'end' => $end, 'description' => $description]);
    }

    public function removeSpecialDay(User $admin, string $date): void
    {
        $this->changing(fn () => $this->repo->removeSpecialDay($date));
        $this->audit->record($admin, 'delete', 'special_day', null, ['date' => $date], null);
    }

    /**
     * FR-13.1: calendar.json biçimindeki takvim dosyasını doğrular ve tümüyle aktarır (çalışma günleri, tatiller,
     * özel günler). Dosya geçersizse hiçbir şey değişmez.
     *
     * @param array<string, mixed> $file
     * @throws ValidationException
     */
    public function importFile(User $admin, array $file): void
    {
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_file($tmp)) {
            throw new ValidationException(['takvim' => 'Bir takvim dosyası seçin.']);
        }
        if ((int) filesize($tmp) > 200 * 1024) {
            throw new ValidationException(['takvim' => 'Takvim dosyası en fazla 200 KB olabilir.']);
        }
        $data = json_decode((string) file_get_contents($tmp), true);
        if (!is_array($data)) {
            throw new ValidationException(['takvim' => 'Takvim dosyası geçersiz: JSON okunamadı.']);
        }
        try {
            CalendarConfig::fromArray($data);
        } catch (InvalidArgumentException $e) {
            throw new ValidationException(['takvim' => 'Takvim dosyası geçersiz: ' . $e->getMessage()]);
        }

        $workDays = is_array($data['work_days'] ?? null) ? $data['work_days'] : [];
        $days = [];
        for ($day = 1; $day <= 7; $day++) {
            $d = is_array($workDays[(string) $day] ?? null) ? $workDays[(string) $day] : null;
            $break = $d !== null && is_array($d['breaks'][0] ?? null) ? $d['breaks'][0] : null;
            $days[$day] = [
                'works' => $d !== null,
                'start' => $d !== null ? (string) $d['start'] : '09:00',
                'end' => $d !== null ? (string) $d['end'] : '18:00',
                'break_start' => $break !== null ? (string) $break['start'] : ($d !== null ? null : '12:00'),
                'break_end' => $break !== null ? (string) $break['end'] : ($d !== null ? null : '13:00'),
            ];
        }
        $holidays = [];
        foreach (is_array($data['holidays'] ?? null) ? $data['holidays'] : [] as $date) {
            $holidays[] = ['date' => (string) $date, 'description' => ''];
        }
        $special = [];
        foreach (is_array($data['special_days'] ?? null) ? $data['special_days'] : [] as $date => $s) {
            $special[] = [
                'date' => (string) $date,
                'start' => (string) ($s['start'] ?? ''),
                'end' => (string) ($s['end'] ?? ''),
                'description' => mb_substr((string) ($s['description'] ?? ''), 0, 100),
            ];
        }

        $this->changing(function () use ($days, $holidays, $special): void {
            $this->repo->replaceAll($days, $holidays);
            $this->repo->replaceSpecialDays($special);
        });
        $this->audit->record($admin, 'import', 'calendar', null, null, ['work_days' => count(array_filter($days, static fn (array $d): bool => $d['works'])), 'holidays' => count($holidays), 'special_days' => count($special)]);
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
        $this->changing(fn () => $this->repo->saveWorkHours($days));
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

        $this->changing(fn () => $this->repo->addHoliday($date, $description));
        $this->audit->record($admin, 'create', 'holiday', null, null, ['date' => $date, 'description' => $description]);
    }

    public function removeHoliday(User $admin, string $date): void
    {
        $this->changing(fn () => $this->repo->removeHoliday($date));
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
