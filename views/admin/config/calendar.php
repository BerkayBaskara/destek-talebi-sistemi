<?php
/** @var array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}> $days */
/** @var array<int, string> $dayNames */
/** @var list<array{date: string, description: string}> $holidays */
/** @var array{date: string, description: string} $holiday */
/** @var array<string, string> $errors */
/** @var callable $csrfField */
/** @var string $monthTitle */
/** @var string $prevMonth */
/** @var string $nextMonth */
/** @var list<array{date: \DateTimeImmutable, type: string, note: string}> $grid */
/** @var list<array{date: string, start: string, end: string, description: string}> $specialDays */
/** @var bool $recalculateOpen */
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
<section class="card">
    <h2>Özel günler (yarım gün, telafi günü)</h2>
    <p class="muted">Belirli bir tarihte haftalık mesai yerine bu saatler geçerli olur. Ör. arife günü 09:00–13:00 ya da cumartesi telafi günü.</p>
    <?php if ($specialDays !== []): ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Saatler</th><th>Açıklama</th><th><span class="sr-only">Sil</span></th></tr></thead>
                <tbody>
                <?php foreach ($specialDays as $sd): ?>
                    <tr>
                        <td data-label="Tarih"><?= e(implode('.', array_reverse(explode('-', $sd['date'])))) ?></td>
                        <td data-label="Saatler"><?= e($sd['start'] . '–' . $sd['end']) ?></td>
                        <td data-label="Açıklama"><?= e($sd['description']) ?></td>
                        <td>
                            <form method="post" action="/yonetici/takvim/ozel-gun/sil">
                                <?= $csrfField() ?>
                                <input type="hidden" name="date" value="<?= e($sd['date']) ?>">
                                <button type="submit" class="btn btn-light">Sil</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <form method="post" action="/yonetici/takvim/ozel-gun" class="filters" novalidate>
        <?= $csrfField() ?>
        <div>
            <label for="special-date">Tarih</label>
            <input type="date" id="special-date" name="date" required>
            <?php if (isset($errors['special_date'])): ?><div class="field-error"><?= e($errors['special_date']) ?></div><?php endif; ?>
        </div>
        <div>
            <label for="special-start">Başlangıç</label>
            <input type="time" id="special-start" name="start" value="09:00">
        </div>
        <div>
            <label for="special-end">Bitiş</label>
            <input type="time" id="special-end" name="end" value="13:00">
            <?php if (isset($errors['special_time'])): ?><div class="field-error"><?= e($errors['special_time']) ?></div><?php endif; ?>
        </div>
        <div class="grow">
            <label for="special-desc">Açıklama</label>
            <input type="text" id="special-desc" name="description" maxlength="100">
        </div>
        <div><button type="submit" class="btn">Özel gün ekle</button></div>
    </form>
</section>
<section class="card">
    <div class="card-head">
        <h2><?= e($monthTitle) ?></h2>
        <div class="actions">
            <a class="btn btn-light" href="/yonetici/takvim?ay=<?= e($prevMonth) ?>">‹ Önceki ay</a>
            <a class="btn btn-light" href="/yonetici/takvim?ay=<?= e($nextMonth) ?>">Sonraki ay ›</a>
        </div>
    </div>
    <div class="month-legend">
        <span class="day-work legend">Çalışma günü</span> <span class="day-special legend">Özel gün</span>
        <span class="day-holiday legend">Tatil</span> <span class="day-off legend">Çalışılmayan gün</span>
    </div>
    <div class="month-grid">
        <?php foreach (['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'] as $wd): ?>
            <div class="month-head"><?= e($wd) ?></div>
        <?php endforeach; ?>
        <?php for ($i = 1; $i < (int) $grid[0]['date']->format('N'); $i++): ?>
            <div class="month-empty"></div>
        <?php endfor; ?>
        <?php foreach ($grid as $cell): ?>
            <div class="month-day day-<?= e($cell['type']) ?>" title="<?= e($cell['note']) ?>">
                <span class="day-num"><?= e($cell['date']->format('j')) ?></span>
                <?php if ($cell['note'] !== ''): ?><span class="day-note"><?= e($cell['note']) ?></span><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<section class="card">
    <h2>Takvim dosyası yükle ve uygulama kuralı</h2>
    <form method="post" action="/yonetici/takvim/yukle" enctype="multipart/form-data" class="filters" novalidate>
        <?= $csrfField() ?>
        <div class="grow">
            <label for="takvim">Takvim dosyası (JSON; çalışma günleri, tatiller ve özel günler). Yüklenen dosya mevcut takvimin yerine geçer.</label>
            <input type="file" id="takvim" name="takvim" accept=".json,application/json" required>
            <?php if (isset($errors['takvim'])): ?><div class="field-error"><?= e($errors['takvim']) ?></div><?php endif; ?>
        </div>
        <div><button type="submit" class="btn">Dosyayı yükle</button></div>
    </form>
    <form method="post" action="/yonetici/takvim/kural">
        <?= $csrfField() ?>
        <div class="form-row"><label class="inline-check"><input type="checkbox" name="recalculate_open" value="1"<?= $recalculateOpen ? ' checked' : '' ?>> Takvim değişikliği açık taleplerin bitiş zamanına da uygulansın (kalan süre yeni takvimle yeniden hesaplanır; ilk SLA bitişi kayıtta kalır)</label></div>
        <button type="submit" class="btn btn-light">Kuralı kaydet</button>
    </form>
</section>

