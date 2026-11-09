<?php

declare(strict_types=1);

namespace App\Core\Session;

/** PHP'nin kendi oturumu ($_SESSION). Çerez yalnız HTTP'den okunur, başka siteden gönderilmez. */
final class NativeSession implements SessionInterface
{
    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
            session_start();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $flash = is_array($_SESSION['_flash'] ?? null) ? $_SESSION['_flash'] : [];
        $flash[$key] = $value;
        $_SESSION['_flash'] = $flash;
    }

    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $flash = is_array($_SESSION['_flash'] ?? null) ? $_SESSION['_flash'] : [];
        $value = $flash[$key] ?? $default;
        unset($flash[$key]);
        $_SESSION['_flash'] = $flash;

        return $value;
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /** Oturum verisini siler ve yeni kimlik verir; oturum açık kalır ki çıkış mesajı gösterilebilsin. */
    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
