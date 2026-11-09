# Aşama 2 Tasarımı — Veritabanısız (JSON) Geliştirme

**Tarih:** 2026-11-09 · **Sorumlu:** Yusuf Taha Aydın · **Teslim:** 11 Kasım 2026
**Dayanak:** `docs/srs.md`, `docs/tasarim.md`, `docs/images/*`, `docs/wireframes/*`, hocanın "Öğrenci Kılavuzu" ve "Proje Konuları ve Beklentiler" belgeleri (proje 15).

## 1. Amaç ve neden

Hocanın takviminde Aşama 2 "veritabanısız geliştirme"dir: sistem, veriler JSON dosyalarında tutularak uçtan uca çalışır hale gelir. Aşama 3'te yalnız repository sınıfları PDO + MySQL ile değiştirilecek, testler aynı kalacaktır. Bu yüzden Aşama 2'nin asıl hedefi iki şeydir:

1. **Çekirdek problemin çözülmüş ve test edilmiş olması:** mesai saatine, öğle arasına ve resmi tatillere duyarlı SLA hesabı (hocanın örneği: Cuma 17:30'da açılan 4 saatlik talebin son zamanı Pazartesi öğleden sonra).
2. **Aşama 3'e temiz devir:** katmanlar ve repository arayüzleri öyle kurulur ki Boran yalnız `MySql*Repository` sınıflarını yazar ve Aşama 3 özelliklerini (eskalasyon, raporlar vb.) hazır olay noktalarına takar.

İş bölümü: Aşama 2'nin tamamı Yusuf'ta, Aşama 3 etiketli işler BoranKoc'ta.

## 2. Hedefler

**Aşama 2 issue'ları (milestone "Sunum 2"):**

| Issue | Gereksinim | Kısa tanım |
|---|---|---|
| #45 | FR-01 | Kategori, öncelik, başlık, mesaj alanlı talep formu |
| #24 | FR-02 | Ek dosya: JPG, PNG, PDF, MP4, WebM; MIME ve boyut kontrolü |
| #25 | FR-03 | Anahtar kelimeyle akıllı (gölge) öncelik |
| #26 | FR-04 | Ortak havuz ve en az iş yüklü personele otomatik atama |
| #27 | FR-05 | Mesai, öğle arası ve tatile duyarlı SLA bitiş zamanı |
| #28 | FR-06 | State deseniyle durum geçişlerinin kısıtlanması |
| #29 | FR-07 | SLA'nın %80'inde sarı, %100'ünde kırmızı uyarı |
| #31 | FR-09 | AJAX, sürükle-bırak personel Kanban panosu |
| #41 | FR-19 | Talep detayında kronolojik durum geçmişi |

**Issue'da olmayan ama gerekli olanlar (hocanın ortak şartları):**

- Kayıt, giriş, çıkış, gizli soruyla şifre sıfırlama; 3 rol; her işlemde yetki kontrolü.
- Router, controller, service, repository, view katmanları; tek giriş noktası.
- Güvenlik: `password_hash`, çıktı kaçışı, CSRF jetonu, `session_regenerate_id`, güvenli dosya yükleme.
- Müşteri ile personel arasında yanıtlaşma (US-04, US-10).
- Arama ve filtre (Taleplerim ekranı).
- **Aşama 2 özet ekranı** (hocanın "en az bir rapor ekranı" şartı; genel/final rapor FR-12 Boran'da).
- Seed betiğiyle yüklenen yeterli örnek veri.
- 375 px'te yatay kaydırmasız arayüz, anlaşılır başarı/hata mesajları, hatalı formda girilen değerlerin korunması.
- Çekirdek için en az 10 PHPUnit testi, PHPStan 0 hata, gereksinim-test izlenebilirlik tablosu, kurulum talimatlı README.

## 3. Hedef dışı (Aşama 3, Boran)

MySQL/PDO repository'leri, migration ve seed SQL; FR-08 otomatik eskalasyon; FR-10 SLA kural yönetimi ekranı; FR-11 tatil takvimi ekranı; FR-12 genel SLA raporu; FR-13 mesai saatlerinin yönetici ekranından düzenlenmesi; FR-14 ek süre; FR-15 puan sistemi; FR-16 yöneticinin talep açması; FR-17 3 iş günü oto-kapanış; FR-18 bildirimler; FR-20 izinli personeli atamadan çıkarma; FR-21 ret sayacı ve 3 hak kuralı; FR-22 personel soru hakkı.

Bu özellikler için Aşama 2'de yalnız **takılma noktaları** bırakılır: alanlar (`attempt_count`, `agent_question_count`, `earned_points`, `is_question`), Observer olayları ve repository arayüzleri.

## 4. Anti-hedefler (çalışsa bile başarısızlık sayılacaklar)

- İş mantığının controller'a veya görünüme sızması (hoca bunu yapay zekânın sık hatası olarak sayıyor).
- Aşama 3'e geçişte servislerin veya testlerin değişmesini gerektiren bir repository tasarımı.
- Yusuf'un sunumda açıklayamayacağı karmaşıklıkta kod. Kod sade, yorumları yerinde Türkçe olmalı.
- Herhangi bir framework veya framework'e özgü kod (Laravel, Symfony vb.).
- Tarayıcıdan gelen tarih, süre veya durum bilgisine güvenmek.

## 5. Kısıtlar

- PHP 8.2+ (geliştirme ortamı WAMP PHP 8.3.28), Composer PSR-4 (`App\` → `src/`), PSR-12, namespace.
- Framework yok. Harici çalışma zamanı kütüphanesi yok; yalnız geliştirme bağımlılıkları PHPUnit 11 ve PHPStan 2.
- Klasör yapısı hocanın şablonu: `public/`, `src/{Controller,Service,Repository,Model}`, `views/`, `database/`, `tests/`, `docs/`, ek olarak mevcut `storage/`.
- Tarayıcıdan yalnız `public/` erişilebilir.
- Saat dilimi sabit `Europe/Istanbul` (SRS risk listesi).
- Durum akışı SRS'teki gibi; hocanın adlarına yalnız eşleme yapılır.
- İzinli ek türleri: JPG, PNG, PDF, MP4, WebM.
- Kayıt: ad, soyad, şifre, gizli soru ve cevabı; e-posta/SMS yok.

## 6. Yaklaşımlar

Yusuf yapım kararlarını bana bıraktı ("ne gerekiyorsa ekleyebilirsin, hocanın isteklerine uysun"). Değerlendirilen seçenekler:

### 6.1 Arayüz

| Seçenek | Artı | Eksi |
|---|---|---|
| **A. Sunucuda üretilen PHP görünümleri + yalnız gereken yerde küçük JavaScript (seçildi)** | Hocanın "view katmanı" şartına birebir uyar; PRG ve form değerlerinin korunması kolay; az JavaScript, açıklaması kolay | Kanban dışındaki sayfalar tam sayfa yenilenir |
| B. JSON API + tek sayfalık JavaScript uygulaması | Akıcı arayüz | View katmanı boşalır; JavaScript yükü ve süre riski 11 Kasım için fazla; CSRF/oturum işleri karmaşıklaşır |

AJAX yalnız hocanın ve SRS'in istediği yerde kullanılır: Kanban sürükle-bırak (FR-09) ve SLA geri sayımının yenilenmesi.

### 6.2 JSON depolama

| Seçenek | Artı | Eksi |
|---|---|---|
| **A. Her tablo için ayrı JSON dosyası, ortak `JsonStore` sınıfı (seçildi)** | ER diyagramındaki tablolarla birebir; Aşama 3'te tablo tablo geçiş kolay; kilit dosya başına | Birden çok dosyaya dokunan işlemler tek kilitle korunmaz |
| B. Tek `db.json` dosyası | Tek kilitle tüm işlemler atomik | Her işlemde tüm veri okunup yazılır; tablolara karşılık gelmez |

Birden çok dosyaya dokunan işlemler (ör. talebi personele atama + log yazma) için `JsonStore::transaction()` birden fazla dosyayı sabit sırayla kilitler; böylece ölü kilit (deadlock) olmaz.

### 6.3 Kanban sürükle-bırak

Tarayıcının yerleşik HTML5 sürükle-bırak API'si + `fetch` (seçildi). Harici kütüphane (ör. SortableJS) gerekmez; mobilde sürükle-bırak yerine her kartta durum seçme menüsü yedek olarak bulunur (375 px şartı).

## 7. Mimari

```
public/index.php  →  Router  →  Controller  →  Service  →  Repository (arayüz)  →  JsonStore → storage/data/*.json
                                    ↓                ↓
                                  View          Observer olayları
```

- **`public/index.php`:** tek giriş noktası. Oturumu başlatır, `src/bootstrap.php` ile nesneleri bağlar (elle yazılmış basit bağımlılık kurulumu, DI kütüphanesi yok), Router'ı çalıştırır. Hata yakalama burada.
- **`App\Core\Router`:** HTTP yöntemi + yol kalıbı (`/tickets/{id}`) → `Controller::metod`. İş kuralı içermez.
- **`App\Core\Request` / `Response`, `Session`, `Csrf`, `View`:** küçük yardımcılar. `View::render('tickets/show', [...])` çıktıyı `htmlspecialchars` ile kaçıran `e()` yardımcısını kullanır.
- **Controller'lar** (`src/Controller`): `AuthController`, `TicketController`, `PoolController`, `KanbanController` (JSON döner), `AttachmentController`, `ReportController`, `HomeController`.
- **Servisler** (`src/Service`): `AuthService`, `TicketService`, `SlaCalculatorService`, `SlaStatusService` (yüzde/renk/kalan süre), `AssignmentService`, `PriorityDetector`, `AttachmentService`, `ReportService`.
- **Repository arayüzleri** (`src/Repository`): `UserRepositoryInterface`, `TicketRepositoryInterface`, `CategoryRepositoryInterface`, `SlaPolicyRepositoryInterface`, `ReplyRepositoryInterface`, `AttachmentRepositoryInterface`, `TicketLogRepositoryInterface`, `CalendarRepositoryInterface`. JSON uygulamaları `src/Repository/Json/` altında. (`escalations` ve `agent_leaves` arayüzleri Aşama 3'te Boran'a ait.)
- **Modeller** (`src/Model`): `User`, `Ticket`, `Category`, `SlaPolicy`, `Reply`, `Attachment`, `TicketLog`, `CalendarConfig`; enum'lar `Role`, `TicketStatus`, `Priority`.
- **Zaman:** `App\Core\Clock\ClockInterface` (`SystemClock`, testlerde `FixedClock`). Hiçbir servis doğrudan `new DateTime()` çağırmaz; böylece SLA ve uyarı testleri tekrarlanabilir.

Sınıf diyagramındaki adlar korunur (`TicketService`, `SlaCalculatorService::calculateWithJson`, `JsonCalendarRepository`, `TicketObserver`, `PendingState`, `TakenState`...). Diyagramda olmayan sınıflar eklenir; diyagramdaki hiçbir sınıf kaldırılmaz.

## 8. Veri (JSON) düzeni

`storage/data/` altında ER diyagramındaki tablo adlarıyla dosyalar: `users.json`, `tickets.json`, `categories.json`, `sla_policies.json`, `replies.json`, `attachments.json`, `ticket_logs.json` ve sayaçlar için `_sequences.json`. Alan adları ER diyagramıyla aynıdır (snake_case), böylece Aşama 3'te sütunlara birebir karşılık gelir. Tarihler ISO 8601 (`2026-11-13T17:30:00+03:00`). Öncelik ER'deki gibi tam sayı: 1 Düşük, 2 Normal, 3 Yüksek, 4 Acil.

**ER diyagramına eklenen alanlar (Boran'ın Aşama 3 migration'ında da eklemesi gerekir):**

| Tablo | Alan | Neden |
|---|---|---|
| `tickets` | `title` VARCHAR(150) | Talep formunda başlık var (FR-01, wireframe), ER'de yok |
| `tickets` | `customer_priority` INT | Müşterinin seçtiği öncelik; `priority` sistemin etkin önceliği (FR-03) |
| `tickets` | `resolved_at` DATETIME NULL | Son çözüm zamanı; SLA'ya uyum ve ortalama çözüm süresi için |
| `users` | `surname` VARCHAR(100) | Kayıtta ad ve soyad ayrı alınıyor (US-06) |

Talebin açıklaması ayrı bir sütunda tutulmaz: müşterinin ilk mesajı olarak `replies` tablosuna yazılır (ER ile uyumlu).

- `JsonStore`: `all()`, `find(id)`, `insert(row)`, `update(id, row)`, `transaction([dosyalar], callable)`. Okuma paylaşımlı kilit (`LOCK_SH`), yazma özel kilit (`LOCK_EX`); yazma önce geçici dosyaya, sonra `rename` ile (yarım yazılmış dosya kalmaz).
- **Eşzamanlılık (NFR-04):** havuzdaki bir talebi iki personel aynı anda almaya çalışırsa, `TicketService::take()` kilit içinde talebin hâlâ `Talep Bekleniyor` ve sahipsiz olduğunu yeniden kontrol eder; ikinci istek "Bu talep az önce başka bir personel tarafından alındı" hatasıyla reddedilir. Aşama 3'teki karşılığı `SELECT ... FOR UPDATE`.
- `storage/` web kökü dışında; `.gitignore`'a `storage/data/*.json` ve `storage/uploads/*` eklenir (örnek veri seed betiğinden üretilir).

### 8.1 Takvim JSON'u (`storage/config/calendar.json`)

```json
{
  "timezone": "Europe/Istanbul",
  "work_days": {
    "1": {"start": "09:00", "end": "18:00", "breaks": [{"start": "12:00", "end": "13:00"}]},
    "2": {"...": "..."},
    "5": {"start": "09:00", "end": "18:00", "breaks": [{"start": "12:00", "end": "13:00"}]}
  },
  "holidays": ["2026-01-01", "2026-04-23", "2026-05-01", "..."]
}
```

- Anahtarlar ISO gün numarası (1 = Pazartesi … 7 = Pazar). Listede olmayan gün tatildir.
- `holidays`: tam gün resmi tatiller (2026 ve 2027 Türkiye resmi tatilleri seed ile yazılır). Yarım gün arifeler bir sonraki sürümün işi; Aşama 2'de tam gün tatil olarak girilebilir.
- **NFR-07:** dosya yoksa, JSON bozuksa ya da şemaya uymuyorsa `JsonCalendarRepository` kod içindeki varsayılana döner (Pzt–Cum 09:00–18:00, öğle 12:00–13:00, tatil yok) ve durumu hata günlüğüne yazar; sistem çökmez.

## 9. Çekirdek: SLA hesabı (FR-05)

`SlaCalculatorService::calculateWithJson(DateTimeImmutable $start, int $durationMinutes): DateTimeImmutable`

1. Başlangıç mesai dışındaysa (akşam, hafta sonu, tatil, öğle arası) bir sonraki mesai anına ilerlenir.
2. Kalan süre, o günün çalışma aralıklarından (mola çıkarılmış parçalar) sırayla düşülür.
3. Gün biterse sonraki iş gününe geçilir; tatil ve çalışılmayan günler atlanır.
4. Süre tam bir aralığın sonunda biterse bitiş zamanı o anın kendisidir (ör. 18:00).

Karmaşıklık: aşılan gün sayısıyla doğrusal; yüzlerce yıllık tatil verisinde bile milisaniyeler (NFR-01). Tatiller `isset` ile sabit zamanda bakılan bir küme olarak tutulur. Sonsuz döngüye karşı en fazla 3 yıl ileri bakılır, aşılırsa istisna atılır (takvimde hiç iş günü yoksa).

**SLA saati ne zaman başlar ve durur:** SLA, talebin açıldığı anda başlar (FR-05; havuzda beklemek de süreye sayılır). Bitiş zamanı (`deadline`) açılışta bir kez hesaplanır ve Aşama 2'de değişmez (ek süre FR-14 Boran). Talep `resolved` olunca sayaç durur ve `resolved_at` yazılır. "SLA'ya uyuldu mu" sorusu `resolved_at <= deadline` ile cevaplanır. Müşteri çözümü reddederse talep `in_review`'a döner ve sayaç aynı `deadline`'a göre devam eder (çözümde geçen süre için ayrıca duraklatma yapılmaz). `closed` ve `cancelled` taleplerde sayaç gösterilmez.

**SLA süresi:** `sla_policies` dosyasından `(category_id, priority)` çiftine göre saat cinsinden; bulunamazsa önceliğin varsayılan süresi (Acil 4 sa, Yüksek 8 sa, Normal 16 sa, Düşük 24 sa — iş saati).

**Ortak yardımcı:** `businessMinutesBetween($a, $b)` aynı takvim mantığıyla iki an arasındaki iş dakikasını sayar; `SlaStatusService` bunu kullanır.

**Vaka tablosu (testlerin çekirdeği, en az 10):**

| # | Açılış | Süre | Beklenen bitiş | Neyi gösterir |
|---|---|---|---|---|
| 1 | Pzt 10:00 | 2 sa | Pzt 12:00 | Basit, mola öncesi |
| 2 | Pzt 11:00 | 2 sa | Pzt 14:00 | Öğle arası atlanır |
| 3 | Pzt 12:30 (molada) | 1 sa | Pzt 14:00 | Molada açılan talep moladan sonra başlar |
| 4 | Pzt 17:00 | 2 sa | Sal 10:00 | Gün sonu devri |
| 5 | **Cum 17:30** | **4 sa** | **Pzt 13:30** | Hocanın örneği: hafta sonu atlanır (Cuma 30 dk + Pzt 09:00–12:00 3 sa + 13:00–13:30) |
| 6 | Cmt 10:00 | 1 sa | Pzt 10:00 | Hafta sonunda açılan talep |
| 7 | Pzt 20:00 | 1 sa | Sal 10:00 | Mesai sonrası açılan talep |
| 8 | Tatil öncesi gün 17:00 | 2 sa | Tatil sonrası ilk iş günü 10:00 | Resmi tatil atlanır |
| 9 | Tatilde açılan talep | 1 sa | Sonraki iş günü 10:00 | Tatil günü başlangıç |
| 10 | Pzt 09:00 | 8 sa | Pzt 18:00 | Tam gün, bitiş tam mesai sonunda |
| 11 | Cum 16:00 | 24 sa | Çar 16:00 | Çok günlü süre (Cum 2 + Pzt 8 + Sal 8 + Çar 6 sa) |
| 12 | Bozuk takvim JSON'u | 2 sa | Varsayılan takvimle sonuç | NFR-07 |

Tarihler testlerde sabit (ör. Kasım 2026 haftaları ve 29 Ekim gibi gerçek tatiller) seçilir.

## 10. Durumlar ve State deseni (FR-06)

`TicketStatus` (backed enum, değerleri ER'deki ENUM ile aynı):

| Değer | Ekranda | Hocanın adı | State sınıfı |
|---|---|---|---|
| `pending` | Talep Bekleniyor | Açık | `PendingState` |
| `taken` | Talep Alındı | İşlemde | `TakenState` |
| `in_review` | Talep İnceleniyor | İşlemde | `InReviewState` |
| `resolved` | Talep Çözüldü | Çözüldü | `ResolvedState` |
| `closed` | Kapalı | Kapalı | `ClosedState` |
| `cancelled` | İptal Edildi | (Kapalı, iptal) | `CancelledState` |

`TicketState` arayüzü sınıf diyagramındaki `cancel`, `take`, `resolve` metotlarına ek olarak `startReview`, `reject`, `close` metotlarını içerir. İzin verilmeyen geçiş `InvalidTransitionException` fırlatır ve kullanıcıya nedeni gösterilir.

İzinli geçişler:

- `pending → taken` (personel havuzdan alır veya otomatik atama), `pending → cancelled` (yalnız talebin sahibi müşteri — US-07)
- `taken → in_review` (personel incelemeye başlar)
- `in_review → resolved` (personel çözüm gönderir)
- `resolved → closed` (müşteri çözümü onaylar)
- `resolved → in_review` (müşteri çözümü reddeder; `attempt_count` bir artar. 3 hak sınırı ve eskalasyon FR-21/FR-08, Boran)
- `closed` ve `cancelled` son durumlardır; hiçbir geçişe izin vermez ("kapatılmış bilet yeniden İşlemde'ye alınamaz").

Her geçiş `TicketService` üzerinden yapılır, `ticket_logs`'a kayıt düşer (FR-19) ve `status_changed` olayı yayınlanır.

## 11. Observer altyapısı

`TicketObserver::update(Ticket $ticket, string $eventType): void` (sınıf diyagramı). `TicketService` gözlemcileri tutar ve olayları yayar: `ticket_created`, `ticket_assigned`, `status_changed`, `customer_replied`, `agent_replied`.

- Aşama 2'de çalışan gözlemci: `TicketLogObserver` (durum geçmişini yazar, FR-19). Böylece desen sunumda gerçekten çalışırken gösterilebilir.
- Aşama 3'te Boran'ın ekleyeceği: `EscalationObserver`, `SlaExtensionObserver` (sınıf diyagramındaki adlar). `bootstrap.php`'de tek satırla eklenirler.

## 12. Özellik ayrıntıları

- **Kayıt / giriş (US-06):** kayıt formu ad, soyad, şifre (en az 8 karakter), gizli soru (hazır listeden seçim) ve cevap. Cevap `password_hash` ile saklanır, karşılaştırma büyük/küçük harf ve baştaki/sondaki boşluk duyarsız. Kayıt sonunda sistem `M` + 6 haneli benzersiz **müşteri numarası** üretir ve bir kez gösterir ("bu numarayla giriş yapacaksınız"). Giriş: müşteri numarası + şifre. Personel (`P…`) ve yönetici (`Y…`) hesapları seed ile oluşturulur; personel ekleme ekranı (US-15) Aşama 2 kapsamında değil. Şifremi unuttum: müşteri numarası → gizli soru → doğru cevap → yeni şifre. Başarılı girişte `session_regenerate_id(true)`. 30 dakika hareketsizlikte oturum sonlanır (NFR-10).
- **Yetki:** her controller metodu rol kontrolü yapar; müşteri yalnız kendi taleplerini, personel yalnız kendisine atanmış talepleri ve havuzu, yönetici her şeyi görür. Yetkisiz erişim 403.
- **CSRF (NFR-06):** her POST formunda gizli alan, AJAX'ta `X-CSRF-Token` başlığı; eksik/yanlışsa 403.
- **Talep açma (FR-01, FR-03, FR-04, FR-05):** form alanları kategori, öncelik (Düşük, Normal, Yüksek, Acil), başlık, açıklama, en fazla 3 ek dosya. `PriorityDetector` başlık + açıklamada anahtar kelime arar (`storage/config/priority_keywords.json`, ör. "acil, çöktü, erişilemiyor, çalışmıyor, kritik" → Acil); sonuç müşterinin seçtiğinden yüksekse **etkin öncelik** odur, müşterinin seçimi ayrıca saklanır ve ekranda "Sistem önceliği yükseltti" notu görünür. SLA etkin önceliğe göre hesaplanır. Talep önce sıralama diyagramındaki gibi `pending` olarak kaydedilir. Aynı istekte `AssignmentService` aktif iş yükü (durumu `taken` veya `in_review` olan talep sayısı) en az olan personeli seçer; eşitlikte en küçük kullanıcı numaralı personel seçilir (öngörülebilir, test edilebilir). Talep o personele atanır ve `taken` olur. Hiç personel yoksa talep havuzda `pending` kalır. Başarıda PRG ile "Talebiniz alındı" mesajı.
- **Ortak havuz:** sahipsiz `pending` talepler; personel "Üzerime al" ile alır (kilitli kontrol, bkz. 8).
- **Ek dosya (FR-02, NFR-03, NFR-08):** izinli MIME türleri `finfo` ile gerçek içerikten kontrol edilir: `image/jpeg`, `image/png`, `application/pdf`, `video/mp4`, `video/webm`. Uzantıya güvenilmez. Boyut sınırı `storage/config/settings.json`'da (görsel/PDF 5 MB, video 50 MB; yönetici ekranı Aşama 3). Dosyalar `storage/uploads/` altında rastgele adla (`bin2hex(random_bytes(16))`) saklanır, yalnız yetkili kullanıcıya `AttachmentController` üzerinden verilir. JPG/PNG en fazla 1920×1080'e GD ile küçültülür; PDF ve video olduğu gibi saklanır.
- **Yanıtlaşma (US-04, US-10):** talep detay sayfasında kronolojik mesajlar; müşteri ve atanan personel yanıt yazabilir. `is_question` alanı her zaman `false` yazılır (soru hakkı FR-22 Boran).
- **Çözüm onayı:** `resolved` talebin detayında müşteri "Çözümü onayla" (→ `closed`) veya "Reddet" (→ `in_review`, `attempt_count` +1) düğmelerini görür. Personel detay sayfasından da durum ilerletebilir (`taken → in_review → resolved`), Kanban'a mecbur değildir.
- **SLA görünümü (FR-07, US-11):** `SlaStatusService` geçen iş dakikası / toplam iş dakikası oranını hesaplar: %80 altı normal, %80–99 sarı, %100 ve üstü kırmızı ("Süre aşıldı"). Kalan süre iş saatine göre "3 sa 20 dk" biçiminde. Kanban ve listelerde rozet olarak; sayfa açıkken `GET /api/tickets/sla` her 60 saniyede bir yenilenir. `closed`, `cancelled`, `resolved` taleplerde sayaç durur. (Eskalasyon tetiklemesi FR-08 Boran.)
- **Kanban (FR-09):** personelin kendi talepleri üç sütunda: Talep Alındı, Talep İnceleniyor, Talep Çözüldü. Kart sürüklenince `POST /api/tickets/{id}/status` (JSON); sunucu State ile doğrular, geçersizse kart eski yerine döner ve neden gösterilir. Geçerli hedefler: `taken → in_review`, `in_review → resolved`. Mobilde her kartta durum menüsü.
- **Durum geçmişi (FR-19):** talep detayında `ticket_logs` kronolojik listesi: tarih, kim, eski → yeni durum.
- **Taleplerim + arama/filtre:** müşteri listesi; durum filtresi ve başlık/numara araması; sayfa başına 10, sayfalama.
- **Aşama 2 özet ekranı (yönetici):** durumlara göre talep sayıları, etkin önceliğe göre dağılım, şu an sarı ve kırmızı durumdaki talepler (liste), personel başına aktif iş yükü, çözülmüş talepler için ortalama çözüm süresi (iş saati). Personel bazında ihlal oranı yok (FR-12, Boran).
- **Sayfalar:** sayfa haritasındaki Aşama 2'ye düşen sayfalar: Giriş, Kayıt, Şifremi Unuttum; Müşteri ana paneli, Yeni Talep, Taleplerim, Talep Detayı; Personel ana paneli, Ortak Havuz, Kanban, Talep Yönetimi (detay); Yönetici ana paneli, Özet raporu, tüm talepler listesi. Wireframe'ler görsel referanstır.
- **Arayüz:** sade, kendi yazdığımız tek CSS dosyası; 375–1920 px arası yatay kaydırma yok (NFR-05). Hatalı form gönderiminde değerler korunur.

## 13. Örnek veri (seed)

`php bin/seed.php` `storage/data/` ve `storage/config/` dosyalarını sıfırdan üretir: 1 yönetici, 3 personel, 5 müşteri (giriş bilgileri README'de), 4 kategori (Donanım, Yazılım, Ağ/Erişim, Hesap), kategori × öncelik SLA kuralları, 2026–2027 resmi tatilleri, her durumdan toplam 25–30 talep (bir kısmı sarı, bir kısmı kırmızı durumda olacak şekilde zamanlanmış), yanıtlar ve loglar. Sunumda boş veriyle demo yapılmaz.

## 14. Test ve kalite

- PHPUnit (`phpunit.xml`, `tests/Unit`): SLA vaka tablosu (≥12), State geçişleri (izinli/izinsiz), atama (en az iş yükü, eşitlik, personel yok), öncelik algılama, SLA yüzdesi/renk eşikleri, takvim yedeğe dönme (NFR-07), MIME/uzantı reddi, `JsonStore` kilit altında "ikinci alan reddedilir" senaryosu.
- PHPStan seviye 6, 0 hata (`phpstan.neon`).
- PSR-12'ye uygun yazım (otomatik biçimlendirici kurulmaz; elle uyum).
- `docs/izlenebilirlik.md`: gereksinim (FR/NFR) → test sınıfı/metodu tablosu.

## 15. Süreç

- Çalışma `asama-2` dalında, her parça ayrı ve anlamlı commit mesajıyla. Push ve pull request'i Yusuf açar (hoca PR ile çalışma istiyor; GitHub hesabı Yusuf'un).
- Yerel çalıştırma: `composer install`, `php bin/seed.php`, `php -S localhost:8000 -t public`. WAMP ile de çalışır (sanal host `public/`'i göstermeli). README'ye yazılır.
- AI günlüğü kayıtları Aşama 2 sonunda Yusuf tarafından eklenir.

## 16. Kurucuya bırakılanlar

Sınıf içi ayrıntılar, CSS görünümü, hata mesajlarının tam metni, seed verisindeki örnek isim ve başlıklar, rota adlarının tam biçimi (yukarıdakilerle çelişmemek kaydıyla).

## 17. Kararlarım (Yusuf'un kontrol etmesi için)

1. Giriş müşteri numarasıyla; numarayı sistem üretir (`M`, `P`, `Y` önekleri).
2. Öncelik değerleri: Düşük, Normal, Yüksek, Acil; varsayılan SLA süreleri 24/16/8/4 iş saati.
3. Akıllı öncelik yalnız yükseltir, hiçbir zaman düşürmez.
4. Eşit iş yükünde en küçük numaralı personel seçilir.
5. Müşterinin çözümü reddetmesi Aşama 2'de çalışır (sayaç artar), ama 3 hak kuralı ve eskalasyon Aşama 3'te.
6. Kanban'da yalnız ileri geçişler (Alındı → İnceleniyor → Çözüldü).
7. Dosya sınırları: görsel/PDF 5 MB, video 50 MB, talep başına en fazla 3 dosya.
8. Personel ve yönetici hesapları yalnız seed ile; kullanıcı yönetimi ekranı yok.
9. Yeni talep, açıldığı anda en az iş yüklü personele otomatik atanır; havuzda yalnız personel bulunamayan talepler kalır.
10. SLA havuzda beklerken de işler; çözümde durur; ret sonrası aynı bitiş zamanıyla devam eder.
11. ER'ye 4 alan eklendi (`tickets.title`, `tickets.customer_priority`, `tickets.resolved_at`, `users.surname`); açıklama ilk mesaj olarak `replies`'e yazılır.
