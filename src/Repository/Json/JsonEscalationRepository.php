<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Repository\EscalationRepositoryInterface;
use DateTimeImmutable;

/** escalations.json (ER diyagramındaki escalations tablosu). */
final class JsonEscalationRepository implements EscalationRepositoryInterface
{
    private const TABLE = 'escalations';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function add(int $ticketId, ?int $fromAgentId, ?int $toAgentId, string $reason, ?string $note, DateTimeImmutable $at): int
    {
        return $this->store->insert(self::TABLE, [
            'ticket_id' => $ticketId,
            'from_agent_id' => $fromAgentId,
            'to_agent_id' => $toAgentId,
            'reason' => $reason,
            'note' => $note,
            'created_at' => $at->format(DATE_ATOM),
        ]);
    }

    public function findByTicket(int $ticketId): array
    {
        return array_values(array_filter($this->all(), static fn (array $row): bool => $row['ticket_id'] === $ticketId));
    }

    public function all(): array
    {
        $list = [];
        foreach ($this->store->all(self::TABLE) as $row) {
            $list[] = [
                'id' => (int) $row['id'],
                'ticket_id' => (int) ($row['ticket_id'] ?? 0),
                'from_agent_id' => isset($row['from_agent_id']) ? (int) $row['from_agent_id'] : null,
                'to_agent_id' => isset($row['to_agent_id']) ? (int) $row['to_agent_id'] : null,
                'reason' => (string) ($row['reason'] ?? ''),
                'note' => isset($row['note']) ? (string) $row['note'] : null,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $list;
    }
}
