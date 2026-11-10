<?php
/** @var string $content */
/** @var string|null $title */
/** @var string|null $flashSuccess */
/** @var string|null $flashError */
/** @var \App\Model\User|null $currentUser */
/** @var callable $csrfField */

use App\Model\Enum\Role;

?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrfToken ?? '') ?>">
    <title>Destek Talebi Sistemi<?= isset($title) ? ' · ' . e($title) : '' ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="/">Destek Talebi Sistemi</a>
        <nav class="menu">
            <?php if (isset($currentUser)): ?>
                <?php if ($currentUser->role === Role::Customer): ?>
                    <a href="/talepler">Taleplerim</a>
                    <a href="/talepler/yeni">Yeni Talep</a>
                <?php elseif ($currentUser->role === Role::Agent): ?>
                    <a href="/kanban">Kanban</a>
                    <a href="/havuz">Ortak Havuz</a>
                    <a href="/personel/talepler">Taleplerim</a>
                <?php else: ?>
                    <a href="/yonetici">Tüm Talepler</a>
                    <a href="/yonetici/ozet">Özet Rapor</a>
                    <a href="/havuz">Ortak Havuz</a>
                <?php endif; ?>
                <span class="who"><?= e($currentUser->fullName()) ?> (<?= e($currentUser->role->label()) ?>)</span>
                <form method="post" action="/cikis">
                    <?= $csrfField() ?>
                    <button type="submit" class="linklike">Çıkış</button>
                </form>
            <?php else: ?>
                <a href="/giris">Giriş</a>
                <a href="/kayit">Kayıt ol</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
        <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
    <?php endif; ?>
    <?= $content ?>
</main>
</body>
</html>
