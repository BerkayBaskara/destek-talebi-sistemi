<?php
/** @var list<array{agent: \App\Model\User, active: int, capacity: int}> $rows */
/** @var int $defaultCapacity */
/** @var array<string, string> $old */
/** @var array<string, string> $errors */
/** @var int $rowId */
/** @var array<string, string> $rowOld */
/** @var array<string, string> $rowErrors */
/** @var callable $csrfField */
$fieldError = static fn (string $f): string => isset($errors[$f]) ? '<div class="field-error">' . e($errors[$f]) . '</div>' : '';
?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card">
    <h1>Personel</h1>
    <p class="muted">Pasif personel giriş yapamaz ve yeni talep almaz. Kapasite boş bırakılırsa varsayılan (<?= e($defaultCapacity) ?>) uygulanır.</p>
    <?php if ($rows === []): ?>
        <p class="muted">Henüz personel yok.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Numara</th><th>Ad Soyad</th><th>Durum</th><th>Aktif talep / kapasite</th><th>Kapasite ve durum</th><th><span class="sr-only">Kaydet</span></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $agent = $row['agent'];
                    $id = (int) $agent->id;
                    $form = 'staff-' . $id;
                    $isRow = $rowId === $id;
                    $checked = $isRow ? ($rowOld['active'] ?? '') === '1' : $agent->active;
                    $capacity = $isRow ? ($rowOld['capacity'] ?? '') : ($agent->capacity !== null ? (string) $agent->capacity : '');
                    $warn = !$agent->active && $row['active'] > 0;
                    ?>
                    <tr<?= $warn ? ' class="row-warning"' : '' ?>>
                        <td data-label="Numara"><?= e($agent->customerNo) ?></td>
                        <td data-label="Ad Soyad">
                            <?= e($agent->fullName()) ?>
                            <?php if ($warn): ?>
                                <div class="field-warning">Bu personelin <?= e($row['active']) ?> açık talebi var; talepler şimdilik onun üzerinde kalıyor.</div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Durum"><span class="badge <?= $agent->active ? 'status-resolved' : 'status-cancelled' ?>"><?= $agent->active ? 'Aktif' : 'Pasif' ?></span></td>
                        <td data-label="Aktif talep / kapasite"><?= e($row['active']) ?> / <?= e($row['capacity']) ?></td>
                        <td data-label="Kapasite">
                            <form id="<?= e($form) ?>" method="post" action="/yonetici/personel/<?= e($id) ?>"><?= $csrfField() ?></form>
                            <label class="sr-only" for="<?= e($form) ?>-cap"><?= e($agent->fullName()) ?> kapasitesi</label>
                            <input type="number" id="<?= e($form) ?>-cap" name="capacity" min="1" max="100" class="input-sm" form="<?= e($form) ?>" value="<?= e($capacity) ?>" placeholder="<?= e($defaultCapacity) ?>">
                            <label><input type="checkbox" name="active" value="1" form="<?= e($form) ?>"<?= $checked ? ' checked' : '' ?>> Aktif</label>
                            <?php if ($isRow): ?>
                                <?php foreach ($rowErrors as $message): ?><div class="field-error"><?= e($message) ?></div><?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td><button type="submit" class="btn btn-light" form="<?= e($form) ?>">Kaydet</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<section class="card narrow-wide">
    <h2>Yeni personel</h2>
    <p class="muted">Personel numarası (P ile başlayan) otomatik verilir ve kaydedildikten sonra gösterilir.</p>
    <form method="post" action="/yonetici/personel" novalidate>
        <?= $csrfField() ?>
        <div class="grid-2">
            <div class="form-row">
                <label for="name">Ad</label>
                <input type="text" id="name" name="name" maxlength="50" value="<?= e($old['name'] ?? '') ?>" required>
                <?= $fieldError('name') ?>
            </div>
            <div class="form-row">
                <label for="surname">Soyad</label>
                <input type="text" id="surname" name="surname" maxlength="50" value="<?= e($old['surname'] ?? '') ?>" required>
                <?= $fieldError('surname') ?>
            </div>
        </div>
        <div class="form-row">
            <label for="password">Geçici şifre</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required>
            <?= $fieldError('password') ?>
        </div>
        <?php require __DIR__ . '/../../partials/password_rules.php'; ?>
        <button type="submit" class="btn">Personel oluştur</button>
    </form>
</section>
