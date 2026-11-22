<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Yönetilebilir çalışma takvimi (ER: work_hours, holidays). SLA hesabı CalendarRepositoryInterface
 * üzerinden okur; yönetici ekranı buradaki yazma metotlarını kullanır.
 *
 * Gün kaydı: works (çalışılır mı), start/end ("09:00"), break_start/break_end (öğle arası, boş olabilir).
 */
interface WorkCalendarRepositoryInterface extends CalendarRepositoryInterface
{
    /** @return array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}> 1 = Pazartesi … 7 = Pazar */
    public function workHours(): array;

    /** @param array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}> $days */
    public function saveWorkHours(array $days): void;

    /** @return list<array{date: string, description: string}> tarih sırasıyla */
    public function holidays(): array;

    public function addHoliday(string $date, string $description): void;

    public function removeHoliday(string $date): void;

    /** @return list<array{date: string, start: string, end: string, description: string}> tarih sırasıyla (FR-13.2) */
    public function specialDays(): array;

    public function addSpecialDay(string $date, string $start, string $end, string $description): void;

    public function removeSpecialDay(string $date): void;

    /** @param list<array{date: string, start: string, end: string, description: string}> $days */
    public function replaceSpecialDays(array $days): void;

    /**
     * Takvimi baştan yazar (örnek veri ve eski calendar.json'dan aktarım için).
     *
     * @param array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}> $days
     * @param list<array{date: string, description: string}> $holidays
     */
    public function replaceAll(array $days, array $holidays): void;
}
