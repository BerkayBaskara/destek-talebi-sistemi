<?php
/* Sistem Yapılandırması giriş sayfası: bölüm kartları. */
$sections = [
    ['/yonetici/ayarlar/site', 'Site Ayarları', 'Site başlığı, logo, ana sayfa metni ve personel varsayılan kapasitesi.'],
    ['/yonetici/kategoriler', 'Kategoriler', 'Talep kategorilerini ekleyin, adlandırın, aktif ya da pasif yapın.'],
    ['/yonetici/oncelikler', 'Öncelikler', 'Öncelik seviyeleri, sıraları, varsayılan SLA süreleri ve anahtar kelimeler.'],
    ['/yonetici/sla', 'SLA Süreleri', 'Kategori ve öncelik eşleşmesine göre çözüm süreleri (iş saati).'],
    ['/yonetici/takvim', 'Çalışma Takvimi', 'Çalışma günleri, mesai ve öğle arası saatleri, resmî tatiller.'],
    ['/yonetici/personel', 'Personel', 'Personel hesapları, aktif/pasif durumu ve kapasite.'],
    ['/yonetici/denetim', 'Denetim Kaydı', 'Yöneticilerin yaptığı son değişiklikler.'],
];
?>
<section class="card">
    <h1>Sistem Yapılandırması</h1>
    <p class="muted">Değişiklikler yeni taleplere uygulanır; açılmış taleplerin SLA bitiş zamanı yeniden hesaplanmaz.</p>
    <div class="config-grid">
        <?php foreach ($sections as [$url, $name, $text]): ?>
            <a class="config-card" href="<?= e($url) ?>">
                <strong><?= e($name) ?></strong>
                <span class="muted"><?= e($text) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
