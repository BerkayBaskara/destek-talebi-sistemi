<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;
use App\Model\User;

/**
 * Site logosunun yüklenmesi. Tür dosya adına göre değil içeriğe göre (finfo) belirlenir; yalnız PNG ve JPG,
 * en fazla 1 MB. Dosya rastgele adla public/branding klasörüne yazılır. Eski logo ancak yeni logo
 * kaydedildikten SONRA silinir; hata olursa eski logo yerinde kalır.
 */
final class BrandingService
{
    public const MAX_BYTES = 1_048_576;
    private const TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
    private const URL_PREFIX = '/branding/';

    /** @var callable(string, string): bool */
    private $mover;

    /** @param (callable(string, string): bool)|null $mover dosyayı taşıyan fonksiyon (varsayılan move_uploaded_file) */
    public function __construct(
        private readonly string $publicBrandingDir,
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
        ?callable $mover = null,
    ) {
        $this->mover = $mover ?? static fn (string $from, string $to): bool => move_uploaded_file($from, $to);
    }

    /**
     * @param array<string, mixed> $file $_FILES içindeki tek dosya kaydı
     * @return string logonun site içi adresi (/branding/...)
     * @throws ValidationException
     */
    public function storeLogo(User $admin, array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['logo' => 'Bir logo dosyası seçin.']);
        }
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || ($error === UPLOAD_ERR_OK && is_file($tmp) && (int) filesize($tmp) > self::MAX_BYTES)) {
            throw new ValidationException(['logo' => 'Logo en fazla 1 MB olabilir.']);
        }
        if ($error !== UPLOAD_ERR_OK || !is_file($tmp)) {
            throw new ValidationException(['logo' => 'Logo yüklenemedi. Lütfen tekrar deneyin.']);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extension = self::TYPES[$mime] ?? null;
        if ($extension === null || getimagesize($tmp) === false) {
            throw new ValidationException(['logo' => 'Logo yalnız PNG ya da JPG olabilir.']);
        }

        if (!is_dir($this->publicBrandingDir) && !mkdir($this->publicBrandingDir, 0775, true) && !is_dir($this->publicBrandingDir)) {
            throw new ValidationException(['logo' => 'Logo yüklenemedi. Lütfen tekrar deneyin.']);
        }
        $name = 'logo-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = $this->publicBrandingDir . '/' . $name;
        if (!($this->mover)($tmp, $target)) {
            throw new ValidationException(['logo' => 'Logo yüklenemedi. Lütfen tekrar deneyin.']);
        }

        $oldPath = $this->settings->string('site.logo_path');
        $newPath = self::URL_PREFIX . $name;
        try {
            $this->settings->update($admin, ['site.logo_path' => $newPath]);
        } catch (\Throwable $e) {
            @unlink($target); // ayar yazılamadıysa yeni dosya ortada kalmasın; eski logo geçerli kalır
            throw $e;
        }
        $this->audit->record($admin, 'upload', 'logo', null, ['path' => $oldPath], ['path' => $newPath, 'type' => $mime]);

        if (str_starts_with($oldPath, self::URL_PREFIX)) {
            $oldFile = $this->publicBrandingDir . '/' . basename($oldPath);
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        return $newPath;
    }
}
