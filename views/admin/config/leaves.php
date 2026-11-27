<?php
/** @var list<array{id: int, user_id: int, start: string, end: string, note: string}> $leaves */
/** @var array<int, string> $names */
/** @var array<string, string> $old */
/** @var array<string, string> $errors */
/** @var callable $csrfField */
$fieldError = static fn (string $f): string => isset($errors[$f]) ? '<div class="field-error">' . e($errors[$f]) . '</div>' : '';
$day = static fn (string $d): string => implode('.', array_reverse(explode('-', $d)));
?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card">
    <h1>Personel İzinleri</h1>
    <p class="muted">İzinli personel izin günlerinde yeni talep almaz. Elindeki talepler onda kalır.</p>
    <?php if ($leaves === []): ?>
        <p class="muted">Kayıtlı izin yok.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Personel</th><th>Başlangıç</th><th>Bitiş</th><th>Not</th><th><span class="sr-only">Sil</span></th></tr></thead>
                <tbody>
                <?php foreach ($leaves as $leave): ?>
                    <tr>
                        <td data-label="Personel"><?= e($names[$leave['user_id']] ?? '-') ?></td>
                        <td data-label="Başlangıç"><?= e($day($leave['start'])) ?></td>
                        <td data-label="Bitiş"><?= e($day($leave['end'])) ?></td>
                        <td data-label="Not"><?= e($leave['note']) ?></td>
                        <td>
                            <form method="post" action="/yonetici/izinler/<?= e($leave['id']) ?>/sil" data-confirm="Bu izin kaydı silinsin mi?">
                                <?= $csrfField() ?>
                                <button type="submit" class="btn btn-light">Sil</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<section class="card narrow-wide">
    <h2>İzin ekle</h2>
    <form method="post" action="/yonetici/izinler" novalidate>
        <?= $csrfField() ?>
        <div class="form-row">
            <label for="user_id">Personel</label>
            <select id="user_id" name="user_id" required>
                <option value="">Seçiniz</option>
                <?php foreach ($names as $id => $name): ?>
                    <option value="<?= e($id) ?>"<?= ($old['user_id'] ?? '') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $fieldError('user_id') ?>
        </div>
        <div class="grid-2">
            <div class="form-row">
                <label for="start">Başlangıç</label>
                <input type="date" id="start" name="start" value="<?= e($old['start'] ?? '') ?>" required>
                <?= $fieldError('start') ?>
            </div>
            <div class="form-row">
                <label for="end">Bitiş</label>
                <input type="date" id="end" name="end" value="<?= e($old['end'] ?? '') ?>" required>
                <?= $fieldError('end') ?>
            </div>
        </div>
        <div class="form-row">
            <label for="note">Not (isteğe bağlı)</label>
            <input type="text" id="note" name="note" maxlength="100" value="<?= e($old['note'] ?? '') ?>">
        </div>
        <button type="submit" class="btn">İzin ekle</button>
    </form>
</section>
