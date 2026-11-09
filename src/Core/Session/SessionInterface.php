<?php

declare(strict_types=1);

namespace App\Core\Session;

/** Oturum verisine erişim. Uygulamada $_SESSION, testlerde bellek kullanılır. */
interface SessionInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /** Yalnız bir sonraki sayfada gösterilecek mesaj/veri bırakır. */
    public function flash(string $key, mixed $value): void;

    /** Flash veriyi okur ve siler. */
    public function pullFlash(string $key, mixed $default = null): mixed;

    /** Oturum kimliğini yeniler (girişte oturum sabitleme saldırısına karşı). */
    public function regenerate(): void;

    public function destroy(): void;
}
