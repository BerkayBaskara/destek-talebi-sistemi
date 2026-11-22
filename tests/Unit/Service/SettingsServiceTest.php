<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Clock\FixedClock;
use App\Exception\ValidationException;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\Json\JsonAuditLogRepository;
use App\Repository\Json\JsonSettingsRepository;
use App\Service\AuditLogger;
use App\Service\SettingsService;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

/** Merkezi ayarlar: varsayılanlar, doğrulama ve denetim kaydı. */
final class SettingsServiceTest extends TestCase
{
    use TempStoreTrait;

    private SettingsService $settings;
    private JsonAuditLogRepository $audit;
    private User $admin;

    protected function setUp(): void
    {
        $store = $this->makeStore();
        $this->audit = new JsonAuditLogRepository($store);
        $this->settings = new SettingsService(
            new JsonSettingsRepository($store),
            new AuditLogger($this->audit, new FixedClock(new DateTimeImmutable('2026-11-24 10:00'))),
        );
        $this->admin = new User(1, 'Y000001', 'x', Role::Admin, 'Selin', 'Arslan', 'S?', 'x');
    }

    public function test_unknown_key_falls_back_to_default(): void
    {
        self::assertSame('Destek Talebi Sistemi', $this->settings->string('site.title'));
        self::assertSame(5, $this->settings->int('staff.default_capacity'));
    }

    public function test_update_persists_and_returns_typed_values(): void
    {
        $this->settings->update($this->admin, ['staff.default_capacity' => '7', 'site.title' => 'Yardım Masası']);

        self::assertSame(7, $this->settings->int('staff.default_capacity'));
        self::assertSame('Yardım Masası', $this->settings->string('site.title'));
    }

    public function test_invalid_value_writes_nothing(): void
    {
        try {
            $this->settings->update($this->admin, ['site.title' => '', 'staff.default_capacity' => '9']);
            self::fail('Doğrulama hatası bekleniyordu.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('site.title', $e->errors());
        }

        self::assertSame(5, $this->settings->int('staff.default_capacity'));
        self::assertSame([], $this->audit->latest(10));
    }

    public function test_capacity_must_be_whole_number_in_range(): void
    {
        foreach (['0', '101', 'abc', '2.5'] as $bad) {
            try {
                $this->settings->update($this->admin, ['staff.default_capacity' => $bad]);
                self::fail($bad . ' kabul edilmemeliydi.');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('staff.default_capacity', $e->errors());
            }
        }
    }

    public function test_update_records_old_and_new_value(): void
    {
        $this->settings->update($this->admin, ['site.title' => 'Yeni']);
        $this->settings->update($this->admin, ['site.title' => 'Yeni']); // değişmeyen değer kayıt düşmez

        $logs = $this->audit->latest(10);
        self::assertCount(1, $logs);
        self::assertSame('setting', $logs[0]->entity);
        self::assertSame(['site.title' => 'Destek Talebi Sistemi'], $logs[0]->oldValue);
        self::assertSame(['site.title' => 'Yeni'], $logs[0]->newValue);
    }

    public function test_unknown_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->settings->update($this->admin, ['olmayan.ayar' => 'x']);
    }
}
