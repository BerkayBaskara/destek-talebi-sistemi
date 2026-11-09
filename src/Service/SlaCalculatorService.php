<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\CalendarRepositoryInterface;
use DateTimeImmutable;
use RuntimeException;

/**
 * ÇEKİRDEK PROBLEM: mesai saatine duyarlı SLA hesabı (FR-05).
 *
 * SLA süresi takvim saati değil iş saatidir: akşamlar, hafta sonları, öğle arası ve resmi
 * tatiller sayılmaz. Örnek: Cuma 17:30'da açılan 4 saatlik talep → Cuma 30 dk +
 * Pazartesi 09:00–12:00 (3 sa) + 13:00–13:30 (30 dk) = Pazartesi 13:30.
 */
final class SlaCalculatorService
{
    /** Takvimde hiç iş günü yoksa sonsuz döngüye girmemek için en fazla 3 yıl ileri bakılır. */
    private const MAX_DAYS = 1096;

    public function __construct(private readonly CalendarRepositoryInterface $calendar)
    {
    }

    /**
     * Başlangıç anına iş dakikası olarak süre ekler ve bitiş anını döner.
     * (Adı sınıf diyagramından: takvim JSON dosyasından okunur.)
     */
    public function calculateWithJson(DateTimeImmutable $start, int $durationMinutes): DateTimeImmutable
    {
        $config = $this->calendar->getConfig();
        $remaining = max(0, $durationMinutes);
        $day = $start->setTime(0, 0);
        $minute = self::minuteOfDay($start);

        for ($i = 0; $i < self::MAX_DAYS; $i++) {
            foreach ($config->intervalsFor($day) as [$intervalStart, $intervalEnd]) {
                if ($minute >= $intervalEnd) {
                    continue; // bu aralık çoktan geçmiş
                }
                $from = max($minute, $intervalStart); // molada/mesai öncesinde başladıysa aralığın başına kay
                if ($from + $remaining <= $intervalEnd) {
                    return self::at($day, $from + $remaining); // süre bu aralıkta bitiyor
                }
                $remaining -= $intervalEnd - $from; // aralığın tamamını harca, kalanı sonraki aralığa taşı
            }
            $day = $day->modify('+1 day');
            $minute = 0;
        }

        throw new RuntimeException('Takvimde iş günü bulunamadı; SLA hesaplanamıyor.');
    }

    /** İki an arasındaki iş dakikası (mesai dışı, mola ve tatil sayılmaz). Sıra tersse 0. */
    public function businessMinutesBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        if ($to <= $from) {
            return 0;
        }

        $config = $this->calendar->getConfig();
        $day = $from->setTime(0, 0);
        $lastDay = $to->setTime(0, 0);
        $total = 0;

        while ($day <= $lastDay) {
            $windowStart = $day == $from->setTime(0, 0) ? self::minuteOfDay($from) : 0;
            $windowEnd = $day == $lastDay ? self::minuteOfDay($to) : 24 * 60;

            foreach ($config->intervalsFor($day) as [$intervalStart, $intervalEnd]) {
                $overlap = min($intervalEnd, $windowEnd) - max($intervalStart, $windowStart);
                if ($overlap > 0) {
                    $total += $overlap;
                }
            }
            $day = $day->modify('+1 day');
        }

        return $total;
    }

    private static function minuteOfDay(DateTimeImmutable $moment): int
    {
        return (int) $moment->format('G') * 60 + (int) $moment->format('i');
    }

    private static function at(DateTimeImmutable $day, int $minuteOfDay): DateTimeImmutable
    {
        return $day->setTime(intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }
}
