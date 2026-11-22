<?php

declare(strict_types=1);

/*
 * SLA denetimini elle (ya da Windows Görev Zamanlayıcı ile) çalıştırır:
 *     php bin/sla-monitor.php
 * Uygulama zaten her istekte dakikada bir denetler; bu komut site açık değilken de kuralların
 * işlemesi içindir. Aynı işi iki kez yapmaz.
 */

use App\Core\Session\ArraySession;
use App\Kernel;

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Bu komut yalnız komut satırından çalıştırılabilir.';
    exit(1);
}

$kernel = new Kernel(dirname(__DIR__), null, new ArraySession());
$result = $kernel->slaMonitor()->run();

echo 'SLA denetimi tamamlandı. Aşım işlendi: ' . $result['breaches'] . ', eskalasyon: ' . $result['escalations'] . PHP_EOL;
