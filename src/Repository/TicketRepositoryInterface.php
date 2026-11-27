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

    /**
     * Kayıttaki durum ve personel beklenenle aynıysa talebi yazar (personeli değiştiren sistem işlemleri için:
     * SLA aşımında havuza alma, eskalasyon, müşteri kararı). Tek kilit içinde yapılır.
     */
    public function saveIfUnchanged(Ticket $ticket, \App\Model\Enum\TicketStatus $expectedStatus, ?int $expectedAgentId): bool;

    /**
     * saveIfUnchanged gibi, ama yalnız verilen alanları yazar; okuma ile yazma arasında başka alanlarda yapılan değişiklik korunur.
     *
     * @param list<string> $fields toArray() anahtarları
     */
    public function updateIfUnchanged(Ticket $ticket, \App\Model\Enum\TicketStatus $expectedStatus, ?int $expectedAgentId, array $fields): bool;

    /** 6 karakterli talep koduna göre (büyük harf). */
    public function findByCode(string $code): ?Ticket;

    /** @return list<Ticket> Görev Havuzu: kimseye atanmamış, Beklemede durumundaki talepler (eskiden yeniye). */
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
     * Alındı yapar, true döner; başka biri önce aldıysa ya da personelin aktif talep sayısı $capacity'ye
     * ulaştıysa false. Tek kilit içinde yapılır; böylece iki eşzamanlı istek aynı boş kapasiteyi kullanamaz.
     * Aşama 3 karşılığı: UPDATE ... WHERE agent_id IS NULL AND status = 'pending' (veya SELECT ... FOR UPDATE).
     */
    public function assignIfUnassigned(int $ticketId, int $agentId, int $capacity = PHP_INT_MAX): bool;
}
