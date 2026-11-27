<?php
/** @var callable $csrfField */
/** @var array<string, string> $errors */
?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card narrow-wide">
    <h1>Veri Yedeği</h1>
    <p>Tüm talepler, kullanıcılar, ayarlar ve geçmiş kayıtları tek bir zip dosyası olarak indirilir. Yüklenen ek dosyalar yedeğe dahil değildir. Yedekte şifrelerin geri döndürülemez özetleri de bulunur; dosyayı güvenli bir yerde saklayın.</p>
    <form method="post" action="/yonetici/yedek/indir">
        <?= $csrfField() ?>
        <button type="submit" class="btn">Yedeği indir</button>
    </form>
</section>
<section class="card narrow-wide">
    <h2>Yedekten geri yükle</h2>
    <p class="muted">Seçilen yedek mevcut verinin yerine geçer. Geri yüklemeden önce mevcut veri sunucuda <code>storage/backups</code> klasörüne otomatik olarak yedeklenir. Geri yüklemeden sonra yeniden giriş yapmanız gerekebilir.</p>
    <form method="post" action="/yonetici/yedek/geri-yukle" enctype="multipart/form-data" data-confirm="Mevcut verinin yerine yedekteki veri yüklenecek. Onaylıyor musunuz?">
        <?= $csrfField() ?>
        <div class="form-row">
            <label for="yedek">Yedek dosyası (zip)</label>
            <input type="file" id="yedek" name="yedek" accept=".zip,application/zip" required>
            <?php if (isset($errors['yedek'])): ?><div class="field-error"><?= e($errors['yedek']) ?></div><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-danger">Geri yükle</button>
    </form>
</section>
