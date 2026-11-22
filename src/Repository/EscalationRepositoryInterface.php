<?php

declare(strict_types=1);

namespace App\Repository;

use DateTimeImmutable;

/** Eskalasyon geçmişi (FR-58): neden, önceki ve yeni sorumlu, zaman. Yalnız ekleme. */
interface EscalationRepositoryInterface
{
    public function add(int $ticketId, ?int $fromAgentId, ?int $toAgentId, string $reason, ?string $note, DateTimeImmutable $at): int;

    /** @return list<array{id: int, ticket_id: int, from_agent_id: ?int, to_agent_id: ?int, reason: string, note: ?string, created_at: string}> */
    public function findByTicket(int $ticketId): array;

    /** @return list<array{id: int, ticket_id: int, from_agent_id: ?int, to_agent_id: ?int, reason: string, note: ?string, created_at: string}> */
    public function all(): array;
}
