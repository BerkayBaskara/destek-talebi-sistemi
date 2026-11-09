<?php
/** @var \App\Model\Ticket $ticket */
/** @var \App\Model\User $viewer */
/** @var \App\Model\Category|null $category */
/** @var \App\Model\SlaStatus $sla */
/** @var list<\App\Model\Reply> $replies */
/** @var list<\App\Model\Attachment> $attachments */
/** @var \App\Core\View $view */
?>
<p><a href="javascript:history.back()">&lsaquo; Geri dön</a></p>
<section class="card">
    <h1>Talep #<?= e($ticket->id) ?> · <?= e($ticket->title) ?></h1>
    <div class="meta-grid">
        <div><strong>Kategori:</strong> <?= e($category->name ?? '-') ?></div>
        <div><strong>Açılış:</strong> <?= e($ticket->createdAt->format('d.m.Y H:i')) ?></div>
        <div><strong>Öncelik:</strong> <?= e($ticket->priority->label()) ?></div>
        <div><strong>Durum:</strong> <?= $view->partial('partials/status_badge', ['status' => $ticket->status]) ?></div>
        <div><strong>SLA:</strong> <?= $view->partial('partials/sla_badge', ['sla' => $sla, 'ticketId' => $ticket->id]) ?></div>
    </div>
</section>
<?php if ($attachments !== []): ?>
<section class="card">
    <h2>Ekler</h2>
    <ul class="attachments">
        <?php foreach ($attachments as $attachment): ?>
            <li><a href="/ekler/<?= e($attachment->id) ?>" target="_blank" rel="noopener">📎 ek-<?= e($attachment->id) ?> (<?= e($attachment->fileType) ?>)</a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
<section class="card">
    <h2>Mesajlar</h2>
    <?php foreach ($replies as $reply): ?>
        <div class="message">
            <div class="muted"><?= e($reply->createdAt->format('d.m.Y H:i')) ?></div>
            <p><?= nl2br(e($reply->message)) ?></p>
        </div>
    <?php endforeach; ?>
</section>
