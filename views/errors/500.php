<?php /** @var string $message */ /** @var int $status */ ?>
<section class="card error-page">
    <h1>Bir hata oluştu (<?= e($status) ?>)</h1>
    <p><?= e($message !== '' ? $message : 'Beklenmeyen bir hata oluştu.') ?></p>
    <p><a href="/">Ana sayfaya dön</a></p>
</section>
