<?php
/** @var array<string, string> $values */
/** @var array<string, string> $errors */
/** @var callable $csrfField */
$fieldError = static fn (string $f): string => isset($errors[$f]) ? '<div class="field-error">' . e($errors[$f]) . '</div>' : '';
?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card narrow-wide">
    <h1>Güvenlik ve Oturum</h1>
    <form method="post" action="/yonetici/ayarlar/guvenlik" novalidate>
        <?= $csrfField() ?>
        <div class="form-row">
            <label for="max_attempts">Hatalı deneme sınırı (giriş ve şifre sıfırlama)</label>
            <input type="number" id="max_attempts" name="max_attempts" min="3" max="20" value="<?= e($values['max_attempts']) ?>">
            <?= $fieldError('max_attempts') ?>
        </div>
        <div class="form-row">
            <label for="lock_minutes">Sınır aşılınca engel süresi (dakika)</label>
            <input type="number" id="lock_minutes" name="lock_minutes" min="1" max="120" value="<?= e($values['lock_minutes']) ?>">
            <?= $fieldError('lock_minutes') ?>
        </div>
        <div class="form-row">
            <label for="idle_minutes">İşlem yapılmazsa oturumun kapanacağı süre (dakika)</label>
            <input type="number" id="idle_minutes" name="idle_minutes" min="5" max="480" value="<?= e($values['idle_minutes']) ?>">
            <?= $fieldError('idle_minutes') ?>
        </div>
        <div class="form-row">
            <label for="inactivity_days">Bu kadar gün giriş yapmayan personel pasif yapılsın (0 = kapalı)</label>
            <input type="number" id="inactivity_days" name="inactivity_days" min="0" max="365" value="<?= e($values['inactivity_days']) ?>">
            <?= $fieldError('inactivity_days') ?>
        </div>
        <button type="submit" class="btn">Kaydet</button>
    </form>
</section>
