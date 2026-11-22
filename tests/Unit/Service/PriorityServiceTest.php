<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\Clock\FixedClock;
use App\Exception\ValidationException;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\Json\JsonAuditLogRepository;
use App\Repository\Json\JsonPriorityRepository;
use App\Service\AuditLogger;
use App\Service\PriorityService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

/** Yöneticinin yönettiği öncelik listesi. */
final class PriorityServiceTest extends TestCase
{
    use TempStoreTrait;

    private JsonPriorityRepository $repo;
    private JsonAuditLogRepository $audit;
    private PriorityService $service;
    private User $admin;

    protected function setUp(): void
    {
        $store = $this->makeStore();
        $this->repo = new JsonPriorityRepository($store);
        $this->audit = new JsonAuditLogRepository($store);
        $this->service = new PriorityService($this->repo, new AuditLogger($this->audit, new FixedClock(new DateTimeImmutable('2026-11-24 10:00'))));
        $this->admin = new User(1, 'Y000001', 'x', Role::Admin, 'Selin', 'Arslan', 'S?', 'x');
        foreach (PriorityService::defaults() as $priority) {
            $this->repo->save($priority);
        }
    }

    public function test_defaults_are_five_levels_with_stable_ids(): void
    {
        $names = array_map(static fn ($p): string => $p->name, $this->repo->findAll());
        self::assertSame(['Çok Düşük', 'Düşük', 'Normal', 'Yüksek', 'Çok Yüksek'], $names);
        self::assertSame('Normal', $this->repo->findById(2)?->name);
        self::assertSame(4, $this->repo->findById(4)?->defaultSlaHours);
        self::assertSame(32, $this->repo->findById(5)?->defaultSlaHours);
    }

    public function test_create_validates_and_records_audit(): void
    {
        $created = $this->service->create($this->admin, ['name' => 'Kritik', 'rank' => '6', 'default_sla_hours' => '2', 'active' => '1', 'keywords' => 'veri sızıntısı']);

        self::assertSame('Kritik', $this->repo->findById((int) $created->id)?->name);
        self::assertSame('create', $this->audit->latest(1)[0]->action);

        try {
            $this->service->create($this->admin, ['name' => 'X', 'rank' => '0', 'default_sla_hours' => '721']);
            self::fail('Doğrulama hatası bekleniyordu.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('name', $e->errors());
            self::assertArrayHasKey('rank', $e->errors());
            self::assertArrayHasKey('default_sla_hours', $e->errors());
        }
    }

    public function test_name_and_rank_are_unique_case_and_turkish_insensitive(): void
    {
        foreach (['NORMAL', 'ÇOK YÜKSEK'] as $name) {
            try {
                $this->service->create($this->admin, ['name' => $name, 'rank' => '9', 'default_sla_hours' => '8']);
                self::fail($name . ' kabul edilmemeliydi.');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('name', $e->errors());
            }
        }

        try {
            $this->service->create($this->admin, ['name' => 'Ara Seviye', 'rank' => '3', 'default_sla_hours' => '8']);
            self::fail('Aynı sıra numarası kabul edilmemeliydi.');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('rank', $e->errors());
        }
    }

    public function test_cannot_deactivate_last_active_priority(): void
    {
        foreach ([1, 2, 3, 4] as $id) {
            $p = $this->repo->findById($id);
            self::assertNotNull($p);
            $this->service->update($this->admin, $id, ['name' => $p->name, 'rank' => (string) $p->rank, 'default_sla_hours' => (string) $p->defaultSlaHours, 'active' => '0']);
        }

        try {
            $this->service->update($this->admin, 5, ['name' => 'Çok Düşük', 'rank' => '1', 'default_sla_hours' => '32', 'active' => '0']);
            self::fail('Son aktif öncelik pasif yapılamamalı.');
        } catch (ValidationException $e) {
            self::assertSame('En az bir aktif öncelik olmalı.', $e->errors()['active']);
        }
        self::assertTrue($this->repo->findById(5)?->active);
    }

    public function test_keywords_are_parsed_from_comma_text(): void
    {
        $p = $this->service->update($this->admin, 3, ['name' => 'Yüksek', 'rank' => '4', 'default_sla_hours' => '8', 'active' => '1', 'keywords' => 'acil, , çöktü ']);

        self::assertSame(['acil', 'çöktü'], $p->keywords);
    }
}
