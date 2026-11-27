<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\AuditLog;

/** Denetim kaydı yalnız eklenir ve okunur; güncelleme veya silme yoktur. */
interface AuditLogRepositoryInterface
{
    public function add(AuditLog $log): AuditLog;

    /** @return list<AuditLog> yeniden eskiye */
    public function latest(int $limit): array;
}
