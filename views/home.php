<?php
/** @var string $heading */
/** @var string $text */
/** @var string $image */
?>
<section class="hero">
    <?php if ($image !== ''): ?>
        <img src="<?= e($image) ?>" alt="" class="hero-image">
    <?php endif; ?>
    <h1><?= e($heading) ?></h1>
    <?php if (trim($text) !== ''): ?>
        <p class="hero-text"><?= nl2br(e($text)) ?></p>
    <?php endif; ?>
    <div class="hero-actions">
        <a class="btn btn-login" href="/giris">Giriş Yap</a>
        <a class="btn btn-light" href="/kayit">Kayıt Ol</a>
    </div>
</section>
<section class="steps">
    <div class="step">
        <strong>1. Kayıt olun</strong>
        <span class="muted">Müşteri numaranız kayıttan sonra ekranda gösterilir.</span>
    </div>
    <div class="step">
        <strong>2. Talebinizi açın</strong>
        <span class="muted">Kategori ve önceliği seçin, sorununuzu anlatın; isterseniz dosya ekleyin.</span>
    </div>
    <div class="step">
        <strong>3. Süreci takip edin</strong>
        <span class="muted">Talebinizin durumunu ve personelin yanıtlarını talep sayfasından izleyin.</span>
    </div>
</section>
