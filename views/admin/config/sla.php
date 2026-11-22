<?php
/** @var list<\App\Model\Category> $categories */
/** @var list<\App\Model\Priority> $priorities */
/** @var array<int, array<int, ?int>> $grid */
/** @var array<int, array<int, string>> $old */
/** @var array<string, string> $errors */
/** @var callable $csrfField */
/** @var array<string, string> $rules */
/** @var array<string, string> $ruleErrors */
$ruleError = static fn (string $f): string => isset($ruleErrors[$f]) ? '<div class="field-error">' . e($ruleErrors[$f]) . '</div>' : '';
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
<section class="card">
    <h2>SLA aşımı ve çözüm onayı kuralları</h2>
    <p class="muted">SLA aşılınca talep personelden alınır ve müşteriye "aynı personelle devam" ya da "sorun çözüldü" diye sorulur. Bekleme süresince talep Görev Havuzu'nda kalır; yanıt gelmezse bir üst seviyeye aktarılır. Bekleme 0 ise talep hemen üst seviyeye aktarılır.</p>
    <form method="post" action="/yonetici/sla/kurallar" class="filters" novalidate>
        <?= $csrfField() ?>
        <div>
            <label for="breach_wait_days">Müşteri kararı için bekleme (iş günü)</label>
            <input type="number" id="breach_wait_days" name="breach_wait_days" min="0" max="10" value="<?= e($rules['breach_wait_days']) ?>">
            <?= $ruleError('breach_wait_days') ?>
        </div>
        <div>
            <label for="extra_percent">Ek SLA oranı (ilk sürenin %)</label>
            <input type="number" id="extra_percent" name="extra_percent" min="1" max="100" value="<?= e($rules['extra_percent']) ?>">
            <?= $ruleError('extra_percent') ?>
        </div>
        <div>
            <label for="auto_close_days">Çözüm onayı yanıtsızsa otomatik tamamlama (iş günü)</label>
            <input type="number" id="auto_close_days" name="auto_close_days" min="1" max="30" value="<?= e($rules['auto_close_days']) ?>">
            <?= $ruleError('auto_close_days') ?>
        </div>
        <div>
            <label for="max_questions">Personelin talep başına soru sınırı</label>
            <input type="number" id="max_questions" name="max_questions" min="0" max="10" value="<?= e($rules['max_questions']) ?>">
            <?= $ruleError('max_questions') ?>
        </div>
        <div>
            <label for="question_reply_hours">Müşterinin soruyu yanıtlama süresi (saat; bu sürede SLA durur)</label>
            <input type="number" id="question_reply_hours" name="question_reply_hours" min="1" max="168" value="<?= e($rules['question_reply_hours']) ?>">
            <?= $ruleError('question_reply_hours') ?>
        </div>
        <div>
            <label for="score_base">Performans puanı: talep başına taban puan (1. denemede tam, 2. %70, 3. %40; eskalasyonla devralana +%20)</label>
            <input type="number" id="score_base" name="score_base" min="1" max="1000" value="<?= e($rules['score_base']) ?>">
            <?= $ruleError('score_base') ?>
        </div>
        <div><button type="submit" class="btn">Kuralları kaydet</button></div>
    </form>
</section>
