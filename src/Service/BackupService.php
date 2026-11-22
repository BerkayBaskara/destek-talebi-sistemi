<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use RuntimeException;
use ZipArchive;

/**
 * Veri yedeği: storage/data altındaki JSON tabloları tek zip dosyası olarak verir (kilit ve geçici dosyalar hariç).
 * Geri yükleme web'den yapılmaz (yanlış dosya tüm veriyi bozabilir); gerekirse dosyalar sunucuda elle geri konur.
 */
final class BackupService
{
    public function __construct(
        private readonly string $dataDir,
        private readonly AuditLogger $audit,
    ) {
    }

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
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('/^[a-z0-9_]+\.json$/', $name) !== 1) {
                $zip->close();
                throw $invalid('beklenmeyen dosya (' . $name . ').');
            }
            $content = (string) $zip->getFromIndex($i);
            if (!is_array(json_decode($content, true))) {
                $zip->close();
                throw $invalid($name . ' okunamadı.');
            }
            $tables[$name] = $content;
        }
        $zip->close();
        if (!isset($tables['users.json'])) {
            throw $invalid('kullanıcı tablosu (users.json) yok.');
        }

        // Önce mevcut durumun otomatik yedeği
        $backupDir = dirname($this->dataDir) . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }
        $auto = $backupDir . '/otomatik-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
        file_put_contents($auto, $this->zipBytes()[0]);

        foreach (glob($this->dataDir . '/*.json') ?: [] as $existing) {
            @unlink($existing);
        }
        foreach ($tables as $name => $content) {
            $target = $this->dataDir . '/' . $name;
            file_put_contents($target . '.tmp', $content);
            rename($target . '.tmp', $target);
        }
        $this->audit->record($admin, 'restore', 'backup', null, ['automatic_backup' => basename($auto)], ['files' => count($tables)]);

        return basename($auto);
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
