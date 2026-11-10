<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Ticket;

/** Talep verisine erişim (sınıf diyagramı: TicketRepositoryInterface). Aşama 3'te MySqlTicketRepository. */
interface TicketRepositoryInterface
{
    /** id yoksa ekler, varsa günceller. */
    public function save(Ticket $ticket): Ticket;

    /**
     * Eşzamanlılık güvenli güncelleme: kayıttaki durum hâlâ $expectedOld ve atanan personel değişmemişse yazar,
     * true döner; bu arada başka bir istek talebi değiştirdiyse hiçbir şey yazmaz, false döner.
     * Aşama 3 karşılığı: UPDATE ... WHERE id = ? AND status = ? AND agent_id <=> ?
     */
    public function saveIfStatus(Ticket $ticket, \App\Model\Enum\TicketStatus $expectedOld): bool;

    public function findById(int $id): ?Ticket;

    /** @return list<Ticket> Ortak havuz: kimseye atanmamış, Bekleniyor durumundaki talepler (eskiden yeniye). */
    public function getPoolTickets(): array;

    /** @return list<Ticket> */
    public function findByCreator(int $creatorId): array;

    /** @return list<Ticket> */
    public function findByAgent(int $agentId): array;

    /** @return list<Ticket> */
    public function findAll(): array;

    /** @return array<int, int> personel id => aktif (Alındı + İnceleniyor) talep sayısı */
    public function countActiveByAgent(): array;

    /**
     * Eşzamanlılık güvenli atama (NFR-04): talep hâlâ Bekleniyor ve sahipsizse personele atar ve
     * Alındı yapar, true döner; başka biri önce aldıysa false. Tek kilit içinde yapılır.
     * Aşama 3 karşılığı: UPDATE ... WHERE agent_id IS NULL AND status = 'pending' (veya SELECT ... FOR UPDATE).
     */
    public function assignIfUnassigned(int $ticketId, int $agentId): bool;
}
