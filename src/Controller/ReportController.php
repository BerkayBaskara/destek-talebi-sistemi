<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Kernel;
use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;
use App\Model\Ticket;

/** Ayrıntılı rapor sayfası ve CSV dışa aktarımı (yalnız yönetici). */
final class ReportController
{
    private const CLOSURES = [
        'customer_resolved' => 'Müşteri tarafından çözüldü',
        'auto_closed' => 'Otomatik tamamlandı',
        'unresolvable' => 'Çözülemedi',
    ];

    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function page(Request $request): Response
    {
        $this->kernel->requireRole(Role::Admin);
        $filters = $this->filters($request);
        $report = $this->kernel->detailedReportService();
        $tickets = $report->filter($filters);
        $byStatus = [];
        foreach (TicketStatus::cases() as $status) {
            $byStatus[$status->label()] = count(array_filter($tickets, static fn (Ticket $t): bool => $t->status === $status));
        }

        $page = \App\Service\TicketService::paginate($tickets, max(1, (int) $request->string('sayfa')), self::PER_PAGE);
        $params = array_filter($request->query, static fn ($v, $k): bool => is_string($v) && $v !== '' && $k !== 'sayfa', ARRAY_FILTER_USE_BOTH);

        return $this->kernel->render('admin/report', [
            'title' => 'Ayrıntılı Rapor',
            'filters' => $request->query,
            'tickets' => $page['items'],
            'page' => $page['page'],
            'pages' => $page['pages'],
            'pageParams' => array_map('strval', $params),
            'total' => count($tickets),
            'byStatus' => $byStatus,
            'perAgent' => $report->perAgent($tickets),
            'categories' => $this->kernel->categoryRepository()->findAll(),
            'priorities' => $this->kernel->priorityRepository()->findAll(),
            'agents' => $this->kernel->userRepository()->findByRole(Role::Agent),
            'names' => $this->names(),
            'format' => fn (int $minutes): string => $this->kernel->slaStatusService()->formatMinutes($minutes),
            'escalations' => $this->escalations($tickets),
            'query' => http_build_query(array_filter($request->query, static fn ($v): bool => is_string($v) && $v !== '')),
        ]);
    }

    public function csv(Request $request): Response
    {
        $this->kernel->requireRole(Role::Admin);
        $tickets = $this->kernel->detailedReportService()->filter($this->filters($request));
        $names = $this->names();
        $categories = [];
        foreach ($this->kernel->categoryRepository()->findAll() as $category) {
            $categories[(int) $category->id] = $category->name;
        }
        $priorities = [];
        foreach ($this->kernel->priorityRepository()->findAll() as $priority) {
            $priorities[(int) $priority->id] = $priority->name;
        }

        $rows = [['Talep Kodu', 'Konu', 'Kategori', 'Öncelik', 'Durum', 'Müşteri', 'Personel', 'Açılış', 'SLA Bitişi', 'Çözüm', 'SLA Sonucu', 'Kapanış Nedeni']];
        foreach ($tickets as $t) {
            $rows[] = [
                $t->displayCode(),
                $t->title,
                $categories[$t->categoryId] ?? '-',
                $priorities[$t->priorityId] ?? '-',
                $t->status->label(),
                $names[$t->creatorId] ?? '-',
                $t->agentId !== null ? ($names[$t->agentId] ?? '-') : 'Görev Havuzu',
                $t->createdAt->format('d.m.Y H:i'),
                $t->deadline->format('d.m.Y H:i'),
                $t->resolvedAt?->format('d.m.Y H:i') ?? '',
                $t->resolvedAt === null ? '' : (\App\Service\DetailedReportService::metFirstDeadline($t) ? 'Süre içinde' : 'Süre aşıldı'),
                self::CLOSURES[$t->closureReason ?? ''] ?? '',
            ];
        }

        $file = 'talep-raporu-' . $this->kernel->clock()->now()->format('Ymd-Hi') . '.csv';

        return new Response($this->kernel->detailedReportService()::csv($rows), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
        ]);
    }

    /** @return array{from: string, to: string, status: string, priority: int, category: int, agent: int} */
    private function filters(Request $request): array
    {
        return [
            'from' => $request->string('baslangic'),
            'to' => $request->string('bitis'),
            'status' => $request->string('durum'),
            'priority' => (int) $request->string('oncelik'),
            'category' => (int) $request->string('kategori'),
            'agent' => (int) $request->string('personel'),
        ];
    }

    /** NFR-33: talep tablosunda sayfa başına en fazla 50 kayıt; tamamı CSV'de. */
    public const PER_PAGE = 50;

    private const REASONS = [
        'sla_breach' => 'SLA aşıldı (beklemeden)',
        'sla_no_response' => 'SLA aşımında müşteri yanıt vermedi',
        'manual' => 'Manuel (personel)',
        'rejected_limit' => 'Çözüm 3 kez reddedildi',
        'customer_request' => 'Müşteri başka personel istedi',
        'previous_unavailable' => 'Önceki personel uygun değil',
    ];

    /**
     * RPT-011: süzülen taleplerin eskalasyon (devir) kayıtları, en yeni önce.
     *
     * @param list<Ticket> $tickets
     * @return list<array{at: string, code: string, ticketId: int, transfer: string, reason: string, note: string}>
     */
    private function escalations(array $tickets): array
    {
        $byId = [];
        foreach ($tickets as $ticket) {
            $byId[(int) $ticket->id] = $ticket;
        }
        $names = $this->names();
        $rows = [];
        foreach ($this->kernel->escalationRepository()->all() as $e) {
            $ticket = $byId[$e['ticket_id']] ?? null;
            if ($ticket === null) {
                continue;
            }
            $from = $e['from_agent_id'] !== null ? ($names[$e['from_agent_id']] ?? '-') : '-';
            $to = $e['to_agent_id'] !== null ? ($names[$e['to_agent_id']] ?? '-') : 'Yönetici müdahalesi';
            $rows[] = [
                'at' => $e['created_at'],
                'code' => $ticket->displayCode(),
                'ticketId' => (int) $ticket->id,
                'transfer' => $from . ' → ' . $to,
                'reason' => self::REASONS[$e['reason']] ?? $e['reason'],
                'note' => (string) $e['note'],
            ];
        }
        usort($rows, static fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return $rows;
    }

    /** @return array<int, string> */
    private function names(): array
    {
        $names = [];
        foreach ([Role::Customer, Role::Agent, Role::Admin] as $role) {
            foreach ($this->kernel->userRepository()->findByRole($role) as $user) {
                $names[(int) $user->id] = $user->fullName();
            }
        }

        return $names;
    }
}
