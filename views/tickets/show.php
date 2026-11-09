<?php
/** @var \App\Model\Ticket $ticket */
/** @var \App\Model\User $viewer */
/** @var array<int, string> $names */
/** @var \App\Model\Category|null $category */
/** @var \App\Model\SlaStatus $sla */
/** @var list<\App\Model\Reply> $replies */
/** @var list<\App\Model\TicketLog> $logs */
/** @var list<\App\Model\Attachment> $attachments */
/** @var list<string> $actions */
/** @var bool $canReply */
/** @var callable $csrfField */
/** @var \App\Core\View $view */

use App\Model\Enum\Role;

$actionLabels = [
    'start_review' => ['İncelemeye al', 'btn'],
    'resolve' => ['Çözüldü olarak işaretle', 'btn'],
    'approve' => ['Çözümü onayla', 'btn'],
    'reject' => ['Çözümü reddet', 'btn btn-light'],
    'cancel' => ['Talebi iptal et', 'btn btn-danger'],
];
$backUrl = $viewer->role === Role::Customer ? '/talepler' : ($viewer->role === Role::Agent ? '/kanban' : '/yonetici');
?>
<p><a href="<?= e($backUrl) ?>">&lsaquo; Geri dön</a></p>
<section class="card">
    <h1>Talep #<?= e($ticket->id) ?> · <?= e($ticket->title) ?></h1>
    <div class="meta-grid">
        <div><strong>Müşteri:</strong> <?= e($names[$ticket->creatorId] ?? '-') ?></div>
        <div><strong>Kategori:</strong> <?= e($category->name ?? '-') ?></div>
        <div><strong>Açılış:</strong> <?= e($ticket->createdAt->format('d.m.Y H:i')) ?></div>
        <div>
            <strong>Öncelik:</strong> <?= e($ticket->priority->label()) ?>
            <?php if ($ticket->priorityRaised()): ?>
                <span class="muted">(Sistem önceliği yükseltti; seçilen: <?= e($ticket->customerPriority->label()) ?>)</span>
            <?php endif; ?>
        </div>
        <div><strong>Durum:</strong> <?= $view->partial('partials/status_badge', ['status' => $ticket->status]) ?></div>
        <div><strong>Personel:</strong> <?= e($ticket->agentId !== null ? ($names[$ticket->agentId] ?? '-') : 'Ortak havuzda') ?></div>
        <div><strong>SLA bitişi:</strong> <?= e($ticket->deadline->format('d.m.Y H:i')) ?></div>
        <div><strong>SLA:</strong> <?= $view->partial('partials/sla_badge', ['sla' => $sla, 'ticketId' => $ticket->id]) ?></div>
        <?php if ($ticket->attemptCount > 0): ?>
            <div><strong>Reddedilen çözüm:</strong> <?= e($ticket->attemptCount) ?></div>
        <?php endif; ?>
    </div>

    <?php if ($actions !== []): ?>
        <div class="actions">
            <?php foreach ($actions as $action): ?>
                <form method="post" action="/talepler/<?= e($ticket->id) ?>/islem">
                    <?= $csrfField() ?>
                    <input type="hidden" name="islem" value="<?= e($action) ?>">
                    <button type="submit" class="<?= e($actionLabels[$action][1]) ?>"><?= e($actionLabels[$action][0]) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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

<section class="card" id="mesajlar">
    <h2>Mesajlar</h2>
    <?php foreach ($replies as $reply): ?>
        <div class="message<?= $reply->userId === $viewer->id ? ' mine' : '' ?>">
            <div class="muted"><strong><?= e($names[$reply->userId] ?? '-') ?></strong> · <?= e($reply->createdAt->format('d.m.Y H:i')) ?></div>
            <p><?= nl2br(e($reply->message)) ?></p>
        </div>
    <?php endforeach; ?>

    <?php if ($canReply): ?>
        <form method="post" action="/talepler/<?= e($ticket->id) ?>/yanit">
            <?= $csrfField() ?>
            <div class="form-row">
                <label for="mesaj">Yanıtınız</label>
                <textarea id="mesaj" name="mesaj" maxlength="5000" required placeholder="Mesajınızı yazın..."></textarea>
            </div>
            <button type="submit" class="btn">Yanıtı gönder</button>
        </form>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Durum geçmişi</h2>
    <ol class="timeline">
        <?php foreach ($logs as $log): ?>
            <li>
                <span class="muted"><?= e($log->changedAt->format('d.m.Y H:i')) ?></span> ·
                <strong><?= e($names[$log->userId] ?? '-') ?></strong> ·
                <?= e($log->oldStatus !== null ? $log->oldStatus->label() . ' → ' . $log->newStatus->label() : 'Talep oluşturuldu (' . $log->newStatus->label() . ')') ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
