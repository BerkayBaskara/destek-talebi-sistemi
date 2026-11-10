<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Storage\JsonStore;
use App\Core\Storage\StorageException;
use PHPUnit\Framework\TestCase;

final class JsonStoreTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/js_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function test_insert_assigns_incrementing_ids(): void
    {
        $store = new JsonStore($this->dir);

        self::assertSame(1, $store->insert('users', ['name' => 'Ali']));
        self::assertSame(2, $store->insert('users', ['name' => 'Ayşe']));
        self::assertCount(2, $store->all('users'));
        self::assertSame(2, $store->find('users', 2)['id'] ?? null);
        self::assertSame('Ayşe', $store->find('users', 2)['name'] ?? null);
        self::assertNull($store->find('users', 99));
    }

    public function test_update_merges_fields(): void
    {
        $store = new JsonStore($this->dir);
        $store->insert('users', ['name' => 'A', 'role' => 'customer']);

        $store->update('users', 1, ['name' => 'B']);

        self::assertSame(['id' => 1, 'name' => 'B', 'role' => 'customer'], $store->find('users', 1));
    }

    public function test_update_missing_row_throws(): void
    {
        $store = new JsonStore($this->dir);

        $this->expectException(StorageException::class);
        $store->update('users', 5, ['name' => 'X']);
    }

    public function test_missing_or_empty_file_reads_as_empty_list(): void
    {
        $store = new JsonStore($this->dir);
        self::assertSame([], $store->all('users'));

        file_put_contents($this->dir . '/users.json', "  \n");
        self::assertSame([], $store->all('users'));
    }

    public function test_corrupt_file_throws_and_is_not_overwritten(): void
    {
        $path = $this->dir . '/users.json';
        file_put_contents($path, '[{"id":1,');
        $store = new JsonStore($this->dir);

        try {
            $store->all('users');
            self::fail('Bozuk dosya için istisna bekleniyordu.');
        } catch (StorageException $e) {
            self::assertStringContainsString('Veri dosyası okunamadı: users', $e->getMessage());
        }

        try {
            $store->insert('users', ['name' => 'X']);
            self::fail('Bozuk dosyaya ekleme için istisna bekleniyordu.');
        } catch (StorageException) {
        }

        self::assertSame('[{"id":1,', file_get_contents($path));
    }

    public function test_transaction_conditional_update_only_first_wins(): void
    {
        (new JsonStore($this->dir))->insert('tickets', ['status' => 'pending', 'agent_id' => null]);

        $claim = static function (JsonStore $store, int $agentId): bool {
            return (bool) $store->transaction(['tickets'], static function (JsonStore $s) use ($agentId): bool {
                $row = $s->find('tickets', 1);
                if ($row === null || $row['agent_id'] !== null) {
                    return false;
                }
                $s->update('tickets', 1, ['agent_id' => $agentId, 'status' => 'taken']);

                return true;
            });
        };

        self::assertTrue($claim(new JsonStore($this->dir), 7));
        self::assertFalse($claim(new JsonStore($this->dir), 9));
        self::assertSame(7, (new JsonStore($this->dir))->find('tickets', 1)['agent_id'] ?? null);
    }

    public function test_unicode_is_stored_readably(): void
    {
        $store = new JsonStore($this->dir);
        $store->insert('categories', ['name' => 'Ağ / Erişim']);

        self::assertStringContainsString('Ağ / Erişim', (string) file_get_contents($this->dir . '/categories.json'));
    }
}
