<?php
/** @var list<\App\Model\Category> $categories */
/** @var array<int|string, array<string, string>> $old */
/** @var array<int|string, array<string, string>> $errors */
/** @var callable $csrfField */
/** @var array<int, list<array{id: int, category_id: int, label: string, type: string, required: bool, options: list<string>, max_length: ?int}>> $fields */
/** @var array<int, string> $labels */
/** @var array<string, string> $fieldTypes */
$fieldError = static fn (string $row, string $f): string => isset($errors[$row][$f]) ? '<div class="field-error">' . e($errors[$row][$f]) . '</div>' : '';
?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card">
    <h1>Kategoriler</h1>
    <p class="muted">Kategoriler silinmez; pasif yapılan kategori yeni talep formunda görünmez, eski talepler etkilenmez.</p>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>No</th><th>Ad</th><th>Durum</th><th><span class="sr-only">Kaydet</span></th></tr></thead>
            <tbody>
            <?php foreach ($categories as $category): ?>
                <?php
                $row = (string) $category->id;
                $form = 'cat-' . $row;
                $active = isset($old[$row]) ? ($old[$row]['active'] ?? '') === '1' : $category->active;
                ?>
                <tr>
                    <td data-label="No"><?= e($category->id) ?><?= $category->parentId !== null ? '<div class="muted">' . e($labels[$category->parentId] ?? '') . ' altında</div>' : '' ?></td>
                    <td data-label="Ad">
                        <form id="<?= e($form) ?>" method="post" action="/yonetici/kategoriler/<?= e($row) ?>"><?= $csrfField() ?></form>
                        <label class="sr-only" for="<?= e($form) ?>-name">Kategori adı</label>
                        <input type="text" id="<?= e($form) ?>-name" name="name" maxlength="60" form="<?= e($form) ?>" value="<?= e($old[$row]['name'] ?? $category->name) ?>">
                        <?= $fieldError($row, 'name') ?>
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
    <h2>Yeni kategori</h2>
    <form method="post" action="/yonetici/kategoriler" novalidate>
        <?= $csrfField() ?>
        <div class="form-row">
            <label for="new-name">Kategori adı</label>
            <input type="text" id="new-name" name="name" maxlength="60" value="<?= e($old['new']['name'] ?? '') ?>" required>
            <?= $fieldError('new', 'name') ?>
        </div>
        <div class="form-row">
            <label for="new-parent">Ana kategori (alt kategori eklemek için seçin)</label>
            <select id="new-parent" name="parent_id">
                <option value="">Yok (ana kategori)</option>
                <?php foreach ($categories as $parent): ?>
                    <?php if ($parent->parentId === null): ?>
                        <option value="<?= e($parent->id) ?>"<?= ($old['new']['parent_id'] ?? '') === (string) $parent->id ? ' selected' : '' ?>><?= e($parent->name) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('new', 'parent_id') ?>
        </div>
        <button type="submit" class="btn">Ekle</button>
    </form>
</section>
<section class="card">
    <h2>Kategoriye özel ek alanlar</h2>
    <p class="muted">Seçilen kategoride talep formunda ek alanlar gösterilir (ör. cihaz seri numarası). Zorunlu alan boş bırakılırsa talep açılmaz.</p>
    <?php foreach ($categories as $category): ?>
        <?php $c = (int) $category->id; $fe = $errors['field-' . $c] ?? []; ?>
        <div class="field-block">
            <h3><?= e($labels[$c] ?? $category->name) ?></h3>
            <?php if (($fields[$c] ?? []) === []): ?>
                <p class="muted">Ek alan yok.</p>
            <?php else: ?>
                <ul class="plain">
                    <?php foreach ($fields[$c] as $field): ?>
                        <li>
                            <?= e($field['label']) ?> <span class="muted">(<?= e($fieldTypes[$field['type']] ?? $field['type']) ?><?= $field['options'] !== [] ? ': ' . e(implode(', ', $field['options'])) : '' ?><?= $field['max_length'] !== null ? ', en fazla ' . e($field['max_length']) . ' karakter' : '' ?><?= $field['required'] ? ', zorunlu' : '' ?>)</span>
                            <form method="post" action="/yonetici/kategoriler/alanlar/<?= e($field['id']) ?>/sil" class="inline-form" data-confirm="Bu alan silinsin mi? Daha önce girilen değerler taleplerde kalır.">
                                <?= $csrfField() ?>
                                <button type="submit" class="linklike danger-link">Sil</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form method="post" action="/yonetici/kategoriler/<?= e($c) ?>/alanlar" class="filters" novalidate>
                <?= $csrfField() ?>
                <div class="grow">
                    <label for="field-label-<?= e($c) ?>">Alan adı</label>
                    <input type="text" id="field-label-<?= e($c) ?>" name="label" maxlength="60">
                    <?php if (isset($fe['field_label'])): ?><div class="field-error"><?= e($fe['field_label']) ?></div><?php endif; ?>
                </div>
                <div>
                    <label for="field-type-<?= e($c) ?>">Tür</label>
                    <select id="field-type-<?= e($c) ?>" name="type">
                        <?php foreach ($fieldTypes as $value => $label): ?>
                            <option value="<?= e($value) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($fe['field_type'])): ?><div class="field-error"><?= e($fe['field_type']) ?></div><?php endif; ?>
                </div>
                <div class="grow">
                    <label for="field-options-<?= e($c) ?>">Seçenekler (seçim listesi için, virgülle)</label>
                    <input type="text" id="field-options-<?= e($c) ?>" name="options" maxlength="300">
                    <?php if (isset($fe['field_options'])): ?><div class="field-error"><?= e($fe['field_options']) ?></div><?php endif; ?>
                </div>
                <div>
                    <label for="field-max-<?= e($c) ?>">En fazla karakter</label>
                    <input type="number" id="field-max-<?= e($c) ?>" name="max_length" min="1" max="200" class="input-sm">
                    <?php if (isset($fe['field_max_length'])): ?><div class="field-error"><?= e($fe['field_max_length']) ?></div><?php endif; ?>
                </div>
                <div><label><input type="checkbox" name="required" value="1"> Zorunlu</label></div>
                <div><button type="submit" class="btn btn-light">Alan ekle</button></div>
            </form>
        </div>
    <?php endforeach; ?>
</section>

