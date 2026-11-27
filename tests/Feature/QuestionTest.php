<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\Ticket;
use App\Model\User;
use DateTimeImmutable;
use DateTimeZone;

/**
 * FR-22: personelin müşteriye soru sorması. Soru sayısı ve müşterinin yanıt süresi yönetici ayarı (varsayılan 2 soru,
 * 12 saat). Soru beklerken SLA durur; müşteri yanıtlayınca (ya da süre dolunca) kalan süreyle devam eder.
 * Talep Pzt 2026-11-09 10:00'da açılır, bitiş Çar 2026-11-11 10:00.
 */
final class QuestionTest extends FeatureTestCase
{
    private User $admin;
    private User $agent;
    private string $customerNo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->admin = $this->kernel->authService()->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD);
        $this->agent = $this->kernel->authService()->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->customerNo = $this->registerCustomer();
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']);
        $this->post('/cikis');
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

    private function agentAsks(string $text = 'Yazıcının modeli nedir?'): void
    {
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
        $this->post('/talepler/1/yanit', ['mesaj' => $text, 'soru' => '1']);
        $this->post('/cikis');
    }

    public function test_question_pauses_sla_and_answer_resumes_with_remaining_time(): void
    {
        $this->at('2026-11-11 09:00'); // bitişe 60 iş dakikası kaldı
        $this->agentAsks();

        $ticket = $this->ticket();
        self::assertSame('2026-11-11 09:00', $ticket->pausedAt?->format('Y-m-d H:i'));
        self::assertSame('2026-11-11 21:00', $ticket->questionDueAt?->format('Y-m-d H:i'), 'Varsayılan yanıt süresi 12 saat.');
        self::assertSame(1, $ticket->agentQuestionCount);

        $this->at('2026-11-11 15:00'); // bitiş geçti ama SLA durdu
        $this->loginAs($this->customerNo, self::PASSWORD);
        self::assertNull($this->ticket()->holdUntil, 'Duran talepte aşım işlenmez.');
        $detail = $this->get('/talepler/1')->body;
        self::assertStringContainsString('SLA durdu', $detail);
        self::assertStringContainsString('Soru', $detail);

        $this->post('/talepler/1/yanit', ['mesaj' => 'HP LaserJet 400.']);

        $ticket = $this->ticket();
        self::assertNull($ticket->pausedAt);
        self::assertSame('2026-11-11 16:00', $ticket->deadline->format('Y-m-d H:i'), 'Kalan 60 dk yanıt anından itibaren.');
    }

    public function test_unanswered_question_resumes_sla_when_reply_time_ends(): void
    {
        $this->at('2026-11-11 09:00');
        $this->agentAsks();

        $this->at('2026-11-11 21:30');
        $this->kernel->slaMonitor()->run();

        $ticket = $this->ticket();
        self::assertNull($ticket->pausedAt);
        self::assertSame('2026-11-12 10:00', $ticket->deadline->format('Y-m-d H:i'), 'Kalan 60 dk sonraki mesai başından.');
    }

    public function test_question_limit_is_enforced_and_configurable(): void
    {
        $this->agentAsks('Birinci soru?');
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/talepler/1/yanit', ['mesaj' => 'Yanıt bir.']);
        $this->post('/cikis');
        $this->agentAsks('İkinci soru?');
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/talepler/1/yanit', ['mesaj' => 'Yanıt iki.']);
        $this->post('/cikis');

        $this->loginAs($this->agent->customerNo, self::PASSWORD);
        $this->post('/talepler/1/yanit', ['mesaj' => 'Üçüncü soru?', 'soru' => '1']);
        self::assertStringContainsString('Bu talepte en fazla 2 soru sorulabilir.', $this->get('/talepler/1')->body);
        self::assertSame(2, $this->ticket()->agentQuestionCount);
        self::assertNull($this->ticket()->pausedAt);
        $this->post('/cikis');

        $this->loginAs($this->admin->customerNo, self::PASSWORD);
        $bad = $this->post('/yonetici/sla/kurallar', ['max_questions' => '11', 'question_reply_hours' => '0']);
        self::assertSame(422, $bad->status);
        self::assertStringContainsString('Soru sınırı 0 ile 10 arasında olmalı.', $bad->body);
        self::assertStringContainsString('Soru yanıt süresi 1 ile 168 saat arasında olmalı.', $bad->body);
        self::assertSame(302, $this->post('/yonetici/sla/kurallar', ['max_questions' => '3', 'question_reply_hours' => '24'])->status);
        $this->post('/cikis');

        $this->agentAsks('Üçüncü soru?');
        self::assertSame(3, $this->ticket()->agentQuestionCount);
        self::assertSame('2026-11-10 10:00', $this->ticket()->questionDueAt?->format('Y-m-d H:i'), '24 saatlik yanıt süresi.');
    }

    public function test_customer_cannot_send_question_and_second_question_waits_for_answer(): void
    {
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/talepler/1/yanit', ['mesaj' => 'Bir soru gibi?', 'soru' => '1']);
        self::assertNull($this->ticket()->pausedAt, 'Müşteri soru işaretleyemez.');
        $this->post('/cikis');

        $this->agentAsks('Birinci soru?');
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
        $this->post('/talepler/1/yanit', ['mesaj' => 'İkinci soru hemen?', 'soru' => '1']);
        self::assertStringContainsString('Önceki sorunun yanıtı bekleniyor.', $this->get('/talepler/1')->body);
        self::assertSame(1, $this->ticket()->agentQuestionCount);
    }
}
