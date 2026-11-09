<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\CalendarConfig;

/** Çalışma takvimine erişim (sınıf diyagramındaki CalendarRepositoryInterface). */
interface CalendarRepositoryInterface
{
    public function getConfig(): CalendarConfig;

    /** @return array<int, list<array{0: int, 1: int}>> ISO gün => mola çıkarılmış aralıklar (dakika) */
    public function getBusinessHours(): array;

    /** @return list<string> 'Y-m-d' biçiminde tatiller */
    public function getHolidays(): array;
}
