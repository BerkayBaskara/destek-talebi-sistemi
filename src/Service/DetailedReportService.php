<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;
use App\Model\User;
use App\Repository\TicketRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use DateTimeImmutable;

/**
 * Ayrıntılı rapor (FR-12): tarih, durum, öncelik, kategori ve personele göre süzülmüş talepler, personel bazında
 * SLA uyum oranı ve ortalama çözüm süresi; aynı süzgeçle CSV dışa aktarımı.
 */
final class DetailedReportService
{
    public function __construct(
        private readonly TicketRepositoryInterface $tickets,
        private readonly UserRepositoryInterface $users,
        private readonly SlaCalculatorService $calculator,
    ) {
    }

    /**
     * @param array{from?: string, to?: string, status?: string, priority?: int, category?: int, agent?: int} $filters
     * @return list<Ticket> yeniden eskiye
     */
    public function filter(array $filters): array
    {
        $status = TicketStatus::tryFrom($filters['status'] ?? '');
        $from = self::day($filters['from'] ?? '', '00:00:00');
        $to = self::day($filters['to'] ?? '', '23:59:59');

        $items = array_values(array_filter($this->tickets->findAll(), static function (Ticket $t) use ($filters, $status, $from, $to): bool {
            return ($status === null || $t->status === $status)
                && (($filters['priority'] ?? 0) === 0 || $t->priorityId === $filters['priority'])
                && (($filters['category'] ?? 0) === 0 || $t->categoryId === $filters['category'])
                && (($filters['agent'] ?? 0) === 0 || $t->agentId === $filters['agent'])
                && ($from === null || $t->createdAt >= $from)
                && ($to === null || $t->createdAt <= $to);
        }));
        usort($items, static fn (Ticket $a, Ticket $b): int => [$b->createdAt, $b->id] <=> [$a->createdAt, $a->id]);

        return $items;
    }

    /**
     * Personel bazında: üzerindeki talep, çözülen, SLA içinde çözülme oranı, ortalama çözüm süresi (iş dakikası).
     *
     * @param list<Ticket> $tickets
     * @return list<array{agent: User, total: int, resolved: int, metPercent: ?int, avgMinutes: ?int}>
     */
    public function perAgent(array $tickets): array
    {
        $rows = [];
        foreach ($this->users->findByRole(Role::Agent) as $agent) {
            $own = array_filter($tickets, static fn (Ticket $t): bool => $t->agentId === $agent->id);
            $resolved = array_filter($own, static fn (Ticket $t): bool => $t->resolvedAt !== null);
            $met = array_filter($resolved, static fn (Ticket $t): bool => self::metFirstDeadline($t));
            $minutes = array_map(fn (Ticket $t): int => $this->calculator->businessMinutesBetween($t->createdAt, $t->resolvedAt ?? $t->createdAt), $resolved);
            $rows[] = [
                'agent' => $agent,
                'total' => count($own),
                'resolved' => count($resolved),
                'metPercent' => $resolved !== [] ? (int) round(count($met) * 100 / count($resolved)) : null,
                'avgMinutes' => $minutes !== [] ? (int) round(array_sum($minutes) / count($minutes)) : null,
            ];
        }

        return $rows;
    }

    /** SLA uyumu ilk SLA bitişine göre (ek SLA ile uzayan bitiş "süresinde" sayılmaz). */
    public static function metFirstDeadline(Ticket $ticket): bool
    {
        return $ticket->resolvedAt !== null && $ticket->resolvedAt <= $ticket->firstDeadline();
    }

    /**
     * Excel'in Türkçe ayarıyla açılan CSV (UTF-8 BOM, noktalı virgül). Formül gibi başlayan hücreler ' ile korunur.
     *
     * @param list<list<string>> $rows ilk satır başlık
     */
    public static function csv(array $rows): string
    {
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $out .= implode(';', array_map(static function (string $cell): string {
                if ($cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                    $cell = "'" . $cell;
                }

                return strpbrk($cell, ";\"\r\n") !== false ? '"' . str_replace('"', '""', $cell) . '"' : $cell;
            }, $row)) . "\r\n";
        }

        return $out;
    }

    private static function day(string $value, string $time): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), new \DateTimeZone('Europe/Istanbul'));
        if ($date === false || $date->format('Y-m-d') !== trim($value)) {
            return null;
        }

        return new DateTimeImmutable(trim($value) . ' ' . $time, new \DateTimeZone('Europe/Istanbul'));
    }
}
