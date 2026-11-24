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

    public function saveIfStatus(Ticket $ticket, TicketStatus $expectedOld): bool
    {
        $id = (int) $ticket->id;

        return (bool) $this->store->transaction([self::TABLE], function (JsonStore $store) use ($ticket, $id, $expectedOld): bool {
            $row = $store->find(self::TABLE, $id);
            if ($row === null || ($row['status'] ?? null) !== $expectedOld->value || ($row['agent_id'] ?? null) !== $ticket->agentId) {
                return false; // bu arada başka bir istek talebi değiştirdi
            }
            $data = $ticket->toArray();
            unset($data['id']);
            $store->update(self::TABLE, $id, $data);

            return true;
        });
    }

    public function saveIfUnchanged(Ticket $ticket, TicketStatus $expectedStatus, ?int $expectedAgentId): bool
    {
        $id = (int) $ticket->id;

        return (bool) $this->store->transaction([self::TABLE], function (JsonStore $store) use ($ticket, $id, $expectedStatus, $expectedAgentId): bool {
            $row = $store->find(self::TABLE, $id);
            if ($row === null || ($row['status'] ?? null) !== $expectedStatus->value || ($row['agent_id'] ?? null) !== $expectedAgentId) {
                return false;
            }
            $data = $ticket->toArray();
            unset($data['id']);
            $store->update(self::TABLE, $id, $data);

            return true;
        });
    }

    public function updateIfUnchanged(Ticket $ticket, TicketStatus $expectedStatus, ?int $expectedAgentId, array $fields): bool
    {
        $id = (int) $ticket->id;
        $data = array_intersect_key($ticket->toArray(), array_flip($fields));

        return (bool) $this->store->transaction([self::TABLE], function (JsonStore $store) use ($data, $id, $expectedStatus, $expectedAgentId): bool {
            $row = $store->find(self::TABLE, $id);
            if ($row === null || ($row['status'] ?? null) !== $expectedStatus->value || ($row['agent_id'] ?? null) !== $expectedAgentId) {
                return false;
            }
            $store->update(self::TABLE, $id, $data);

            return true;
        });
    }

    public function findByCode(string $code): ?Ticket
    {
        foreach ($this->store->all(self::TABLE) as $row) {
            if (($row['code'] ?? null) === $code) {
                return Ticket::fromArray($row);
            }
        }

        return null;
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

    public function assignIfUnassigned(int $ticketId, int $agentId, int $capacity = PHP_INT_MAX): bool
    {
        return (bool) $this->store->transaction([self::TABLE], function (JsonStore $store) use ($ticketId, $agentId, $capacity): bool {
            $row = $store->find(self::TABLE, $ticketId);
            if ($row === null || ($row['agent_id'] ?? null) !== null || ($row['status'] ?? null) !== TicketStatus::Pending->value) {
                return false; // başka biri önce aldı, iptal edildi ya da talep yok
            }
            if (($row['hold_until'] ?? null) !== null || ($row['awaiting_admin'] ?? false) === true) {
                return false; // müşteri kararı ya da yönetici müdahalesi bekleniyor
            }
            $active = 0;
            foreach ($store->all(self::TABLE) as $other) {
                if (($other['agent_id'] ?? null) === $agentId
                    && in_array($other['status'] ?? null, [TicketStatus::Taken->value, TicketStatus::InReview->value], true)) {
                    $active++;
                }
            }
            if ($active >= $capacity) {
                return false; // personelin kapasitesi dolu
            }
            $store->update(self::TABLE, $ticketId, ['agent_id' => $agentId, 'status' => TicketStatus::Taken->value]);

            return true;
        });
    }
}
