<?php

declare(strict_types=1);

/*
 * Eşzamanlılık testinin işçi süreci: verilen ana kadar bekler, sonra talebi "Üzerime al" ile almaya çalışır.
 * Kullanım: php take_worker.php <veri kökü> <talep id> <personel id> <başlama anı (microtime)>
 * Çıktı: "ok" ya da yakalanan istisnanın sınıf adı.
 */

use App\Core\Clock\FixedClock;
use App\Core\Session\ArraySession;
use App\Kernel;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

[, $root, $ticketId, $agentId, $startAt] = $argv;
$kernel = new Kernel($root, new FixedClock(new DateTimeImmutable('2026-11-09 10:00', new DateTimeZone('Europe/Istanbul'))), new ArraySession());
$agent = $kernel->userRepository()->findById((int) $agentId) ?? throw new RuntimeException('Personel yok');

while (microtime(true) < (float) $startAt) {
    usleep(200); // bütün süreçler aynı anda başlasın
}

try {
    $kernel->ticketService()->take((int) $ticketId, $agent);
    echo 'ok';
} catch (Throwable $e) {
    echo get_class($e);
}
