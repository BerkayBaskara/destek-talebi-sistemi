<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\TicketStatus;

final class TicketCreateTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    /** @return array<string, mixed> */
    private static function form(array $override = []): array
    {
        return $override + [
            'category_id' => '1',
            'priority' => '2',
            'title' => 'Yazıcıdan çıktı alamıyorum',
            'description' => 'Muhasebe yazıcısı sürekli kağıt sıkıştırıyor.',
        ];
    }

    public function test_customer_creates_ticket_and_is_redirected(): void
    {
        $this->loginAs($this->registerCustomer(), self::PASSWORD);

        $response = $this->post('/talepler', self::form());

        self::assertSame(302, $response->status);
        self::assertSame('/talepler/1/olusturuldu', $response->headers['Location']);
        $list = $this->get('/talepler');
        self::assertStringContainsString('Yazıcıdan çıktı alamıyorum', $list->body);
        self::assertSame(TicketStatus::Pending, $this->kernel->ticketRepository()->findById(1)?->status);
    }

    public function test_create_form_lists_categories_and_priorities(): void
    {
        $this->loginAs($this->registerCustomer(), self::PASSWORD);

        $body = $this->get('/talepler/yeni')->body;

        self::assertStringContainsString('Ağ / Erişim', $body);
        self::assertStringContainsString('Çok Yüksek', $body);
        self::assertStringContainsString('name="_csrf"', $body);
    }

    public function test_invalid_form_keeps_values_422(): void
    {
        $this->loginAs($this->registerCustomer(), self::PASSWORD);

        $response = $this->post('/talepler', self::form(['description' => 'kısa', 'title' => 'Başlığım <b>kalın</b>']));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Açıklama 10-5000 karakter olmalı.', $response->body);
        self::assertStringContainsString('value="Başlığım &lt;b&gt;kalın&lt;/b&gt;"', $response->body, 'Değer korunmalı ve kaçırılmalı (XSS).');
        self::assertSame([], $this->kernel->ticketRepository()->findAll());
    }

    public function test_list_shows_only_own_tickets(): void
    {
        $other = $this->registerCustomer('Ayşe');
        $this->loginAs($other, self::PASSWORD);
        $this->post('/talepler', self::form(['title' => 'Ayşenin gizli talebi']));
        $this->post('/cikis');

        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        $this->post('/talepler', self::form(['title' => 'Benim talebim']));

        $body = $this->get('/talepler')->body;
        self::assertStringContainsString('Benim talebim', $body);
        self::assertStringNotContainsString('Ayşenin gizli talebi', $body);
    }

    public function test_list_filter_and_search(): void
    {
        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        $this->post('/talepler', self::form(['title' => 'İNTERNET bağlantısı yok']));
        $this->post('/talepler', self::form(['title' => 'Klavye arızası']));
        $this->post('/talepler/2/islem', ['islem' => 'cancel']);

        $search = $this->get('/talepler?q=internet')->body;
        self::assertStringContainsString('İNTERNET bağlantısı yok', $search);
        self::assertStringNotContainsString('Klavye arızası', $search);

        $filtered = $this->get('/talepler?durum=cancelled')->body;
        self::assertStringContainsString('Klavye arızası', $filtered);
        self::assertStringNotContainsString('İNTERNET bağlantısı yok', $filtered);
    }

    public function test_agent_cannot_open_create_form(): void
    {
        $agent = $this->kernel->authService()->createStaff(\App\Model\Enum\Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD);
        $this->loginAs($agent->customerNo, self::PASSWORD);

        self::assertSame(403, $this->get('/talepler/yeni')->status);
        self::assertSame(403, $this->post('/talepler', self::form())->status);
    }

    public function test_create_redirects_to_success_page_and_only_owner_sees_it(): void
    {
        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        $this->post('/talepler', self::form());

        $page = $this->get('/talepler/1/olusturuldu');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('Talebiniz alındı.', $page->body);
        self::assertStringContainsString('#1', $page->body);
        self::assertStringContainsString('<meta http-equiv="refresh" content="5;url=/talepler">', $page->body);
        self::assertStringContainsString('data-countdown="5"', $page->body);

        $this->post('/cikis');
        $this->loginAs($this->registerCustomer('Ayşe'), self::PASSWORD);
        self::assertSame(403, $this->get('/talepler/1/olusturuldu')->status);
    }

    public function test_register_done_has_link_and_no_refresh(): void
    {
        $this->post('/kayit', [
            'name' => 'Ayşe', 'surname' => 'Demir', 'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD,
            'secret_question' => \App\Service\AuthService::SECRET_QUESTIONS[0], 'secret_answer' => 'Pamuk',
        ]);

        $done = $this->get('/kayit/tamam')->body;

        self::assertStringContainsString('<a href="/giris">Giriş yapmak için buraya tıklayınız</a>', $done);
        self::assertStringNotContainsString('http-equiv="refresh"', $done);
        self::assertMatchesRegularExpression('/M\d{6}/', $done);
    }
}
