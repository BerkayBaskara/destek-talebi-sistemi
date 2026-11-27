<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\User;

/** FR-18: uygulama içi bildirimler (çan, liste, okundu, yalnız kendi bildirimleri). */
final class NotificationTest extends FeatureTestCase
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
        $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']);
        $this->post('/cikis');
    }

    private function switchTo(string $number): void
    {
        $this->post('/cikis');
        $this->loginAs($number, self::PASSWORD);
    }

    private function code(): string
    {
        return (string) $this->kernel->ticketRepository()->findById(1)?->code;
    }

    public function test_assignment_notifies_agent_and_bell_shows_count(): void
    {
        $this->loginAs($this->agent->customerNo, self::PASSWORD);

        $page = $this->get('/kanban')->body;
        self::assertStringContainsString('class="bell-count">1<', $page);

        $list = $this->get('/bildirimler')->body;
        self::assertStringContainsString('Size yeni bir talep atandı: #' . $this->code() . ' Yazıcı arızası', $list);

        $api = $this->get('/api/bildirimler/sayi');
        self::assertSame(['count' => 1], json_decode($api->body, true));
    }

    public function test_replies_and_resolution_notify_the_other_side(): void
    {
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
        $this->post('/talepler/1/yanit', ['mesaj' => 'İnceliyorum.']);
        $this->post('/talepler/1/islem', ['islem' => 'start_review']);
        $this->post('/talepler/1/islem', ['islem' => 'resolve']);

        $this->switchTo($this->customerNo);
        $list = $this->get('/bildirimler')->body;
        self::assertStringContainsString('#' . $this->code() . ' talebinize yeni yanıt var.', $list);
        self::assertStringContainsString('#' . $this->code() . ' talebiniz çözüldü olarak işaretlendi. Lütfen çözümün işe yarayıp yaramadığını bildirin.', $list);
        self::assertStringNotContainsString('Size yeni bir talep atandı', $list, 'Başkasının bildirimi görünmez.');

        $this->post('/talepler/1/yanit', ['mesaj' => 'Teşekkürler.']);
        $this->switchTo($this->agent->customerNo);
        self::assertStringContainsString('#' . $this->code() . ' talebinde müşteriden yeni yanıt var.', $this->get('/bildirimler')->body);
    }

    public function test_opening_notification_marks_read_and_others_cannot_open_it(): void
    {
        $id = $this->kernel->notificationRepository()->forUser((int) $this->agent->id)[0]->id;

        $this->loginAs($this->customerNo, self::PASSWORD);
        self::assertSame(404, $this->get('/bildirimler/' . $id)->status);

        $this->switchTo($this->agent->customerNo);
        $open = $this->get('/bildirimler/' . $id);
        self::assertSame('/talepler/1', $open->headers['Location'] ?? null);
        self::assertSame(0, $this->kernel->notificationRepository()->unreadCount((int) $this->agent->id));
        self::assertStringNotContainsString('class="bell-count"', $this->get('/kanban')->body);
    }

    public function test_mark_all_read(): void
    {
        $this->loginAs($this->agent->customerNo, self::PASSWORD);

        $response = $this->post('/bildirimler/okundu');

        self::assertSame(302, $response->status);
        self::assertSame(0, $this->kernel->notificationRepository()->unreadCount((int) $this->agent->id));
    }

    public function test_notification_poll_does_not_keep_session_alive(): void
    {
        $this->loginAs($this->agent->customerNo, self::PASSWORD);
        $this->clock->advance('+20 minutes');
        $this->get('/api/bildirimler/sayi');
        $this->clock->advance('+20 minutes');

        self::assertSame('/giris', $this->get('/kanban')->headers['Location'] ?? null);
    }
}
