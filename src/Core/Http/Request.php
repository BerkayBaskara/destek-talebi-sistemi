<?php

declare(strict_types=1);

namespace App\Core\Http;

/** Gelen HTTP isteğinin değişmez kopyası. Controller'lar $_GET/$_POST yerine bunu okur. */
final class Request
{
    public readonly string $method;
    public readonly string $path;

    /** @var array<string, string> Başlık adları küçük harfle. */
    private array $headers = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, string> $headers
     */
    public function __construct(
        string $method,
        string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $files = [],
        array $headers = [],
        public readonly string $body = '',
    ) {
        $this->method = strtoupper($method);
        $path = '/' . trim($path, '/');
        $this->path = $path;
        foreach ($headers as $name => $value) {
            $this->headers[strtolower($name)] = $value;
        }
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && str_starts_with((string) $key, 'HTTP_')) {
                $name = str_replace('_', '-', substr((string) $key, 5));
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_LENGTH']) && is_scalar($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = (string) $_SERVER['CONTENT_LENGTH']; // HTTP_ öneki olmadan gelir
        }
        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
        $method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';

        return new self(
            $method,
            (string) parse_url($uri, PHP_URL_PATH),
            $_GET,
            $_POST,
            $_FILES,
            $headers,
            (string) file_get_contents('php://input'),
        );
    }

    /** Önce form (POST), sonra adres çubuğu (GET) parametresine bakar. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /** Metin girdisi; yoksa veya metin değilse boş dize. Baştaki/sondaki boşluklar kırpılır. */
    public function string(string $key): string
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : '';
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $data = json_decode($this->body, true);

        return is_array($data) ? $data : [];
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path, '/api/');
    }
}
