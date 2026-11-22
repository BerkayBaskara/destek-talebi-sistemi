<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;
use App\Model\User;
use App\Repository\SettingsRepositoryInterface;
use InvalidArgumentException;

/**
 * Yöneticinin değiştirebildiği genel ayarlar. Anahtar listesi ve varsayılanlar tek yerde (DEFAULTS);
 * depoda olmayan anahtar varsayılanını döner. Her değişiklik eski ve yeni değeriyle denetim kaydına yazılır.
 */
final class SettingsService
{
    public const DEFAULTS = [
        'site.title' => 'Destek Talebi Sistemi',
        'site.logo_path' => '',
        'site.home_heading' => 'Destek talebinizi kolayca oluşturun',
        'site.home_text' => 'Sorununuzu iletin, talebiniz mesai saatlerine göre hesaplanan süre içinde uzman personelimize ulaşsın.',
        'staff.default_capacity' => '5',
    ];

    public function __construct(
        private readonly SettingsRepositoryInterface $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    public function string(string $key): string
    {
        $this->assertKnown($key);

        return $this->repo->get($key) ?? self::DEFAULTS[$key];
    }

    public function int(string $key): int
    {
        return (int) $this->string($key);
    }

    /**
     * Değerleri doğrular ve kaydeder. Bir değer bile geçersizse hiçbiri yazılmaz.
     *
     * @param array<string, string> $values
     * @throws ValidationException
     */
    public function update(User $admin, array $values): void
    {
        $errors = [];
        foreach ($values as $key => $value) {
            $this->assertKnown($key);
            $error = $this->validate($key, $value);
            if ($error !== null) {
                $errors[$key] = $error;
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $old = [];
        $new = [];
        foreach ($values as $key => $value) {
            $current = $this->string($key);
            if ($current === $value) {
                continue;
            }
            $this->repo->set($key, $value);
            $old[$key] = $current;
            $new[$key] = $value;
        }

        if ($new !== []) {
            $this->audit->record($admin, 'update', 'setting', null, $old, $new);
        }
    }

    private function validate(string $key, string $value): ?string
    {
        $length = mb_strlen(trim($value));

        return match ($key) {
            'site.title' => $length >= 1 && $length <= 80 ? null : 'Site başlığı 1-80 karakter olmalı.',
            'site.home_heading' => $length >= 1 && $length <= 120 ? null : 'Ana sayfa başlığı 1-120 karakter olmalı.',
            'site.home_text' => $length <= 2000 ? null : 'Ana sayfa metni en fazla 2000 karakter olabilir.',
            'staff.default_capacity' => preg_match('/^\d+$/', $value) === 1 && (int) $value >= 1 && (int) $value <= 100
                ? null : 'Kapasite 1 ile 100 arasında bir tam sayı olmalı.',
            default => null,
        };
    }

    private function assertKnown(string $key): void
    {
        if (!array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidArgumentException('Bilinmeyen ayar: ' . $key);
        }
    }
}
