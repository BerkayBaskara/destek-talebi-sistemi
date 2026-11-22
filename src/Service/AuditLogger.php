<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Model\AuditLog;
use App\Model\User;
use App\Repository\AuditLogRepositoryInterface;

/** Yönetici işlemlerini (ayar, kategori, öncelik, SLA, takvim, personel) eski ve yeni değeriyle kaydeder. */
final class AuditLogger
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $repo,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    public function record(User $actor, string $action, string $entity, ?int $entityId, ?array $old, ?array $new): void
    {
        $this->repo->add(new AuditLog(null, (int) $actor->id, $action, $entity, $entityId, $old, $new, $this->clock->now()));
    }
}
