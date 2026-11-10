<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use App\Model\Category;
use App\Model\Enum\Priority;
use App\Model\Enum\TicketStatus;
use App\Model\Reply;
use App\Model\SlaPolicy;
use App\Model\TicketLog;
use App\Repository\Json\JsonCategoryRepository;
use App\Repository\Json\JsonReplyRepository;
use App\Repository\Json\JsonSlaPolicyRepository;
use App\Repository\Json\JsonTicketLogRepository;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

final class JsonCatalogRepositoriesTest extends TestCase
{
    use TempStoreTrait;

    private static function at(string $s): DateTimeImmutable
    {
        return new DateTimeImmutable($s, new DateTimeZone('Europe/Istanbul'));
    }

    public function test_categories(): void
    {
        $repo = new JsonCategoryRepository($this->makeStore());
        $repo->save(new Category(null, 'Donanım'));
        $repo->save(new Category(null, 'Ağ / Erişim'));

        self::assertSame(['Donanım', 'Ağ / Erişim'], array_map(static fn (Category $c): string => $c->name, $repo->findAll()));
        self::assertSame('Ağ / Erişim', $repo->findById(2)?->name);
        self::assertNull($repo->findById(9));
    }

    public function test_sla_policy_lookup_by_category_and_priority(): void
    {
        $repo = new JsonSlaPolicyRepository($this->makeStore());
        $repo->save(new SlaPolicy(null, 1, Priority::Urgent, 4));
        $repo->save(new SlaPolicy(null, 1, Priority::Normal, 16));
        $repo->save(new SlaPolicy(null, 2, Priority::Urgent, 2));

        self::assertSame(2, $repo->findFor(2, Priority::Urgent)?->durationHours);
        self::assertSame(16, $repo->findFor(1, Priority::Normal)?->durationHours);
        self::assertNull($repo->findFor(2, Priority::Low));
        self::assertCount(3, $repo->findAll());
    }

    public function test_replies_sorted_oldest_first(): void
    {
        $repo = new JsonReplyRepository($this->makeStore());
        $repo->save(new Reply(null, 1, 5, 'ikinci', self::at('2026-11-09 11:00')));
        $repo->save(new Reply(null, 1, 2, 'birinci', self::at('2026-11-09 10:00')));
        $repo->save(new Reply(null, 2, 5, 'başka talep', self::at('2026-11-09 09:00')));

        $replies = $repo->findByTicket(1);

        self::assertSame(['birinci', 'ikinci'], array_map(static fn (Reply $r): string => $r->message, $replies));
        self::assertFalse($replies[0]->isQuestion);
    }

    public function test_ticket_logs(): void
    {
        $repo = new JsonTicketLogRepository($this->makeStore());
        $repo->save(new TicketLog(null, 1, 5, null, TicketStatus::Pending, self::at('2026-11-09 10:00')));
        $repo->save(new TicketLog(null, 1, 0, TicketStatus::Pending, TicketStatus::Taken, self::at('2026-11-09 10:00')));

        $logs = $repo->findByTicket(1);

        self::assertCount(2, $logs);
        self::assertNull($logs[0]->oldStatus);
        self::assertSame(TicketStatus::Taken, $logs[1]->newStatus);
        self::assertSame([], $repo->findByTicket(2));
    }
}
