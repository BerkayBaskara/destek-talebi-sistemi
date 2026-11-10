<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\User;

/** FR-19 durum geçmişi, mesajlaşma (US-04, US-10) ve durum eylemleri talep detayında. */
final class TicketDetailTest extends FeatureTestCase
{
    private string $customerNo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->customerNo = $this->registerCustomer();
    }

    private function agent(string $name = 'Zeynep'): User
    {
        return $this->kernel->authService()->createStaff(Role::Agent, $name, 'Kaya', self::PASSWORD);
    }

    private function openTicket(string $title = 'Sunucu erişimi koptu'): void
    {
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/talepler', [
            'category_id' => '1', 'priority' => '2', 'title' => $title,
            'description' => 'Sabah geldiğimizden beri sunucuya bağlanamıyoruz.',
        ]);
    }

    private function switchTo(string $number): void
    {
        $this->post('/cikis');
        $this->loginAs($number, self::PASSWORD);
    }

    public function test_owner_sees_messages_timeline_and_sla(): void
    {
        $this->agent();
        $this->openTicket();

        $body = $this->get('/talepler/1')->body;

        self::assertStringContainsString('Sabah geldiğimizden beri sunucuya bağlanamıyoruz.', $body);
        self::assertStringContainsString('Durum geçmişi', $body);
        self::assertStringContainsString('Sistem', $body, 'Otomatik atama satırı "Sistem" adıyla görünmeli.');
        self::assertStringContainsString('Talep Bekleniyor → Talep Alındı', $body);
        self::assertStringContainsString('sla-badge', $body);
        self::assertStringContainsString('Ahmet Yılmaz', $body);
        self::assertStringContainsString('Zeynep Kaya', $body, 'Atanan personel görünmeli.');
    }

    public function test_priority_raised_note(): void
    {
        $this->openTicket('Muhasebe sunucusu çöktü');

        self::assertStringContainsString('Sistem önceliği yükseltti', $this->get('/talepler/1')->body);
    }

    public function test_other_customer_gets_403_and_no_content(): void
    {
        $this->openTicket('Gizli şirket sunucusu');
        $this->switchTo($this->registerCustomer('Ayşe'));

        $response = $this->get('/talepler/1');

        self::assertSame(403, $response->status);
        self::assertStringNotContainsString('Gizli şirket sunucusu', $response->body);
        self::assertStringNotContainsString('Sabah geldiğimizden', $response->body);
    }

    public function test_unknown_ticket_404(): void
    {
        $this->loginAs($this->customerNo, self::PASSWORD);

        self::assertSame(404, $this->get('/talepler/999')->status);
    }

    public function test_customer_cancels_pending_ticket(): void
    {
        $this->openTicket();
        self::assertStringContainsString('Talebi iptal et', $this->get('/talepler/1')->body);

        $this->post('/talepler/1/islem', ['islem' => 'cancel']);

        self::assertSame(TicketStatus::Cancelled, $this->kernel->ticketRepository()->findById(1)?->status);
        $body = $this->get('/talepler/1')->body;
        self::assertStringContainsString('Talep durumu güncellendi.', $body);
        self::assertStringNotContainsString('Talebi iptal et', $body);
    }

    public function test_invalid_action_shows_reason(): void
    {
        $this->agent();
        $this->openTicket();

        $this->post('/talepler/1/islem', ['islem' => 'cancel']);

        self::assertStringContainsString(e("'Talep Alındı' durumundaki talep iptal edilemez."), $this->get('/talepler/1')->body);
        self::assertSame(TicketStatus::Taken, $this->kernel->ticketRepository()->findById(1)?->status);
    }

    public function test_agent_works_and_customer_rejects_then_approves(): void
    {
        $agent = $this->agent();
        $this->openTicket();
        $this->switchTo($agent->customerNo);

        self::assertStringContainsString('İncelemeye al', $this->get('/talepler/1')->body);
        $this->post('/talepler/1/islem', ['islem' => 'start_review']);
        $this->post('/talepler/1/yanit', ['mesaj' => 'Sunucuyu yeniden başlattım, deneyebilir misiniz?']);
        $this->post('/talepler/1/islem', ['islem' => 'resolve']);

        $this->switchTo($this->customerNo);
        $body = $this->get('/talepler/1')->body;
        self::assertStringContainsString('Sunucuyu yeniden başlattım', $body);
        self::assertStringContainsString('Çözümü onayla', $body);

        $this->post('/talepler/1/islem', ['islem' => 'reject']);
        $ticket = $this->kernel->ticketRepository()->findById(1);
        self::assertSame(TicketStatus::InReview, $ticket?->status);
        self::assertSame(1, $ticket?->attemptCount);
        self::assertStringContainsString('Talep Çözüldü → Talep İnceleniyor', $this->get('/talepler/1')->body);

        $this->switchTo($agent->customerNo);
        $this->post('/talepler/1/islem', ['islem' => 'resolve']);
        $this->switchTo($this->customerNo);
        $this->post('/talepler/1/islem', ['islem' => 'approve']);

        self::assertSame(TicketStatus::Closed, $this->kernel->ticketRepository()->findById(1)?->status);
        self::assertStringNotContainsString('name="mesaj"', $this->get('/talepler/1')->body, 'Kapalı talepte yanıt formu olmamalı.');
    }

    public function test_reply_is_escaped(): void
    {
        $this->openTicket();

        $this->post('/talepler/1/yanit', ['mesaj' => '<script>alert(1)</script>']);

        $body = $this->get('/talepler/1')->body;
        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_empty_reply_shows_error(): void
    {
        $this->openTicket();

        $this->post('/talepler/1/yanit', ['mesaj' => '   ']);

        self::assertStringContainsString('Mesaj 1-5000 karakter olmalı.', $this->get('/talepler/1')->body);
    }

    public function test_other_agent_cannot_reply_or_act(): void
    {
        $this->agent();
        $other = $this->agent('Murat');
        $this->openTicket();
        $this->switchTo($other->customerNo);

        self::assertSame(403, $this->post('/talepler/1/yanit', ['mesaj' => 'Merhaba'])->status);
        self::assertSame(403, $this->post('/talepler/1/islem', ['islem' => 'start_review'])->status);
    }
}
