<?php

declare(strict_types=1);

namespace App\Core\Session;

/** Bellekte tutulan oturum; testlerde gerçek PHP oturumu yerine kullanılır. */
final class ArraySession implements SessionInterface
{
    /** @var array<string, mixed> */
    public array $data = [];

    /** Testlerin, girişte oturum kimliğinin yenilendiğini doğrulayabilmesi için. */
    public bool $regenerated = false;

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $flash = is_array($this->data['_flash'] ?? null) ? $this->data['_flash'] : [];
        $flash[$key] = $value;
        $this->data['_flash'] = $flash;
    }

    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $flash = is_array($this->data['_flash'] ?? null) ? $this->data['_flash'] : [];
        $value = $flash[$key] ?? $default;
        unset($flash[$key]);
        $this->data['_flash'] = $flash;

        return $value;
    }

    public function regenerate(): void
    {
        $this->regenerated = true;
    }

    public function destroy(): void
    {
        $this->data = [];
    }
}
