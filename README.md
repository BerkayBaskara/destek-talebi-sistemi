# Destek Talebi ve Dinamik SLA Yönetim Sistemi

Bu proje, Core PHP (OOP, PDO) kullanılarak dış bir framework'e (Laravel, Symfony vb.) bağlı kalmadan katmanlı MVC mimarisiyle geliştirilmiş gelişmiş bir IT Hizmet Yönetimi ve Destek Talebi yazılımıdır. 

Proje, dinamik SLA (Hizmet Seviyesi Sözleşmesi) hesaplamaları, puanlama tabanlı personel metrikleri ve katı durum geçiş yönetimi sunar.

> Aşağıdaki özellik listesi projenin **tamamını** (Aşama 3 dahil) anlatır. Şu an hangi özelliklerin çalıştığı için aşağıdaki **"Aşama 2 durumu"** tablosuna bakın; PDO/MySQL, puanlama, eskalasyon ve oto-kapanış Aşama 3'tedir.

## 🚀 Temel Özellikler

- **Dinamik SLA Hesaplama:** Yöneticiler tarafından yüklenen JSON tabanlı tatil ve mesai saatleri dosyasına göre çalışma dışı saatleri atlayarak milisaniye hassasiyetinde SLA bitiş tarihi hesaplar.
- **Puanlama:** Personel talepleri tek seferde çözdüğünde tam puan, 2. ve 3. denemelerde azalan puanlar alır. Eskalasyon devirlerinde asıl personel puan kaybederken, devralan personel %20 bonus zorluk puanı kazanır.
- **State ve Observer Desenleri:** Biletlerin durum geçişleri (Bekleniyor -> İşlemde -> Çözüldü -> Kapalı) State deseni ile korunur. SLA süre bitimleri veya yönetici izin girişleri Observer deseni ile dinlenerek anında otomatik eskalasyon tetikler.
- **Havuz ve İzin Yönetimi (Smart Dispatch):** Uygun personel bulunamayan biletler Görev Havuzu'na düşer ve personelde yer açılınca otomatik atanır. İzni olan personeller havuza dahil edilmez. Aynı bileti iki personelin almasını engellemek için veritabanında satır kilidi kullanılır.
- **Sorgulama Hakkı ve Oto-Kapanış:** Personelin çözüme ulaşmak için SLA süresini dondurmadan müşteriye en fazla 2 soru sorma hakkı vardır. Çözülen talepler 3 iş günü içinde müşteri yanıtı almazsa otomatik olarak kapanır.
- **Üst Düzey Güvenlik:** Tüm formlarda CSRF Token, PDO ile SQL Injection koruması, XSS çıktı kaçışları (escaping) ve N-2 tarayıcı desteği mevcuttur. Müşteriler şifrelerini "Gizli Soru" mantığıyla sıfırlayabilir.

## 🛠️ Kullanılan Teknolojiler ve Mimari

- **Dil:** PHP 8.2+ (Strict Types, ES6+ JS, HTML5, CSS3)
- **Veritabanı:** MySQL / MariaDB (3NF Normalizasyon)
- **Mimari:** Model-View-Controller (MVC) Katmanlı Mimari
- **Tasarım Desenleri:** Repository, State, Observer
- **Standartlar:** PSR-4 (Autoloading), PSR-12 (Coding Style)
- **Test ve Analiz:** PHPUnit (Birim Testleri), PHPStan (Statik Kod Analizi)

## 📂 Klasör Yapısı

```text
destek-talebi-sistemi/
├── bin/               # Komut satırı betikleri (örnek veri, yönetici hesabı)
├── database/          # Veritabanı dosyaları (Migration ve Seed betikleri)
├── docs/              # Dokümantasyon klasörü
│   ├── asama-2/       # Aşama 2 tasarım belgesi ve uygulama planı
│   ├── images/        # Proje içi kullanılan görseller
│   ├── wireframes/    # Arayüz taslakları (Wireframe çizimleri)
│   ├── ai-gunlugu.md  # Yapay zeka kullanım günlüğü
│   ├── izlenebilirlik.md # Gereksinim → test tablosu
│   ├── srs.md         # Yazılım Gereksinim Şartnamesi
│   ├── surec-raporu.md # Aşama 2 süreç raporu
│   └── tasarim.md     # Tasarım ve mimari belgesi
├── public/            # Dışa açık klasör (index.php, CSS, JS, görseller)
├── src/               # Uygulama kaynak kodları
│   ├── Controller/    # Gelen HTTP isteklerini karşılayan kontrolcüler
│   ├── Model/         # Veritabanı tablolarını temsil eden varlık sınıfları
│   ├── Repository/    # Veri erişim arayüzleri ve sınıfları (JSON/MySQL)
│   └── Service/       # İş mantığı, SLA, oyunlaştırma ve eskalasyon kuralları
├── storage/           # Gizli JSON yapılandırmaları ve yüklenen bilet ekleri
├── tests/             # PHPUnit birim (unit) testleri
├── vendor/            # Composer bağımlılıkları ve otomatik yükleyici
├── views/             # Arayüz şablonları (HTML, formlar, Kanban vb.)
├── .gitignore         # Git tarafından takip edilmeyecek kural listesi
├── composer.json      # Proje bağımlılıkları ve PSR-4 ayarları
├── composer.lock      # Kurulan bağımlılıkların versiyon kilit dosyası
└── README.md          # Kurulum talimatları ve proje özeti
```

## 📌 Aşama 2 durumu (veritabanısız, JSON)

Aşama 2'de veriler `storage/data/*.json` dosyalarında tutulur (MySQL yok). Her tablo ER diyagramındaki adıyla ayrı bir dosyadır; aynı anda yazmaya karşı dosya kilidi (`flock`) kullanılır. Aşama 3'te yalnız `src/Repository/Json/*` sınıflarının yerine `MySql*` sınıfları yazılır; servisler ve testler değişmez.

| Gereksinim | Aşama 2'de |
|---|---|
| FR-01 talep formu, FR-02 ek dosya, FR-03 akıllı öncelik, FR-04 otomatik atama, FR-05 SLA hesabı, FR-06 State, FR-07 %80/%100 uyarı, FR-09 AJAX Kanban, FR-19 durum geçmişi | ✅ |
| Kayıt / giriş / gizli soruyla şifre sıfırlama, 3 rol, arama + filtre, Aşama 2 özet raporu | ✅ |
| Sistem Yapılandırması: site ayarları ve logo, kategoriler, öncelik seviyeleri ve anahtar kelimeler, SLA tablosu (FR-10), çalışma takvimi ve tatiller (FR-11, FR-13), personel hesapları, aktif/pasif durum ve kapasite, denetim kaydı | ✅ |
| Görev Havuzu: uygun personel yoksa talep bekler, yer açılınca otomatik atanır; "Üzerime Al" kapasiteye uyar | ✅ |
| Kategori bazlı personel hiyerarşisi ve personel izinleri; hiyerarşiye göre atama | ✅ |
| SLA aşımı kuralı: müşteriye sorulur ("aynı personelle devam" / "sorun çözüldü"), bekleme süresi, ek SLA, üst seviyeye eskalasyon (FR-08, FR-14) | ✅ |
| Çözüm onayı ("İşe yaradı" / "İşe yaramadı, aynı personelle devam" / "İşe yaramadı, başka personel istiyorum"), 3. retten sonra eskalasyon, yanıtsız çözümün otomatik tamamlanması, manuel eskalasyon, yöneticinin elle ataması, "çözülemedi" kapanışı | ✅ |
| Personelin müşteriye soru sorması (SLA durur, sınır ayarlı), SLA süresi dolan talebe personel yanıtının engellenmesi (FR-22, FR-60) | ✅ |
| Uygulama içi bildirimler (çan), 6 karakterli talep kodu, işlem geçmişi (Günlük) | ✅ |
| Ayrıntılı rapor, eskalasyon kayıtları ve CSV, alt kategoriler, kategoriye özel ek alanlar (metin, sayı, tarih, seçim, e-posta), dosya sınırları, güvenlik soruları, giriş ve şifre sıfırlama deneme sınırı, veri yedeği indirme ve geri yükleme | ✅ |
| Personel puanı (FR-15), yöneticinin talep açması (FR-16), müşteri hesaplarının aktif/pasif yapılması (FR-33), özel günler ve takvim dosyası yükleme (FR-13) | ✅ |
| MySQL, migration, JSON'dan aktarım | Aşama 3 |

Ayrıntılar: `docs/asama-2/tasarim-belgesi.md` (tasarım), `docs/asama-2/uygulama-plani.md` (uygulama planı), `docs/izlenebilirlik.md` (gereksinim → test), `docs/test-senaryolari.md` (test senaryosu → test).

## ⚙️ Kurulum

Gereksinimler: **PHP 8.2+** (`fileinfo`, `gd`, `mbstring` eklentileri), **Composer**.

```bash
composer install
php bin/seed.php
```

`php bin/seed.php` örnek veriyi sıfırdan üretir (storage/data içindeki tüm veriyi siler). Sarı/kırmızı SLA örnekleri seed'in çalıştığı ana göre ayarlanır; **sunumdan hemen önce yeniden çalıştırın**. Örnek veride her personelin kapasitesi elindeki talep sayısına eşittir; bu yüzden birkaç talep Görev Havuzu'nda bekler. Yönetici bir personelin kapasitesini artırınca ya da bir talep çözülünce bu talepler otomatik atanır.

**Yönetici hesabı** web'den açılamaz; yalnız sunucuda komut satırından oluşturulur:

```bash
php bin/create-admin.php <ad> <soyad> <şifre>
```

Komut yeni yöneticinin giriş numarasını (`Y` ile başlar) yazdırır. Personel hesapları yönetici panelindeki **Sistem Yapılandırması → Personel** ekranından açılır.

**SLA denetimi:** zamana bağlı kurallar (aşım sorusu, bekleme sonunda eskalasyon, çözüm onayının otomatik tamamlanması) uygulama açıkken her istekte en fazla dakikada bir çalışır; cron gerekmez. Site kapalıyken de çalıştırmak için:

```bash
php bin/sla-monitor.php
```

**Şifre kuralı:** en az 8 karakter; en az bir harf, bir rakam ve bir noktalama işareti. Kayıt, şifre sıfırlama ve personel oluşturma formlarında kural anlık gösterilir; asıl kontrol sunucudadır.

### Çalıştırma

**PHP'nin yerleşik sunucusu** (en kolayı):

```bash
php -d upload_max_filesize=50M -d post_max_size=160M -S localhost:8000 -t public public/index.php
```

Tarayıcıda `http://localhost:8000` açın. (`-d` ayarları, 5 MB görsel / 50 MB video yüklemesi içindir; PHP'nin varsayılanı 2 MB'tır.)

**WAMP (Apache):** bir sanal host oluşturup `DocumentRoot`'u bu projenin `public/` klasörü yapın. `public/.htaccess` tüm istekleri `index.php`'ye yönlendirir ve yükleme sınırlarını ayarlar (mod_rewrite açık olmalı; sanal hostta `AllowOverride All` gerekir).

### Örnek hesaplar (şifre: `Destek2026!`)

| Rol | Numara | Ad |
|---|---|---|
| Yönetici | Y000001 | Selin Arslan |
| Personel | P000001, P000002, P000003 | Zeynep Kaya, Murat Demir, Elif Şahin |
| Müşteri | M100001 – M100005 | Ahmet Yılmaz, Ayşe Çelik, Mehmet Öztürk, Fatma Aydın, Can Koç |

Gizli sorunun cevabı (şifre sıfırlama denemesi için): `pamuk`. Yeni müşteri "Kayıt ol" ekranından açılır; sistem müşteri numarasını kendisi üretir.

### Testler ve statik analiz

```bash
php vendor/bin/phpunit
php vendor/bin/phpstan analyse
```

Çekirdek SLA vaka tablosunu adlarıyla görmek için: `php vendor/bin/phpunit --testdox --filter SlaCalculatorServiceTest`

### Sistem Yapılandırması (yönetici paneli)

Yönetici menüsündeki **Sistem Yapılandırması** ekranından:

- **Site Ayarları:** site başlığı, logo (PNG/JPG, en fazla 1 MB), herkese açık ana sayfanın başlığı, metni ve görseli, gönderilecek bildirim türleri, personelin varsayılan kapasitesi.
- **Kategoriler** ve **Öncelikler:** ekleme, ad değiştirme, aktif/pasif yapma (silme yok; eski talepler bozulmaz). Önceliklerin sırası, varsayılan SLA süresi ve anahtar kelimeleri buradan düzenlenir.
- **SLA Süreleri:** kategori × öncelik tablosu (iş saati). Boş hücrede önceliğin varsayılan süresi kullanılır.
- **Çalışma Takvimi:** çalışma günleri, mesai ve öğle arası saatleri, resmi tatiller, özel günler (yarım gün, kapalı gün, özel çalışma günü), aylık görünüm, JSON takvim dosyası yükleme; değişikliğin açık taleplere uygulanıp uygulanmayacağı seçilir.
- **Güvenlik ve Oturum:** hatalı giriş deneme sınırı, engel süresi, hareketsizlikte oturum kapanma süresi, uzun süre giriş yapmayan personelin pasif yapılması.
- **Personel:** personel hesabı açma, ad soyad düzeltme, aktif/pasif yapma ve kapasite.
- **Müşteriler:** müşteri arama, aktif/pasif yapma (pasif müşteri giriş yapamaz).
- **Personel Hiyerarşisi** ve **Personel İzinleri:** her kategoride personelin seviyesi (1 ilk basamak); izinli personel yeni talep almaz.
- **SLA Süreleri** sayfasındaki kurallar: müşteri kararı için bekleme (varsayılan 1 iş günü; 0 = hemen eskalasyon), ek SLA oranı (varsayılan %33), çözüm onayı için otomatik tamamlama süresi (varsayılan 3 iş günü), personelin soru sınırı (varsayılan 2) ve müşterinin soruya yanıt süresi.
- **Site Ayarları** sayfasındaki dosya sınırları ve güvenlik soruları; **Veri Yedeği** (zip indirme ve geri yükleme; geri yüklemeden önce otomatik yedek alınır).
- **Kategoriler** sayfasında kategoriye özel ek talep alanları. Yönetici menüsünde **Ayrıntılı Rapor** (süzgeç + CSV).
- **Denetim Kaydı:** yöneticilerin yaptığı son 100 değişiklik (eski ve yeni değerleriyle).

Yapılan değişiklikler yeni taleplere uygulanır. Takvim değişikliğinin açık taleplerin SLA bitişine de uygulanması Çalışma Takvimi ekranından seçilir.

### Yapılandırma dosyaları (`storage/config/`)

- `calendar.json`: örnek verinin ilk takvimi (mesai, öğle arası, 2026–2027 resmi tatilleri). `php bin/seed.php` bu dosyayı takvim tablolarına aktarır; sonrasında takvim yönetici ekranından düzenlenir. Takvim verisi okunamazsa sistem varsayılan mesaiye (Pzt–Cum 09:00–18:00, öğle 12:00–13:00) döner.
- Ek dosya sınırları artık yönetici ayarındadır (Site Ayarları → Dosya sınırları).

## 🗄️ Aşama 3 için notlar (MySQL)

- ER diyagramına eklenen alanlar: `tickets.title`, `tickets.customer_priority`, `tickets.resolved_at`, `users.surname`. Talep açıklaması `replies` tablosuna ilk mesaj olarak yazılır.
- 2. grupla eklenen tablolar: `category_staff` (hiyerarşi), `staff_leaves`, `escalations`, `notifications`, `ticket_activities` (işlem geçmişi), `category_fields`, `attempts`, `runtime_state`. Bilete eklenen alanlar: `code`, `original_deadline`, `previous_agent_id`, `hold_until`, `closure_reason`, `breach_handled_at`, `awaiting_admin`, `extra_fields`.
- Yapılandırma için eklenen tablolar: `system_settings` (anahtar/değer), `admin_audit_logs` (yalnız ekleme), `priorities` (ad, sıra, varsayılan SLA, aktiflik, anahtar kelimeler), `work_hours` (7 gün), `holidays`. Eklenen alanlar: `users.active`, `users.capacity`, `categories.active`. `tickets.priority` ve `tickets.customer_priority` artık `priorities` tablosuna yabancı anahtardır.
- Eşzamanlılık: `TicketRepositoryInterface::assignIfUnassigned` ve `saveIfStatus` koşullu güncellemedir; MySQL karşılığı `UPDATE ... WHERE agent_id IS NULL AND status = 'pending'` / `WHERE status = ?` (veya `SELECT ... FOR UPDATE`).
- Observer: `TicketService` olayları (`ticket_created`, `status_changed`, `customer_replied` ...) yayınlar; `EscalationObserver` ve `SlaExtensionObserver` `src/Kernel.php`'de `attachObserver` ile eklenir.