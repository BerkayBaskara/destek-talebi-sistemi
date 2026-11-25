<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Http\Request;
use App\Model\Enum\Role;
use App\Model\User;

/**
 * NFR-35 (yoklama ile): personelin Kanban panosu açıkken müşterinin açtığı ve personele atanan talep birkaç saniye
 * içinde panoda görünür. Sayfa /api/kanban/surum ucunu yoklar; sürüm değişince pano yenilenir.
 */
final class KanbanLiveTest extends FeatureTestCase
{
    private User $agent;
    private string $customerNo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->agent = $this->kernel->authService()->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->customerNo = $this->registerCustomer();
    }

    /** @return array<string, mixed> */
    private function version(): array
    {
        $response = $this->kernel->handle(new Request('GET', '/api/kanban/surum', [], [], [], ['Accept' => 'application/json']));
        self::assertSame(200, $response->status);

        return (array) json_decode($response->body, true);
    }

    private function openTicket(string $title): void
    {
        $this->post('/cikis');
        $this->loginAs($this->customerNo, self::PASSWORD);
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => $title, 'description' => 'Canlı pano denemesi için açıldı.']);
        $this->post('/cikis');
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
    }

    public function test_version_changes_when_a_new_ticket_lands_on_the_board(): void
    {
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
        $before = $this->version();
        self::assertSame([], $before['ids']);

        $this->openTicket('Yazıcı çalışmıyor');
        $after = $this->version();

        self::assertNotSame($before['version'], $after['version']);
        self::assertSame([1], $after['ids'], 'Yeni talep personelin panosunda.');
        self::assertStringContainsString('data-version="' . $after['version'] . '"', $this->get('/kanban')->body, 'Sayfa ilk sürümü taşır.');
    }

    public function test_version_is_stable_without_changes_and_changes_on_status_move(): void
    {
        $this->openTicket('Yazıcı çalışmıyor');
        $first = $this->version();
        self::assertSame($first, $this->version());

        $this->post('/talepler/1/islem', ['islem' => 'start_review']);

        self::assertNotSame($first['version'], $this->version()['version']);
    }

    public function test_only_staff_can_poll(): void
    {
        $this->loginAs($this->customerNo, self::PASSWORD);
        $response = $this->kernel->handle(new Request('GET', '/api/kanban/surum', [], [], [], ['Accept' => 'application/json']));
        self::assertSame(403, $response->status);

        $this->post('/cikis');
        $response = $this->kernel->handle(new Request('GET', '/api/kanban/surum', [], [], [], ['Accept' => 'application/json']));
        self::assertSame(401, $response->status);
    }

    public function test_polling_does_not_keep_session_alive(): void
    {
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
        for ($i = 0; $i < 31; $i++) {
            $this->clock->advance('+1 minute');
            $this->kernel->handle(new Request('GET', '/api/kanban/surum'));
        }

        self::assertSame(302, $this->get('/kanban')->status, 'NFR-10: yalnız otomatik yoklama varsa oturum 30 dakikada kapanır.');
    }

    public function test_board_loads_live_script(): void
    {
        $this->loginAs($this->agent->customerNo, self::PASSWORD);

        self::assertStringContainsString('/assets/kanban-canli.js', $this->get('/kanban')->body);
    }
}
