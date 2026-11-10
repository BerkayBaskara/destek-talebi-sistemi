<?php

declare(strict_types=1);

/*
 * Örnek veriyi sıfırdan üretir: php bin/seed.php
 * DİKKAT: storage/data içindeki tüm JSON verisini siler.
 */

use App\Core\Clock\FixedClock;
use App\Core\Session\ArraySession;
use App\Kernel;
use App\Seed\DemoSeeder;

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Europe/Istanbul');

$root = dirname(__DIR__);
foreach (glob($root . '/storage/data/*.{json,lock,tmp}', GLOB_BRACE) ?: [] as $file) {
    unlink($file);
}

$clock = new FixedClock(new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul')));
$seeder = new DemoSeeder(new Kernel($root, $clock, new ArraySession()), $clock);
$accounts = $seeder->run();

echo "Örnek veri oluşturuldu. Tüm hesapların şifresi: " . DemoSeeder::PASSWORD . PHP_EOL . PHP_EOL;
echo mb_str_pad('Rol', 11) . mb_str_pad('Numara', 11) . 'Ad Soyad' . PHP_EOL;
foreach ($accounts as $account) {
    echo mb_str_pad($account['rol'], 11) . mb_str_pad($account['numara'], 11) . $account['ad'] . PHP_EOL;
}
