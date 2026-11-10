<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Repository\TicketRepositoryInterface;

/** tickets.json üzerinde talep deposu. */
final class JsonTicketRepository implements TicketRepositoryInterface
{
    private const TABLE = 'tickets';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function save(Ticket $ticket): Ticket
    {
        $data = $ticket->toArray();
        unset($data['id']);

        if ($ticket->id === null) {
            $ticket->id = $this->store->insert(self::TABLE, $data);
        } else {
            $this->store->update(self::TABLE, $ticket->id, $data);
        }

        return $ticket;
    }

    public function findById(int $id): ?Ticket
    {
        $row = $this->store->find(self::TABLE, $id);

        return $row === null ? null : Ticket::fromArray($row);
    }

    public function getPoolTickets(): array
    {
        $pool = array_values(array_filter(
            $this->findAll(),
            static fn (Ticket $t): bool => $t->status === TicketStatus::Pending && $t->agentId === null,
        ));
        usort($pool, static fn (Ticket $a, Ticket $b): int => [$a->createdAt, $a->id] <=> [$b->createdAt, $b->id]);

        return $pool;
    }

    public function findByCreator(int $creatorId): array
    {
        return array_values(array_filter($this->findAll(), static fn (Ticket $t): bool => $t->creatorId === $creatorId));
    }

    public function findByAgent(int $agentId): array
    {
        return array_values(array_filter($this->findAll(), static fn (Ticket $t): bool => $t->agentId === $agentId));
    }

    public function findAll(): array
    {
        return array_map(static fn (array $row): Ticket => Ticket::fromArray($row), $this->store->all(self::TABLE));
    }

    public function countActiveByAgent(): array
    {
        $counts = [];
        foreach ($this->findAll() as $ticket) {
            if ($ticket->agentId !== null && in_array($ticket->status, [TicketStatus::Taken, TicketStatus::InReview], true)) {
                $counts[$ticket->agentId] = ($counts[$ticket->agentId] ?? 0) + 1;
            }
        }
        ksort($counts);

        return $counts;
    }

    public function assignIfUnassigned(int $ticketId, int $agentId): bool
    {
        return (bool) $this->store->transaction([self::TABLE], function (JsonStore $store) use ($ticketId, $agentId): bool {
            $row = $store->find(self::TABLE, $ticketId);
            if ($row === null || ($row['agent_id'] ?? null) !== null || ($row['status'] ?? null) !== TicketStatus::Pending->value) {
                return false; // başka biri önce aldı, iptal edildi ya da talep yok
            }
            $store->update(self::TABLE, $ticketId, ['agent_id' => $agentId, 'status' => TicketStatus::Taken->value]);

            return true;
        });
    }
}
