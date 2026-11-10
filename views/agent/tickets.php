<?php
/** @var list<array{ticket: \App\Model\Ticket, sla: \App\Model\SlaStatus}> $rows */
/** @var \App\Core\View $view */
?>
<section class="card">
    <div class="card-head">
        <h1>Bana Atanan Talepler</h1>
        <a class="btn btn-light" href="/kanban">Kanban görünümü</a>
    </div>
    <?php if ($rows === []): ?>
        <p class="muted">Size atanmış talep yok. <a href="/havuz">Ortak havuza</a> bakabilirsiniz.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>ID</th><th>Konu Başlığı</th><th>Öncelik</th><th>Durum</th><th>SLA Kalan Süre</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($rows as ['ticket' => $ticket, 'sla' => $sla]): ?>
                <tr class="row-<?= e($sla->level) ?>">
                    <td data-label="ID">#<?= e($ticket->id) ?></td>
                    <td data-label="Konu"><?= e($ticket->title) ?></td>
                    <td data-label="Öncelik"><?= e($ticket->priority->label()) ?></td>
                    <td data-label="Durum"><?= $view->partial('partials/status_badge', ['status' => $ticket->status]) ?></td>
                    <td data-label="SLA"><?= $view->partial('partials/sla_badge', ['sla' => $sla, 'ticketId' => $ticket->id]) ?></td>
                    <td><a class="btn<?= $ticket->status->isFinal() ? ' btn-light' : '' ?>" href="/talepler/<?= e($ticket->id) ?>"><?= $ticket->status->isFinal() ? 'İncele' : 'Yanıtla' ?> &rsaquo;</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<script src="/assets/sla.js" defer></script>
