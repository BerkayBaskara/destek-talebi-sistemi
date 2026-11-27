<?php

declare(strict_types=1);

namespace App\Core\Http;

/** Tarayıcıya gönderilecek yanıt. Controller'lar echo yapmaz, Response döner. */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    public static function redirect(string $url): self
    {
        return new self('', 302, ['Location' => $url]);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        return new self($this->body, $this->status, $headers + $this->headers);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
