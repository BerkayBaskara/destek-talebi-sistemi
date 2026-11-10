<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Seed\DemoSeeder;

/** Hoca: "Sunumda boş veritabanıyla demo yapılmaz." Örnek veri sunuma hazır olmalı. */
final class SeedTest extends FeatureTestCase
{
    /** @return array<string, array{string}> Seed sunumdan önce günün herhangi bir anında çalıştırılabilir. */
    public static function moments(): array
    {
        return [
            'pazartesi_sabah' => ['2026-11-09 10:00'],
            'cumartesi' => ['2026-11-14 12:00'],
            'gece_03' => ['2026-11-10 03:00'],
            'cuma_mesai_bitimi' => ['2026-11-13 17:55'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('moments')]
    public function test_seed_produces_demo_ready_data(string $moment): void
    {
        $this->clock->set(new \DateTimeImmutable($moment, new \DateTimeZone('Europe/Istanbul')));
        $started = $this->clock->now();
        $accounts = (new DemoSeeder($this->kernel, $this->clock))->run();

        self::assertEquals($started, $this->clock->now(), 'Seed bitince saat başladığı ana dönmeli.');
        self::assertCount(9, $accounts);
        $users = $this->kernel->userRepository();
        self::assertCount(1, $users->findByRole(Role::Admin));
        self::assertCount(3, $users->findByRole(Role::Agent));
        self::assertCount(5, $users->findByRole(Role::Customer));

        $tickets = $this->kernel->ticketRepository()->findAll();
        self::assertGreaterThanOrEqual(28, count($tickets));

        $byStatus = [];
        foreach ($tickets as $ticket) {
            $byStatus[$ticket->status->value] = ($byStatus[$ticket->status->value] ?? 0) + 1;
            self::assertNotSame([], $this->kernel->ticketLogRepository()->findByTicket((int) $ticket->id), 'Her talebin durum geçmişi olmalı.');
            self::assertNotSame([], $this->kernel->replyRepository()->findByTicket((int) $ticket->id), 'Her talebin en az bir mesajı olmalı.');
        }
        foreach (TicketStatus::cases() as $status) {
            self::assertGreaterThanOrEqual(3, $byStatus[$status->value] ?? 0, 'Her durumdan en az 3 talep: ' . $status->value);
        }

        $levels = [];
        foreach ($tickets as $ticket) {
            $level = $this->kernel->slaStatusService()->forTicket($ticket)->level;
            $levels[$level] = ($levels[$level] ?? 0) + 1;
        }
        self::assertGreaterThanOrEqual(3, $levels['warning'] ?? 0, 'En az 3 sarı (SLA %80+) talep.');

        // Sunum seed'den sonra başlar: 2 iş saati sonra da en az 3 sarı örnek kalmalı.
        $this->clock->set($this->kernel->slaCalculator()->calculateWithJson($started, 120));
        $later = 0;
        foreach ($tickets as $ticket) {
            $later += $this->kernel->slaStatusService()->forTicket($ticket)->level === 'warning' ? 1 : 0;
        }
        self::assertGreaterThanOrEqual(3, $later, '2 iş saati sonra da en az 3 sarı talep olmalı.');
        $this->clock->set($started);
        self::assertGreaterThanOrEqual(3, $levels['breached'] ?? 0, 'En az 3 kırmızı (SLA aşılmış) talep.');
        self::assertGreaterThanOrEqual(1, $levels['missed'] ?? 0, 'En az 1 geç çözülmüş talep.');
        self::assertGreaterThanOrEqual(3, count($this->kernel->ticketRepository()->getPoolTickets()), 'Havuzda en az 3 talep.');

        $raised = array_filter($tickets, static fn ($t): bool => $t->priorityRaised());
        self::assertNotSame([], $raised, 'Anahtar kelimeyle önceliği yükselmiş talep olmalı.');
        $rejected = array_filter($tickets, static fn ($t): bool => $t->attemptCount > 0);
        self::assertNotSame([], $rejected, 'Çözümü bir kez reddedilmiş talep olmalı.');
    }

    public function test_seeded_accounts_can_log_in(): void
    {
        (new DemoSeeder($this->kernel, $this->clock))->run();

        $this->loginAs('M100001', DemoSeeder::PASSWORD);
        self::assertSame(200, $this->get('/talepler')->status);
    }
}
