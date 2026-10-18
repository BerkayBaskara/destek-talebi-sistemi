# 1. Mimari

Projemiz, herhangi bir harici framework (Laravel, Symfony vb.) kullanılmadan, tamamen modern PHP standartlarına (PSR-4, PSR-12) uygun Core PHP ile geliştirilmektedir. Sistem; JSON tabanlı dinamik takvim işleme, özel Müşteri Numarası ile e-postasız giriş, ortak havuz yönetimi ve akıllı eskalasyon gibi karmaşık iş kurallarını barındırdığı için, bu yükü hafifletmek adına katı bir MVC (Model-View-Controller) türevi mimari ile tasarlanmıştır.

## 1.1. Katman Şeması

| Katman | Görevi | Asla Yapmaz | Destek Talebi Projesi Örneği |
| :--- | :--- | :--- | :--- |
| **Router** | URL adresine göre hangi controller metodunun çalışacağını seçer. | İş kuralı veya algoritma içermez. | `POST /ticket/accept` → `TicketController::acceptTicket` |
| **Controller** | İstekten veriyi alır, ilgili servisi çağırır ve görünümü (View) veya JSON (AJAX için) döndürür. | Veritabanı sorgusu yazmaz, JSON takvimini ayrıştırmaz. | `TicketController`, `AuthController` |
| **Service** | Projenin iş kurallarını (3 hak kuralı, 3 gün oto-kapanış) ve çekirdek SLA algoritmasını barındırır. | `$_POST` okumaz, HTML üretmez veya ekrana çıktı basmaz. | `SlaCalculatorService::calculateWithJson`, `TicketService::assignFromPool` |
| **Repository** | Veritabanı (PDO) veya dosya sistemi (JSON) okuma/yazma işlemlerini soyutlar. | İş kuralı, yetki veya eskalasyon kontrolü içermez. | `TicketRepositoryInterface`, `JsonCalendarRepository` |
| **Model** | Veritabanındaki bir kaydı temsil eden, veriyi taşıyan sade sınıflardır. | Veritabanı bağlantısı açmaz, SQL çalıştırmaz. | `Ticket`, `User`, `CalendarConfig` |
| **View** | Kullanıcıya HTML arayüzünü (Kanban panosu vb.) gösterir ve XSS önlemi alır. | Veritabanından doğrudan veri çekmez, iş hesabı yapmaz. | `views/kanban/board.php`, `views/auth/login.php` |

## 1.2. Klasör Yapısı

Sistemdeki tüm istekler `public/index.php` üzerinden karşılanır. Güvenlik gereği JSON takvim dosyaları ve kullanıcı yüklemeleri kapalı dizinlerde (storage) tutulur.

```text
destek-talebi-sistemi/
├── public/            # index.php (tek giriş noktası), css, js (Kanban AJAX), görseller
├── src/
│   ├── Controller/    # HTTP/AJAX isteklerini karşılayan sınıflar
│   ├── Service/       # JSON takvim okuma, SLA esnekliği ve eskalasyon algoritmaları
│   ├── Repository/    # PDO veritabanı ve JSON dosya okuma/yazma arayüzleri
│   └── Model/         # Varlık sınıfları (User, Ticket, Escalation)
├── views/             # Şablon dosyaları (Müşteri ekranları, Personel Kanban panosu)
├── database/          # MySQL migration (3NF) ve örnek veri (seed) betikleri
├── tests/             # JSON çalışma saatleri ve SLA sınır testleri için PHPUnit
├── docs/              # srs.md, tasarim.md, ai-gunlugu.md, wireframes/
├── storage/           # Yüklenen takvim JSON dosyaları ve müşteri eklentileri (korumalı alan)
├── composer.json      # PSR-4 otomatik yükleme ve test/analiz bağımlılıkları
└── README.md          # Kurulum ve yapılandırma talimatları
```


## 2. Sınıf Diyagramı


## 3. Sıralama Diyagramı


## 4. ER Diyagramı Ve Normalizasyon


## 5. Tasarım Desenleri

Projede mimari zorunluluk olan Repository desenine ek olarak, dinamik bilet akışını ve eskalasyon/izin yönetimini kontrol altında tutmak için iki temel tasarım deseni uygulanmıştır.

## 5.1 State Deseni
* **Problem:** Sistemdeki taleplerin Talep Bekleniyor, Talep Alındı, Talep İnceleniyor ve Talep Çözüldü gibi katı bir akış izlemesi gerekmektedir. Bir müşterinin talebi sadece "Bekleniyor" statüsündeyken iptal edilebilmeli, ilgili personel ise 3. çözüm reddinden sonra talebi tekrar "Alındı" statüsüne geçirememelidir.
* **Desen:** State deseni; biletin güncel statüsünü temsil eden durum sınıfları (PendingState, TakenState, ResolvedState vb.) ile uygulanır. Her sınıf kendi içindeki "iptal edilebilir mi?", "tekrar alınabilir mi?" kurallarını barındırır.
* **Alternatif:** Desensiz çözüm, TicketService veya Controller içinde durumu kontrol eden devasa ve okunabilirliği çok az olan bir if/elseif/switch yığını kullanmaktı.
* **Neden Seçildi:** Kurallar karmaşıklaştığı için , her durumun geçiş mantığını kendi sınıfına hapsetmek koddaki hata ihtimalini minimize eder ve mimariyi temiz tutar.

## 5.2 Observer Deseni
* **Problem:** Sistemde bir olayın başka olayları tetiklemesi gereken çoklu durumlar vardır: Yöneticinin bir personele izin girmesi o an SLA'sı dolmak üzere olan biletleri anında eskalasyona sokmalı, 3 iş günü dolduğunda bilet otomatik kapanmalı veya müşteri itiraz ettiğinde SLA süresi örn: +12 saat uzamalıdır.
* **Desen:** Observer deseni; bir olay gerçekleştiğinde sistemi haberdar eden yayıncılar ve bu olayları dinleyip tepki veren EscalationObserver, SlaExtensionObserver gibi gözlemci sınıflarla uygulanır.
* **Alternatif:** Desensiz çözüm, personelin izin ekleme fonksiyonunun içine gidip SLA hesaplama ve eskalasyon devretme fonksiyonlarını (hard-coded) doğrudan çağırmaktı.
* **Neden Seçildi:** Gözlemci deseni sayesinde izin yönetim modülü ile bilet yönetim modülü birbirinden tamamen bağımsız çalışır. Bu sayede modüller arası tight coupling engellenir.


## 6. Arayüz ve Sayfa Haritası


## 7. Risk Listesi

Geliştirme sürecinde projenin iş kurallarını (JSON takvim, SLA, havuz yönetimi) ve zaman planlamasını tehdit eden riskler aşağıda listelenmiş ve derecelendirilmiştir.

| Risk | Olasılık | Etki | Önlem |
| :--- | :--- | :--- | :--- |
| **JSON takvim işleme algoritmalarının SLA'i yanlış hesaplaması (Çekirdek Problem)** | Orta | Yüksek | Yüklenen JSON formatının kesin yapısal doğrulamadan (schema validation) geçirilmesi ve `SlaCalculatorService` için kapsamlı PHPUnit testleri yazılması. |
| **Aynı anda birden fazla personelin Ortak Havuzdaki aynı bileti alması (Race Condition)** | Düşük | Yüksek | Veritabanı okuma-yazma anında `SELECT ... FOR UPDATE` ile satır kilitleme kullanılması ve PDO transaction bloklarının oluşturulması. |
| **Müşterilerin sistem üretimi numaralarını veya Gizli Soru cevaplarını unutması** | Yüksek | Orta | Müşteriye kayıt sonrasında benzersiz numarasının net bir tasarımla vurgulanması ve Yöneticiler için "Müşteri Bilgisi Görüntüleme/Sıfırlama" arayüzü sağlanması. |
| **Oto-eskalasyon döngülerinin sunucuyu kilitlemesi veya sonsuz döngüye girmesi** | Orta | Yüksek | Yöneticinin personele izin girdiği anda çalışan eskalasyon tetikleyicisinin, işlem öncesi veritabanı kilit mekanizmaları ile korumaya alınması. |
| **Yapay zekânın, mimari katman kurallarını çiğneyerek spagetti kod üretmesi** | Yüksek | Orta | AI'a verilen her geliştirme isteminin başına 1. Bölüm'deki katman tablosunun bağlam (context) olarak verilmesi ve kodların depoya atılmadan önce manuel incelenmesi. |
| **Ekip içi geliştirme esnasında çakışan kodlar (Git Conflict)** | Yüksek | Düşük | Özelliklerin dosya bazında modüllere ayrılarak dağıtılması ve dal (branch) / Pull Request sistemiyle kontrollü kod birleştirme uygulanması. |


## 8. Plan