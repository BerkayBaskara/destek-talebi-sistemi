<?php
/** @var list<\App\Model\Category> $categories */
/** @var list<\App\Model\User> $agents */
/** @var array<int, array<int, int>> $levels kategori → personel → seviye */
/** @var int $errorCategory */
/** @var array<int, string> $old */
/** @var array<string, string> $errors */
/** @var int $maxLevel */
/** @var callable $csrfField */
?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card">
    <h1>Personel Hiyerarşisi</h1>
    <p class="muted">Her kategoride personelin seviyesini girin: 1 ilk basamaktır, sayı büyüdükçe kıdem artar. Yeni talep en alt seviyedeki uygun personele atanır; SLA aşımında talep bir üst seviyeye aktarılır. Boş bırakılan personel o kategoride çalışmaz. Hiç seviye girilmemiş kategoride tüm personel adaydır.</p>
</section>
<?php if ($agents === []): ?>
    <section class="card"><p class="muted">Henüz personel yok.</p></section>
<?php endif; ?>
<?php foreach ($categories as $category): ?>
    <?php
    $c = (int) $category->id;
    $isError = $errorCategory === $c;
    ?>
    <section class="card">
        <h2><?= e($category->name) ?><?= $category->active ? '' : ' <span class="muted">(pasif)</span>' ?></h2>
        <?php if ($isError && $errors !== []): ?>
            <div class="alert alert-error" role="alert">Seviye 1 ile <?= e($maxLevel) ?> arasında olmalı. Bu kategori kaydedilmedi.</div>
        <?php endif; ?>
        <form method="post" action="/yonetici/hiyerarsi/<?= e($c) ?>" novalidate>
            <?= $csrfField() ?>
            <div class="level-grid">
                <?php foreach ($agents as $agent): ?>
                    <?php
                    $u = (int) $agent->id;
                    $value = $isError ? ($old[$u] ?? '') : (string) ($levels[$c][$u] ?? '');
                    ?>
                    <div class="form-row">
                        <label for="lvl-<?= e($c . '-' . $u) ?>"><?= e($agent->fullName()) ?> <span class="muted"><?= e($agent->customerNo) ?></span></label>
                        <input type="number" id="lvl-<?= e($c . '-' . $u) ?>" name="level[<?= e($u) ?>]" min="1" max="<?= e($maxLevel) ?>" class="input-sm" value="<?= e($value) ?>" placeholder="—">
                        <?php if ($isError && isset($errors['level_' . $u])): ?><div class="field-error"><?= e($errors['level_' . $u]) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn">Kaydet</button>
        </form>
    </section>
<?php endforeach; ?>
