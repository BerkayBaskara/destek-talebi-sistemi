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

    public function test_api_post_without_csrf_returns_json_403(): void
    {
        $response = $this->postJson('/api/deneme', ['a' => 1], withCsrf: false);

        self::assertSame(403, $response->status);
        self::assertSame('application/json; charset=utf-8', $response->headers['Content-Type'] ?? null);
        self::assertFalse(json_decode($response->body, true)['ok']);
    }

    public function test_home_renders_layout(): void
    {
        $response = $this->get('/');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('<title>Destek Talebi Sistemi', $response->body);
        self::assertStringContainsString('name="viewport"', $response->body);
    }

    public function test_html_responses_have_security_headers(): void
    {
        $response = $this->get('/');

        self::assertSame('nosniff', $response->headers['X-Content-Type-Options'] ?? null);
        self::assertSame('DENY', $response->headers['X-Frame-Options'] ?? null);
    }
}
