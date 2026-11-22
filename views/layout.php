<?php
/** @var string $content */
/** @var string|null $title */
/** @var string|null $flashSuccess */
/** @var string|null $flashError */
/** @var \App\Model\User|null $currentUser */
/** @var callable $csrfField */
/** @var string $siteTitle */
/** @var string $siteLogo */

use App\Model\Enum\Role;

?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrfToken ?? '') ?>">
    <title><?= e($siteTitle ?? 'Destek Talebi Sistemi') ?><?= isset($title) ? ' · ' . e($title) : '' ?></title>
    <?php if (!empty($refresh)): ?>
        <meta http-equiv="refresh" content="<?= e($refresh) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/menu.js" defer></script>
    <script src="/assets/ui.js" defer></script>
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div class="brand-group">
            <?php if (!empty($siteLogo)): ?>
                <a class="brand-logo" href="/"><img src="<?= e($siteLogo) ?>" alt="" height="32"></a>
            <?php endif; ?>
            <a class="brand" href="/"><?= e($siteTitle ?? 'Destek Talebi Sistemi') ?></a>
        </div>
        <button type="button" class="menu-toggle" aria-controls="main-menu" aria-expanded="false" aria-label="Menüyü aç" hidden>
            <span class="menu-toggle-bar" aria-hidden="true"></span>
        </button>
        <nav class="menu" id="main-menu" aria-label="Ana menü">
            <?php if (isset($currentUser)): ?>
                <?php if ($currentUser->role === Role::Customer): ?>
                    <a href="/talepler">Taleplerim</a>
                    <a href="/talepler/yeni">Yeni Talep</a>
                <?php elseif ($currentUser->role === Role::Agent): ?>
                    <a href="/kanban">Kanban</a>
                    <a href="/havuz">Görev Havuzu</a>
                    <a href="/personel/talepler">Görevlerim</a>
                <?php else: ?>
                    <a href="/yonetici">Tüm Talepler</a>
                    <a href="/yonetici/ozet">Özet Rapor</a>
                    <a href="/havuz">Görev Havuzu</a>
                    <a href="/yonetici/ayarlar">Sistem Yapılandırması</a>
                <?php endif; ?>
                <span class="who"><?= e($currentUser->fullName()) ?> (<?= e($currentUser->role->label()) ?>)</span>
                <form method="post" action="/cikis">
                    <?= $csrfField() ?>
                    <button type="submit" class="linklike">Çıkış</button>
                </form>
            <?php else: ?>
                <a class="btn btn-login btn-sm" href="/giris">Giriş</a>
                <a href="/kayit">Kayıt ol</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container" id="main">
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
