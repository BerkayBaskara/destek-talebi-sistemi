<?php
/** @var list<\App\Model\Category> $categories */
/** @var list<\App\Model\Priority> $priorities */
/** @var array<int, array<int, ?int>> $grid */
/** @var array<int, array<int, string>> $old */
/** @var array<string, string> $errors */
/** @var callable $csrfField */
?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card">
    <h1>SLA Süreleri</h1>
    <p class="muted">Süreler iş saatidir (çalışma takvimine göre). Boş bırakılan hücrede önceliğin varsayılan süresi kullanılır. Değişiklik yalnız yeni taleplere uygulanır.</p>
    <?php if ($errors !== []): ?>
        <div class="alert alert-error" role="alert">Tabloda düzeltilmesi gereken hücreler var; hiçbir değer kaydedilmedi.</div>
    <?php endif; ?>
    <form method="post" action="/yonetici/sla" novalidate>
        <?= $csrfField() ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Kategori</th>
                    <?php foreach ($priorities as $priority): ?>
                        <th><?= e($priority->name) ?><?= $priority->active ? '' : ' <span class="muted">(pasif)</span>' ?><br><span class="muted">varsayılan <?= e($priority->defaultSlaHours) ?> sa</span></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <th scope="row"><?= e($category->name) ?><?= $category->active ? '' : ' <span class="muted">(pasif)</span>' ?></th>
                        <?php foreach ($priorities as $priority): ?>
                            <?php
                            $c = (int) $category->id;
                            $p = (int) $priority->id;
                            $cell = $old[$c][$p] ?? (string) ($grid[$c][$p] ?? '');
                            $error = $errors[$c . '-' . $p] ?? null;
                            ?>
                            <td data-label="<?= e($priority->name) ?>">
                                <label class="sr-only" for="sla-<?= e($c . '-' . $p) ?>"><?= e($category->name . ' / ' . $priority->name) ?></label>
                                <input type="number" id="sla-<?= e($c . '-' . $p) ?>" name="sla[<?= e($c) ?>][<?= e($p) ?>]" min="1" max="720" class="input-sm" value="<?= e($cell) ?>" placeholder="<?= e($priority->defaultSlaHours) ?>">
                                <?php if ($error !== null): ?><div class="field-error"><?= e($error) ?></div><?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn">Kaydet</button>
    </form>
</section>
