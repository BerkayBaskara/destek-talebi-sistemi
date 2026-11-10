<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Storage\JsonStore;

/** Her test için boş, geçici bir JSON veri klasörü. */
trait TempStoreTrait
{
    private string $tempDir;

    private function makeStore(): JsonStore
    {
        $this->tempDir = sys_get_temp_dir() . '/ts_' . bin2hex(random_bytes(4));

        return new JsonStore($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempDir) && is_dir($this->tempDir)) {
            foreach (glob($this->tempDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->tempDir);
        }
    }
}
