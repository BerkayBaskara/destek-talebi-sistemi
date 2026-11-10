<?php
/**
 * @var array{
 *     total: int, byStatus: array<string, int>, byPriority: array<int, int>,
 *     warning: list<\App\Model\Ticket>, breached: list<\App\Model\Ticket>,
 *     workload: list<array{agent: \App\Model\User, active: int}>,
 *     avgResolutionMinutes: ?int, resolvedCount: int, metPercent: ?int
 * } $summary
 */
/** @var callable(int): string $format */

use App\Model\Enum\Priority;
use App\Model\Enum\TicketStatus;

$ticketList = static function (array $tickets): string {
    if ($tickets === []) {
        return '<p class="muted">Yok.</p>';
    }
    $html = '<ul class="plain">';
    foreach ($tickets as $t) {
        $html .= '<li><a href="/talepler/' . e($t->id) . '">#' . e($t->id) . ' · ' . e($t->title) . '</a> <span class="muted">(bitiş ' . e($t->deadline->format('d.m.Y H:i')) . ')</span></li>';
    }

    return $html . '</ul>';
};
?>
<section class="card">
    <div class="card-head">
        <h1>Özet Rapor</h1>
        <span class="muted">Aşama 2 · tüm zamanlar · <?= e($summary['total']) ?> talep</span>
    </div>
    <div class="stats">
        <div class="stat">
            <div class="muted">Ortalama Çözüm Süresi</div>
            <div class="stat-value"><?= $summary['avgResolutionMinutes'] === null ? '—' : e($format($summary['avgResolutionMinutes'])) ?></div>
            <div class="muted">iş saatiyle</div>
        </div>
        <div class="stat">
            <div class="muted">Çözülen Talep</div>
            <div class="stat-value"><?= e($summary['resolvedCount']) ?></div>
            <div class="muted">süresinde: <?= $summary['metPercent'] === null ? '—' : '%' . e($summary['metPercent']) ?></div>
        </div>
        <div class="stat stat-warning">
            <div class="muted">SLA Uyarısında (%80+)</div>
            <div class="stat-value"><?= e(count($summary['warning'])) ?></div>
        </div>
        <div class="stat stat-danger">
            <div class="muted">SLA Aşılmış</div>
            <div class="stat-value"><?= e(count($summary['breached'])) ?></div>
        </div>
    </div>
</section>

<div class="grid-2">
    <section class="card">
        <h2>Durumlara Göre</h2>
        <table class="table">
            <thead><tr><th>Durum</th><th>Talep</th></tr></thead>
            <tbody>
            <?php foreach ($summary['byStatus'] as $value => $count): ?>
                <tr><td data-label="Durum"><?= e(TicketStatus::from($value)->label()) ?></td><td data-label="Talep"><?= e($count) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section class="card">
        <h2>Önceliğe Göre</h2>
        <table class="table">
            <thead><tr><th>Öncelik</th><th>Talep</th></tr></thead>
            <tbody>
            <?php foreach ($summary['byPriority'] as $value => $count): ?>
                <tr><td data-label="Öncelik"><?= e(Priority::from($value)->label()) ?></td><td data-label="Talep"><?= e($count) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>

<section class="card">
    <h2>Personel İş Yükü</h2>
    <table class="table">
        <thead><tr><th>Personel</th><th>Aktif talep (Alındı + İnceleniyor)</th></tr></thead>
        <tbody>
        <?php foreach ($summary['workload'] as $row): ?>
            <tr><td data-label="Personel"><?= e($row['agent']->fullName()) ?></td><td data-label="Aktif"><?= e($row['active']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<div class="grid-2">
    <section class="card">
        <h2>SLA Aşılmış Talepler</h2>
        <?= $ticketList($summary['breached']) ?>
    </section>
    <section class="card">
        <h2>SLA Uyarısındaki Talepler</h2>
        <?= $ticketList($summary['warning']) ?>
    </section>
</div>
