# Destek Talebi ve Dinamik SLA Yönetim Sistemi

Bu proje, Core PHP (OOP, PDO) kullanılarak dış bir framework'e (Laravel, Symfony vb.) bağlı kalmadan katmanlı MVC mimarisiyle geliştirilmiş gelişmiş bir IT Hizmet Yönetimi ve Destek Talebi yazılımıdır. 

Proje, dinamik SLA (Hizmet Seviyesi Sözleşmesi) hesaplamaları, puanlama tabanlı personel metrikleri ve katı durum geçiş yönetimi sunar.

## 🚀 Temel Özellikler

- **Dinamik SLA Hesaplama:** Yöneticiler tarafından yüklenen JSON tabanlı tatil ve mesai saatleri dosyasına göre çalışma dışı saatleri atlayarak milisaniye hassasiyetinde SLA bitiş tarihi hesaplar.
- **Puanlama:** Personel talepleri tek seferde çözdüğünde tam puan, 2. ve 3. denemelerde azalan puanlar alır. Eskalasyon devirlerinde asıl personel puan kaybederken, devralan personel %20 bonus zorluk puanı kazanır.
- **State ve Observer Desenleri:** Biletlerin durum geçişleri (Bekleniyor -> İşlemde -> Çözüldü -> Kapalı) State deseni ile korunur. SLA süre bitimleri veya yönetici izin girişleri Observer deseni ile dinlenerek anında otomatik eskalasyon tetikler.
- **Havuz ve İzin Yönetimi (Smart Dispatch):** Boştaki biletler ortak havuza düşer. İzni olan personeller havuza dahil edilmez. Aynı bileti iki personelin almasını engellemek için veritabanında satır kilidi kullanılır.
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
├── database/          # Veritabanı dosyaları (Migration ve Seed betikleri)
├── docs/              # Dokümantasyon klasörü
│   ├── images/        # Proje içi kullanılan görseller
│   ├── wireframes/    # Arayüz taslakları (Wireframe çizimleri)
│   ├── ai-gunlugu.md  # Yapay zeka kullanım günlüğü
│   ├── srs.md         # Yazılım Gereksinim Şartnamesi
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