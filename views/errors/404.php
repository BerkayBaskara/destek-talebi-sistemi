<?php /** @var string $message */ ?>
<section class="card error-page">
    <h1>Sayfa bulunamadı (404)</h1>
    <p><?= e($message !== '' ? $message : 'Aradığınız sayfa veya kayıt bulunamadı.') ?></p>
    <p><a href="/">Ana sayfaya dön</a></p>
</section>
