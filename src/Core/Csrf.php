<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Session\SessionInterface;

/**
 * CSRF koruması: her oturuma rastgele bir anahtar verilir; her form bu anahtarı gizli
 * alanda geri gönderir. Başka bir siteden gelen sahte istek anahtarı bilemez.
 */
final class Csrf
{
    private const KEY = '_csrf';

    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }

        return $token;
    }

    public function isValid(?string $token): bool
    {
        $expected = $this->session->get(self::KEY);

        return is_string($expected) && $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }
}
