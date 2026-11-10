<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * İşletmenin çalışma takvimi: hangi gün hangi saatlerde çalışıldığı ve resmi tatiller.
 *
 * Çalışma saatleri "gece yarısından itibaren dakika" olarak tutulur ve öğle arası çıkarılmış
 * aralıklara bölünür. Ör. 09:00–18:00, mola 12:00–13:00 → [[540, 720], [780, 1080]].
 */
final class CalendarConfig
{
    /**
     * @param array<int, list<array{0: int, 1: int}>> $workDays ISO gün numarası (1=Pzt … 7=Paz) => aralıklar
     * @param array<string, true> $holidays 'Y-m-d' anahtarlı küme (isset ile sabit sürede bakılır)
     */
    public function __construct(
        public readonly array $workDays,
        public readonly array $holidays,
        public readonly string $timezone = 'Europe/Istanbul',
    ) {
    }

    /** Kod içine gömülü varsayılan: Pzt–Cum 09:00–18:00, öğle arası 12:00–13:00, tatil yok (NFR-07). */
    public static function default(): self
    {
        $day = [[540, 720], [780, 1080]];

        return new self([1 => $day, 2 => $day, 3 => $day, 4 => $day, 5 => $day], []);
    }

    /**
     * calendar.json içeriğinden oluşturur; şema hatasında InvalidArgumentException atar.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $workDaysData = $data['work_days'] ?? null;
        if (!is_array($workDaysData)) {
            throw new InvalidArgumentException('"work_days" bir nesne olmalı.');
        }

        $workDays = [];
        foreach ($workDaysData as $dayKey => $dayData) {
            $day = (int) $dayKey;
            if ($day < 1 || $day > 7 || !is_array($dayData)) {
                throw new InvalidArgumentException('Geçersiz gün: ' . $dayKey);
            }
            $start = self::minutes($dayData['start'] ?? null);
            $end = self::minutes($dayData['end'] ?? null);
            if ($start >= $end) {
                throw new InvalidArgumentException('Mesai başlangıcı bitişten önce olmalı (gün ' . $day . ').');
            }

            $breaks = [];
            foreach (is_array($dayData['breaks'] ?? null) ? $dayData['breaks'] : [] as $break) {
                if (!is_array($break)) {
                    throw new InvalidArgumentException('Geçersiz mola tanımı (gün ' . $day . ').');
                }
                $bStart = self::minutes($break['start'] ?? null);
                $bEnd = self::minutes($break['end'] ?? null);
                if ($bStart >= $bEnd || $bStart < $start || $bEnd > $end) {
                    throw new InvalidArgumentException('Mola mesai saatleri içinde olmalı (gün ' . $day . ').');
                }
                $breaks[] = [$bStart, $bEnd];
            }

            $workDays[$day] = self::subtractBreaks($start, $end, $breaks);
        }
        ksort($workDays);
        if (array_filter($workDays) === []) {
            // Hiç çalışma saati yoksa SLA hiçbir zaman bitemez; varsayılan mesaiye dönülsün.
            throw new InvalidArgumentException('Takvimde en az bir çalışma günü olmalı.');
        }

        $holidays = [];
        foreach (is_array($data['holidays'] ?? null) ? $data['holidays'] : [] as $holiday) {
            if (!is_string($holiday) || DateTimeImmutable::createFromFormat('!Y-m-d', $holiday) === false) {
                throw new InvalidArgumentException('Geçersiz tatil tarihi: ' . (is_string($holiday) ? $holiday : '?'));
            }
            $holidays[$holiday] = true;
        }

        $timezone = is_string($data['timezone'] ?? null) ? $data['timezone'] : 'Europe/Istanbul';

        return new self($workDays, $holidays, $timezone);
    }

    /**
     * Verilen günün çalışma aralıkları; tatil veya çalışılmayan günse boş liste.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function intervalsFor(DateTimeImmutable $day): array
    {
        if (isset($this->holidays[$day->format('Y-m-d')])) {
            return [];
        }

        return $this->workDays[(int) $day->format('N')] ?? [];
    }

    private static function minutes(mixed $time): int
    {
        if (!is_string($time) || preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m) !== 1) {
            throw new InvalidArgumentException('Saat SS:DD biçiminde olmalı, ör. 09:00.');
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    /**
     * @param list<array{0: int, 1: int}> $breaks
     * @return list<array{0: int, 1: int}>
     */
    private static function subtractBreaks(int $start, int $end, array $breaks): array
    {
        usort($breaks, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $intervals = [];
        $cursor = $start;
        foreach ($breaks as [$bStart, $bEnd]) {
            if ($bStart > $cursor) {
                $intervals[] = [$cursor, $bStart];
            }
            $cursor = max($cursor, $bEnd);
        }
        if ($cursor < $end) {
            $intervals[] = [$cursor, $end];
        }

        return $intervals;
    }
}
