<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->add('GET', '/talepler/{id}', static function (Request $r, array $params): Response {
            return new Response('talep ' . $params['id']);
        });
        $this->router->add('GET', '/', static fn (Request $r, array $p): Response => new Response('ana sayfa'));
    }

    public function test_matches_numeric_param(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/talepler/42'));

        self::assertSame('talep 42', $response->body);
    }

    public function test_param_rejects_non_numeric(): void
    {
        try {
            $this->router->dispatch(new Request('GET', '/talepler/abc'));
            self::fail('404 bekleniyordu.');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status);
        }
    }

    public function test_wrong_method_is_405(): void
    {
        try {
            $this->router->dispatch(new Request('POST', '/talepler/42'));
            self::fail('405 bekleniyordu.');
        } catch (HttpException $e) {
            self::assertSame(405, $e->status);
        }
    }

    public function test_trailing_slash_is_ignored(): void
    {
        self::assertSame('talep 7', $this->router->dispatch(new Request('GET', '/talepler/7/'))->body);
        self::assertSame('ana sayfa', $this->router->dispatch(new Request('GET', '/'))->body);
    }
}
