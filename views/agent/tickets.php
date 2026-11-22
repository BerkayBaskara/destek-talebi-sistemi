<?php
/** @var list<array{ticket: \App\Model\Ticket, sla: \App\Model\SlaStatus}> $rows */
/** @var \App\Core\View $view */
?>
<section class="card">
    <div class="card-head">
        <h1>Görevlerim</h1>
        <a class="btn btn-light" href="/kanban">Kanban görünümü</a>
    </div>
    <?php if ($rows === []): ?>
        <p class="muted">Size atanmış talep yok. <a href="/havuz">Talep Havuzu</a> sayfasına bakabilirsiniz.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>ID</th><th>Konu Başlığı</th><th>Öncelik</th><th>Durum</th><th>SLA Kalan Süre</th></tr></thead>
            <tbody>
            <?php foreach ($rows as ['ticket' => $ticket, 'sla' => $sla]): ?>
                <tr class="row-link row-<?= e($sla->level) ?>" data-href="/talepler/<?= e($ticket->id) ?>">
                    <td data-label="ID">#<?= e($ticket->id) ?></td>
                    <td data-label="Konu"><a href="/talepler/<?= e($ticket->id) ?>"><?= e($ticket->title) ?></a></td>
                    <td data-label="Öncelik"><?= $view->partial('partials/priority', ['ticket' => $ticket]) ?></td>
                    <td data-label="Durum"><?= $view->partial('partials/status_badge', ['status' => $ticket->status]) ?></td>
                    <td data-label="SLA"><?= $view->partial('partials/sla_badge', ['sla' => $sla, 'ticketId' => $ticket->id]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<script src="/assets/sla.js" defer></script>
