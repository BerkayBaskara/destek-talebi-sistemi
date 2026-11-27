<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\Enum\Role;
use App\Model\User;

/**
 * NFR-04, NFR-26, SEC-025: gerçek eşzamanlılık. Ayrı PHP süreçleri aynı anda (ortak başlama anında) aynı veri
 * klasörüne yazar; dosya kilidi ve karşılaştır-yaz kuralı çift atamayı ve kapasite aşımını önlemelidir.
 */
final class ConcurrencyTest extends FeatureTestCase
{
    /**
     * @param list<array{0: int, 1: int}> $jobs talep id, personel id
     * @return list<string> her sürecin çıktısı
     */
    private function runTogether(array $jobs): array
    {
        $worker = dirname(__DIR__) . '/Concurrency/take_worker.php';
        $startAt = microtime(true) + 1.5; // süreçlerin hepsi açılsın, sonra aynı anda başlasın
        $procs = [];
        foreach ($jobs as [$ticketId, $agentId]) {
            $cmd = [PHP_BINARY, $worker, $this->root, (string) $ticketId, (string) $agentId, sprintf('%.4F', $startAt)];
            $pipes = [];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($proc);
            $procs[] = [$proc, $pipes];
        }
        $out = [];
        foreach ($procs as [$proc, $pipes]) {
            $out[] = trim((string) stream_get_contents($pipes[1])) . trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }

        return $out;
    }

    private function poolTickets(int $count): void
    {
        $this->loginAs($this->registerCustomer(), self::PASSWORD);
        for ($i = 1; $i <= $count; $i++) {
            $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Havuz talebi ' . $i, 'description' => 'Personel yokken açılan talep.']);
        }
        $this->post('/cikis');
        self::assertCount($count, $this->kernel->ticketRepository()->getPoolTickets());
    }

    private function agent(string $name, int $capacity): User
    {
        $agent = $this->kernel->authService()->createStaff(Role::Agent, $name, 'Personel', self::PASSWORD);
        $agent->capacity = $capacity;

        return $this->kernel->userRepository()->save($agent);
    }

    /** NFR-04: on personel aynı talebi aynı anda alır; yalnız biri başarılı olur. */
    public function test_ten_agents_taking_the_same_ticket_at_once(): void
    {
        $this->seedCatalog();
        $this->poolTickets(1);
        $agents = [];
        for ($i = 1; $i <= 10; $i++) {
            $agents[] = $this->agent('Personel' . $i, 5);
        }

        $out = $this->runTogether(array_map(static fn (User $a): array => [1, (int) $a->id], $agents));

        self::assertSame(1, count(array_filter($out, static fn (string $o): bool => $o === 'ok')), implode(', ', $out));
        foreach ($out as $o) {
            self::assertContains($o, ['ok', 'App\Exception\ConflictException'], 'Beklenmeyen hata yok.');
        }
        $ticket = $this->kernel->ticketRepository()->findById(1);
        self::assertNotNull($ticket?->agentId);
    }

    /** NFR-26: kapasitesi 3 olan personel on talebi aynı anda almaya çalışır; en fazla 3'ü atanır. */
    public function test_capacity_is_not_exceeded_under_concurrent_takes(): void
    {
        $this->seedCatalog();
        $this->poolTickets(10);
        $agent = $this->agent('Zeynep', 3);

        $out = $this->runTogether(array_map(static fn (int $id): array => [$id, (int) $agent->id], range(1, 10)));

        self::assertSame(3, count(array_filter($out, static fn (string $o): bool => $o === 'ok')), implode(', ', $out));
        self::assertSame(3, $this->kernel->ticketRepository()->countActiveByAgent()[(int) $agent->id] ?? 0);
        self::assertCount(7, $this->kernel->ticketRepository()->getPoolTickets(), 'Kalan talepler havuzda.');
    }
}
