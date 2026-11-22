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
        'sla.breach_wait_days' => '1',
        'sla.extra_percent' => '33',
        'sla.auto_close_days' => '3',
        'site.home_image' => '',
        'auth.max_attempts' => '5',
        'auth.lock_minutes' => '5',
        'auth.idle_minutes' => '30',
        'staff.inactivity_days' => '0',
        'notify.disabled' => '',
        'tickets.max_questions' => '2',
        'tickets.question_reply_hours' => '12',
        'calendar.recalculate_open' => '0',
        'score.base' => '100',
        'files.max_image_mb' => '5',
        'files.max_video_mb' => '50',
        'files.max_count' => '3',
        'auth.security_questions' => "İlk evcil hayvanınızın adı nedir?\nDoğduğunuz şehir neresidir?\nİlkokul öğretmeninizin soyadı nedir?\nEn sevdiğiniz yemek nedir?",
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
            'sla.breach_wait_days' => self::intIn($value, 0, 10) ? null : 'Bekleme süresi 0 ile 10 iş günü arasında olmalı.',
            'sla.extra_percent' => self::intIn($value, 1, 100) ? null : 'Ek SLA oranı 1 ile 100 arasında olmalı.',
            'sla.auto_close_days' => self::intIn($value, 1, 30) ? null : 'Otomatik tamamlama süresi 1 ile 30 iş günü arasında olmalı.',
            'auth.max_attempts' => self::intIn($value, 3, 20) ? null : 'Deneme sınırı 3 ile 20 arasında olmalı.',
            'auth.lock_minutes' => self::intIn($value, 1, 120) ? null : 'Engel süresi 1 ile 120 dakika arasında olmalı.',
            'auth.idle_minutes' => self::intIn($value, 5, 480) ? null : 'Oturum süresi 5 ile 480 dakika arasında olmalı.',
            'staff.inactivity_days' => self::intIn($value, 0, 365) ? null : 'Hareketsizlik süresi 0 ile 365 gün arasında olmalı (0 = kapalı).',
            'notify.disabled' => array_diff(array_filter(explode(',', $value)), array_keys(NotificationService::TYPES)) === [] ? null : 'Bilinmeyen bildirim türü.',
            'tickets.max_questions' => self::intIn($value, 0, 10) ? null : 'Soru sınırı 0 ile 10 arasında olmalı.',
            'tickets.question_reply_hours' => self::intIn($value, 1, 168) ? null : 'Soru yanıt süresi 1 ile 168 saat arasında olmalı.',
            'calendar.recalculate_open' => in_array($value, ['0', '1'], true) ? null : 'Geçersiz seçim.',
            'score.base' => self::intIn($value, 1, 1000) ? null : 'Taban puan 1 ile 1000 arasında olmalı.',
            'files.max_image_mb' => self::intIn($value, 1, 20) ? null : 'Görsel/PDF sınırı 1 ile 20 MB arasında olmalı.',
            'files.max_video_mb' => self::intIn($value, 1, 200) ? null : 'Video sınırı 1 ile 200 MB arasında olmalı.',
            'files.max_count' => self::intIn($value, 1, 10) ? null : 'Dosya sayısı 1 ile 10 arasında olmalı.',
            'auth.security_questions' => self::questionsError($value),
            default => null,
        };
    }

    /**
     * Satır başına bir değer tutan ayar (ör. güvenlik soruları), boş satırlar atılır.
     *
     * @return list<string>
     */
    public function lines(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $this->string($key)))), static fn (string $l): bool => $l !== ''));
    }

    private static function questionsError(string $value): ?string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $value))), static fn (string $l): bool => $l !== ''));
        if ($lines === [] || count($lines) > 10) {
            return 'En az bir güvenlik sorusu olmalı, en fazla 10 soru yazılabilir (her satıra bir soru).';
        }
        foreach ($lines as $line) {
            if (mb_strlen($line) < 5 || mb_strlen($line) > 150) {
                return 'Her soru 5-150 karakter olmalı.';
            }
        }

        return null;
    }

    private static function intIn(string $value, int $min, int $max): bool
    {
        return preg_match('/^\d+$/', $value) === 1 && (int) $value >= $min && (int) $value <= $max;
    }

    private function assertKnown(string $key): void
    {
        if (!array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidArgumentException('Bilinmeyen ayar: ' . $key);
        }
    }
}
