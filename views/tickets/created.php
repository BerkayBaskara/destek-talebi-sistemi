<?php
/** @var \App\Model\Ticket $ticket */
/** @var int $seconds */
?>
<section class="card narrow success-card">
    <div class="success-icon" aria-hidden="true">
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M4 12.5l5 5L20 6.5"/></svg>
    </div>
    <h1>Talebiniz alındı.</h1>
    <p>Talep numaranız</p>
    <p class="big-number">#<?= e($ticket->id) ?></p>
    <p class="muted" data-countdown="<?= e($seconds) ?>" data-countdown-url="/talepler">
        <span data-countdown-number><?= e($seconds) ?></span> saniye içinde taleplerinizin listesine yönlendirileceksiniz.
    </p>
    <div class="progress" aria-hidden="true"><div class="progress-bar" style="animation-duration: <?= e($seconds) ?>s"></div></div>
    <p class="success-links">
        <a href="/talepler">Taleplerime dön</a> · <a href="/talepler/<?= e($ticket->id) ?>">Talebi görüntüle</a>
    </p>
</section>
