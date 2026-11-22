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

    /** Ayar anahtarı → [hata alanı, dosya adı öneki, ekranda adı] */
    private const KINDS = [
        'site.logo_path' => ['logo', 'logo', 'Logo'],
        'site.home_image' => ['home_image', 'home', 'Görsel'],
    ];

    /**
     * @param array<string, mixed> $file $_FILES içindeki tek dosya kaydı
     * @return string logonun site içi adresi (/branding/...)
     * @throws ValidationException
     */
    public function storeLogo(User $admin, array $file): string
    {
        return $this->storeImage($admin, $file, 'site.logo_path');
    }

    /**
     * Logo ya da ana sayfa görseli yükler (FR-75, FR-76). Eski dosya yeni dosya kaydedildikten sonra silinir.
     *
     * @param array<string, mixed> $file
     * @param (callable(string, string): bool)|null $mover
     * @throws ValidationException
     */
    public function storeImage(User $admin, array $file, string $settingKey, ?callable $mover = null): string
    {
        [$field, $prefix, $label] = self::KINDS[$settingKey] ?? throw new \InvalidArgumentException('Bilinmeyen görsel: ' . $settingKey);
        $lower = mb_strtolower($label);
        $failed = $label . ' yüklenemedi. Lütfen tekrar deneyin.';
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException([$field => 'Bir ' . $lower . ' dosyası seçin.']);
        }
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || ($error === UPLOAD_ERR_OK && is_file($tmp) && (int) filesize($tmp) > self::MAX_BYTES)) {
            throw new ValidationException([$field => $label . ' en fazla 1 MB olabilir.']);
        }
        if ($error !== UPLOAD_ERR_OK || !is_file($tmp)) {
            throw new ValidationException([$field => $failed]);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extension = self::TYPES[$mime] ?? null;
        if ($extension === null || getimagesize($tmp) === false) {
            throw new ValidationException([$field => $label . ' yalnız PNG ya da JPG olabilir.']);
        }

        if (!is_dir($this->publicBrandingDir) && !mkdir($this->publicBrandingDir, 0775, true) && !is_dir($this->publicBrandingDir)) {
            throw new ValidationException([$field => $failed]);
        }
        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target = $this->publicBrandingDir . '/' . $name;
        if (!($mover ?? $this->mover)($tmp, $target)) {
            throw new ValidationException([$field => $failed]);
        }

        $oldPath = $this->settings->string($settingKey);
        $newPath = self::URL_PREFIX . $name;
        try {
            $this->settings->update($admin, [$settingKey => $newPath]);
        } catch (\Throwable $e) {
            @unlink($target); // ayar yazılamadıysa yeni dosya ortada kalmasın; eski görsel geçerli kalır
            throw $e;
        }
        $this->audit->record($admin, 'upload', $prefix === 'logo' ? 'logo' : 'home_image', null, ['path' => $oldPath], ['path' => $newPath, 'type' => $mime]);
        $this->deleteFile($oldPath);

        return $newPath;
    }

    /** Ana sayfa görselini kaldırır (FR-76). */
    public function removeImage(User $admin, string $settingKey): void
    {
        $old = $this->settings->string($settingKey);
        if ($old === '') {
            return;
        }
        $this->settings->update($admin, [$settingKey => '']);
        $this->deleteFile($old);
    }

    private function deleteFile(string $path): void
    {
        if (str_starts_with($path, self::URL_PREFIX)) {
            $file = $this->publicBrandingDir . '/' . basename($path);
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
