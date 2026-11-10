<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use App\Model\Enum\Priority;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Repository\Json\JsonTicketRepository;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

final class JsonTicketRepositoryTest extends TestCase
{
    use TempStoreTrait;

    private JsonTicketRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new JsonTicketRepository($this->makeStore());
    }

    private static function newTicket(TicketStatus $status = TicketStatus::Pending, ?int $agentId = null, string $created = '2026-11-09 10:00', int $creatorId = 5): Ticket
    {
        $tz = new DateTimeZone('Europe/Istanbul');

        return new Ticket(null, $creatorId, $agentId, 1, 'Yazıcı çalışmıyor', $status, Priority::Normal, Priority::Normal, 0,
            new DateTimeImmutable($created, $tz), new DateTimeImmutable('2026-11-10 10:00', $tz), null);
    }

    public function test_save_and_find_round_trip(): void
    {
        $saved = $this->repo->save(self::newTicket());

        self::assertSame(1, $saved->id);
        $found = $this->repo->findById(1);
        self::assertNotNull($found);
        self::assertEquals($saved, $found);
        self::assertSame('2026-11-10T10:00:00+03:00', $found->deadline->format(DATE_ATOM));
        self::assertNull($this->repo->findById(99));
    }

    public function test_save_existing_updates(): void
    {
        $ticket = $this->repo->save(self::newTicket());
        $ticket->setStatus(TicketStatus::Cancelled);

        $this->repo->save($ticket);

        self::assertSame(TicketStatus::Cancelled, $this->repo->findById(1)?->status);
        self::assertCount(1, $this->repo->findAll());
    }

    public function test_pool_contains_only_unassigned_pending_oldest_first(): void
    {
        $this->repo->save(self::newTicket(TicketStatus::Pending, null, '2026-11-09 11:00'));
        $this->repo->save(self::newTicket(TicketStatus::Taken, 2));
        $this->repo->save(self::newTicket(TicketStatus::Pending, null, '2026-11-09 09:00'));
        $this->repo->save(self::newTicket(TicketStatus::Cancelled, null));

        $pool = $this->repo->getPoolTickets();

        self::assertSame([3, 1], array_map(static fn (Ticket $t): ?int => $t->id, $pool));
    }

    public function test_find_by_creator_and_agent(): void
    {
        $this->repo->save(self::newTicket(TicketStatus::Taken, 2, creatorId: 5));
        $this->repo->save(self::newTicket(TicketStatus::Taken, 3, creatorId: 6));

        self::assertCount(1, $this->repo->findByCreator(5));
        self::assertSame(2, $this->repo->findByAgent(2)[0]->agentId);
    }

    public function test_count_active_by_agent(): void
    {
        $this->repo->save(self::newTicket(TicketStatus::Taken, 2));
        $this->repo->save(self::newTicket(TicketStatus::InReview, 2));
        $this->repo->save(self::newTicket(TicketStatus::Resolved, 2));
        $this->repo->save(self::newTicket(TicketStatus::Taken, 3));
        $this->repo->save(self::newTicket(TicketStatus::Closed, 4));

        self::assertSame([2 => 2, 3 => 1], $this->repo->countActiveByAgent());
    }

    public function test_assign_if_unassigned_second_call_fails(): void
    {
        $this->repo->save(self::newTicket());

        self::assertTrue($this->repo->assignIfUnassigned(1, 2));
        self::assertFalse($this->repo->assignIfUnassigned(1, 3));

        $ticket = $this->repo->findById(1);
        self::assertSame(2, $ticket?->agentId);
        self::assertSame(TicketStatus::Taken, $ticket?->status);
    }

    public function test_save_if_status_is_compare_and_set(): void
    {
        $ticket = $this->repo->save(self::newTicket(TicketStatus::Taken, 2));
        $ticket->setStatus(TicketStatus::InReview);

        self::assertTrue($this->repo->saveIfStatus($ticket, TicketStatus::Taken));
        self::assertSame(TicketStatus::InReview, $this->repo->findById(1)?->status);

        $ticket->setStatus(TicketStatus::Resolved);
        self::assertFalse($this->repo->saveIfStatus($ticket, TicketStatus::Taken), 'Durum artık Taken değil; yazılmamalı.');
        self::assertSame(TicketStatus::InReview, $this->repo->findById(1)?->status);
    }

    public function test_save_if_status_also_requires_same_agent(): void
    {
        $ticket = $this->repo->save(self::newTicket(TicketStatus::Pending));
        $stale = $this->repo->findById(1);
        $this->repo->assignIfUnassigned(1, 7);
        self::assertNotNull($stale);

        $stale->setStatus(TicketStatus::Cancelled);
        self::assertFalse($this->repo->saveIfStatus($stale, TicketStatus::Pending), 'Talep bu arada atandı; eski kopya yazılmamalı.');
        self::assertNotNull($ticket->id);
    }

    public function test_assign_if_unassigned_rejects_cancelled_or_missing(): void
    {
        $this->repo->save(self::newTicket(TicketStatus::Cancelled));

        self::assertFalse($this->repo->assignIfUnassigned(1, 2));
        self::assertFalse($this->repo->assignIfUnassigned(42, 2));
    }
}
