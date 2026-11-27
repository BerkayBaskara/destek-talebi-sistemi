<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\TicketLog;
use App\Repository\TicketLogRepositoryInterface;

/** ticket_logs.json üzerinde durum geçmişi deposu. */
final class JsonTicketLogRepository implements TicketLogRepositoryInterface
{
    private const TABLE = 'ticket_logs';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function save(TicketLog $log): TicketLog
    {
        $data = $log->toArray();
        unset($data['id']);
        $log->id = $this->store->insert(self::TABLE, $data);

        return $log;
    }

    public function findByTicket(int $ticketId): array
    {
        $logs = [];
        foreach ($this->store->all(self::TABLE) as $row) {
            if (($row['ticket_id'] ?? null) === $ticketId) {
                $logs[] = TicketLog::fromArray($row);
            }
        }

        return $logs;
    }
}
