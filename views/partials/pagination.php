<?php
/** @var int $page */
/** @var int $pages */
/** @var string $baseUrl */
/** @var array<string, string> $params */
if ($pages <= 1) {
    return;
}
$link = static function (int $p) use ($baseUrl, $params): string {
    return $baseUrl . '?' . http_build_query(array_filter($params + ['sayfa' => (string) $p], static fn (string $v): bool => $v !== ''));
};
?>
<nav class="pagination" aria-label="Sayfalar">
    <?php if ($page > 1): ?><a href="<?= e($link($page - 1)) ?>">&lsaquo; Önceki</a><?php endif; ?>
    <?php for ($p = 1; $p <= $pages; $p++): ?>
        <?php if ($p === $page): ?>
            <strong aria-current="page"><?= $p ?></strong>
        <?php else: ?>
            <a href="<?= e($link($p)) ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="<?= e($link($page + 1)) ?>">Sonraki &rsaquo;</a><?php endif; ?>
</nav>
