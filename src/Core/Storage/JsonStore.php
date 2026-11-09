<?php

declare(strict_types=1);

namespace App\Core\Storage;

use JsonException;

/**
 * Aşama 2'nin "veritabanı": her tablo storage/data altında ayrı bir JSON dosyasıdır
 * (users.json, tickets.json ...). Aşama 3'te bunun yerini PDO + MySQL alacak.
 *
 * Eşzamanlılık: her tablonun yanında bir .lock dosyası vardır ve flock() ile kilitlenir.
 * Okuma paylaşımlı (LOCK_SH), yazma özel (LOCK_EX) kilitle yapılır. Böylece iki istek
 * aynı anda aynı dosyayı bozamaz.
 */
final class JsonStore
{
    private const SEQUENCES = '_sequences';

    /** @var array<string, resource> Bu nesnenin şu an tuttuğu özel kilitler (tablo => dosya tanıtıcısı). */
    private array $heldLocks = [];

    public function __construct(private readonly string $dataDir)
    {
        if (!is_dir($this->dataDir) && !mkdir($this->dataDir, 0775, true) && !is_dir($this->dataDir)) {
            throw new StorageException('Veri klasörü oluşturulamadı: ' . $this->dataDir);
        }
    }

    /** @return list<array<string, mixed>> */
    public function all(string $table): array
    {
        if (isset($this->heldLocks[$table])) {
            return $this->read($table);
        }

        $handle = $this->openLock($table);
        flock($handle, LOCK_SH);
        try {
            return $this->read($table);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array<string, mixed>|null */
    public function find(string $table, int $id): ?array
    {
        foreach ($this->all($table) as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Satırı ekler ve ona sıradaki id'yi verir.
     *
     * @param array<string, mixed> $row
     */
    public function insert(string $table, array $row): int
    {
        return $this->transaction([$table, self::SEQUENCES], function () use ($table, $row): int {
            $rows = $this->read($table);
            $sequences = $this->read(self::SEQUENCES)[0] ?? [];

            $maxId = 0;
            foreach ($rows as $existing) {
                $maxId = max($maxId, (int) ($existing['id'] ?? 0));
            }
            $id = max($maxId, (int) ($sequences[$table] ?? 0)) + 1;

            $rows[] = ['id' => $id] + $row;
            $sequences[$table] = $id;

            $this->write($table, $rows);
            $this->write(self::SEQUENCES, [$sequences]);

            return $id;
        });
    }

    /**
     * Var olan satırın verilen alanlarını değiştirir (diğer alanlar korunur).
     *
     * @param array<string, mixed> $changes
     */
    public function update(string $table, int $id, array $changes): void
    {
        $this->transaction([$table], function () use ($table, $id, $changes): void {
            $rows = $this->read($table);
            foreach ($rows as $i => $row) {
                if (($row['id'] ?? null) === $id) {
                    $rows[$i] = array_merge($row, $changes, ['id' => $id]);
                    $this->write($table, $rows);

                    return;
                }
            }

            throw new StorageException(sprintf('Kayıt bulunamadı: %s #%d', $table, $id));
        });
    }

    /**
     * Verilen tabloları özel kilitle kilitleyip $fn'i çalıştırır.
     * Kilitler her zaman alfabetik sırayla alınır; böylece iki istek birbirini
     * sonsuza kadar bekleyemez (deadlock olmaz). Zaten tutulan kilit tekrar alınmaz.
     *
     * @template T
     * @param list<string> $tables
     * @param callable(JsonStore): T $fn
     * @return T
     */
    public function transaction(array $tables, callable $fn): mixed
    {
        $tables = array_values(array_unique($tables));
        sort($tables);

        $acquired = [];
        try {
            foreach ($tables as $table) {
                if (isset($this->heldLocks[$table])) {
                    continue;
                }
                $handle = $this->openLock($table);
                flock($handle, LOCK_EX);
                $this->heldLocks[$table] = $handle;
                $acquired[] = $table;
            }

            return $fn($this);
        } finally {
            foreach (array_reverse($acquired) as $table) {
                flock($this->heldLocks[$table], LOCK_UN);
                fclose($this->heldLocks[$table]);
                unset($this->heldLocks[$table]);
            }
        }
    }

    /** @return resource */
    private function openLock(string $table)
    {
        $handle = fopen($this->path($table) . '.lock', 'c');
        if ($handle === false) {
            throw new StorageException('Kilit dosyası açılamadı: ' . $table);
        }

        return $handle;
    }

    /** @return list<array<string, mixed>> */
    private function read(string $table): array
    {
        $path = $this->path($table) . '.json';
        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new StorageException('Veri dosyası okunamadı: ' . $table);
        }
        if (trim($content) === '') {
            return [];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new StorageException('Veri dosyası okunamadı: ' . $table);
        }
        if (!is_array($data) || !array_is_list($data)) {
            throw new StorageException('Veri dosyası okunamadı: ' . $table);
        }

        /** @var list<array<string, mixed>> $data */
        return $data;
    }

    /**
     * Önce geçici dosyaya yazar, sonra yerine taşır: yarım yazılmış dosya kalmaz.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function write(string $table, array $rows): void
    {
        $path = $this->path($table) . '.json';
        $tmp = $path . '.tmp';
        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($tmp, $json . "\n") === false || !rename($tmp, $path)) {
            throw new StorageException('Veri dosyası yazılamadı: ' . $table);
        }
    }

    private function path(string $table): string
    {
        if (preg_match('/^[a-z_]+$/', $table) !== 1) {
            throw new StorageException('Geçersiz tablo adı: ' . $table);
        }

        return $this->dataDir . DIRECTORY_SEPARATOR . $table;
    }
}
