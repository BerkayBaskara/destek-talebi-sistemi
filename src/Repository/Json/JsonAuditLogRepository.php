<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\AuditLog;
use App\Repository\AuditLogRepositoryInterface;

/** admin_audit_logs.json üzerinde denetim kaydı deposu. */
final class JsonAuditLogRepository implements AuditLogRepositoryInterface
{
    private const TABLE = 'admin_audit_logs';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function add(AuditLog $log): AuditLog
    {
        $data = $log->toArray();
        unset($data['id']);
        $log->id = $this->store->insert(self::TABLE, $data);

        return $log;
    }

    public function latest(int $limit): array
    {
        $rows = array_map(static fn (array $row): AuditLog => AuditLog::fromArray($row), $this->store->all(self::TABLE));
        usort($rows, static fn (AuditLog $a, AuditLog $b): int => [$b->createdAt, $b->id] <=> [$a->createdAt, $a->id]);

        return array_slice($rows, 0, max(0, $limit));
    }
}
