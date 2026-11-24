<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use RuntimeException;
use ZipArchive;

/**
 * Veri yedeği (FR-87, FR-88): storage/data altındaki JSON tabloları tek zip dosyası olarak verir (kilit ve geçici dosyalar
 * hariç) ve yöneticinin yüklediği yedekten geri yükler. Geri yüklemeden önce yedek denetlenir ve mevcut veri otomatik yedeklenir.
 */
final class BackupService
{
    public function __construct(
        private readonly string $dataDir,
        private readonly AuditLogger $audit,
        private readonly ?\App\Core\Storage\JsonStore $store = null,
    ) {
    }

    /** Zip'in açılmış hâlinin üst sınırı (zip bombasına karşı). */
    public const MAX_UNCOMPRESSED_BYTES = 50 * 1024 * 1024;

    /** Zip içeriğini (bayt) döner. */
    public function createZip(User $admin): string
    {
        [$bytes, $count] = $this->zipBytes();
        $this->audit->record($admin, 'download', 'backup', null, null, ['files' => $count]);

        return $bytes;
    }

    /**
     * FR-88: yedekten geri yükleme. Zip'teki her dosya yalnız "tablo.json" adında ve geçerli JSON olmalı, users.json
     * bulunmalı (aksi hâlde hiçbir şey değişmez). Yüklemeden önce mevcut veri storage/backups altına otomatik yedeklenir.
     *
     * @param array<string, mixed> $file
     * @throws \App\Exception\ValidationException
     */
    public function restore(User $admin, array $file): string
    {
        $invalid = static fn (string $why): \App\Exception\ValidationException => new \App\Exception\ValidationException(['yedek' => 'Yedek dosyası geçersiz: ' . $why]);
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_file($tmp)) {
            throw new \App\Exception\ValidationException(['yedek' => 'Bir yedek dosyası seçin.']);
        }
        if ((int) filesize($tmp) > 20 * 1024 * 1024) {
            throw $invalid('en fazla 20 MB olabilir.');
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            throw $invalid('zip dosyası açılamadı.');
        }
        $tables = [];
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('/^[a-z_]+\.json$/', $name) !== 1) {
                $zip->close();
                throw $invalid('beklenmeyen dosya (' . $name . ').');
            }
            $size = (int) ($zip->statIndex($i)['size'] ?? 0);
            $total += $size;
            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                $zip->close();
                throw $invalid('açılmış hâli en fazla 50 MB olabilir.');
            }
            // Bildirilen boyuttan fazlası okunmaz (sahte başlıklı zip bombası da sınırı aşamaz).
            $content = $size > 0 ? (string) $zip->getFromIndex($i, $size) : '';
            if (!self::isTable($name, json_decode($content, true))) {
                $zip->close();
                throw $invalid($name . ' okunamadı ya da satırları kimliksiz.');
            }
            $tables[$name] = $content;
        }
        $zip->close();
        if (!isset($tables['users.json'])) {
            throw $invalid('kullanıcı tablosu (users.json) yok.');
        }
        if (!self::hasActiveAdmin((array) json_decode($tables['users.json'], true))) {
            throw $invalid('aktif yönetici hesabı yok; yüklenseydi kimse yönetim ekranına giremezdi.');
        }

        // Önce mevcut durumun otomatik yedeği
        $backupDir = dirname($this->dataDir) . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }
        $auto = $backupDir . '/otomatik-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
        if (file_put_contents($auto, $this->zipBytes()[0]) === false) {
            throw new RuntimeException('Otomatik yedek yazılamadı; geri yükleme yapılmadı.');
        }

        // Bütün tablolar kilitliyken değiştirilir: o sırada süren bir işlem eski veriyi geri yazamaz.
        $names = array_unique(array_merge(
            array_map(static fn (string $p): string => basename($p, '.json'), glob($this->dataDir . '/*.json') ?: []),
            array_map(static fn (string $n): string => basename($n, '.json'), array_keys($tables)),
        ));
        $names = array_values(array_filter($names, static fn (string $n): bool => preg_match('/^[a-z_]+$/', $n) === 1));
        $replace = function () use ($tables): void {
            foreach ($tables as $name => $content) {
                $target = $this->dataDir . '/' . $name;
                if (file_put_contents($target . '.tmp', $content) === false || !rename($target . '.tmp', $target)) {
                    throw new RuntimeException('Yedek yazılamadı: ' . $name);
                }
            }
            foreach (glob($this->dataDir . '/*.json') ?: [] as $existing) {
                if (!isset($tables[basename($existing)])) {
                    @unlink($existing); // yedekte olmayan tablo boş başlar
                }
            }
        };
        if ($this->store !== null) {
            $this->store->transaction($names, static function () use ($replace): void {
                $replace();
            });
        } else {
            $replace();
        }
        $this->audit->record($admin, 'restore', 'backup', null, ['automatic_backup' => basename($auto)], ['files' => count($tables)]);

        return basename($auto);
    }

    /** Tablo: satır listesi, her satır kimlikli bir nesne (iç sayaç tablosu _sequences kimliksiz tek satırdır). */
    private static function isTable(string $name, mixed $data): bool
    {
        if (!is_array($data) || !array_is_list($data)) {
            return false;
        }
        foreach ($data as $row) {
            if (!is_array($row) || ($name !== '_sequences.json' && !is_int($row['id'] ?? null))) {
                return false;
            }
        }

        return true;
    }

    /** @param array<mixed> $users */
    private static function hasActiveAdmin(array $users): bool
    {
        foreach ($users as $row) {
            if (is_array($row) && ($row['role'] ?? null) === 'admin' && ($row['active'] ?? true) === true) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0: string, 1: int} zip baytları ve dosya sayısı */
    private function zipBytes(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'yedek');
        if ($path === false) {
            throw new RuntimeException('Geçici dosya oluşturulamadı.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Yedek dosyası açılamadı.');
        }
        $files = glob($this->dataDir . '/*.json') ?: [];
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return [$bytes, count($files)];
    }
}
