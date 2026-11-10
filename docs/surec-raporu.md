# Aşama 2 Süreç Raporu

**Proje:** Destek Talebi ve Dinamik SLA Yönetim Sistemi
**Depo:** https://github.com/BerkayBaskara/destek-talebi-sistemi
**Grup:** Berkay Başkara (`BerkayBaskara`), Yusuf Taha Aydın (`ysfaydnth`), Boran Koç (`BoranKoc`)

## 1. Proje ve çekirdek problem

Sistem, müşterilerin destek talebi açtığı, personelin bu talepleri ortak havuzdan alıp Kanban panosunda ilerlettiği ve yöneticinin genel durumu izlediği bir web uygulamasıdır. Her talep için bir SLA (Hizmet Seviyesi Anlaşması) bitiş zamanı hesaplanır ve süre dolmaya yaklaşan talepler renkli uyarılarla gösterilir.

Çekirdek problem, SLA bitiş zamanının **iş saatine göre** hesaplanmasıdır. Süre takvim saati olarak değil, yalnızca mesai saatleri içinde işler: akşamlar, hafta sonları, öğle arası ve resmi tatiller sayılmaz. Örneğin Cuma 17:30'da açılan 4 saatlik bir talebin bitiş zamanı Cuma'dan kalan 30 dakika, Pazartesi 09:00–12:00 arası 3 saat ve öğle arasından sonraki 30 dakika toplanarak Pazartesi 13:30 olarak bulunur. Çalışma saatleri ve tatiller `storage/config/calendar.json` dosyasından okunur; dosya bozuk veya eksikse sistem çökmeden varsayılan mesai saatleriyle devam eder.

Aşama 2'de veriler veritabanı yerine `storage/data/` altındaki JSON dosyalarında tutulur.

## 2. Aşama 2 boyunca yapılan işler

| Tarih | İş | Grup üyesi | Bağlantı |
|---|---|---|---|
| 21 Ekim | README ve klasör iskeleti, video eki gereksinimi, sayfa haritasının güncellenmesi | Berkay Başkara | #46, #47, #48, #49 |
| 21 Ekim | Aşama 2 ve Aşama 3 issue'larının (#24–#45) açılıp kilometre taşlarına bağlanması | Berkay Başkara | Issue #24–#45 |
| 9 Kasım | Aşama 2 tasarım belgesi ve uygulama planı | Yusuf Taha Aydın | #50 |
| 9–10 Kasım | Altyapı: tek giriş noktası, router, oturum, CSRF, JSON depolama | Yusuf Taha Aydın | #51 |
| 9–10 Kasım | SLA hesaplama algoritması ve testleri | Yusuf Taha Aydın | #52 |
| 9–10 Kasım | Kayıt, giriş, gizli soruyla şifre sıfırlama | Yusuf Taha Aydın | #53 |
| 9–10 Kasım | State deseniyle durum geçişleri | Yusuf Taha Aydın | #54 |
| 9–10 Kasım | Talep açma, akıllı öncelik, otomatik atama | Yusuf Taha Aydın | #55 |
| 9–10 Kasım | Ek dosya yükleme ve yetkili indirme | Yusuf Taha Aydın | #56 |
| 9–10 Kasım | Talep detayı, mesajlaşma, durum geçmişi | Yusuf Taha Aydın | #57 |
| 9–10 Kasım | Ortak havuz, AJAX Kanban, canlı SLA göstergesi | Yusuf Taha Aydın | #58 |
| 9–10 Kasım | Yönetici talep listesi ve özet rapor | Yusuf Taha Aydın | #59 |
| 9–10 Kasım | Örnek veri, README, izlenebilirlik tablosu | Yusuf Taha Aydın | #60 |
| 10 Kasım | Pull request'lerin `main` dalına birleştirilmesi | Berkay Başkara | #50–#62 |
| 10 Kasım | Belgelerde düzeltmeler ve yapay zeka günlüğü | Yusuf Taha Aydın | #61, #62 |

## 3. Tasarım ve uygulama kararları

**Karar 1: Sunucuda üretilen sayfalar ve yalnız gereken yerde JavaScript.**
Alternatif olarak bir JSON API ile tek sayfalık bir JavaScript uygulaması değerlendirildi. Bu yol daha akıcı bir arayüz verirdi, ancak view katmanını boşaltır, CSRF ve oturum yönetimini karmaşıklaştırır ve kodun büyük bölümünü tarayıcıya taşırdı. Sayfaları PHP görünümleriyle üretip JavaScript'i yalnız Kanban sürükle-bırak ve SLA rozetlerinin yenilenmesinde kullanmayı seçtik. Böylece katmanlar ayrı kaldı ve her kural sunucuda denetlendi.

**Karar 2: Her tablo için ayrı bir JSON dosyası.**
Tüm veriyi tek bir `db.json` dosyasında tutmak her işlemi tek kilitle korumayı kolaylaştırırdı, ancak her istekte bütün veriyi okuyup yazmayı gerektirir ve ER diyagramındaki tablolara karşılık gelmezdi. Her tablo için ayrı dosya (`users.json`, `tickets.json` vb.) ve ortak bir `JsonStore` sınıfı kullandık. Birden çok dosyaya dokunan işlemlerde dosyalar her zaman aynı sırayla kilitlenir; bu da iki işlemin birbirini sonsuza kadar beklemesini (deadlock) önler. Bu yapı Aşama 3'te tablo tablo MySQL'e geçişi de kolaylaştırır.

**Karar 3: Arka plan görevi yerine istek anında hesaplama.**
SRS'teki bazı gereksinimler "arka planda sürekli denetim" ifadesi içeriyor. PHP'de bunun için bir zamanlanmış görev (cron) gerekir; bu da kurulumu zorlaştırır. SLA bitiş zamanını talep açılırken bir kez hesaplayıp kaydettik; kalan süre ve uyarı seviyesi ise sayfa ya da AJAX isteği geldiğinde o anki saate göre hesaplanır. Böylece ek bir servis kurmadan doğru sonuç elde edilir.

**Karar 4: Aynı talebi iki personelin almasını koşullu güncellemeyle engellemek.**
Kontrolü yalnız arayüzde yapmak yetersizdi; iki istek aynı anda gelebilir. Talep alma işlemi kilit içinde "talep hâlâ sahipsiz ve beklemede mi" kontrolüyle yapılır (`assignIfUnassigned`). İkinci personel "Bu talep az önce başka bir personel tarafından alındı." mesajını görür. Aynı yaklaşım diğer durum değişikliklerinde de kullanılır (`saveIfStatus`). Aşama 3'teki karşılığı `UPDATE ... WHERE agent_id IS NULL` biçiminde koşullu bir sorgudur.

**Karar 5: Giriş için sistemin ürettiği müşteri numarası.**
E-posta ile giriş değerlendirildi, ancak SRS e-posta ve SMS'i kapsam dışı bırakıyor. Kayıtta ad, soyad, şifre ve gizli soru alınır; sistem `M` ile başlayan altı haneli bir müşteri numarası üretir. Şifre sıfırlama gizli soruyla yapılır.

## 4. Yapay zeka günlüğünden örnekler

Ayrıntılı kayıtlar `docs/ai-gunlugu.md` dosyasındadır.

1. **Yapay zekanın yanıldığı örnek, rapor ekranı:** Yapay zeka, ödevdeki "en az bir rapor ekranı" şartını tamamen Aşama 3'e bırakmayı önerdi. Aşama 3'teki raporun projenin genel raporu olduğunu, Aşama 2'nin de kendi özet ekranına ihtiyaç duyduğunu fark ettik ve öneriyi reddettik. Yönetici için durum, öncelik, SLA uyarısı ve personel iş yükü gösteren özet rapor Aşama 2'de yapıldı (#59).
2. **Türkçe harf karşılaştırması:** Yapay zekanın yazdığı karşılaştırma "Internet" ile "internet"i, "ACIL" ile "acil"i eşleştirmiyordu. Bu hata, gizli sorunun cevabı büyük harfle yazıldığında şifre sıfırlamayı da bozuyordu. Hata önce bir testle gösterildi, ardından tüm karşılaştırmalar için ortak bir fonksiyon yazıldı (#57).
3. **Yüksek çözünürlüklü fotoğraf:** Dosya boyutu küçük ama çözünürlüğü çok yüksek bir fotoğraf, küçültme sırasında PHP'nin belleğini tüketiyordu. Görselin çözünürlüğü artık dosya açılmadan kontrol ediliyor ve sınırı aşan görsel anlaşılır bir mesajla reddediliyor (#59, #60).

## 5. Testler

- **Test sayısı:** 221 PHPUnit testi, 945 doğrulama; hepsi geçiyor. Testler `vendor/bin/phpunit` komutuyla çalışır. Birim testleri `tests/Unit`, uçtan uca istek testleri `tests/Feature` altındadır.
- **Çekirdek problem:** `SlaCalculatorServiceTest` içinde 18 test vardır. Bunlardan 12'si vaka tablosudur: öğle arasına denk gelen süre, molada açılan talep, mesai öncesi ve sonrası açılan talep, hafta sonu, resmi tatil, birkaç güne yayılan süre ve Cuma 17:30 örneği.
- **Önemli bir sınır durumu:** Pazartesi 09:00'da açılan 8 saatlik talep tam mesai bitiminde, Pazartesi 18:00'de biter; ertesi güne kaymaz. Pazartesi 07:00'de, mesai başlamadan açılan 1 saatlik talep ise 08:00'de değil 10:00'da biter.
- **Geçersiz girdiler:** bozuk takvim dosyası, boş veya bozuk veri dosyası, uzantısı `.jpg` olan PHP dosyası, izinsiz durum geçişi ve başka kullanıcının talebine erişim denemesi de testlerle kontrol edilir.
- **Tekrarlanabilirlik:** Servisler saati doğrudan okumaz, `ClockInterface` üzerinden alır. Testlerde `FixedClock` kullanıldığı için sonuçlar hangi gün çalıştırılırsa çalıştırılsın aynıdır. Testler gerçek veri dosyalarına dokunmaz; her test kendi geçici klasöründe çalışır.
- **Testin gerçekten ölçtüğünün kontrolü:** `SlaCalculatorService` içinde `$from = max($minute, $intervalStart);` satırı geçici olarak `$from = $minute;` yapıldığında, yani mesai dışında geçen süre de sayılmaya başladığında, vaka tablosundaki 12 vakanın 11'i başarısız olur; yalnız öğle arasından önce biten en basit vaka geçer. Satır geri alındığında 18 testin hepsi yeniden geçer.
- **İzlenebilirlik tablosu:** `docs/izlenebilirlik.md`. Her işlevsel gereksinim, kullanıcı hikâyesi ve kalite gereksinimi ilgili test sınıfı ve metoduyla eşleştirilmiştir.
- **PHPStan:** seviye 6, 0 hata.

## 6. Karşılaşılan sorunlar ve çözümler

1. **Aynı anda yapılan durum değişiklikleri:** Müşteri talebini iptal ederken personel aynı talebi alırsa, iki istekten biri diğerinin kaydının üzerine yazabiliyordu. Kayıt artık yalnız talebin durumu okunduğu andaki gibiyse yapılıyor; değilse kullanıcıya "Talep bu arada değişti. Sayfayı yenileyip tekrar deneyin." mesajı gösteriliyor.
2. **PHP'nin yükleme sınırı:** WAMP'taki PHP'nin varsayılan yükleme sınırı 2 MB'tır. Daha büyük bir video gönderildiğinde PHP form verisini tamamen düşürüyor, kullanıcı da yanıltıcı bir güvenlik anahtarı (CSRF) hatası görüyordu. Bu durum ayrıca algılanıp "dosya çok büyük" mesajı gösteriliyor; `public/.htaccess` ve README'deki çalıştırma komutu sınırı 50 MB'a çıkarıyor.
3. **Oturum süresi dolduktan sonra gönderilen form:** 30 dakika işlem yapılmadığında oturum kapanıyor (NFR-10). Bu sırada açık kalan bir form gönderildiğinde kullanıcı anlaşılmaz bir CSRF hatası görüyordu. Artık giriş sayfasına yönlendiriliyor ve oturumun neden kapandığı açıklanıyor.

## 7. Kod incelemesi ve geri bildirimler

Her parça ayrı bir dalda geliştirildi ve pull request ile `main` dalına birleştirildi (#50–#62). Pull request'ler Berkay Başkara tarafından birleştirildi.

Kod incelemesinde tespit edilen sorunlar ve düzeltmeleri:

| Sorun | Düzeltme | Pull request |
|---|---|---|
| Beklenmeyen hatalarda hata ekranının kullanıcı girdisini gösterebilmesi | Genel 500 sayfası; ayrıntı yalnız sunucu kaydında | #57 |
| Durum değişikliklerinin birbirinin üzerine yazabilmesi | Koşullu kayıt (`saveIfStatus`) | #57 |
| Türkçe büyük ve küçük harf karşılaştırması | Ortak karşılaştırma fonksiyonu | #57 |
| Yüksek çözünürlüklü görselin belleği tüketmesi | Çözünürlüğün dosya açılmadan kontrolü | #59 |
| PHP yükleme sınırında yanıltıcı CSRF mesajı | Doğru mesaj ve 50 MB sınır | #59 |
| Yönetici listesinde filtreleme kodunun controller'da olması | Filtreleme servise taşındı | #60 |

**Eleştirmen grubun geri bildirimleri:** Bu rapor hazırlandığı tarihte projemizi inceleyen bir eleştirmen gruptan henüz geri bildirim almadık; bu nedenle kabul ettiğimiz veya katılmadığımız bir eleştirmen önerisi bulunmamaktadır. Geri bildirim geldiğinde öneriler GitHub issue'su olarak açılacak, yapılan düzeltmeler ilgili pull request'lerle bağlanacaktır.

## 8. Grup üyelerinin katkıları

- **Berkay Başkara:** Aşama 1 belgelerinin büyük bölümü (SRS, tasarım belgesi, UML ve ER diyagramları, ekran taslakları, README iskeleti; #1–#49). GitHub issue'larının ve kilometre taşlarının açılması. Aşama 2 pull request'lerinin `main` dalına birleştirilmesi.
- **Yusuf Taha Aydın:** Aşama 2 prototipinin pull request'leri (#50–#62): tasarım belgesi ve uygulama planı, altyapı, SLA algoritması, kimlik doğrulama, durum geçişleri, talep açma, dosya yükleme, talep detayı, Kanban, özet rapor, örnek veri, testler ve belgeler.
- **Boran Koç:** SRS'te kısıtlamalar, bağımlılıklar ve tanımlar bölümü (#5). Aşama 2 issue'larına atanan üyelerden biri. Aşama 3'te MySQL geçişi ve `aşama-3` etiketli issue'lardan sorumlu.

## 9. Aşama 3: MySQL'e geçiş

**Değişecek sınıflar:** `src/Repository/Json/` altındaki sekiz sınıfın yerine aynı arayüzleri uygulayan MySQL sınıfları yazılacak:

- `JsonUserRepository`
- `JsonTicketRepository`
- `JsonCategoryRepository`
- `JsonSlaPolicyRepository`
- `JsonReplyRepository`
- `JsonAttachmentRepository`
- `JsonTicketLogRepository`
- `JsonCalendarRepository` (FR-13 gereği çalışma saatleri veritabanından okunacak)

Nesneler `src/Kernel.php` içinde oluşturulduğu için bağlantı noktası tek yerdedir. Servisler yalnız repository arayüzlerine bağlı olduğundan controller ve servis kodu değişmez; mevcut servis testleri aynen çalışmalıdır.

**Riskler:**

- **Eşzamanlılık:** `assignIfUnassigned` ve `saveIfStatus` yöntemleri MySQL'de koşullu `UPDATE` sorgularıyla (`WHERE agent_id IS NULL AND status = 'pending'`) ve etkilenen satır sayısı kontrolüyle yazılmalıdır; aksi hâlde iki personel aynı talebi alabilir.
- **Saat dilimi:** Tarihler veritabanına yazılırken saat dilimi kaybolursa SLA hesapları kayar. Bağlantıda saat dilimi `Europe/Istanbul` olarak sabitlenmelidir.
- **Şema:** ER diyagramına Aşama 2'de dört alan eklendi (`tickets.title`, `tickets.customer_priority`, `tickets.resolved_at`, `users.surname`); migration dosyaları bunları içermelidir.
- **Kimlik numaraları:** JSON'da sıra numarasını `_sequences.json` üretiyor; MySQL'de bu iş `AUTO_INCREMENT` ile yapılacak.

**Mimarinin desteği:** Repository arayüzleri, Observer olayları (`ticket_created`, `status_changed` vb.) ve talep modelindeki hazır alanlar (`attempt_count`, `agent_question_count`, `earned_points`) sayesinde eskalasyon, ek süre ve puan sistemi gibi Aşama 3 özellikleri mevcut kodu değiştirmeden eklenebilir.
