<?php
/* Sistem Yapılandırması giriş sayfası: bölüm kartları. */
$sections = [
    ['/yonetici/ayarlar/site', 'Site Ayarları', 'Site başlığı, logo, ana sayfa metni ve personel varsayılan kapasitesi.'],
    ['/yonetici/kategoriler', 'Kategoriler', 'Talep kategorilerini ekleyin, adlandırın, aktif ya da pasif yapın.'],
    ['/yonetici/oncelikler', 'Öncelikler', 'Öncelik seviyeleri, sıraları, varsayılan SLA süreleri ve anahtar kelimeler.'],
    ['/yonetici/sla', 'SLA Süreleri', 'Kategori ve öncelik eşleşmesine göre çözüm süreleri (iş saati).'],
    ['/yonetici/takvim', 'Çalışma Takvimi', 'Çalışma günleri, mesai ve öğle arası saatleri, resmî tatiller.'],
    ['/yonetici/ayarlar/guvenlik', 'Güvenlik ve Oturum', 'Hatalı giriş deneme sınırı, engel süresi, oturum süresi, hareketsiz personelin pasif yapılması.'],
    ['/yonetici/personel', 'Personel', 'Personel hesapları, aktif/pasif durumu ve kapasite.'],
    ['/yonetici/hiyerarsi', 'Personel Hiyerarşisi', 'Her kategoride personelin seviyesi; atama ve eskalasyon bu sırayı izler.'],
    ['/yonetici/izinler', 'Personel İzinleri', 'İzinli personel izin günlerinde yeni talep almaz.'],
    ['/yonetici/denetim', 'Denetim Kaydı', 'Yöneticilerin yaptığı son değişiklikler.'],
    ['/yonetici/yedek', 'Veri Yedeği', 'Tüm verileri zip dosyası olarak indirin.'],
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
