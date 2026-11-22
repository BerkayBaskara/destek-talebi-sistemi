<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Model\User;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Çözüm onayı (FR-17, FR-21), itirazda ek SLA (FR-14), 3. retten sonra eskalasyon, otomatik tamamlama,
 * manuel eskalasyon (FR-55), yöneticinin elle ataması (FR-52, FR-57) ve "çözülemedi" kapanışı.
 */
final class ResolutionTest extends FeatureTestCase
{
    private User $admin;
    private User $junior;
    private User $senior;
    private string $customerNo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $auth = $this->kernel->authService();
        $this->admin = $auth->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->junior = $auth->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->senior = $auth->createStaff(Role::Agent, 'Murat', 'Demir', self::PASSWORD);
        $this->kernel->hierarchyRepository()->setLevel(1, (int) $this->junior->id, 1);
        $this->kernel->hierarchyRepository()->setLevel(1, (int) $this->senior->id, 2);

        $this->customerNo = $this->registerCustomer();
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']);
    }

    private function as(string $number): void
    {
        $this->post('/cikis');
        $this->loginAs($number, self::PASSWORD);
    }

    private function ticket(): Ticket
    {
        $ticket = $this->kernel->ticketRepository()->findById(1);
        self::assertNotNull($ticket);

        return $ticket;
    }

    /** Atanan personel incelemeye alıp çözer, müşteriye döner. */
    private function resolve(): void
    {
        $agent = $this->ticket()->agentId === $this->senior->id ? $this->senior : $this->junior;
        $this->as($agent->customerNo);
        if ($this->ticket()->status === TicketStatus::Taken) {
            $this->post('/talepler/1/islem', ['islem' => 'start_review']);
        }
        $this->post('/talepler/1/islem', ['islem' => 'resolve']);
        $this->as($this->customerNo);
    }

    public function test_customer_sees_worked_and_did_not_work_buttons(): void
    {
        $this->resolve();

        $body = $this->get('/talepler/1')->body;

        self::assertStringContainsString('İşe yaradı', $body);
        self::assertStringContainsString('İşe yaramadı', $body);
    }

    public function test_did_not_work_applies_extra_sla(): void
    {
        $this->resolve();
        $before = $this->ticket()->deadline;

        $this->post('/talepler/1/islem', ['islem' => 'reject']);

        $ticket = $this->ticket();
        self::assertSame(TicketStatus::InReview, $ticket->status);
        self::assertSame('2026-11-09 16:17', $ticket->deadline->format('Y-m-d H:i'), 'Pzt 10:00 + 317 iş dk (120 + 197).');
        self::assertNotEquals($before, $ticket->deadline);
        self::assertSame('2026-11-11 10:00', $ticket->firstDeadline()->format('Y-m-d H:i'));
    }

    public function test_third_rejection_escalates_to_next_level(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->resolve();
            $this->post('/talepler/1/islem', ['islem' => 'reject']);
        }

        $ticket = $this->ticket();
        self::assertSame($this->senior->id, $ticket->agentId);
        self::assertSame(3, $ticket->attemptCount);
        self::assertSame('rejected_limit', $this->kernel->escalationRepository()->findByTicket(1)[0]['reason']);
    }

    public function test_unanswered_resolution_is_auto_completed(): void
    {
        $this->resolve(); // Pzt 2026-11-09 10:00'da çözüldü

        $this->clock->set(new DateTimeImmutable('2026-11-12 10:01', new DateTimeZone('Europe/Istanbul')));
        $this->loginAs($this->customerNo, self::PASSWORD);

        $ticket = $this->ticket();
        self::assertSame(TicketStatus::Closed, $ticket->status);
        self::assertSame('auto_closed', $ticket->closureReason);
        self::assertStringContainsString('Otomatik tamamlandı', $this->get('/talepler/1')->body);
    }

    public function test_resolution_is_not_auto_completed_early(): void
    {
        $this->resolve();
        $this->clock->set(new DateTimeImmutable('2026-11-12 09:59', new DateTimeZone('Europe/Istanbul')));
        $this->loginAs($this->customerNo, self::PASSWORD);

        self::assertSame(TicketStatus::Resolved, $this->ticket()->status);
    }

    public function test_agent_escalates_manually_with_reason(): void
    {
        $this->as($this->junior->customerNo);

        $empty = $this->post('/talepler/1/eskale', ['gerekce' => '  ']);
        self::assertSame($this->junior->id, $this->ticket()->agentId);
        self::assertStringContainsString('Gerekçe 5-300 karakter olmalı.', $this->get('/talepler/1')->body);
        unset($empty);

        $this->post('/talepler/1/eskale', ['gerekce' => 'Sunucu yetkisi gerekiyor']);

        self::assertSame($this->senior->id, $this->ticket()->agentId);
        $escalation = $this->kernel->escalationRepository()->findByTicket(1)[0];
        self::assertSame(['manual', 'Sunucu yetkisi gerekiyor'], [$escalation['reason'], $escalation['note']]);

        $this->as($this->customerNo);
        self::assertSame(403, $this->post('/talepler/1/eskale', ['gerekce' => 'Ben de aktarayım'])->status);
    }

    public function test_admin_assigns_waiting_ticket_and_capacity_exception_needs_reason(): void
    {
        $this->kernel->hierarchyRepository()->setLevel(1, (int) $this->senior->id, null);
        $this->as($this->junior->customerNo);
        $this->post('/talepler/1/eskale', ['gerekce' => 'Üst seviye gerekiyor']);
        self::assertTrue($this->ticket()->awaitingAdmin);

        $senior = $this->kernel->userRepository()->findById((int) $this->senior->id);
        self::assertNotNull($senior);
        $senior->capacity = 1;
        $this->kernel->userRepository()->save($senior);
        $this->kernel->ticketRepository()->save(new Ticket(null, 99, (int) $this->senior->id, 2, 'Dolu', TicketStatus::Taken, 2, 2, 0, $this->clock->now(), $this->clock->now()->modify('+1 day'), null));

        $this->as($this->admin->customerNo);
        $this->post('/talepler/1/ata', ['personel' => (string) $this->senior->id, 'gerekce' => '']);
        self::assertTrue($this->ticket()->awaitingAdmin);
        self::assertStringContainsString('Kapasitesi dolu personele atama için gerekçe yazın.', $this->get('/talepler/1')->body);

        $this->post('/talepler/1/ata', ['personel' => (string) $this->senior->id, 'gerekce' => 'Acil müşteri']);
        $ticket = $this->ticket();
        self::assertSame($this->senior->id, $ticket->agentId);
        self::assertFalse($ticket->awaitingAdmin);
        self::assertSame(TicketStatus::Taken, $ticket->status);
    }

    public function test_admin_closes_as_unresolvable_with_reason(): void
    {
        $this->as($this->junior->customerNo);
        self::assertSame(403, $this->post('/talepler/1/cozulemedi', ['gerekce' => 'Donanım üretimden kalktı'])->status);

        $this->as($this->admin->customerNo);
        $this->post('/talepler/1/cozulemedi', ['gerekce' => 'Donanım üretimden kalktı']);

        $ticket = $this->ticket();
        self::assertSame(TicketStatus::Closed, $ticket->status);
        self::assertSame('unresolvable', $ticket->closureReason);
        self::assertStringContainsString('Çözülemedi', $this->get('/talepler/1')->body);
        self::assertStringContainsString('Donanım üretimden kalktı', $this->get('/talepler/1')->body);
    }

    public function test_customer_does_not_see_internal_escalation_reason(): void
    {
        $this->as($this->junior->customerNo);
        $this->post('/talepler/1/eskale', ['gerekce' => 'Bu müşteri çok zor']);
        self::assertStringContainsString('Bu müşteri çok zor', $this->get('/talepler/1')->body === '' ? '' : $this->adminView());

        $this->as($this->customerNo);
        $body = $this->get('/talepler/1')->body;
        self::assertStringNotContainsString('Bu müşteri çok zor', $body);
        self::assertStringContainsString('Üst seviye personele aktarıldı', $body);
    }

    private function adminView(): string
    {
        $this->as($this->admin->customerNo);

        return $this->get('/talepler/1')->body;
    }
}
