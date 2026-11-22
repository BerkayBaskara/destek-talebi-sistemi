<?php
/** @var array<string, string> $values */
/** @var array<string, string> $errors */
/** @var string $logoPath */
/** @var int $maxLogoBytes */
/** @var callable $csrfField */
/** @var array<string, string> $groups */
$fieldError = static fn (string $f): string => isset($errors[$f]) ? '<div class="field-error">' . e($errors[$f]) . '</div>' : '';
?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card narrow-wide">
    <h1>Site Ayarları</h1>
    <?php if (array_intersect_key($errors, ['site_title' => 1, 'home_heading' => 1, 'home_text' => 1, 'default_capacity' => 1]) !== []): ?>
        <div class="alert alert-error" role="alert">Formda düzeltilmesi gereken alanlar var.</div>
    <?php endif; ?>
    <form method="post" action="/yonetici/ayarlar/site" novalidate>
        <?= $csrfField() ?>
        <div class="form-row">
            <label for="site_title">Site başlığı</label>
            <input type="text" id="site_title" name="site_title" maxlength="80" value="<?= e($values['site_title'] ?? '') ?>" required>
            <?= $fieldError('site_title') ?>
        </div>
        <div class="form-row">
            <label for="home_heading">Ana sayfa başlığı</label>
            <input type="text" id="home_heading" name="home_heading" maxlength="120" value="<?= e($values['home_heading'] ?? '') ?>" required>
            <?= $fieldError('home_heading') ?>
        </div>
        <div class="form-row">
            <label for="home_text">Ana sayfa metni</label>
            <textarea id="home_text" name="home_text" maxlength="2000"><?= e($values['home_text'] ?? '') ?></textarea>
            <?= $fieldError('home_text') ?>
        </div>
        <div class="form-row">
            <label for="default_capacity">Personel varsayılan kapasitesi (aynı anda üstlenilen talep sayısı)</label>
            <input type="number" id="default_capacity" name="default_capacity" min="1" max="100" value="<?= e($values['default_capacity'] ?? '') ?>" required>
            <div class="muted">Kendi kapasitesi girilmemiş personele uygulanır.</div>
            <?= $fieldError('default_capacity') ?>
        </div>
        <button type="submit" class="btn">Kaydet</button>
    </form>
</section>
<section class="card narrow-wide">
    <h2>Logo</h2>
    <?php if ($logoPath !== ''): ?>
        <p><img src="<?= e($logoPath) ?>" alt="Mevcut logo" class="logo-preview"></p>
    <?php else: ?>
        <p class="muted">Henüz logo yüklenmedi; üst bölümde yalnız site başlığı görünür.</p>
    <?php endif; ?>
    <form method="post" action="/yonetici/ayarlar/logo" enctype="multipart/form-data" novalidate>
        <?= $csrfField() ?>
        <div class="form-row">
            <label for="logo">Yeni logo (PNG ya da JPG, en fazla <?= e(intdiv($maxLogoBytes, 1_048_576)) ?> MB)</label>
            <input type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,image/png,image/jpeg" required>
            <?= $fieldError('logo') ?>
        </div>
        <button type="submit" class="btn">Logoyu Yükle</button>
    </form>
</section>
<section class="card narrow-wide">
    <h2>Dosya sınırları</h2>
    <p class="muted">Talebe eklenebilecek dosya sayısı ve boyutları. Sunucunun PHP yükleme sınırı bu değerlerden küçükse önce o uygulanır.</p>
    <form method="post" action="/yonetici/ayarlar/dosya" class="filters" novalidate>
        <?= $csrfField() ?>
        <div>
            <label for="max_image_mb">Görsel/PDF (MB)</label>
            <input type="number" id="max_image_mb" name="max_image_mb" min="1" max="20" value="<?= e($groups['max_image_mb']) ?>">
            <?= $fieldError('max_image_mb') ?>
        </div>
        <div>
            <label for="max_video_mb">Video (MB)</label>
            <input type="number" id="max_video_mb" name="max_video_mb" min="1" max="200" value="<?= e($groups['max_video_mb']) ?>">
            <?= $fieldError('max_video_mb') ?>
        </div>
        <div>
            <label for="max_count">Dosya sayısı</label>
            <input type="number" id="max_count" name="max_count" min="1" max="10" value="<?= e($groups['max_count']) ?>">
            <?= $fieldError('max_count') ?>
        </div>
        <div><button type="submit" class="btn">Kaydet</button></div>
    </form>
</section>
<section class="card narrow-wide">
    <h2>Güvenlik soruları</h2>
    <p class="muted">Kayıtta seçilebilen sorular, her satıra bir soru. Kullanıcı isterse kendi sorusunu da yazabilir. Değişiklik mevcut kullanıcıların sorusunu değiştirmez.</p>
    <form method="post" action="/yonetici/ayarlar/sorular" novalidate>
        <?= $csrfField() ?>
        <div class="form-row">
            <label for="questions">Sorular</label>
            <textarea id="questions" name="questions" rows="6"><?= e($groups['questions']) ?></textarea>
            <?= $fieldError('questions') ?>
        </div>
        <button type="submit" class="btn">Kaydet</button>
    </form>
</section>

