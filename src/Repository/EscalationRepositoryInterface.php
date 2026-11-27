<?php

declare(strict_types=1);

namespace App\Repository;

use DateTimeImmutable;

/** Eskalasyon geçmişi (FR-58): neden, önceki ve yeni sorumlu, zaman. Yalnız ekleme. */
interface EscalationRepositoryInterface
{
    /** @param int $attempts aktarma anındaki çözüm denemesi sayısı (devralan personelin puanı kendi denemelerinden hesaplanır) */
    public function add(int $ticketId, ?int $fromAgentId, ?int $toAgentId, string $reason, ?string $note, DateTimeImmutable $at, int $attempts = 0): int;

    /** @return list<array{id: int, ticket_id: int, from_agent_id: ?int, to_agent_id: ?int, reason: string, note: ?string, created_at: string, attempts: int}> */
    public function findByTicket(int $ticketId): array;

    /** @return list<array{id: int, ticket_id: int, from_agent_id: ?int, to_agent_id: ?int, reason: string, note: ?string, created_at: string, attempts: int}> */
    public function all(): array;
}
