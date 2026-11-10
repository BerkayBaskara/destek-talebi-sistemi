<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * URL'yi ve HTTP yöntemini doğru controller metoduna yönlendirir.
 * Kalıpta {id} gibi parametreler yalnız rakam eşler: /talepler/{id} → /talepler/42.
 * İş kuralı içermez.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable(Request, array<string, string>): Response}> */
    private array $routes = [];

    /** @param callable(Request, array<string, string>): Response $handler */
    public function add(string $method, string $pattern, callable $handler): void
    {
        $pattern = '/' . trim($pattern, '/');
        $regex = preg_replace('/\\\{([a-z_]+)\\\}/', '(?P<$1>\d+)', preg_quote($pattern, '#'));
        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return ($route['handler'])($request, $params);
        }

        if ($pathMatched) {
            throw new HttpException(405, 'Bu adres bu yöntemle kullanılamaz.');
        }

        throw new HttpException(404, 'Sayfa bulunamadı.');
    }
}
