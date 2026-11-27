<?php

declare(strict_types=1);

/*
 * Tek giriş noktası: tarayıcıdan gelen her istek buraya düşer.
 * Yalnız public/ klasörü dışarıya açıktır; kod, veri ve ekler bu klasörün dışındadır.
 */

use App\Core\Http\Request;
use App\Kernel;

// PHP'nin yerleşik sunucusunda (php -S) CSS/JS gibi var olan dosyaları doğrudan ver.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Europe/Istanbul');

$kernel = new Kernel(dirname(__DIR__));
$kernel->handle(Request::fromGlobals())->send();
