<?php

declare(strict_types=1);

/*
 * Yönetici hesabı oluşturur (yalnız sunucuda, komut satırından):
 *     php bin/create-admin.php <ad> <soyad> <şifre>
 * Web'den çalıştırılamaz; herkese açık yönetici kaydı yoktur.
 */

use App\Core\Session\ArraySession;
use App\Exception\ValidationException;
use App\Kernel;
use App\Model\Enum\Role;

/** Betik yalnız komut satırından (PHP_SAPI = "cli") çalışır. */
function canCreateAdminFrom(string $sapi): bool
{
    return $sapi === 'cli';
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return; // başka bir dosyadan (ör. test) yüklendi; yalnız fonksiyon tanımlanır
}

if (!canCreateAdminFrom(PHP_SAPI)) {
    http_response_code(403);
    echo 'Bu komut yalnız sunucuda komut satırından çalıştırılabilir.';
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';
date_default_timezone_set('Europe/Istanbul');

if ($argc !== 4) {
    fwrite(STDERR, "Kullanım: php bin/create-admin.php <ad> <soyad> <şifre>\n");
    exit(1);
}

try {
    $kernel = new Kernel(dirname(__DIR__), null, new ArraySession());
    $admin = $kernel->authService()->createStaff(Role::Admin, $argv[1], $argv[2], $argv[3]);
    echo 'Yönetici hesabı oluşturuldu. Giriş numarası: ' . $admin->customerNo . PHP_EOL;
} catch (ValidationException $e) {
    fwrite(STDERR, implode(PHP_EOL, $e->errors()) . PHP_EOL);
    exit(1);
}
