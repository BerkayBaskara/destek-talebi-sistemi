<?php

declare(strict_types=1);

namespace Tests\Feature;

final class KernelTest extends FeatureTestCase
{
    public function test_unknown_path_returns_404_page(): void
    {
        $response = $this->get('/olmayan-sayfa');

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Sayfa bulunamadı', $response->body);
    }

    public function test_post_without_csrf_returns_403(): void
    {
        $response = $this->postWithoutCsrf('/');

        self::assertSame(403, $response->status);
        self::assertStringContainsString('Güvenlik anahtarı (CSRF)', $response->body);
    }

    public function test_api_without_session_returns_json_401(): void
    {
        $response = $this->postJson('/api/deneme', ['a' => 1], withCsrf: false);

        self::assertSame(401, $response->status);
        self::assertSame('application/json; charset=utf-8', $response->headers['Content-Type'] ?? null);
        self::assertFalse(json_decode($response->body, true)['ok']);
    }

    public function test_login_page_renders_layout(): void
    {
        $response = $this->get('/giris');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('<title>Destek Talebi Sistemi', $response->body);
        self::assertStringContainsString('name="viewport"', $response->body);
    }

    public function test_wrong_shaped_data_gives_friendly_500_without_leaking_input(): void
    {
        // Geçerli JSON ama yanlış değer: role büyük harfle yazılmış (ValueError).
        file_put_contents($this->root . '/storage/data/users.json', '[{"id":1,"customer_no":"M100002","role":"Customer","password_hash":"x"}]');

        $response = $this->post('/giris', ['customer_no' => 'M100002', 'password' => 'Gizli2026!']);

        self::assertSame(500, $response->status);
        self::assertStringContainsString('Beklenmeyen bir hata oluştu', $response->body);
        self::assertStringNotContainsString('Gizli2026!', $response->body);
    }

    public function test_post_body_dropped_by_php_limit_shows_size_message_not_csrf(): void
    {
        // post_max_size aşılınca PHP $_POST ve $_FILES'ı boş bırakır; yalnız Content-Length kalır.
        $response = $this->kernel->handle(new \App\Core\Http\Request('POST', '/talepler', [], [], [], ['Content-Length' => '99999999999']));

        self::assertSame(413, $response->status);
        self::assertStringContainsString('Gönderilen dosyalar toplamda çok büyük', $response->body);
        self::assertStringNotContainsString('CSRF', $response->body);
    }

    public function test_html_responses_have_security_headers(): void
    {
        $response = $this->get('/giris');

        self::assertSame('nosniff', $response->headers['X-Content-Type-Options'] ?? null);
        self::assertSame('DENY', $response->headers['X-Frame-Options'] ?? null);
    }
}
