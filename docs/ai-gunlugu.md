# Yapay Zeka Günlüğü

## srs.md
srs.md dosyasında fonksiyonel/kalite gereksinimlerinin çoğu ve kullanıcı hikayeleri için de 3-4 örnek yapay zeka kullanılarak hazırlanmıştır.
* **Neden:** Fonksiyonel ve kalite gereksinimlerinin genel olarak tespit edilmesi ve kâğıda dökülmesi zordur. Bu aşamayı yapay zekaya yaptırıp inceledim; "kullanıcı hikayeleri"ne örnek teşkil etmesi için 3-4 örnek almanın daha verimli olacağına karar verdim.
* **Kullanılan prompt:** "Bana sistemin genelini ele alan 12 adet FR ve 6 adet NFR örneği ayrıca 3-4 tane de kullanıcı hikayesi verebilir misin?"
* **Müdahale:** Verilen 4-5 gereksinim eksik veya hatalıydı; bunlar ele alınıp düzeltildi, geri kalanlar doğrudan eklendi.

## tasarim.md
tasarim.md dosyasında katman şeması, klasör yapısı, tasarım desenleri, risk listesi ile plan ve backlog bölümlerinde ağırlıklı olarak yapay zeka kullanılmıştır. Diyagramlar, arayüz sayfa haritası ve ekran taslakları için ise yapay zekadan draw.io'da kullanılabilecek PlantUML formatında hazır şablonlar alınmış ve bunlar üzerinde değişiklikler yapılmıştır.
* **Neden:** Yapay zekanın sistemin tamamını daha iyi ele alması, gözden kaçan detayları yakalaması ve iş yükünü azaltması nedeniyle tercih edilmiştir.
* **Kullanılan prompt 1:** "Bana Destek Talebi Sistemi + SLA projesi için katman şeması, klasör yapısı, tasarım desenleri, risk listesi ve plan ve backlog'un detaylıca ele alındığı bir markdown kodu verebilir misin?"
* **Müdahale 1:** Müdahale olmadı.
* **Kullanılan prompt 2:** "Bana sınıf, sıralama ve ER diyagramları, arayüz sayfa haritası ve ekran taslaklarının her biri için draw.io'da kullanabileceğim, kopyala-yapıştır formatında alabileceğim PlantUML kodları verebilir misin?"
* **Müdahale 2:** Çoğu kez müdahale etmek zorunda kalındı; en son tüm kodları teker teker vermesi için yönlendirildi.

## Kapsam ve yapay zeka önerileri (09.11.2026)
* **Neden:** Issue'larda olmayan ama hocanın ortak şartları için gerekli altyapıyı belirlemek.
* **Sonuç:** Yapay zekanın önerileri kabul edildi: tek giriş noktası ve kendi Router'ımız, her tablo için ayrı JSON dosyası ve dosya kilidi (flock), örnek veri betiği, arama ve filtre, Observer altyapısının kurulup eskalasyonun Aşama 3'e bırakılması.
* **Müdahale:** Yapay zeka, hocanın "en az bir rapor ekranı" şartını tamamen Aşama 3'e bırakmayı önerdi. Bunu düzelttim: Aşama 3'teki rapor projenin genel raporu, Aşama 2'ye kadar olan kısmın özet raporu bu aşamada yapıldı.

## Kodlama (10.11.2026) — 11 dal, 11 PR (#50–#60)
* **Neden:** Planı görev görev uygulamak: önce başarısız test, sonra kod.
* **Kullanılan prompt:** "Planlarını uygulamaya başla, 1 alt agent gerekli yerlerde denetim yapsın."
* **Sonuç:** Altyapı (JSON depolama, Router, CSRF, oturum), SLA çekirdeği, State deseni, talep açma, akıllı öncelik, otomatik atama, dosya yükleme, talep detayı, AJAX Kanban, özet rapor, örnek veri, README ve izlenebilirlik tablosu. 221 PHPUnit testi geçiyor, PHPStan 0 hata.
* **Müdahale:** Her ekranı tarayıcıda denedim; 375 px genişlikte yatay kaydırma yok.

## Bağımsız denetimde yakalanan hatalar (10.11.2026)
Kodu yazan yapay zekanın kendi kodunda, ayrı bir inceleyici yapay zeka şu hataları buldu. Her biri önce hatayı gösteren bir testle kanıtlandı, sonra düzeltildi:
* Beklenmeyen hatalar yakalanmıyordu; WAMP'ta hata ekranı girilen şifreyi gösterebilirdi → genel 500 sayfası eklendi.
* Durum değişiklikleri kilitsiz okunup yazılıyordu; müşterinin iptali, personelin aynı anda talebi almasını silebiliyordu → "durum hâlâ aynıysa kaydet" kontrolü eklendi.
* Türkçe harf karşılaştırması "Internet" ile "internet"i, "ACIL" ile "acil"i eşleştirmiyordu; bu, gizli soruyla şifre sıfırlamayı da bozuyordu → ortak bir karşılaştırma fonksiyonu yazıldı.
* Çok yüksek çözünürlüklü bir fotoğraf PHP'nin belleğini tüketiyordu; talep o sırada zaten kaydedildiği için tekrar gönderimde çift talep oluşacaktı → görselin boyutu dosya açılmadan kontrol ediliyor.
* WAMP'ın 2 MB yükleme sınırı yüzünden 10 MB'lık video gönderen kullanıcı yanıltıcı bir "CSRF hatası" görüyordu → doğru mesaj gösteriliyor, `.htaccess` ile sınır 50 MB'a çıkarıldı.
* 30 dakika işlem yapılmadan gönderilen form "CSRF geçersiz" hatası veriyordu → giriş sayfasına yönlendiriliyor.
* Son denetimde: PNG görseller JPEG'den fazla bellek istediği için PNG'ye ayrı bir sınır kondu; örnek verideki sarı (SLA %80) talepler 30 dakikada kırmızıya döndüğü için uzun SLA'lı örnekler seçildi; yönetici listesinin filtreleme kodu controller'dan servise taşındı.
