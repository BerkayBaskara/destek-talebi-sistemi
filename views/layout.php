<?php
/** @var string $content */
/** @var string|null $title */
/** @var string|null $flashSuccess */
/** @var string|null $flashError */
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
            <?= isset($menu) && is_string($menu) ? $menu : '' ?>
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
