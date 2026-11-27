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
 * SLA aşımı kuralı: önce müşteriye sorulur, talep bekleme süresince Görev Havuzu'nda kalır.
 * "Aynı personelle devam" → ek SLA; "sorun çözüldü" → kapanış; yanıt yoksa bir üst seviyeye eskalasyon.
 * Talep Pzt 2026-11-09 10:00'da açılır; Normal 16 iş saati → bitiş Çar 2026-11-11 10:00.
 */
final class EscalationTest extends FeatureTestCase
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
        $hierarchy = $this->kernel->hierarchyRepository();
        $hierarchy->setLevel(1, (int) $this->junior->id, 1);
        $hierarchy->setLevel(1, (int) $this->senior->id, 2);

        $this->customerNo = $this->registerCustomer();
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']);
    }

    private function at(string $time): void
    {
        $this->clock->set(new DateTimeImmutable($time, new DateTimeZone('Europe/Istanbul')));
    }

    private function ticket(): Ticket
    {
        $ticket = $this->kernel->ticketRepository()->findById(1);
        self::assertNotNull($ticket);

        return $ticket;
    }

    private function breach(): void
    {
        $this->at('2026-11-11 11:00');
        // Saat iki gün ilerlediği için oturum hareketsizlikten kapanır; müşteri yeniden giriş yapar.
        // Her istekte SLA denetimi çalışır.
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->get('/talepler');
    }

    public function test_breach_puts_ticket_on_hold_in_pool_and_asks_customer(): void
    {
        self::assertSame($this->junior->id, $this->ticket()->agentId);
        self::assertSame('2026-11-11 10:00', $this->ticket()->deadline->format('Y-m-d H:i'));

        $this->breach();

        $ticket = $this->ticket();
        self::assertSame(TicketStatus::Pending, $ticket->status);
        self::assertNull($ticket->agentId);
        self::assertSame($this->junior->id, $ticket->previousAgentId);
        self::assertSame('2026-11-12 11:00', $ticket->holdUntil?->format('Y-m-d H:i'), 'Bekleme 1 iş günü.');

        $detail = $this->get('/talepler/1')->body;
        self::assertStringContainsString('Aynı personelle devam et', $detail);
        self::assertStringContainsString('Sorun çözüldü', $detail);
        self::assertStringContainsString('çözüm süresi aşıldı', $this->get('/bildirimler')->body);

        self::assertSame(0, $this->kernel->ticketService()->fillFromPool(), 'Bekleyen talep otomatik atanmaz.');
        self::assertFalse($this->kernel->ticketRepository()->assignIfUnassigned(1, (int) $this->senior->id), 'Bekleyen talep alınamaz.');

        $this->post('/cikis');
        $this->loginAs($this->senior->customerNo, self::PASSWORD);
        $pool = $this->get('/havuz')->body;
        self::assertStringContainsString('Müşteri kararı bekleniyor', $pool);
        self::assertStringNotContainsString('action="/havuz/1/al"', $pool);
    }

    public function test_same_breach_is_handled_once(): void
    {
        $this->breach();
        $this->clock->advance('+2 minutes');
        $this->get('/talepler');
        $this->kernel->slaMonitor()->run();

        $returned = array_filter($this->kernel->ticketActivityRepository()->findByTicket(1), static fn ($a): bool => $a->action === 'returned_to_pool');
        self::assertCount(1, $returned);
    }

    public function test_customer_continue_returns_to_same_agent_with_extra_sla(): void
    {
        $this->breach();

        $response = $this->post('/talepler/1/karar', ['karar' => 'devam']);

        self::assertSame(302, $response->status);
        $ticket = $this->ticket();
        self::assertSame($this->junior->id, $ticket->agentId);
        self::assertSame(TicketStatus::Taken, $ticket->status);
        self::assertNull($ticket->holdUntil);
        self::assertSame('2026-11-11 17:17', $ticket->deadline->format('Y-m-d H:i'), 'Ek SLA: 960 dk × %33 = 317 iş dakikası.');
        self::assertSame('2026-11-11 10:00', $ticket->firstDeadline()->format('Y-m-d H:i'), 'İlk SLA bitişi korunur.');
        self::assertStringContainsString('Ek SLA: yeni bitiş 11.11.2026 17:17', $this->get('/talepler/1')->body);
    }

    public function test_customer_says_resolved_closes_with_reason(): void
    {
        $this->breach();

        $this->post('/talepler/1/karar', ['karar' => 'cozuldu']);

        $ticket = $this->ticket();
        self::assertSame(TicketStatus::Closed, $ticket->status);
        self::assertSame('customer_resolved', $ticket->closureReason);
        self::assertStringContainsString('Müşteri tarafından çözüldü', $this->get('/talepler/1')->body);
    }

    public function test_no_response_escalates_to_next_level_with_extra_sla(): void
    {
        $this->breach();
        $this->at('2026-11-12 11:05');
        $this->get('/talepler');

        $ticket = $this->ticket();
        self::assertSame($this->senior->id, $ticket->agentId);
        self::assertSame(TicketStatus::Taken, $ticket->status);
        self::assertNull($ticket->holdUntil);
        self::assertSame('2026-11-12 17:22', $ticket->deadline->format('Y-m-d H:i'), 'Eskalasyonda da ek SLA: 11:05 + 317 iş dk (55 + 262).');
        $escalations = $this->kernel->escalationRepository()->findByTicket(1);
        self::assertCount(1, $escalations);
        self::assertSame([$this->junior->id, $this->senior->id, 'sla_no_response'], [$escalations[0]['from_agent_id'], $escalations[0]['to_agent_id'], $escalations[0]['reason']]);

        $this->post('/cikis');
        $this->loginAs($this->senior->customerNo, self::PASSWORD);
        self::assertStringContainsString('Size eskale edilen bir talep atandı', $this->get('/bildirimler')->body);
    }

    public function test_no_upper_level_leaves_ticket_for_admin(): void
    {
        $this->kernel->hierarchyRepository()->setLevel(1, (int) $this->senior->id, null);
        $this->breach();
        $this->at('2026-11-12 11:05');
        $this->get('/talepler');

        $ticket = $this->ticket();
        self::assertNull($ticket->agentId);
        self::assertTrue($ticket->awaitingAdmin);
        self::assertSame(0, $this->kernel->ticketService()->fillFromPool());

        $this->post('/cikis');
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        self::assertStringContainsString('uygun üst seviye personel bulunamadı', $this->get('/bildirimler')->body);
        self::assertStringContainsString('Yönetici müdahalesi bekliyor', $this->get('/havuz')->body);
    }

    public function test_zero_wait_escalates_immediately(): void
    {
        $this->kernel->settingsService()->update($this->admin, ['sla.breach_wait_days' => '0']);

        $this->breach();

        self::assertSame($this->senior->id, $this->ticket()->agentId);
        self::assertSame('sla_breach', $this->kernel->escalationRepository()->findByTicket(1)[0]['reason']);
    }

    public function test_only_owner_decides_and_only_while_waiting(): void
    {
        self::assertSame(302, $this->post('/talepler/1/karar', ['karar' => 'devam'])->status);
        self::assertSame($this->junior->id, $this->ticket()->agentId, 'Bekleme yokken karar işlenmez.');
        self::assertStringContainsString('Bu talep için şu anda karar beklenmiyor.', $this->get('/talepler/1')->body);

        $this->breach();
        $this->post('/cikis');
        $this->loginAs($this->registerCustomer('Ayşe'), self::PASSWORD);
        self::assertSame(403, $this->post('/talepler/1/karar', ['karar' => 'cozuldu'])->status);
        self::assertSame(TicketStatus::Pending, $this->ticket()->status);
    }

    public function test_admin_changes_sla_rules_with_validation(): void
    {
        $this->post('/cikis');
        $this->loginAs($this->admin->customerNo, self::PASSWORD);

        $bad = $this->post('/yonetici/sla/kurallar', ['breach_wait_days' => '11', 'extra_percent' => '0', 'auto_close_days' => '3']);
        self::assertSame(422, $bad->status);
        self::assertStringContainsString('Bekleme süresi 0 ile 10 iş günü arasında olmalı.', $bad->body);
        self::assertStringContainsString('Ek SLA oranı 1 ile 100 arasında olmalı.', $bad->body);

        self::assertSame(302, $this->post('/yonetici/sla/kurallar', ['breach_wait_days' => '2', 'extra_percent' => '50', 'auto_close_days' => '5'])->status);
        $settings = $this->kernel->settingsService();
        self::assertSame([2, 50, 5], [$settings->int('sla.breach_wait_days'), $settings->int('sla.extra_percent'), $settings->int('sla.auto_close_days')]);
    }

    private function setAgent(User $agent, ?bool $active = null, ?int $capacity = null): void
    {
        $user = $this->kernel->userRepository()->findById((int) $agent->id);
        self::assertNotNull($user);
        if ($active !== null) {
            $user->active = $active;
        }
        if ($capacity !== null) {
            $user->capacity = $capacity;
        }
        $this->kernel->userRepository()->save($user);
    }

    public function test_cancelled_ticket_on_hold_is_not_revived(): void
    {
        $this->breach();
        $this->post('/talepler/1/islem', ['islem' => 'cancel']);
        self::assertSame(TicketStatus::Cancelled, $this->ticket()->status);

        $this->at('2026-11-12 11:05');
        $this->loginAs($this->customerNo, self::PASSWORD);

        $ticket = $this->ticket();
        self::assertSame(TicketStatus::Cancelled, $ticket->status);
        self::assertNull($ticket->agentId);
        self::assertNull($ticket->holdUntil);
        self::assertSame([], $this->kernel->escalationRepository()->findByTicket(1));
        self::assertStringNotContainsString('Aynı personelle devam et', $this->get('/talepler/1')->body);
    }

    public function test_overdue_pool_ticket_gets_extra_sla_when_assigned_and_is_not_pulled_back(): void
    {
        $this->setAgent($this->junior, active: false);
        $this->setAgent($this->senior, active: false);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Havuzda bekleyen', 'description' => 'Personel yokken açılan talep.']);
        self::assertNull($this->kernel->ticketRepository()->findById(2)?->agentId);

        $this->at('2026-11-11 11:00'); // talep 2'nin bitişi (Çar 10:00) geçti
        $this->setAgent($this->junior, active: true);
        $this->kernel->ticketService()->fillFromPool();

        $ticket = $this->kernel->ticketRepository()->findById(2);
        self::assertSame($this->junior->id, $ticket?->agentId);
        self::assertSame('2026-11-11 17:17', $ticket?->deadline->format('Y-m-d H:i'), 'Süresi geçmiş talep atanınca ek SLA alır.');

        $this->clock->advance('+2 minutes');
        $this->kernel->slaMonitor()->run();
        self::assertSame($this->junior->id, $this->kernel->ticketRepository()->findById(2)?->agentId, 'Atandıktan hemen sonra geri alınmaz.');
    }

    public function test_hold_frees_capacity_and_waiting_pool_ticket_is_assigned(): void
    {
        $this->setAgent($this->junior, capacity: 1);
        $this->setAgent($this->senior, active: false);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Havuzda bekleyen', 'description' => 'Kapasite doluyken açılan talep.']);
        self::assertNull($this->kernel->ticketRepository()->findById(2)?->agentId);

        $this->breach(); // talep 1 personelden alınır → Zeynep'te yer açılır

        self::assertSame($this->junior->id, $this->kernel->ticketRepository()->findById(2)?->agentId);
    }

    public function test_continue_with_unavailable_agent_goes_to_upper_level(): void
    {
        $this->breach();
        $this->setAgent($this->junior, active: false);

        $this->post('/talepler/1/karar', ['karar' => 'devam']);

        self::assertSame($this->senior->id, $this->ticket()->agentId);
        self::assertSame('previous_unavailable', $this->kernel->escalationRepository()->findByTicket(1)[0]['reason']);
        self::assertStringContainsString('Önceki personel şu anda çalışmadığı için talebiniz üst seviye personele aktarıldı.', $this->get('/talepler/1')->body);
    }

    public function test_admin_assigning_overdue_ticket_gives_extra_sla(): void
    {
        $this->kernel->hierarchyRepository()->setLevel(1, (int) $this->senior->id, null);
        $this->kernel->settingsService()->update($this->admin, ['sla.breach_wait_days' => '0']);
        $this->breach(); // üst seviye yok → yönetici müdahalesi
        self::assertTrue($this->ticket()->awaitingAdmin);

        $this->post('/cikis');
        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        $this->post('/talepler/1/ata', ['personel' => (string) $this->senior->id, 'gerekce' => '']);

        $ticket = $this->ticket();
        self::assertSame($this->senior->id, $ticket->agentId);
        self::assertSame('2026-11-11 17:17', $ticket->deadline->format('Y-m-d H:i'));
        $this->clock->advance('+2 minutes');
        $this->kernel->slaMonitor()->run();
        self::assertSame($this->senior->id, $this->ticket()->agentId);
    }
}
