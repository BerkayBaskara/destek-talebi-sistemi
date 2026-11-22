<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\TicketActivity;
use App\Repository\TicketActivityRepositoryInterface;

/** ticket_activities.json üzerinde talep işlem geçmişi. */
final class JsonTicketActivityRepository implements TicketActivityRepositoryInterface
{
    private const TABLE = 'ticket_activities';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function add(TicketActivity $activity): TicketActivity
    {
        $data = $activity->toArray();
        unset($data['id']);
        $activity->id = $this->store->insert(self::TABLE, $data);

        return $activity;
    }

    public function findByTicket(int $ticketId): array
    {
        $list = [];
        foreach ($this->store->all(self::TABLE) as $row) {
            if (($row['ticket_id'] ?? null) === $ticketId) {
                $list[] = TicketActivity::fromArray($row);
            }
        }

        return $list;
    }
}
