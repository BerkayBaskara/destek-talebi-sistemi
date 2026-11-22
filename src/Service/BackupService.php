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
        $this->audit->record($admin, 'download', 'backup', null, null, ['files' => count($files)]);

        return $bytes;
    }
}
