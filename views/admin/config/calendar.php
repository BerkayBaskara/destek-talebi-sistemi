<?php
/** @var array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}> $days */
/** @var array<int, string> $dayNames */
/** @var list<array{date: string, description: string}> $holidays */
/** @var array{date: string, description: string} $holiday */
/** @var array<string, string> $errors */
/** @var callable $csrfField */
$fields = ['start' => 'Başlangıç', 'end' => 'Bitiş', 'break_start' => 'Öğle arası başı', 'break_end' => 'Öğle arası sonu'];
?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card">
    <h1>Çalışma Takvimi</h1>
    <p class="muted">SLA süreleri yalnız çalışma saatlerinde ilerler. Değişiklik yeni taleplere uygulanır.</p>
    <?php if (isset($errors['days'])): ?>
        <div class="alert alert-error" role="alert"><?= e($errors['days']) ?> Takvim değiştirilmedi.</div>
    <?php endif; ?>
    <form method="post" action="/yonetici/takvim/mesai" novalidate>
        <?= $csrfField() ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Gün</th><th>Çalışılır</th><th>Başlangıç</th><th>Bitiş</th><th>Öğle arası başı</th><th>Öğle arası sonu</th></tr></thead>
                <tbody>
                <?php foreach ($dayNames as $n => $dayName): ?>
                    <?php $d = $days[$n] ?? ['works' => false, 'start' => '09:00', 'end' => '18:00', 'break_start' => null, 'break_end' => null]; ?>
                    <tr>
                        <th scope="row"><?= e($dayName) ?></th>
                        <td data-label="Çalışılır"><label><input type="checkbox" name="days[<?= e($n) ?>][works]" value="1"<?= $d['works'] ? ' checked' : '' ?>><span class="sr-only"><?= e($dayName) ?> çalışılır</span></label></td>
                        <?php foreach ($fields as $key => $label): ?>
                            <td data-label="<?= e($label) ?>">
                                <label class="sr-only" for="day-<?= e($n . '-' . $key) ?>"><?= e($dayName . ' ' . $label) ?></label>
                                <input type="time" id="day-<?= e($n . '-' . $key) ?>" name="days[<?= e($n) ?>][<?= e($key) ?>]" value="<?= e($d[$key] ?? '') ?>">
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn">Çalışma saatlerini kaydet</button>
    </form>
</section>
<section class="card">
    <h2>Resmî tatiller</h2>
    <?php if ($holidays === []): ?>
        <p class="muted">Tatil eklenmedi.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Açıklama</th><th><span class="sr-only">Sil</span></th></tr></thead>
                <tbody>
                <?php foreach ($holidays as $h): ?>
                    <tr>
                        <td data-label="Tarih"><?= e(implode('.', array_reverse(explode('-', $h['date'])))) ?></td>
                        <td data-label="Açıklama"><?= e($h['description']) ?></td>
                        <td>
                            <form method="post" action="/yonetici/takvim/tatil/sil">
                                <?= $csrfField() ?>
                                <input type="hidden" name="date" value="<?= e($h['date']) ?>">
                                <button type="submit" class="btn btn-light">Sil</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <form method="post" action="/yonetici/takvim/tatil" class="filters" novalidate>
        <?= $csrfField() ?>
        <div>
            <label for="holiday-date">Tarih</label>
            <input type="date" id="holiday-date" name="date" value="<?= e($holiday['date']) ?>" required>
            <?php if (isset($errors['date'])): ?><div class="field-error"><?= e($errors['date']) ?></div><?php endif; ?>
        </div>
        <div class="grow">
            <label for="holiday-desc">Açıklama</label>
            <input type="text" id="holiday-desc" name="description" maxlength="100" value="<?= e($holiday['description']) ?>">
        </div>
        <div><button type="submit" class="btn">Tatil ekle</button></div>
    </form>
</section>
