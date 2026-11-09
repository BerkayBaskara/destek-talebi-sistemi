<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Http\Request;
use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\User;

/** FR-09 AJAX Kanban ve FR-07 canlı SLA göstergesi. */
final class KanbanApiTest extends FeatureTestCase
{
    private User $agent;
    private string $customerNo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->agent = $this->kernel->authService()->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->customerNo = $this->registerCustomer();
        $this->loginAs($this->customerNo, self::PASSWORD);
        foreach (['Yazıcı arızası', 'Klavye çalışmıyor'] as $title) {
            $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => $title, 'description' => 'Ayrıntılı açıklama burada yer alıyor.']);
        }
        $this->post('/cikis');
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
    }

    /** @return array<string, mixed> */
    private static function json(\App\Core\Http\Response $response): array
    {
        $data = json_decode($response->body, true);

        return is_array($data) ? $data : [];
    }

    public function test_kanban_page_shows_three_columns_with_cards(): void
    {
        $body = $this->get('/kanban')->body;

        foreach (['Talep Alındı', 'Talep İnceleniyor', 'Talep Çözüldü', 'Yazıcı arızası', 'data-status="in_review"', 'kanban.js'] as $needle) {
            self::assertStringContainsString($needle, $body);
        }
    }

    public function test_move_taken_to_in_review_returns_json(): void
    {
        $response = $this->postJson('/api/talepler/1/durum', ['status' => 'in_review']);
        $data = self::json($response);

        self::assertSame(200, $response->status);
        self::assertTrue($data['ok']);
        self::assertSame('in_review', $data['status']);
        self::assertSame('Talep İnceleniyor', $data['label']);
        self::assertArrayHasKey('level', $data['sla']);
        self::assertSame(TicketStatus::InReview, $this->kernel->ticketRepository()->findById(1)?->status);
    }

    public function test_invalid_move_returns_422_with_reason(): void
    {
        $response = $this->postJson('/api/talepler/1/durum', ['status' => 'closed']);

        self::assertSame(422, $response->status);
        self::assertFalse(self::json($response)['ok']);
        self::assertSame("Kanban'da bu sütuna taşınamaz.", self::json($response)['error']);
    }

    public function test_unknown_status_value_returns_422(): void
    {
        self::assertSame(422, $this->postJson('/api/talepler/1/durum', ['status' => 'uydurma'])->status);
    }

    public function test_other_agents_ticket_returns_403_json(): void
    {
        $other = $this->kernel->authService()->createStaff(Role::Agent, 'Murat', 'Demir', self::PASSWORD);
        $this->post('/cikis');
        $this->loginAs($other->customerNo, self::PASSWORD);

        $response = $this->postJson('/api/talepler/1/durum', ['status' => 'in_review']);

        self::assertSame(403, $response->status);
        self::assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
    }

    public function test_missing_csrf_header_403(): void
    {
        self::assertSame(403, $this->postJson('/api/talepler/1/durum', ['status' => 'in_review'], withCsrf: false)->status);
    }

    public function test_sla_endpoint_returns_levels_for_visible_tickets_only(): void
    {
        $this->post('/cikis');
        $this->loginAs($this->registerCustomer('Ayşe'), self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '4', 'title' => 'Başkasının talebi', 'description' => 'Bu talep başka bir müşteriye ait.']);
        $this->post('/cikis');
        // Normal öncelik 16 iş saati: 9 Kas 10:00 → 11 Kas 10:00; 10 Kas 18:00 itibarıyla 15/16 = %93
        $this->clock->set(new \DateTimeImmutable('2026-11-10 18:00', new \DateTimeZone('Europe/Istanbul')));
        $this->loginAs($this->customerNo, self::PASSWORD);
        $data = self::json($this->get('/api/sla?ids=1,2,3'));

        self::assertSame([1, 2], array_keys($data), 'Yalnız görebildiği talepler dönmeli.');
        self::assertSame('warning', $data[1]['level']);
        self::assertSame('sla-warning', $data[1]['css']);

        $this->clock->set(new \DateTimeImmutable('2026-11-11 10:00', new \DateTimeZone('Europe/Istanbul')));
        $this->loginAs($this->customerNo, self::PASSWORD);
        self::assertSame('breached', self::json($this->get('/api/sla?ids=1'))[1]['level']);
    }

    public function test_sla_polling_does_not_keep_session_alive(): void
    {
        for ($i = 0; $i < 31; $i++) {
            $this->clock->advance('+1 minute');
            $this->kernel->handle(new Request('GET', '/api/sla', ['ids' => '1']));
        }

        self::assertSame(302, $this->get('/kanban')->status, 'NFR-10: yalnız otomatik yoklama varsa oturum 30 dakikada kapanmalı.');
    }

    public function test_api_without_session_returns_401_json(): void
    {
        $this->post('/cikis');

        $response = $this->get('/api/sla?ids=1');

        self::assertSame(401, $response->status);
        self::assertFalse(self::json($response)['ok']);
    }
}
