<?php

declare(strict_types=1);

namespace App\Repository;

/** Sistemin kendi çalışma bilgileri (ör. SLA denetiminin son çalıştığı an). Yönetici ayarı değildir, denetim kaydına girmez. */
interface RuntimeStateRepositoryInterface
{
    /**
     * Anahtardaki zaman damgası $now - $interval'dan eskiyse (ya da yoksa) $now yazar ve true döner; değilse false.
     * Kilit içinde yapılır: aynı anda gelen iki istekten yalnız biri hakkı alır.
     */
    public function claim(string $key, int $now, int $interval): bool;
}
