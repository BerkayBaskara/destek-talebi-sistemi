<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Priority;
use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;

/** NFR-02: personelin üzerinde 100 aktif talep varken Kanban sayfası 1 saniyenin altında üretilmeli. */
final class KanbanPerformanceTest extends FeatureTestCase
{
    public function test_kanban_with_100_active_tickets_renders_under_one_second(): void
    {
        $this->seedCatalog();
        $agent = $this->kernel->authService()->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $repo = $this->kernel->ticketRepository();
        $now = $this->clock->now();
        for ($i = 1; $i <= 100; $i++) {
            $repo->save(new Ticket(null, 99, $agent->id, 1, "Yük testi talebi $i", $i % 2 === 0 ? TicketStatus::Taken : TicketStatus::InReview,
                Priority::Normal, Priority::Normal, 0, $now->modify("-$i hours"), $now->modify('+' . (200 - $i) . ' hours'), null));
        }
        $this->loginAs($agent->customerNo, self::PASSWORD);

        $started = hrtime(true);
        $response = $this->get('/kanban');
        $elapsedMs = (hrtime(true) - $started) / 1_000_000;

        self::assertSame(200, $response->status);
        self::assertSame(100, substr_count($response->body, 'class="kanban-card"'));
        self::assertLessThan(1000, $elapsedMs, sprintf('Kanban %.0f ms sürdü.', $elapsedMs));
    }
}
