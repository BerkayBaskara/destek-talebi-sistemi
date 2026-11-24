<?php
/** @var \App\Model\Ticket $ticket */
/** @var \App\Model\User $viewer */
/** @var array<int, string> $names */
/** @var \App\Model\Category|null $category */
/** @var \App\Model\SlaStatus $sla */
/** @var list<\App\Model\Reply> $replies */
/** @var list<\App\Model\TicketLog> $logs */
/** @var list<\App\Model\TicketActivity> $activities */
/** @var list<\App\Model\Attachment> $attachments */
/** @var list<string> $actions */
/** @var bool $canReply */
/** @var bool $canEscalate */
/** @var string $categoryLabel */
/** @var bool $canAsk */
/** @var int $questionsLeft */
/** @var list<array{agent: \App\Model\User, load: int, capacity: int}> $assignableAgents */
/** @var string|null $replyBlockedMessage */
/** @var callable $csrfField */
/** @var \App\Core\View $view */

use App\Model\Enum\Role;
use App\Model\Enum\TicketStatus;

// Eylem → düğme metni, sınıfı ve eylemden sonraki durum (onay penceresinde gösterilir)
$actionLabels = [
    'start_review' => ['İncelemeye al', 'btn', TicketStatus::InReview],
    'resolve' => ['Çözüldü olarak işaretle', 'btn', TicketStatus::Resolved],
    'approve' => ['İşe yaradı', 'btn btn-new', TicketStatus::Closed],
    'reject' => ['İşe yaramadı, aynı personelle devam', 'btn btn-light', TicketStatus::InReview],
    'reject_other' => ['İşe yaramadı, başka personel istiyorum', 'btn btn-light', TicketStatus::Taken],
    'cancel' => ['Talebi iptal et', 'btn btn-danger', TicketStatus::Cancelled],
];
$backUrl = $viewer->role === Role::Customer ? '/talepler' : ($viewer->role === Role::Agent ? '/kanban' : '/yonetici');
$date = static fn (\DateTimeImmutable $d): string => $d->format('d.m.Y H:i');
$closureReasons = [
    'customer_resolved' => 'Müşteri tarafından çözüldü',
    'auto_closed' => 'Otomatik tamamlandı (müşteri yanıt vermedi)',
    'unresolvable' => 'Çözülemedi',
];
?>
<p><a href="<?= e($backUrl) ?>">&lsaquo; Geri dön</a></p>
<section class="card">
    <h1>Talep <?= e($ticket->displayCode()) ?> · <?= e($ticket->title) ?></h1>
    <div class="detail-grid">
        <dl class="detail-list">
            <?php if ($viewer->role !== Role::Customer): ?>
                <div><dt>Müşteri</dt><dd><?= e($names[$ticket->creatorId] ?? '-') ?></dd></div>
            <?php endif; ?>
            <div><dt>Kategori</dt><dd><?= e($categoryLabel) ?></dd></div>
            <div><dt>Öncelik</dt><dd><?= $view->partial('partials/priority', ['ticket' => $ticket]) ?></dd></div>
            <div><dt>Oluşturulma Tarihi</dt><dd><?= e($date($ticket->createdAt)) ?></dd></div>
            <div><dt>Başlangıç Tarihi</dt><dd><?= e($date($ticket->createdAt)) ?></dd></div>
            <?php foreach ($ticket->extraFields as $label => $value): ?>
                <div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <dl class="detail-list">
            <div><dt>Durum</dt><dd><?= $view->partial('partials/status_badge', ['status' => $ticket->status]) ?></dd></div>
            <div><dt>Atanan Personel</dt><dd><?= e($ticket->agentId !== null ? ($names[$ticket->agentId] ?? '-') : 'Görev Havuzu') ?></dd></div>
            <div><dt>Bitiş Tarihi</dt><dd><?= e($date($ticket->deadline)) ?></dd></div>
            <div><dt>Kalan Süre</dt><dd><?= $view->partial('partials/sla_badge', ['sla' => $sla, 'ticketId' => $ticket->id]) ?></dd></div>
            <?php if ($ticket->attemptCount > 0): ?>
                <div><dt>Reddedilen Çözüm</dt><dd><?= e($ticket->attemptCount) ?></dd></div>
            <?php endif; ?>
            <?php if ($ticket->firstDeadline()->getTimestamp() !== $ticket->deadline->getTimestamp()): ?>
                <div><dt>İlk SLA Bitişi</dt><dd><?= e($date($ticket->firstDeadline())) ?></dd></div>
            <?php endif; ?>
            <?php if ($ticket->closureReason !== null): ?>
                <div><dt>Kapanış Nedeni</dt><dd><?= e($closureReasons[$ticket->closureReason] ?? $ticket->closureReason) ?></dd></div>
            <?php endif; ?>
        </dl>
    </div>

    <?php if ($ticket->holdUntil !== null && $ticket->status === TicketStatus::Pending && $viewer->id === $ticket->creatorId): ?>
        <div class="decision-box" role="region" aria-label="Karar bekleniyor">
            <h2>Talebinizin çözüm süresi aşıldı</h2>
            <p>Ne yapmak istersiniz? <?= e($ticket->holdUntil->format('d.m.Y H:i')) ?> tarihine kadar seçim yapmazsanız talebiniz üst seviye personele aktarılır.</p>
            <div class="actions">
                <form method="post" action="/talepler/<?= e($ticket->id) ?>/karar" data-confirm="Talebiniz aynı personelle ve ek süreyle devam edecek. Onaylıyor musunuz?">
                    <?= $csrfField() ?>
                    <input type="hidden" name="karar" value="devam">
                    <button type="submit" class="btn">Aynı personelle devam et</button>
                </form>
                <form method="post" action="/talepler/<?= e($ticket->id) ?>/karar" data-confirm="Talebiniz &quot;sorun çözüldü&quot; olarak kapatılacak. Onaylıyor musunuz?">
                    <?= $csrfField() ?>
                    <input type="hidden" name="karar" value="cozuldu">
                    <button type="submit" class="btn btn-new">Sorun çözüldü, talebi kapat</button>
                </form>
            </div>
        </div>
    <?php elseif ($ticket->holdUntil !== null): ?>
        <p class="alert alert-warning">SLA aşıldı; müşterinin kararı bekleniyor (<?= e($ticket->holdUntil->format('d.m.Y H:i')) ?> tarihine kadar).</p>
    <?php elseif ($ticket->awaitingAdmin): ?>
        <p class="alert alert-error">Uygun üst seviye personel bulunamadı; talep yönetici müdahalesi bekliyor.</p>
    <?php endif; ?>

    <?php if ($actions !== []): ?>
        <div class="actions">
            <?php foreach ($actions as $action): ?>
                <?php [$label, $class, $target] = $actionLabels[$action]; ?>
                <form method="post" action="/talepler/<?= e($ticket->id) ?>/islem" data-confirm="Bu talebin durumunu «<?= e($target->label()) ?>» olarak değiştirmek istiyor musunuz?">
                    <?= $csrfField() ?>
                    <input type="hidden" name="islem" value="<?= e($action) ?>">
                    <button type="submit" class="<?= e($class) ?>"><?= e($label) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($canEscalate || $assignableAgents !== [] || ($viewer->role === Role::Admin && !$ticket->status->isFinal())): ?>
<section class="card">
    <h2><?= $viewer->role === Role::Admin ? 'Yönetici işlemleri' : 'Üst seviyeye aktarma' ?></h2>
    <?php if ($canEscalate): ?>
        <form method="post" action="/talepler/<?= e($ticket->id) ?>/eskale" class="filters" data-confirm="Talep bir üst seviye personele aktarılacak. Onaylıyor musunuz?">
            <?= $csrfField() ?>
            <div class="grow">
                <label for="eskale-gerekce">Üst seviyeye aktarma gerekçesi</label>
                <input type="text" id="eskale-gerekce" name="gerekce" maxlength="300" required>
            </div>
            <div><button type="submit" class="btn btn-light">Üst seviyeye aktar</button></div>
        </form>
    <?php endif; ?>
    <?php if ($assignableAgents !== []): ?>
        <form method="post" action="/talepler/<?= e($ticket->id) ?>/ata" class="filters">
            <?= $csrfField() ?>
            <div>
                <label for="ata-personel">Personele ata</label>
                <select id="ata-personel" name="personel" required>
                    <?php foreach ($assignableAgents as $row): ?>
                        <option value="<?= e($row['agent']->id) ?>"><?= e($row['agent']->fullName() . ' (' . $row['load'] . '/' . $row['capacity'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grow">
                <label for="ata-gerekce">Gerekçe (kapasitesi dolu personel için zorunlu)</label>
                <input type="text" id="ata-gerekce" name="gerekce" maxlength="300">
            </div>
            <div><button type="submit" class="btn">Ata</button></div>
        </form>
    <?php endif; ?>
    <?php if ($viewer->role === Role::Admin && !$ticket->status->isFinal()): ?>
        <form method="post" action="/talepler/<?= e($ticket->id) ?>/cozulemedi" class="filters" data-confirm="Talep &quot;çözülemedi&quot; olarak kapatılacak. Onaylıyor musunuz?">
            <?= $csrfField() ?>
            <div class="grow">
                <label for="cozulemedi-gerekce">Çözülemedi olarak kapatma gerekçesi</label>
                <input type="text" id="cozulemedi-gerekce" name="gerekce" maxlength="300" required>
            </div>
            <div><button type="submit" class="btn btn-danger">Çözülemedi olarak kapat</button></div>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>

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
            <div class="muted"><strong><?= e($names[$reply->userId] ?? '-') ?></strong> · <?= e($date($reply->createdAt)) ?><?php if ($reply->isQuestion): ?> <span class="badge status-in_review">Soru</span><?php endif; ?></div>
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
            <?php if ($canAsk && $questionsLeft > 0): ?>
                <div class="form-row"><label class="inline-check"><input type="checkbox" name="soru" value="1"> Müşteriye soru olarak gönder (müşteri yanıtlayana kadar SLA durur; kalan soru hakkı: <?= e($questionsLeft) ?>)</label></div>
            <?php endif; ?>
            <button type="submit" class="btn">Yanıtı gönder</button>
        </form>
    <?php elseif ($replyBlockedMessage !== null): ?>
        <p class="alert alert-warning"><?= e($replyBlockedMessage) ?></p>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Günlük</h2>
    <?php
    // Durum değişiklikleri ve diğer işlemler tek zaman çizelgesinde (aynı anda olursa durum satırı önce).
    $entries = [];
    foreach ($logs as $log) {
        $entries[] = [$log->changedAt, 0, (int) $log->id, $log->userId,
            $log->oldStatus !== null ? $log->oldStatus->label() . ' → ' . $log->newStatus->label() : 'Talep oluşturuldu (' . $log->newStatus->label() . ')'];
    }
    foreach ($activities as $activity) {
        $target = $names[(int) $activity->targetUserId] ?? '-';
        $text = match ($activity->action) {
            'assigned' => 'Atama: ' . $target,
            'replied' => 'Yanıt yazıldı',
            'escalated' => $viewer->role === Role::Customer
                ? 'Üst seviye personele aktarıldı' // personelin iç gerekçesi müşteriye gösterilmez
                : 'Eskalasyon: ' . $target . ($activity->note !== null ? ' (' . $activity->note . ')' : ''),
            'returned_to_pool' => 'Görev Havuzu\'na alındı' . ($activity->note !== null ? ' (' . $activity->note . ')' : ''),
            'sla_extended' => 'Ek SLA: yeni bitiş ' . ($activity->note ?? ''),
            'sla_resumed' => 'SLA devam etti: yeni bitiş ' . ($activity->note ?? ''),
            'closed' => 'Kapanış: ' . ($activity->note ?? ''),
            default => $activity->note ?? $activity->action,
        };
        $entries[] = [$activity->createdAt, 1, (int) $activity->id, $activity->userId, $text];
    }
    usort($entries, static fn (array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);
    ?>
    <ol class="timeline">
        <?php foreach ($entries as [$at, , , $userId, $text]): ?>
            <li>
                <span class="muted"><?= e($date($at)) ?></span> ·
                <strong><?= e($names[$userId] ?? '-') ?></strong> ·
                <?= e($text) ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
