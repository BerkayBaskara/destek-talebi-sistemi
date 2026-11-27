<?php /** @var string $message */ ?>
<section class="card error-page">
    <h1>Erişim engellendi (403)</h1>
    <p><?= e($message !== '' ? $message : 'Bu sayfaya erişim yetkiniz yok.') ?></p>
    <p><a href="/">Ana sayfaya dön</a></p>
</section>
