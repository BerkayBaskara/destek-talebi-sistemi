<?php
/** @var list<\App\Model\Priority> $priorities */
/** @var array<int|string, array<string, string>> $old */
/** @var array<int|string, array<string, string>> $errors */
/** @var callable $csrfField */
$fieldError = static fn (string $row, string $f): string => isset($errors[$row][$f]) ? '<div class="field-error">' . e($errors[$row][$f]) . '</div>' : '';
$value = static fn (string $row, string $field, string $current): string => $old[$row][$field] ?? $current;
?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card">
    <h1>Öncelikler</h1>
    <p class="muted">Sıra numarası büyüdükçe öncelik yükselir. Anahtar kelimeler virgülle ayrılır; talep metninde geçerse öncelik en az o seviyeye yükseltilir. Öncelikler silinmez, pasif yapılır.</p>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Ad</th><th>Sıra</th><th>Varsayılan SLA (iş saati)</th><th>Anahtar kelimeler</th><th>Durum</th><th><span class="sr-only">Kaydet</span></th></tr></thead>
            <tbody>
            <?php foreach ($priorities as $priority): ?>
                <?php
                $row = (string) $priority->id;
                $form = 'pri-' . $row;
                $active = isset($old[$row]) ? ($old[$row]['active'] ?? '') === '1' : $priority->active;
                ?>
                <tr>
                    <td data-label="Ad">
                        <form id="<?= e($form) ?>" method="post" action="/yonetici/oncelikler/<?= e($row) ?>"><?= $csrfField() ?></form>
                        <label class="sr-only" for="<?= e($form) ?>-name">Ad</label>
                        <input type="text" id="<?= e($form) ?>-name" name="name" maxlength="40" form="<?= e($form) ?>" value="<?= e($value($row, 'name', $priority->name)) ?>">
                        <?= $fieldError($row, 'name') ?>
                    </td>
                    <td data-label="Sıra">
                        <label class="sr-only" for="<?= e($form) ?>-rank">Sıra</label>
                        <input type="number" id="<?= e($form) ?>-rank" name="rank" min="1" max="99" class="input-sm" form="<?= e($form) ?>" value="<?= e($value($row, 'rank', (string) $priority->rank)) ?>">
                        <?= $fieldError($row, 'rank') ?>
                    </td>
                    <td data-label="Varsayılan SLA">
                        <label class="sr-only" for="<?= e($form) ?>-sla">Varsayılan SLA</label>
                        <input type="number" id="<?= e($form) ?>-sla" name="default_sla_hours" min="1" max="720" class="input-sm" form="<?= e($form) ?>" value="<?= e($value($row, 'default_sla_hours', (string) $priority->defaultSlaHours)) ?>">
                        <?= $fieldError($row, 'default_sla_hours') ?>
                    </td>
                    <td data-label="Anahtar kelimeler">
                        <label class="sr-only" for="<?= e($form) ?>-kw">Anahtar kelimeler</label>
                        <input type="text" id="<?= e($form) ?>-kw" name="keywords" form="<?= e($form) ?>" value="<?= e($value($row, 'keywords', implode(', ', $priority->keywords))) ?>">
                    </td>
                    <td>
                        <label><input type="checkbox" name="active" value="1" form="<?= e($form) ?>"<?= $active ? ' checked' : '' ?>> Aktif</label>
                        <?= $fieldError($row, 'active') ?>
                    </td>
                    <td><button type="submit" class="btn btn-light" form="<?= e($form) ?>">Kaydet</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="card narrow-wide">
    <h2>Yeni öncelik</h2>
    <form method="post" action="/yonetici/oncelikler" novalidate>
        <?= $csrfField() ?>
        <div class="form-row">
            <label for="new-name">Ad</label>
            <input type="text" id="new-name" name="name" maxlength="40" value="<?= e($old['new']['name'] ?? '') ?>" required>
            <?= $fieldError('new', 'name') ?>
        </div>
        <div class="form-row">
            <label for="new-rank">Sıra (1-99, büyük olan daha yüksek öncelik)</label>
            <input type="number" id="new-rank" name="rank" min="1" max="99" value="<?= e($old['new']['rank'] ?? '') ?>" required>
            <?= $fieldError('new', 'rank') ?>
        </div>
        <div class="form-row">
            <label for="new-sla">Varsayılan SLA süresi (iş saati)</label>
            <input type="number" id="new-sla" name="default_sla_hours" min="1" max="720" value="<?= e($old['new']['default_sla_hours'] ?? '') ?>" required>
            <?= $fieldError('new', 'default_sla_hours') ?>
        </div>
        <div class="form-row">
            <label for="new-kw">Anahtar kelimeler (virgülle ayırın, isteğe bağlı)</label>
            <input type="text" id="new-kw" name="keywords" value="<?= e($old['new']['keywords'] ?? '') ?>">
        </div>
        <div class="form-row">
            <label><input type="checkbox" name="active" value="1"<?= ($old['new']['active'] ?? '1') === '1' ? ' checked' : '' ?>> Aktif</label>
        </div>
        <button type="submit" class="btn">Ekle</button>
    </form>
</section>
