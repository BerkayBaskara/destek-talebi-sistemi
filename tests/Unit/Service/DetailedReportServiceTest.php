<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Model\User;
use App\Repository\Json\JsonCalendarRepository;
use App\Repository\Json\JsonTicketRepository;
use App\Repository\Json\JsonUserRepository;
use App\Service\DetailedReportService;
use App\Service\SlaCalculatorService;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

/** SLA uyumu ilk SLA bitişine göre ölçülür; ek SLA ile uzatılmış bitiş "süresinde" sayılmaz. */
final class DetailedReportServiceTest extends TestCase
{
    use TempStoreTrait;

    public function test_sla_compliance_uses_first_deadline(): void
    {
        $store = $this->makeStore();
        $users = new JsonUserRepository($store);
        $tickets = new JsonTicketRepository($store);
        $agent = $users->save(new User(null, 'P000001', 'x', Role::Agent, 'Zeynep', 'Kaya', 'S', 'C'));
        $tz = new DateTimeZone('Europe/Istanbul');
        $created = new DateTimeImmutable('2026-11-09 10:00', $tz);
        $ticket = new Ticket(null, 9, (int) $agent->id, 1, 'Geç çözülen', TicketStatus::Resolved, 2, 2, 0, $created,
            new DateTimeImmutable('2026-11-12 12:00', $tz), new DateTimeImmutable('2026-11-12 11:00', $tz));
        $ticket->originalDeadline = new DateTimeImmutable('2026-11-11 10:00', $tz); // ek SLA öncesi bitiş
        $tickets->save($ticket);

        $report = new DetailedReportService($tickets, $users, new SlaCalculatorService(new JsonCalendarRepository(__DIR__ . '/../../fixtures/calendar.json')));
        $row = $report->perAgent($report->filter([]))[0];

        self::assertSame(0, $row['metPercent'], 'İlk bitişten sonra çözülen talep süresinde sayılmaz.');
        self::assertFalse(DetailedReportService::metFirstDeadline($ticket));
    }
}
