<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\Enum\Priority;
use App\Model\Enum\TicketStatus;
use App\Model\State\InvalidTransitionException;
use App\Model\Ticket;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** FR-06: durum geçişleri State deseniyle kısıtlanır. */
final class TicketStateTest extends TestCase
{
    public static function ticket(TicketStatus $status = TicketStatus::Pending): Ticket
    {
        $tz = new DateTimeZone('Europe/Istanbul');

        return new Ticket(
            id: 7,
            creatorId: 5,
            agentId: null,
            categoryId: 1,
            title: 'Sunucu erişimi koptu',
            status: $status,
            priority: Priority::Urgent,
            customerPriority: Priority::Normal,
            attemptCount: 0,
            createdAt: new DateTimeImmutable('2026-11-09 10:00', $tz),
            deadline: new DateTimeImmutable('2026-11-09 14:00', $tz),
            resolvedAt: null,
            earnedPoints: 0,
            agentQuestionCount: 0,
        );
    }

    /** @return array<string, array{TicketStatus, string, TicketStatus}> */
    public static function allowed(): array
    {
        return [
            'havuzdan_alma' => [TicketStatus::Pending, 'take', TicketStatus::Taken],
            'musteri_iptali' => [TicketStatus::Pending, 'cancel', TicketStatus::Cancelled],
            'incelemeye_alma' => [TicketStatus::Taken, 'startReview', TicketStatus::InReview],
            'cozum' => [TicketStatus::InReview, 'resolve', TicketStatus::Resolved],
            'musteri_onayi' => [TicketStatus::Resolved, 'close', TicketStatus::Closed],
            'musteri_reddi' => [TicketStatus::Resolved, 'reject', TicketStatus::InReview],
        ];
    }

    #[DataProvider('allowed')]
    public function test_allowed_transition(TicketStatus $from, string $action, TicketStatus $expected): void
    {
        $ticket = self::ticket($from);

        self::assertSame($expected, $ticket->state()->{$action}($ticket));
    }

    /** @return array<string, array{TicketStatus, string}> */
    public static function forbidden(): array
    {
        return [
            'kapali_tekrar_islemde' => [TicketStatus::Closed, 'startReview'],
            'iptal_alinamaz' => [TicketStatus::Cancelled, 'take'],
            'alinmis_iptal_edilemez' => [TicketStatus::Taken, 'cancel'],
            'incelemede_kapatilamaz' => [TicketStatus::InReview, 'close'],
            'bekleyen_cozulemez' => [TicketStatus::Pending, 'resolve'],
            'cozulmus_iptal_edilemez' => [TicketStatus::Resolved, 'cancel'],
            'kapali_reddedilemez' => [TicketStatus::Closed, 'reject'],
            'alinmis_tekrar_alinamaz' => [TicketStatus::Taken, 'take'],
        ];
    }

    #[DataProvider('forbidden')]
    public function test_forbidden_transition_throws(TicketStatus $from, string $action): void
    {
        $ticket = self::ticket($from);

        $this->expectException(InvalidTransitionException::class);
        $ticket->state()->{$action}($ticket);
    }

    public function test_message_names_current_status(): void
    {
        $ticket = self::ticket(TicketStatus::Closed);

        try {
            $ticket->state()->startReview($ticket);
            self::fail('İstisna bekleniyordu.');
        } catch (InvalidTransitionException $e) {
            self::assertSame("'Kapalı' durumundaki talep incelemeye alınamaz.", $e->getMessage());
        }
    }

    public function test_final_statuses(): void
    {
        self::assertTrue(TicketStatus::Closed->isFinal());
        self::assertTrue(TicketStatus::Cancelled->isFinal());
        self::assertFalse(TicketStatus::Resolved->isFinal());
    }

    public function test_hoca_labels(): void
    {
        self::assertSame('Açık', TicketStatus::Pending->hocaLabel());
        self::assertSame('İşlemde', TicketStatus::Taken->hocaLabel());
        self::assertSame('İşlemde', TicketStatus::InReview->hocaLabel());
        self::assertSame('Çözüldü', TicketStatus::Resolved->hocaLabel());
        self::assertSame('Kapalı', TicketStatus::Closed->hocaLabel());
        self::assertSame('Talep İnceleniyor', TicketStatus::InReview->label());
    }

    public function test_priority_labels_and_default_hours(): void
    {
        self::assertSame('Acil', Priority::Urgent->label());
        self::assertSame([24, 16, 8, 4], array_map(static fn (Priority $p): int => $p->defaultSlaHours(), Priority::cases()));
    }

    public function test_ticket_round_trips_array(): void
    {
        $ticket = self::ticket(TicketStatus::Resolved);
        $ticket->resolvedAt = new DateTimeImmutable('2026-11-09 13:00', new DateTimeZone('Europe/Istanbul'));
        $ticket->agentId = 3;

        $copy = Ticket::fromArray($ticket->toArray());

        self::assertEquals($ticket, $copy);
        self::assertSame('2026-11-09T14:00:00+03:00', $ticket->toArray()['deadline']);
        self::assertSame(4, $ticket->toArray()['priority']);
        self::assertSame('resolved', $ticket->toArray()['status']);
    }

    public function test_set_status_and_increment_attempt(): void
    {
        $ticket = self::ticket(TicketStatus::Resolved);

        $ticket->setStatus(TicketStatus::InReview);
        $ticket->incrementAttempt();

        self::assertSame(TicketStatus::InReview, $ticket->status);
        self::assertSame(1, $ticket->attemptCount);
    }
}
