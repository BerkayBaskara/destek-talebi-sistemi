<?php
/** @var list<\App\Model\Notification> $notifications */
/** @var callable $csrfField */
?>
<section class="card">
    <div class="card-head">
        <h1>Bildirimler</h1>
        <?php if ($notifications !== []): ?>
            <form method="post" action="/bildirimler/okundu">
                <?= $csrfField() ?>
                <button type="submit" class="btn btn-light">Tümünü okundu yap</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($notifications === []): ?>
        <p class="muted">Bildiriminiz yok.</p>
    <?php else: ?>
        <ul class="notification-list">
            <?php foreach ($notifications as $n): ?>
                <li class="<?= $n->readAt === null ? 'unread' : '' ?>">
                    <a href="/bildirimler/<?= e($n->id) ?>"><?= e($n->message) ?></a>
                    <span class="muted"><?= e($n->createdAt->format('d.m.Y H:i')) ?><?= $n->readAt === null ? ' · yeni' : '' ?>
                        <?php if ($n->readAt !== null): ?>
                            <form method="post" action="/bildirimler/<?= e($n->id) ?>/okunmadi" class="inline-form">
                                <?= $csrfField() ?>
                                <button type="submit" class="linklike link-small">Okunmadı yap</button>
                            </form>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
