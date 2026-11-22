<?php /** @var callable $csrfField */ ?>
<p><a href="/yonetici/ayarlar">← Sistem Yapılandırması</a></p>
<section class="card narrow-wide">
    <h1>Veri Yedeği</h1>
    <p>Tüm talepler, kullanıcılar, ayarlar ve geçmiş kayıtları tek bir zip dosyası olarak indirilir. Yüklenen ek dosyalar yedeğe dahil değildir.</p>
    <p class="muted">Geri yükleme güvenlik nedeniyle web'den yapılmaz; gerekirse zip içindeki dosyalar sunucuda <code>storage/data</code> klasörüne elle kopyalanır.</p>
    <form method="post" action="/yonetici/yedek/indir">
        <?= $csrfField() ?>
        <button type="submit" class="btn">Yedeği indir</button>
    </form>
</section>
