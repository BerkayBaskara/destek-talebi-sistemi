<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Model\TicketCode;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/** 6 karakterli talep kodu ve biletin yeni alanları. */
final class TicketCodeTest extends TestCase
{
    public function test_generated_code_is_six_unambiguous_characters(): void
    {
        for ($i = 0; $i < 200; $i++) {
            self::assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', TicketCode::generate());
        }
    }

    public function test_normalize_accepts_hash_and_lowercase(): void
    {
        self::assertSame('AB23CD', TicketCode::normalize(' #ab23cd '));
        self::assertNull(TicketCode::normalize('AB23C'));
        self::assertNull(TicketCode::normalize('ABO1CD'), 'Karışan karakterler (O, 1) kodda olmaz.');
    }

    public function test_new_fields_round_trip_and_old_rows_still_load(): void
    {
        $tz = new DateTimeZone('Europe/Istanbul');
        $at = new DateTimeImmutable('2026-11-24 10:00', $tz);
        $ticket = new Ticket(7, 1, null, 1, 'Başlık', TicketStatus::Pending, 2, 2, 0, $at, $at, null);
        $ticket->code = 'AB23CD';
        $ticket->originalDeadline = $at;
        $ticket->previousAgentId = 4;
        $ticket->holdUntil = $at->modify('+1 day');
        $ticket->closureReason = 'customer_resolved';
        $ticket->breachHandledAt = $at;

        $copy = Ticket::fromArray($ticket->toArray());
        self::assertSame('AB23CD', $copy->code);
        self::assertSame(4, $copy->previousAgentId);
        self::assertSame('customer_resolved', $copy->closureReason);
        self::assertEquals($ticket->holdUntil, $copy->holdUntil);
        self::assertSame('#AB23CD', $copy->displayCode());

        $old = Ticket::fromArray(['id' => 3, 'status' => 'pending', 'created_at' => $at->format(DATE_ATOM), 'deadline' => $at->format(DATE_ATOM)]);
        self::assertNull($old->code);
        self::assertNull($old->holdUntil);
        self::assertSame('#3', $old->displayCode(), 'Kodu olmayan eski talepte numara gösterilir.');
        self::assertEquals($old->deadline, $old->firstDeadline(), 'İlk SLA bitişi yoksa mevcut bitiş kullanılır.');
    }
}
