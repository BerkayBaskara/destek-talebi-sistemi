<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Clock\FixedClock;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\AuditLogRepositoryInterface;
use App\Repository\Json\JsonAuditLogRepository;
use App\Service\AuditLogger;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

/** Yönetici işlemlerinin değiştirilemez denetim kaydı. */
final class AuditLoggerTest extends TestCase
{
    use TempStoreTrait;

    private JsonAuditLogRepository $repo;
    private FixedClock $clock;
    private AuditLogger $logger;
    private User $admin;

    protected function setUp(): void
    {
        $this->repo = new JsonAuditLogRepository($this->makeStore());
        $this->clock = new FixedClock(new DateTimeImmutable('2026-11-24 10:00', new DateTimeZone('Europe/Istanbul')));
        $this->logger = new AuditLogger($this->repo, $this->clock);
        $this->admin = new User(1, 'Y000001', 'x', Role::Admin, 'Selin', 'Arslan', 'S?', 'x');
    }

    public function test_record_stores_actor_time_and_values(): void
    {
        $this->logger->record($this->admin, 'update', 'setting', null, ['site.title' => 'Eski'], ['site.title' => 'Yeni']);

        $logs = $this->repo->latest(10);
        self::assertCount(1, $logs);
        self::assertSame(1, $logs[0]->userId);
        self::assertSame('update', $logs[0]->action);
        self::assertSame('setting', $logs[0]->entity);
        self::assertNull($logs[0]->entityId);
        self::assertSame(['site.title' => 'Eski'], $logs[0]->oldValue);
        self::assertSame(['site.title' => 'Yeni'], $logs[0]->newValue);
        self::assertSame('2026-11-24 10:00', $logs[0]->createdAt->format('Y-m-d H:i'));
    }

    public function test_latest_returns_newest_first_and_limits(): void
    {
        foreach (['a', 'b', 'c'] as $i => $name) {
            $this->clock->advance('+' . ($i + 1) . ' minutes');
            $this->logger->record($this->admin, 'create', 'category', $i + 1, null, ['name' => $name]);
        }

        $logs = $this->repo->latest(2);
        self::assertCount(2, $logs);
        self::assertSame(['name' => 'c'], $logs[0]->newValue);
        self::assertSame(['name' => 'b'], $logs[1]->newValue);
    }

    public function test_repository_has_no_update_or_delete(): void
    {
        foreach ([AuditLogRepositoryInterface::class, JsonAuditLogRepository::class] as $class) {
            self::assertFalse(method_exists($class, 'update'), $class . ' güncelleme metodu içermemeli.');
            self::assertFalse(method_exists($class, 'delete'), $class . ' silme metodu içermemeli.');
            self::assertFalse(method_exists($class, 'save'), $class . ' save metodu içermemeli.');
        }
    }
}
