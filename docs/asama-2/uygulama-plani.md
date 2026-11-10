# Aşama 2 (JSON) Uygulama Planı

> Bu plan, [Aşama 2 tasarım belgesindeki](tasarim-belgesi.md) kararları görev görev uygulamak için kodlamadan önce yazıldı. Her görevde önce başarısız test yazıldı, sonra kod (TDD). Uygulama sırasında plandan ayrılan noktalar en alttaki "Uygulamada yapılan değişiklikler" bölümündedir.

**Hedef:** Destek Talebi + SLA sisteminin veritabanısız, verileri JSON dosyalarında tutan, çekirdek SLA algoritması test edilmiş Aşama 2 sürümünü 11 Kasım 2026'ya kadar çalışır hale getirmek.

**Mimari:** `public/index.php` → `Kernel` → `Router` → Controller → Service → Repository arayüzü → `JsonStore` (`storage/data/*.json`, `flock` kilidi). Görünümler sunucuda üretilen PHP şablonları; JavaScript yalnız Kanban sürükle-bırak ve SLA yenilemede. Zaman `ClockInterface` üzerinden okunur, durumlar State deseniyle, olaylar Observer deseniyle yönetilir.

**Teknolojiler:** PHP 8.2+ (geliştirme: WAMP PHP 8.3.28), Composer PSR-4 (`App\` → `src/`), PHPUnit 11, PHPStan 2, GD ve fileinfo eklentileri, sade CSS + vanilla JS.

**Tasarım belgesi:** `docs/asama-2/tasarim-belgesi.md`

## Genel kısıtlar

- PHP ≥ 8.2, `declare(strict_types=1);` her dosyada, PSR-12, namespace `App\…`.
- Framework yok; çalışma zamanı bağımlılığı yok. Yalnız dev: `phpunit/phpunit ^11`, `phpstan/phpstan ^2`.
- Saat dilimi her yerde `Europe/Istanbul`; hiçbir servis `new DateTime()`/`time()` çağırmaz, `ClockInterface::now()` kullanır.
- Tarayıcıdan yalnız `public/` erişilebilir; veri `storage/data/`, ekler `storage/uploads/`, ayarlar `storage/config/`.
- JSON alan adları ER diyagramıyla aynı (snake_case) + tasarımdaki 4 ek alan (`tickets.title`, `tickets.customer_priority`, `tickets.resolved_at`, `users.surname`).
- Öncelik tam sayı: 1 Düşük, 2 Normal, 3 Yüksek, 4 Acil; varsayılan SLA 24/16/8/4 iş saati.
- Durum değerleri: `pending`, `taken`, `in_review`, `resolved`, `closed`, `cancelled`.
- İzinli ek MIME türleri: `image/jpeg`, `image/png`, `application/pdf` (≤ 5 MB), `video/mp4`, `video/webm` (≤ 50 MB); talep başına en fazla 3 dosya.
- Her POST'ta CSRF (`_csrf` alanı veya `X-CSRF-Token` başlığı), yoksa 403. Her çıktı `e()` ile kaçırılır.
- Kullanıcıya görünen tüm metinler Türkçe. Kod adları sınıf diyagramındaki İngilizce adlarla.
- Her dal bir PR; dal adı `ozellik/<konu>`; commit mesajları Türkçe ve ne yapıldığını söyler.
- Komutlar repo kökünde: testler `php vendor/bin/phpunit`, analiz `php vendor/bin/phpstan analyse`.

## Denetimde özellikle bakılacaklar

1. **Türkçe büyük harfli anahtar kelime** ("ACİL", "SUNUCU ÇÖKTÜ") başlıkta yazılsa da akıllı öncelik algılamalı → Görev 11'e test.
2. **Mesai başlamadan açılan talep** (Pzt 07:00, 1 sa) Pzt 10:00'da bitmeli, 08:00'de değil → Görev 5'e test.
3. **Bozuk veya boş veri dosyası** (`users.json` elle boşaltılmış ya da yarım JSON) → boş dosya `[]` sayılır; bozuk JSON'da `StorageException`, dosyanın üzerine asla yazılmaz, kullanıcı "Veri okunamadı" 500 sayfası görür → Görev 2'ye test.
4. **Uzantısı `.jpg` ama içeriği PHP olan dosya** reddedilmeli (MIME içerikten okunur) → Görev 15'e test.
5. **Başka müşterinin talebine / ekine URL ile erişim** (`/talepler/7`, `/ekler/3`) 403, olmayan numara 404 vermeli; içerik sızmamalı → Görev 16'ya test.

---

## Dal ve PR düzeni

Her dal bir öncekinin ucundan açılır (birleştirmeyi beklemek gerekmez): `git switch -c ozellik/<sonraki>`. Dal bitince `git push -u origin <dal>` ile gönderilir, GitHub'da **base: main** olan bir PR açılır, açıklamaya ilgili `Closes #N` satırları yazılır ve PR'lar sırayla birleştirilir. Her dalın sonunda `php vendor/bin/phpunit` ve `php vendor/bin/phpstan analyse` temiz olmalıdır.

| Dal | Görevler | Kapattığı issue |
|---|---|---|
| `ozellik/tasarim-belgesi` | tasarım + bu plan | – |
| `ozellik/altyapi` | 1–3 | – |
| `ozellik/sla-hesaplama` | 4–6 | #27 |
| `ozellik/kimlik-dogrulama` | 7–8 | – |
| `ozellik/durum-gecisleri` | 9 | #28 |
| `ozellik/talep-acma` | 10–14 | #45, #25, #26 |
| `ozellik/dosya-yukleme` | 15 | #24 |
| `ozellik/talep-detayi` | 16 | #41 |
| `ozellik/kanban` | 17–18 | #31, #29 |
| `ozellik/ozet-raporu` | 19 | – |
| `ozellik/belgeler-ve-kalite` | 20 | – |

## Dosya haritası

```
bin/seed.php                         örnek veriyi üretir
public/index.php                     tek giriş noktası
public/assets/app.css, kanban.js, sla.js
src/Kernel.php                       nesneleri kurar, rotaları bağlar, isteği işler
src/routes.php                       rota tablosu
src/Core/Clock/{ClockInterface,SystemClock,FixedClock}.php
src/Core/Storage/{JsonStore,StorageException}.php
src/Core/Http/{Request,Response,Router,HttpException}.php
src/Core/Session/{SessionInterface,NativeSession,ArraySession}.php
src/Core/{Csrf,View}.php
src/Exception/{ValidationException,AuthorizationException,ConflictException,NotFoundException}.php
src/Model/{User,Ticket,Category,SlaPolicy,Reply,Attachment,TicketLog,CalendarConfig,SlaStatus}.php
src/Model/Enum/{Role,Priority,TicketStatus}.php
src/Model/State/{TicketState,AbstractTicketState,PendingState,TakenState,InReviewState,ResolvedState,ClosedState,CancelledState,InvalidTransitionException}.php
src/Repository/*RepositoryInterface.php
src/Repository/Json/Json*Repository.php
src/Service/{AuthService,SlaCalculatorService,SlaStatusService,PriorityDetector,AssignmentService,TicketService,AttachmentService,ReportService}.php
src/Service/Observer/{TicketObserver,TicketLogObserver}.php
src/Controller/{HomeController,AuthController,TicketController,AttachmentController,AgentController,KanbanController,AdminController}.php
src/Seed/DemoSeeder.php
views/layout.php, views/partials/*.php, views/{auth,tickets,agent,admin,errors}/*.php
storage/config/{calendar,priority_keywords,settings}.json   (repoda)
storage/data/, storage/uploads/                              (.gitignore)
tests/Unit/..., tests/Feature/..., tests/fixtures/...
docs/izlenebilirlik.md
```

---

### Görev 1: Araç yapılandırması ve saat soyutlaması

**Dal:** `ozellik/altyapi` (bu görevden önce: `git switch -c ozellik/altyapi`)

**Dosyalar:**
- Oluştur: `phpunit.xml`, `phpstan.neon`, `src/Core/Clock/ClockInterface.php`, `src/Core/Clock/SystemClock.php`, `src/Core/Clock/FixedClock.php`
- Değiştir: `composer.json` (scripts), `.gitignore`
- Test: `tests/Unit/Core/ClockTest.php`

**Arayüzler:**
- Sağlar: `ClockInterface::now(): \DateTimeImmutable`; `SystemClock::__construct(string $timezone = 'Europe/Istanbul')`; `FixedClock::__construct(\DateTimeImmutable $now)`, `FixedClock::set(\DateTimeImmutable $now): void`, `FixedClock::advance(string $modifier): void` (ör. `'+2 hours'`).

- [x] **Adım 1:** `phpunit.xml` (bootstrap `vendor/autoload.php`, suites `Unit` = `tests/Unit`, `Feature` = `tests/Feature`, `failOnWarning="true"`), `phpstan.neon` (level 6, paths `src`, `bin`), `composer.json`'a `"scripts": {"test": "phpunit", "analyse": "phpstan analyse"}` ve `autoload-dev` `"Tests\\": "tests/"`; `.gitignore`'a `storage/data/*.json`, `storage/uploads/*`, `!storage/uploads/.gitkeep`, `.phpunit.cache/`. `composer dump-autoload`.
- [x] **Adım 2: Failing test** `ClockTest`:
  - `test_system_clock_uses_istanbul_timezone`: `(new SystemClock())->now()->getTimezone()->getName() === 'Europe/Istanbul'`
  - `test_fixed_clock_returns_and_advances`: `new FixedClock(new DateTimeImmutable('2026-11-13 17:30', tz))` → `now()` formatı `'2026-11-13 17:30'`; `advance('+90 minutes')` → `'2026-11-13 19:00'`.
- [x] **Adım 3:** `php vendor/bin/phpunit --filter ClockTest` → test kırmızı (sınıf bulunamadı).
- [x] **Adım 4:** Üç sınıfı yaz.
- [x] **Adım 5:** Test yeşil; `php vendor/bin/phpstan analyse` → `[OK] No errors`.
- [x] **Adım 6:** Commit: `Test ve statik analiz yapılandırması ile saat soyutlaması eklendi`

### Görev 2: JsonStore (dosya başına tablo, kilitli yazma)

**Dosyalar:**
- Oluştur: `src/Core/Storage/JsonStore.php`, `src/Core/Storage/StorageException.php`
- Test: `tests/Unit/Core/JsonStoreTest.php` (her test geçici dizin kullanır: `sys_get_temp_dir().'/js_'.bin2hex(random_bytes(4))`)

**Arayüzler:**
- Sağlar (`App\Core\Storage\JsonStore`):
  - `__construct(string $dataDir)`
  - `all(string $table): list<array<string,mixed>>`
  - `find(string $table, int $id): ?array<string,mixed>`
  - `insert(string $table, array $row): int` — `_sequences.json`'dan sıradaki `id`'yi verir, satıra yazar, id döner
  - `update(string $table, int $id, array $changes): void` — alanları birleştirir; satır yoksa `StorageException`
  - `transaction(list<string> $tables, callable $fn): mixed` — tabloları **alfabetik sırayla** `LOCK_EX` ile kilitler (`<dataDir>/<table>.lock`), `$fn($this)` çalıştırır, kilitleri bırakır. Kilit içindeyken aynı tablo için tekrar kilit alınmaz (tutulan kilitler bir dizide izlenir).
- Okuma: dosya yoksa veya içerik boş/boşluksa `[]`. `json_decode` hatasında `StorageException("Veri dosyası okunamadı: <table>")`, dosyaya dokunulmaz.
- Yazma: `<table>.json.tmp`'ye `JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES` ile yaz, `rename` ile değiştir. Kilitsiz çağrılan `insert`/`update` kendi tablosunu (ve `_sequences`) kendisi kilitler.

- [x] **Adım 1: Başarısız testler:**
  - `test_insert_assigns_incrementing_ids`: iki `insert('users', [...])` → `1`, `2`; `all('users')` 2 satır, `find('users', 2)['id'] === 2`.
  - `test_update_merges_fields`: insert `['name'=>'A','role'=>'customer']`, `update(1, ['name'=>'B'])` → `find` `['id'=>1,'name'=>'B','role'=>'customer']`.
  - `test_missing_or_empty_file_reads_as_empty_list`: dosya yok → `[]`; `file_put_contents(users.json, "  \n")` → `[]`.
  - `test_corrupt_file_throws_and_is_not_overwritten` *(Denetim odağı 3)*: içerik `'[{"id":1,'` → `all('users')` `StorageException`; ardından `insert` de `StorageException`; dosya içeriği hâlâ `'[{"id":1,'`.
  - `test_transaction_conditional_update_only_first_wins`: `tickets`'a `['status'=>'pending','agent_id'=>null]`; iki ayrı `JsonStore` örneği sırayla `transaction(['tickets'], fn)` içinde "agent_id null ise yaz ve true dön" yapar → ilk `true`, ikinci `false`; son `agent_id` ilk yazanınki.
  - `test_unicode_is_stored_readably`: `insert('categories', ['name'=>'Ağ / Erişim'])` → dosya metni `Ağ / Erişim` içerir.
- [x] **Adım 2:** `php vendor/bin/phpunit --filter JsonStoreTest` → test kırmızı.
- [x] **Adım 3:** `JsonStore`'u yaz.
- [x] **Adım 4:** Testler yeşil, PHPStan temiz.
- [x] **Adım 5:** Commit: `JSON depolama katmanı (kilitli yazma, sıra numarası, bozuk dosya koruması) eklendi`

### Görev 3: HTTP çekirdeği, oturum, CSRF, görünüm ve Kernel iskeleti

**Dosyalar:**
- Oluştur: `src/Core/Http/{Request,Response,Router,HttpException}.php`, `src/Core/Session/{SessionInterface,NativeSession,ArraySession}.php`, `src/Core/Csrf.php`, `src/Core/View.php`, `src/Exception/{ValidationException,AuthorizationException,ConflictException,NotFoundException}.php`, `src/Kernel.php`, `src/routes.php`, `public/index.php`, `public/assets/app.css`, `views/layout.php`, `views/errors/{403,404,500}.php`, `views/home.php`
- Test: `tests/Unit/Core/RouterTest.php`, `tests/Unit/Core/CsrfTest.php`, `tests/Feature/KernelTest.php`, `tests/Feature/FeatureTestCase.php`

**Arayüzler:**
- Sağlar:
  - `Request::__construct(string $method, string $path, array $query = [], array $post = [], array $files = [], array $headers = [], string $body = '')`, `Request::fromGlobals(): self`, `input(string $key, mixed $default = null): mixed` (post→query), `header(string $name): ?string` (büyük/küçük harf duyarsız), `json(): array`.
  - `Response::__construct(string $body = '', int $status = 200, array $headers = [])`, `static redirect(string $url): self` (302), `static json(mixed $data, int $status = 200): self`, `send(): void`, public readonly `body`, `status`, `headers`.
  - `Router::add(string $method, string $pattern, callable $handler): void` — kalıpta `{id}` yalnız rakam eşler; `dispatch(Request $r): Response` — handler `fn(Request $r, array $params): Response`; yol yoksa `HttpException(404)`, yöntem uymazsa `HttpException(405)`.
  - `SessionInterface`: `get(string $k, mixed $d = null): mixed`, `set(string $k, mixed $v): void`, `remove(string $k): void`, `flash(string $k, mixed $v): void`, `pullFlash(string $k, mixed $d = null): mixed`, `regenerate(): void`, `destroy(): void`. `NativeSession` `$_SESSION` kullanır (cookie `httponly`, `samesite=Lax`); `ArraySession` testler için bellekte.
  - `Csrf::__construct(SessionInterface $s)`, `token(): string` (oturumda yoksa `bin2hex(random_bytes(32))`), `isValid(?string $t): bool` (`hash_equals`).
  - `View::__construct(string $viewsDir)`, `render(string $template, array $data = [], ?string $layout = 'layout'): string`; şablonlarda global `e(mixed $v): string` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`) ve `$csrfField()` yardımcısı.
  - `HttpException(int $status, string $message = '')`; diğer istisnalar `ValidationException(array<string,string> $errors)` (`errors()`), `AuthorizationException`, `ConflictException`, `NotFoundException`.
  - `Kernel::__construct(string $rootDir, ?ClockInterface $clock = null, ?SessionInterface $session = null)`, `handle(Request $r): Response`. Kernel: POST/PUT/DELETE isteklerinde CSRF'yi `_csrf` girdisinden veya `X-CSRF-Token` başlığından doğrular (geçersizse 403); `HttpException`/`AuthorizationException`(403)/`NotFoundException`(404)/`StorageException`(500 "Veri okunamadı") hata görünümlerine çevrilir; `ValidationException` controller'larda yakalanır.
  - `FeatureTestCase` (Feature testleri için): geçici `rootDir` kopyası (views, storage/config), `ArraySession`, `FixedClock('2026-11-09 10:00')`; `get(string $path): Response`, `post(string $path, array $data): Response` (CSRF'yi otomatik ekler), `postWithoutCsrf(...)`, `loginAs(string $customerNo, string $password)` (Görev 8'de dolar).
- `public/index.php`: `require vendor/autoload.php`, `date_default_timezone_set('Europe/Istanbul')`, `session_start`, `(new Kernel(dirname(__DIR__)))->handle(Request::fromGlobals())->send()`.

- [x] **Adım 1: Başarısız testler:**
  - `RouterTest::test_matches_numeric_param` (`GET /talepler/{id}` + `/talepler/42` → params `['id'=>'42']`), `test_param_rejects_non_numeric` (`/talepler/abc` → 404), `test_wrong_method_is_405`.
  - `CsrfTest::test_token_is_stable_within_session_and_validates`, `test_rejects_null_and_wrong_token`.
  - `KernelTest::test_unknown_path_returns_404_page` (status 404, gövde "Sayfa bulunamadı"), `test_post_without_csrf_returns_403`, `test_home_renders_layout` (GET `/` → 200, gövde `<title>Destek Talebi Sistemi` içerir).
- [x] **Adım 2:** Çalıştır → test kırmızı.
- [x] **Adım 3:** Sınıfları ve `views/layout.php` (üst menü, flash mesaj alanı, `<meta name="viewport" content="width=device-width, initial-scale=1">`), `app.css` (mobil önce; `max-width` kapsayıcı, tablolar dar ekranda kart görünümüne döner, yatay kaydırma yok) yaz. `routes.php` şimdilik yalnız `GET /`.
- [x] **Adım 4:** Testler yeşil; PHPStan temiz; elle kontrol: `php -S localhost:8000 -t public` → `http://localhost:8000` ana sayfa açılır.
- [x] **Adım 5:** Commit: `Router, istek/yanıt, oturum, CSRF ve görünüm altyapısı eklendi`

---

### Görev 4: Takvim yapılandırması (JSON) ve yedeğe dönme

**Dal:** `git switch -c ozellik/sla-hesaplama`

**Dosyalar:**
- Oluştur: `src/Model/CalendarConfig.php`, `src/Repository/CalendarRepositoryInterface.php`, `src/Repository/Json/JsonCalendarRepository.php`, `storage/config/calendar.json`
- Test: `tests/Unit/Repository/JsonCalendarRepositoryTest.php`, fixtures `tests/fixtures/calendar.json` (Pzt–Cum 09:00–18:00, mola 12:00–13:00, tatil `2026-10-29`), `tests/fixtures/calendar-bozuk.json` (`{"work_days": "x"`)

**Arayüzler:**
- Sağlar:
  - `CalendarConfig::__construct(array $workDays, array $holidays, string $timezone)` — `$workDays`: `array<int,list<array{0:int,1:int}>>`, ISO gün → mola çıkarılmış çalışma aralıkları, **gece yarısından itibaren dakika** (09:00–12:00 → `[540,720]`, 13:00–18:00 → `[780,1080]`); `$holidays`: `array<string,true>` (`'Y-m-d'` anahtarlı küme).
  - `CalendarConfig::fromArray(array $data): self` — tasarım §8.1 şeması; `HH:MM` biçimi, `start < end`, molalar aralık içinde değilse `\InvalidArgumentException`.
  - `CalendarConfig::default(): self` — Pzt–Cum 09:00–18:00, mola 12:00–13:00, tatil yok, `Europe/Istanbul`.
  - `intervalsFor(\DateTimeImmutable $day): list<array{0:int,1:int}>` — tatil veya çalışılmayan günse `[]`.
  - `CalendarRepositoryInterface`: `getConfig(): CalendarConfig`, `getBusinessHours(): array`, `getHolidays(): list<string>` (sınıf diyagramı).
  - `JsonCalendarRepository::__construct(string $path, ?callable $logger = null)` — dosya yok/bozuk/şemaya aykırı → `CalendarConfig::default()` ve `$logger("Takvim dosyası okunamadı, varsayılan mesai kullanılıyor: <neden>")` (varsayılan logger `error_log`). Sonuç örnekte önbelleğe alınır.
- `storage/config/calendar.json`: Pzt–Cum 09:00–18:00, mola 12:00–13:00; tatiller 2026–2027: `01-01`, `04-23`, `05-01`, `05-19`, `07-15`, `08-30`, `10-29` (her iki yıl) + dini bayramlar 2026 Ramazan `03-20..03-22`, Kurban `05-27..05-30`; 2027 Ramazan `03-09..03-11`, Kurban `05-16..05-19`. Dini bayram tarihleri resmi takvimden kontrol edildi.

- [x] **Adım 1: Başarısız testler:** `test_reads_intervals_with_break_removed` (Pzt 2026-11-09 → `[[540,720],[780,1080]]`), `test_weekend_and_holiday_have_no_intervals` (2026-11-14 Cmt ve 2026-10-29 → `[]`), `test_missing_file_falls_back_to_default_and_logs`, `test_corrupt_file_falls_back_to_default_and_logs` (logger bir kez çağrılır, mesaj "Takvim dosyası okunamadı" içerir), `test_break_outside_day_is_rejected` (`fromArray` → `InvalidArgumentException`).
- [x] **Adım 2:** Çalıştır → test kırmızı. **Adım 3:** Kodu yaz. **Adım 4:** Test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `JSON çalışma takvimi ve bozuk dosyada varsayılan mesaiye dönüş (NFR-07) eklendi`

### Görev 5: SLA hesaplama algoritması (çekirdek, FR-05)

**Dosyalar:**
- Oluştur: `src/Service/SlaCalculatorService.php`
- Test: `tests/Unit/Service/SlaCalculatorServiceTest.php`

**Arayüzler:**
- Kullanır: `CalendarRepositoryInterface::getConfig()`, `CalendarConfig::intervalsFor()`.
- Sağlar: `SlaCalculatorService::__construct(CalendarRepositoryInterface $calendar)`; `calculateWithJson(\DateTimeImmutable $start, int $durationMinutes): \DateTimeImmutable`; `businessMinutesBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int` (`$to <= $from` ise 0).

Algoritma (`calculateWithJson`):
```
kalan = durationMinutes; gün = start'ın günü; dakika = start'ın gün içi dakikası
3 yıl (1096 gün) boyunca:
  her [a,b] aralığı için (intervalsFor(gün)):
    if dakika >= b: devam
    başla = max(dakika, a)
    if başla + kalan <= b: return gün + (başla + kalan) dakika
    kalan -= b - başla
  gün = sonraki gün; dakika = 0
throw RuntimeException("Takvimde iş günü bulunamadı")
```
`durationMinutes = 0` → başlangıç mesai içindeyse kendisi, değilse sonraki mesai anı. `businessMinutesBetween` aynı gezinmeyle iki an arasındaki aralık kesişimlerini toplar.

- [x] **Adım 1: Başarısız test** — `tests/fixtures/calendar.json` ile, `#[DataProvider('cases')]` `test_deadline_matches_case_table(string $start, int $hours, string $expected)`; tarih biçimi `'Y-m-d H:i'`, saat dilimi İstanbul:

| ad | start | saat | beklenen |
|---|---|---|---|
| mola_oncesi | 2026-11-09 10:00 | 2 | 2026-11-09 12:00 |
| ogle_arasi_atlanir | 2026-11-09 11:00 | 2 | 2026-11-09 14:00 |
| molada_acilan | 2026-11-09 12:30 | 1 | 2026-11-09 14:00 |
| gun_sonu_devri | 2026-11-09 17:00 | 2 | 2026-11-10 10:00 |
| cuma_1730_hocanin_ornegi | 2026-11-13 17:30 | 4 | 2026-11-16 13:30 |
| hafta_sonu_acilan | 2026-11-14 10:00 | 1 | 2026-11-16 10:00 |
| mesai_sonrasi_acilan | 2026-11-09 20:00 | 1 | 2026-11-10 10:00 |
| tatil_oncesi_gun | 2026-10-28 17:00 | 2 | 2026-10-30 10:00 |
| tatilde_acilan | 2026-10-29 11:00 | 1 | 2026-10-30 10:00 |
| tam_gun_mesai_sonunda_biter | 2026-11-09 09:00 | 8 | 2026-11-09 18:00 |
| cok_gunlu | 2026-11-13 16:00 | 24 | 2026-11-18 16:00 |
| mesai_oncesi_acilan *(Denetim odağı 2)* | 2026-11-09 07:00 | 1 | 2026-11-09 10:00 |

  Ek testler: `test_corrupt_calendar_uses_default_hours` (`calendar-bozuk.json`, 2026-11-09 11:00 + 2 sa → 14:00; varsayılan da öğle arasını atlar), `test_business_minutes_between_skips_break_and_weekend` (2026-11-13 17:30 → 2026-11-16 13:30 = 240), `test_business_minutes_reverse_order_is_zero`, `test_calendar_without_workdays_throws` (`work_days: {}` geçerli şema → `RuntimeException`), `test_five_years_of_holidays_is_fast` (2026–2030 arası her Cuma tatil eklenmiş config, 1000 hesap < 500 ms toplam — NFR-01).
- [x] **Adım 2:** Çalıştır → test kırmızı. **Adım 3:** Kodu yaz. **Adım 4:** Test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `Mesai, öğle arası ve tatile duyarlı SLA hesaplama algoritması ve vaka testleri eklendi`

### Görev 6: SLA durum değerlendirmesi (%80 / %100, FR-07 mantığı)

**Dosyalar:**
- Oluştur: `src/Model/SlaStatus.php`, `src/Service/SlaStatusService.php`
- Test: `tests/Unit/Service/SlaStatusServiceTest.php`

**Arayüzler:**
- Sağlar:
  - `SlaStatus` (readonly): `string $level` (`'normal'|'warning'|'breached'|'met'|'missed'|'none'`), `int $percent`, `int $remainingMinutes`, `string $label`, `cssClass(): string` (`sla-normal`, `sla-warning`, `sla-breached`, `sla-met`, `sla-missed`, `sla-none`).
  - `SlaStatusService::__construct(SlaCalculatorService $calc, ClockInterface $clock)`; `evaluate(\DateTimeImmutable $createdAt, \DateTimeImmutable $deadline, ?\DateTimeImmutable $resolvedAt, bool $hidden): SlaStatus`; `formatMinutes(int $m): string` → `"45 dk"`, `"3 sa 20 dk"`, `"2 sa"`.
- Kurallar: `hidden` (kapalı/iptal) → `none`, etiket `"—"`. `resolvedAt` varsa → `met` ("Süre içinde çözüldü") veya `missed` ("Süre aşılarak çözüldü"). Aksi halde `total = businessMinutesBetween(createdAt, deadline)`, `elapsed = businessMinutesBetween(createdAt, now)`, `percent = total > 0 ? intdiv(elapsed*100, total) : (now >= deadline ? 100 : 0)`; `now >= deadline` → `breached` ("Süre aşıldı", percent ≥ 100); `percent >= 80` → `warning`; değilse `normal`. Etiket `"<kalan> kaldı"`.

- [x] **Adım 1: Başarısız testler** (created 2026-11-09 09:00, deadline 2026-11-09 19:00 değil — fixture takvimle 10 iş saati = 2026-11-10 11:00):
  - `test_normal_below_80_percent`: now 2026-11-09 14:00 (4 sa geçti / 10) → `normal`, `percent 40`, label `"6 sa kaldı"`.
  - `test_warning_at_exactly_80_percent`: now 2026-11-10 09:00 (8/10) → `warning`, `80`, `"2 sa kaldı"`.
  - `test_breached_after_deadline`: now 2026-11-10 12:00 → `breached`, label `"Süre aşıldı"`.
  - `test_resolved_in_time_is_met` / `test_resolved_late_is_missed`.
  - `test_hidden_status_shows_none`.
  - `test_remaining_excludes_weekend`: created 2026-11-13 09:00, deadline 2026-11-16 13:30, now 2026-11-13 18:00 → remaining `"3 sa 30 dk"`.
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `SLA yüzde ve uyarı seviyesi hesaplaması (%80 sarı, %100 kırmızı) eklendi`

---

### Görev 7: Kullanıcı modeli, depo ve kimlik servisi

**Dal:** `git switch -c ozellik/kimlik-dogrulama`

**Dosyalar:**
- Oluştur: `src/Model/Enum/Role.php`, `src/Model/User.php`, `src/Repository/UserRepositoryInterface.php`, `src/Repository/Json/JsonUserRepository.php`, `src/Service/AuthService.php`
- Test: `tests/Unit/Service/AuthServiceTest.php`, `tests/Unit/Repository/JsonUserRepositoryTest.php`

**Arayüzler:**
- Sağlar:
  - `enum Role: string { Customer='customer'; Agent='agent'; Admin='admin'; }` + `prefix(): string` (`M`,`P`,`Y`), `label(): string` (Müşteri, Personel, Yönetici).
  - `User` (public props): `?int $id, string $customerNo, string $passwordHash, Role $role, string $name, string $surname, string $secretQuestion, string $secretAnswerHash, int $totalPoints = 0`; `fullName(): string`; `fromArray(array): self`, `toArray(): array` (ER alan adları).
  - `UserRepositoryInterface`: `findById(int): ?User`, `findByCustomerNo(string): ?User`, `findByRole(Role): list<User>`, `save(User): User` (id yoksa ekler, varsa günceller; id'li User döner).
  - `AuthService::__construct(UserRepositoryInterface $users)`:
    - `register(string $name, string $surname, string $password, string $secretQuestion, string $secretAnswer): User` — doğrulama: ad/soyad 2–100 karakter, şifre ≥ 8, soru `AuthService::SECRET_QUESTIONS` listesinden, cevap ≥ 2 karakter; hatada `ValidationException` (alan → Türkçe mesaj). Müşteri no: `Role::Customer->prefix()` + `random_int(100000, 999999)`, çakışırsa yeniden dene.
    - `login(string $customerNo, string $password): ?User` (numara büyük harfe çevrilir, boşluk kırpılır).
    - `secretQuestionFor(string $customerNo): ?string`
    - `resetPassword(string $customerNo, string $secretAnswer, string $newPassword): bool` — cevap `mb_strtolower(trim())` ile karşılaştırılır; yeni şifre ≥ 8 değilse `ValidationException`.
    - `const SECRET_QUESTIONS = ['İlk evcil hayvanınızın adı nedir?', 'Doğduğunuz şehir neresidir?', 'İlkokul öğretmeninizin soyadı nedir?', 'En sevdiğiniz yemek nedir?']`.
    - `createStaff(Role $role, string $name, string $surname, string $password): User` (seed için; soru ilk soru, cevap rastgele).

- [x] **Adım 1: Başarısız testler:** `test_register_creates_customer_with_M_number` (`/^M\d{6}$/`, şifre düz metin saklanmaz: `password_verify`), `test_register_validation_messages` (kısa şifre → `errors()['password'] === 'Şifre en az 8 karakter olmalı.'`), `test_login_success_and_wrong_password`, `test_login_accepts_lowercase_and_spaces` (`" m123456 "`), `test_reset_password_with_answer_case_insensitive` (cevap "Pamuk" kayıtlı, "  pamuk " ile sıfırlanır, eski şifre artık geçmez), `test_reset_password_wrong_answer_returns_false`; repo: `test_save_then_find_by_customer_no`, `test_find_by_role`.
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `Kullanıcı modeli, JSON kullanıcı deposu ve gizli sorulu kimlik servisi eklendi`

### Görev 8: Giriş/kayıt ekranları, yetki koruması, örnek kullanıcılar

**Dosyalar:**
- Oluştur: `src/Controller/AuthController.php`, `src/Controller/HomeController.php`, `src/Exception/RedirectException.php`, `views/auth/{login,register,register_done,forgot,reset}.php`, `src/Seed/DemoSeeder.php`, `bin/seed.php`
- Değiştir: `src/Kernel.php` (oturumdaki kullanıcı, `requireRole`), `src/routes.php`, `views/layout.php` (rol menüsü, çıkış formu), `tests/Feature/FeatureTestCase.php` (`loginAs`)
- Test: `tests/Feature/AuthFlowTest.php`

**Arayüzler:**
- Sağlar:
  - `Kernel::currentUser(): ?User`, `Kernel::requireRole(Role ...$roles): User` — giriş yoksa `RedirectException('/giris')` fırlatır (Kernel yakalar, `Response::redirect` döner); rol uymazsa `AuthorizationException`.
  - Kernel servis erişicileri (seeder ve testler için; sonraki görevler kendi servislerinin erişicisini ekler): `store(): JsonStore`, `clock(): ClockInterface`, `userRepository(): UserRepositoryInterface`, `authService(): AuthService`. Oturum anahtarları: `user_id`, `last_activity`. Her istekte `last_activity` 1800 sn'den eskiyse oturum kapatılır, flash "Oturumunuz 30 dakika işlem yapılmadığı için sonlandırıldı." (NFR-10).
  - Rotalar: `GET/POST /giris`, `GET/POST /kayit`, `GET /kayit/tamam`, `GET/POST /sifre-sifirla` (1. adım müşteri no → soru; 2. adım `POST /sifre-sifirla/yeni`: cevap + yeni şifre), `POST /cikis`, `GET /` (role göre: müşteri `/talepler`, personel `/kanban`, yönetici `/yonetici`; girişsiz `/giris`).
  - Başarılı girişte `SessionInterface::regenerate()`. Hatalı formda girilen değerler (şifreler hariç) korunur.
  - `DemoSeeder::__construct(Kernel $kernel, FixedClock $clock)` (Kernel aynı `$clock` ile kurulur; seeder saati geçmişe alıp ileri sararak veri üretebilir); `run(): list<array{rol: string, numara: string, ad: string}>` (oluşturulan giriş bilgileri); bu görevde `seedUsers()`: Yönetici `Y000001`, personel `P000001`–`P000003` (Zeynep Kaya, Murat Demir, Elif Şahin), müşteriler `M100001`–`M100005`; hepsinin şifresi `Destek2026!`. Sabit numaralar için seeder `userRepository()->save` ile doğrudan yazar.
  - `bin/seed.php`: `storage/data/*.json` siler, `$clock = new FixedClock(now)`, `new DemoSeeder(new Kernel($root, $clock), $clock)->run()`, giriş bilgilerini tablo olarak yazdırır.

- [x] **Adım 1: Başarısız testler** (`AuthFlowTest`): `test_register_shows_customer_number_then_login_works`, `test_wrong_password_shows_error_and_keeps_number` (gövde `value="M100001"` içerir, "Müşteri numarası veya şifre hatalı."), `test_session_id_regenerated_on_login` (`ArraySession::$regenerated === true`), `test_customer_cannot_open_admin_page` (`/yonetici` → 403), `test_guest_redirected_to_login` (`/talepler` → 302 `/giris`), `test_idle_30_minutes_logs_out` (giriş, `FixedClock::advance('+31 minutes')`, `/talepler` → 302), `test_forgot_password_flow`.
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan; elle: `php bin/seed.php`, `php -S localhost:8000 -t public`, `M100001 / Destek2026!` ile giriş.
- [x] **Adım 5:** Commit: `Kayıt, giriş, gizli soruyla şifre sıfırlama ekranları ve rol koruması eklendi`

---

### Görev 9: Talep modeli, enum'lar ve State deseni (FR-06)

**Dal:** `git switch -c ozellik/durum-gecisleri`

**Dosyalar:**
- Oluştur: `src/Model/Enum/{Priority,TicketStatus}.php`, `src/Model/Ticket.php`, `src/Model/State/*.php` (dosya haritasındaki 9 dosya)
- Değiştir: `src/Service/SlaStatusService.php` (`forTicket`)
- Test: `tests/Unit/Model/TicketStateTest.php`, `tests/Unit/Service/SlaStatusServiceTest.php` (ek)

**Arayüzler:**
- Sağlar:
  - `enum Priority: int { Low=1; Normal=2; High=3; Urgent=4; }` + `label()` (Düşük, Normal, Yüksek, Acil), `defaultSlaHours()` (24,16,8,4).
  - `enum TicketStatus: string { Pending='pending'; Taken='taken'; InReview='in_review'; Resolved='resolved'; Closed='closed'; Cancelled='cancelled'; }` + `label()` (Talep Bekleniyor, Talep Alındı, Talep İnceleniyor, Talep Çözüldü, Kapalı, İptal Edildi), `hocaLabel()` (Açık, İşlemde, İşlemde, Çözüldü, Kapalı, Kapalı), `isFinal(): bool` (closed, cancelled), `state(): TicketState`.
  - `Ticket` (public props): `?int $id, int $creatorId, ?int $agentId, int $categoryId, string $title, TicketStatus $status, Priority $priority, Priority $customerPriority, int $attemptCount, \DateTimeImmutable $createdAt, \DateTimeImmutable $deadline, ?\DateTimeImmutable $resolvedAt, int $earnedPoints, int $agentQuestionCount`; `fromArray`, `toArray` (ER adları, ISO 8601 tarihler); `state(): TicketState`; `setStatus(TicketStatus $s): void`; `incrementAttempt(): void`.
  - `interface TicketState { cancel(Ticket): TicketStatus; take(Ticket): TicketStatus; startReview(Ticket): TicketStatus; resolve(Ticket): TicketStatus; reject(Ticket): TicketStatus; close(Ticket): TicketStatus; }` — her metot hedef durumu döner, izinsizse `InvalidTransitionException("'<mevcut label>' durumundaki talep <eylem adı> yapılamaz.")`. `AbstractTicketState` hepsini izinsiz yapar; alt sınıflar yalnız izinli olanları ezer (tasarım §10 listesi).
  - `SlaStatusService::forTicket(Ticket $t): SlaStatus` → `evaluate($t->createdAt, $t->deadline, in_array($t->status, [TicketStatus::Resolved, TicketStatus::Closed], true) ? $t->resolvedAt : null, $t->status->isFinal() && $t->resolvedAt === null)`. (Kapalı ama çözülmüş talep `met/missed` gösterir; iptal `none`.)

- [x] **Adım 1: Başarısız testler:** `TicketStateTest` — `#[DataProvider]` ile **izinli** 7 geçiş (pending→take→taken, pending→cancel→cancelled, taken→startReview→in_review, in_review→resolve→resolved, resolved→close→closed, resolved→reject→in_review) ve **izinsiz** örnekler: closed→startReview, cancelled→take, taken→cancel, in_review→close, pending→resolve, resolved→cancel (hepsi `InvalidTransitionException`); `test_message_names_current_status` ("'Kapalı' durumundaki talep…"); `test_hoca_labels` (in_review → "İşlemde"); `test_ticket_round_trips_array` (`fromArray(toArray())` eşit); `SlaStatusServiceTest::test_cancelled_ticket_is_none`, `test_closed_resolved_ticket_shows_met`.
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `Talep modeli, durum ve öncelik enum'ları ile State deseniyle durum geçişleri eklendi`

---

### Görev 10: Talep, katalog, yanıt ve log depoları

**Dal:** `git switch -c ozellik/talep-acma`

**Dosyalar:**
- Oluştur: `src/Model/{Category,SlaPolicy,Reply,TicketLog}.php`, `src/Repository/{TicketRepositoryInterface,CategoryRepositoryInterface,SlaPolicyRepositoryInterface,ReplyRepositoryInterface,TicketLogRepositoryInterface}.php`, `src/Repository/Json/Json{Ticket,Category,SlaPolicy,Reply,TicketLog}Repository.php`
- Test: `tests/Unit/Repository/JsonTicketRepositoryTest.php`, `tests/Unit/Repository/JsonCatalogRepositoriesTest.php`

**Arayüzler:**
- Sağlar:
  - Modeller (public props, `fromArray/toArray`): `Category(?int id, string name)`, `SlaPolicy(?int id, int categoryId, Priority priority, int durationHours)`, `Reply(?int id, int ticketId, int userId, string message, \DateTimeImmutable createdAt, bool isQuestion = false)`, `TicketLog(?int id, int ticketId, int userId, ?TicketStatus oldStatus, TicketStatus newStatus, \DateTimeImmutable changedAt)`.
  - `TicketRepositoryInterface`: `save(Ticket): Ticket`, `findById(int): ?Ticket`, `getPoolTickets(): list<Ticket>` (pending ve agent yok, eskiden yeniye), `findByCreator(int): list<Ticket>`, `findByAgent(int): list<Ticket>`, `findAll(): list<Ticket>`, `countActiveByAgent(): array<int,int>` (agentId → taken+in_review sayısı), `assignIfUnassigned(int $ticketId, int $agentId): bool` — **tek kilit içinde** talep `pending` ve `agent_id` null ise `agent_id` yazar, `status`'u `taken` yapar, `true`; değilse `false` (Aşama 3 karşılığı koşullu UPDATE / FOR UPDATE).
  - `CategoryRepositoryInterface`: `findAll(): list<Category>`, `findById(int): ?Category`. `SlaPolicyRepositoryInterface`: `findFor(int $categoryId, Priority $p): ?SlaPolicy`, `findAll()`. `ReplyRepositoryInterface`: `save(Reply): Reply`, `findByTicket(int): list<Reply>` (eskiden yeniye). `TicketLogRepositoryInterface`: `save(TicketLog): TicketLog`, `findByTicket(int): list<TicketLog>`.
  - Tüm `Json*Repository` sınıfları yapıcıda `JsonStore` alır; tablo adları ER'deki gibi (`tickets`, `categories`, `sla_policies`, `replies`, `ticket_logs`).

- [x] **Adım 1: Başarısız testler:** `test_save_and_find_round_trip` (tarihler saat dilimiyle korunur), `test_pool_contains_only_unassigned_pending`, `test_count_active_by_agent`, `test_assign_if_unassigned_second_call_fails` (ilk `true`, ikinci başka agent ile `false`, kayıtta ilk agent), `test_sla_policy_lookup_by_category_and_priority`, `test_replies_sorted_oldest_first`.
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `Talep, kategori, SLA kuralı, yanıt ve durum logu JSON depoları eklendi`

### Görev 11: Anahtar kelimeyle akıllı öncelik (FR-03)

**Dosyalar:**
- Oluştur: `src/Service/PriorityDetector.php`, `storage/config/priority_keywords.json`
- Test: `tests/Unit/Service/PriorityDetectorTest.php`

**Arayüzler:**
- Sağlar: `PriorityDetector::__construct(array<int, list<string>> $keywords)` (öncelik değeri → kelimeler), `static fromFile(string $path): self` (dosya yok/bozuksa boş liste), `detect(string $text): ?Priority` (eşleşen en yüksek öncelik), `effective(Priority $chosen, string $title, string $description): Priority` (seçilen ile algılananın büyüğü; asla düşürmez).
- Normalleştirme: `str_replace(['İ','I'], ['i','ı'], $s)` sonra `mb_strtolower($s, 'UTF-8')`; alt dize araması.
- `priority_keywords.json`: `{"4": ["acil","çöktü","erişilemiyor","çalışmıyor","kritik","veri kaybı"], "3": ["hata","yavaş","bağlanamıyorum","açılmıyor"]}`.

- [x] **Adım 1: Başarısız testler:** `test_detects_urgent_keyword`, `test_turkish_uppercase_is_detected` *(Denetim odağı 1)*: `detect('ACİL: SUNUCU ÇÖKTÜ') === Priority::Urgent` ve `detect('BAĞLANAMIYORUM') === Priority::High`, `test_effective_never_lowers` (Acil seçilmiş + "yavaş" → Acil), `test_effective_raises` (Düşük + "çalışmıyor" → Acil), `test_no_keyword_returns_null`, `test_missing_file_means_no_detection`.
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `Anahtar kelimeyle akıllı öncelik belirleme eklendi`

### Görev 12: En az iş yüklü personele atama (FR-04)

**Dosyalar:**
- Oluştur: `src/Service/AssignmentService.php`
- Test: `tests/Unit/Service/AssignmentServiceTest.php`

**Arayüzler:**
- Kullanır: `UserRepositoryInterface::findByRole(Role::Agent)`, `TicketRepositoryInterface::countActiveByAgent()`.
- Sağlar: `AssignmentService::__construct(UserRepositoryInterface $users, TicketRepositoryInterface $tickets)`, `pickAgent(): ?User` — en az aktif talep; eşitlikte en küçük `id`; personel yoksa `null`.

- [x] **Adım 1: Başarısız testler:** `test_picks_least_loaded` (P1:3, P2:1, P3:2 → P2), `test_tie_picks_lowest_id` (P1:1, P2:1 → P1), `test_agent_without_tickets_counts_zero`, `test_no_agents_returns_null`, `test_resolved_and_closed_do_not_count`.
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `İş yüküne göre otomatik personel atama servisi eklendi`

### Görev 13: TicketService ve Observer altyapısı

**Dosyalar:**
- Oluştur: `src/Service/Observer/TicketObserver.php`, `src/Service/Observer/TicketLogObserver.php`, `src/Service/TicketService.php`
- Test: `tests/Unit/Service/TicketServiceTest.php`

**Arayüzler:**
- Kullanır: Görev 5, 9, 10, 11, 12 arayüzleri.
- Sağlar:
  - `interface TicketObserver { public function update(Ticket $ticket, string $eventType, array $context = []): void; }`. Olaylar: `TicketService::EVENT_CREATED='ticket_created'`, `EVENT_ASSIGNED='ticket_assigned'`, `EVENT_STATUS_CHANGED='status_changed'` (context: `old`, `new`: `TicketStatus`, `actor_id`: int), `EVENT_CUSTOMER_REPLIED='customer_replied'`, `EVENT_AGENT_REPLIED='agent_replied'`.
  - `TicketLogObserver::__construct(TicketLogRepositoryInterface $logs, ClockInterface $clock)` — `ticket_created` için `old=null,new=pending`; `status_changed` için `old→new` log yazar.
  - `TicketService::__construct(TicketRepositoryInterface, ReplyRepositoryInterface, CategoryRepositoryInterface, SlaPolicyRepositoryInterface, SlaCalculatorService, PriorityDetector, AssignmentService, ClockInterface)`; `attachObserver(TicketObserver)`; `notifyObservers(Ticket, string, array = [])`.
  - `createTicket(User $creator, array $data): Ticket` — `$data`: `category_id`, `priority`, `title`, `description`. Doğrulama (Türkçe mesaj, `ValidationException`): kategori var, öncelik 1–4, başlık 3–150, açıklama 10–5000 karakter. Etkin öncelik `PriorityDetector::effective`; süre `SlaPolicy::durationHours` yoksa `Priority::defaultSlaHours()`; `deadline = calculateWithJson(now, saat*60)`. Kaydet (`pending`), açıklamayı ilk `Reply` olarak kaydet, `EVENT_CREATED`; `pickAgent()` varsa `assignIfUnassigned` → `true` ise talebi yeniden oku, `EVENT_ASSIGNED` + `EVENT_STATUS_CHANGED(pending→taken, actor = sistem: 0)`.
  - `take(int $ticketId, User $agent): Ticket` — `assignIfUnassigned` `false` ise `ConflictException("Bu talep az önce başka bir personel tarafından alındı.")`.
  - `perform(int $ticketId, User $actor, string $action): Ticket` — `$action ∈ {'cancel','start_review','resolve','approve','reject'}`; sahiplik: cancel/approve/reject yalnız `creatorId`, start_review/resolve yalnız `agentId` (yönetici her ikisini yapabilir) → aksi `AuthorizationException`. State metodunu çağırır (`approve`→`close`); `resolve` → `resolvedAt = now`; `reject` → `incrementAttempt()`, `resolvedAt = null`. Kaydet, `EVENT_STATUS_CHANGED`.
  - `moveOnKanban(int $ticketId, User $agent, TicketStatus $target): Ticket` — `InReview`→`start_review`, `Resolved`→`resolve`, diğerleri `InvalidTransitionException("Kanban'da bu sütuna taşınamaz.")`.
  - `addReply(int $ticketId, User $author, string $message): Reply` — 1–5000 karakter; yazabilir: talep sahibi, atanan personel, yönetici; final durumda `ValidationException("Kapalı veya iptal edilmiş talebe yanıt yazılamaz.")`; olay müşteri/personel'e göre.
  - `findVisible(int $ticketId, User $viewer): Ticket` — yoksa `NotFoundException`; müşteri yalnız kendi, personel kendisine atanan veya havuzdaki, yönetici hepsi; aksi `AuthorizationException`.
  - `searchForCustomer(User $c, ?TicketStatus $status, string $query, int $page, int $perPage = 10): array{items: list<Ticket>, total: int, page: int, pages: int}` — başlıkta (Türkçe duyarsız, Görev 11'deki normalleştirme) veya `#id`/`id` eşleşmesi; yeniden eskiye.

- [x] **Adım 1: Başarısız testler** (FixedClock `2026-11-13 17:30`, fixture takvim, bellek içi JsonStore):
  - `test_create_computes_deadline_with_policy` (kategori 1 + Acil kuralı 4 sa → deadline `2026-11-16 13:30`).
  - `test_create_uses_keyword_priority` (Normal + "sunucu çöktü" → `priority` Acil, `customerPriority` Normal).
  - `test_create_auto_assigns_least_loaded_and_logs` (status `taken`, agentId beklenen; log listesi `[null→pending, pending→taken]`).
  - `test_create_without_agents_stays_in_pool`.
  - `test_create_validation_errors` (boş başlık → `errors()['title']`).
  - `test_take_twice_second_conflicts`.
  - `test_customer_cannot_resolve` / `test_other_agent_cannot_start_review` (`AuthorizationException`).
  - `test_reject_increments_attempt_and_returns_to_review`.
  - `test_reply_on_closed_ticket_rejected`.
  - `test_search_filters_by_status_and_text_and_paginates` (12 talep, sayfa 2'de 2 kayıt).
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `Talep servisi, Observer altyapısı ve durum logu gözlemcisi eklendi`

### Görev 14: Talep açma ve Taleplerim ekranları, katalog seed'i

**Dosyalar:**
- Oluştur: `src/Controller/TicketController.php`, `views/tickets/{index,create}.php`, `views/partials/{flash,errors,pagination,status_badge}.php`
- Değiştir: `src/Kernel.php` (servisleri bağla, `TicketLogObserver` ekle; erişiciler `ticketService(): TicketService`, `ticketRepository(): TicketRepositoryInterface`), `src/routes.php`, `src/Seed/DemoSeeder.php` (`seedCatalog()`: 4 kategori Donanım, Yazılım, Ağ / Erişim, Hesap; her kategori × öncelik için SLA kuralı = `Priority::defaultSlaHours()`, yalnız Ağ / Erişim Acil = 2 sa), `bin/seed.php`
- Test: `tests/Feature/TicketCreateTest.php`

**Arayüzler:**
- Sağlar: rotalar `GET /talepler` (müşteri; `?durum=<TicketStatus>&q=&sayfa=`), `GET /talepler/yeni`, `POST /talepler` (başarıda PRG → `/talepler/{id}` flash "Talebiniz alındı. Talep no: #<id>"). Formda hata varsa 422 ile form tekrar, değerler korunur. Listede durum rozeti `label()` gösterir.

- [x] **Adım 1: Başarısız testler:** `test_customer_creates_ticket_and_is_redirected`, `test_invalid_form_keeps_values_422`, `test_list_shows_only_own_tickets`, `test_list_filter_and_search`, `test_agent_cannot_open_create_form` (403).
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan; elle: seed, müşteri girişi, talep aç.
- [x] **Adım 5:** Commit: `Talep açma formu ve arama/filtreli Taleplerim listesi eklendi`

---

### Görev 15: Ek dosya yükleme ve indirme (FR-02)

**Dal:** `git switch -c ozellik/dosya-yukleme`

**Dosyalar:**
- Oluştur: `src/Model/Attachment.php`, `src/Repository/AttachmentRepositoryInterface.php`, `src/Repository/Json/JsonAttachmentRepository.php`, `src/Service/AttachmentService.php`, `src/Controller/AttachmentController.php`, `storage/config/settings.json`, `storage/uploads/.gitkeep`, `tests/fixtures/files/{ornek.jpg,ornek.png,ornek.pdf,sahte.jpg}` (`sahte.jpg` içeriği `<?php echo 1;`)
- Değiştir: `TicketController` (çoklu `ekler[]` alanı), `views/tickets/create.php` (`enctype`, `accept=".jpg,.jpeg,.png,.pdf,.mp4,.webm"`)
- Test: `tests/Unit/Service/AttachmentServiceTest.php`

**Arayüzler:**
- Sağlar:
  - `Attachment(?int id, int ticketId, int userId, string filePath, string fileType, \DateTimeImmutable uploadedAt)` (`filePath` = rastgele ad, `fileType` = MIME).
  - `AttachmentRepositoryInterface`: `save`, `findById(int): ?Attachment`, `findByTicket(int): list<Attachment>`.
  - `AttachmentService::__construct(AttachmentRepositoryInterface $repo, string $uploadDir, array $settings, ClockInterface $clock)`; `const ALLOWED = ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf','video/mp4'=>'mp4','video/webm'=>'webm']`; `store(int $ticketId, User $u, array $file): Attachment` (`$file` = `$_FILES` öğesi); `absolutePath(Attachment): string`; `protected moveFile(string $from, string $to): bool` (`move_uploaded_file`; testte `rename` ile ezilir).
  - `settings.json`: `{"max_upload_bytes": 5242880, "max_video_bytes": 52428800, "max_files_per_ticket": 3}`.
  - Akış: `UPLOAD_ERR_OK` değilse "Dosya yüklenemedi."; MIME `finfo(FILEINFO_MIME_TYPE)->file(tmp)` → izinli değilse "Yalnız JPG, PNG, PDF, MP4 ve WebM dosyaları yüklenebilir."; boyut aşımı "Dosya en fazla 5 MB olabilir." / "Video en fazla 50 MB olabilir."; ad `bin2hex(random_bytes(16)).'.'.ext`; JPG/PNG genişlik > 1920 veya yükseklik > 1080 ise GD ile oranı koruyarak küçült (NFR-08).
  - `TicketService::createTicket` sonrası controller her dosya için `store` çağırır; dosya sayısı > `max_files_per_ticket` ise form hatası. Doğrulama dosyalardan önce yapılır, böylece geçersiz dosya talebi yarım bırakmaz (önce tüm dosyalar `validate(array $file): string` (ext) ile kontrol edilir, sonra talep, sonra `store`).
  - `GET /ekler/{id}` — `TicketService::findVisible` ile yetki; `Content-Type` MIME, `Content-Disposition: inline` (görsel/PDF) veya `attachment`, `X-Content-Type-Options: nosniff`.

- [x] **Adım 1: Başarısız testler:** `test_accepts_jpg_png_pdf`, `test_rejects_php_disguised_as_jpg` *(Denetim odağı 4)*, `test_rejects_oversized_image` (settings'te 10 bayt sınır), `test_stored_name_is_random_and_has_correct_extension` (`/^[0-9a-f]{32}\.png$/`), `test_large_png_is_downscaled_to_1920x1080` (GD ile 3000×2000 üret → sonuç ≤ 1920×1080, oran korunur), `test_upload_error_code_message`.
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `Ek dosya yükleme (MIME/boyut kontrolü, rastgele ad, küçültme) ve yetkili indirme eklendi`

---

### Görev 16: Talep detayı: mesajlar, durum geçmişi, eylemler (FR-19)

**Dal:** `git switch -c ozellik/talep-detayi`

**Dosyalar:**
- Oluştur: `views/tickets/show.php`, `views/partials/sla_badge.php`, `views/partials/timeline.php`
- Değiştir: `TicketController` (`show`, `reply`, `action`), `src/routes.php`
- Test: `tests/Feature/TicketDetailTest.php`

**Arayüzler:**
- Sağlar: `GET /talepler/{id}` (müşteri, personel, yönetici; `findVisible`), `POST /talepler/{id}/yanit` (`mesaj`), `POST /talepler/{id}/islem` (`islem` ∈ `cancel|start_review|resolve|approve|reject`; `InvalidTransitionException` mesajı flash hata olarak gösterilir). Sayfa: başlık bilgileri (müşteri adı, kategori, açılış, öncelik + "Sistem önceliği yükseltti" notu, SLA rozeti), mesajlar (eskiden yeniye), ekler (indirme bağlantıları), durum geçmişi ("09.11.2026 10:05 · Zeynep Kaya · Talep Bekleniyor → Talep Alındı"; sistem için "Sistem"), role ve duruma göre yalnız izinli eylem düğmeleri.

- [x] **Adım 1: Başarısız testler:** `test_owner_sees_messages_timeline_and_sla`, `test_other_customer_gets_403_and_no_content` *(Denetim odağı 5)* (gövde talep başlığını içermez), `test_unknown_ticket_404`, `test_other_customers_attachment_403` *(Denetim odağı 5)*, `test_customer_cancels_pending_ticket`, `test_invalid_action_shows_reason` (taken talepte müşteri "cancel" → flash "'Talep Alındı' durumundaki talep iptal yapılamaz."), `test_reject_returns_to_review_and_logs`.
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `Talep detay sayfası: mesajlaşma, durum geçmişi, SLA göstergesi ve durum eylemleri eklendi`

---

### Görev 17: Ortak havuz ve personel talep listesi

**Dal:** `git switch -c ozellik/kanban`

**Dosyalar:**
- Oluştur: `src/Controller/AgentController.php`, `views/agent/{pool,tickets}.php`
- Değiştir: `src/routes.php`, `views/layout.php` (personel menüsü)
- Test: `tests/Feature/PoolTest.php`

**Arayüzler:**
- Sağlar: `GET /havuz` (personel/yönetici; `getPoolTickets` + SLA rozeti), `POST /havuz/{id}/al` (`TicketService::take`; `ConflictException` → flash hata, 409 değil yönlendirme), `GET /personel/talepler` ("Bana Atanan Talepler" wireframe tablosu: ID, başlık, öncelik, durum, SLA kalan, işlem).

- [x] **Adım 1: Başarısız testler:** `test_agent_takes_pool_ticket`, `test_second_take_shows_conflict_message`, `test_customer_cannot_see_pool` (403).
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `Ortak havuz ve personel talep listesi eklendi`

### Görev 18: AJAX Kanban ve canlı SLA göstergesi (FR-09, FR-07)

**Dosyalar:**
- Oluştur: `src/Controller/KanbanController.php`, `views/agent/kanban.php`, `public/assets/kanban.js`, `public/assets/sla.js`
- Değiştir: `src/routes.php`, `views/layout.php` (`<meta name="csrf-token">`), `views/partials/sla_badge.php` (`data-ticket-id`)
- Test: `tests/Feature/KanbanApiTest.php`

**Arayüzler:**
- Sağlar:
  - `GET /kanban` — üç sütun (Talep Alındı, Talep İnceleniyor, Talep Çözüldü), kartlarda başlık, öncelik, SLA rozeti; kart `draggable="true"`, sütun `data-status`; her kartta mobil için `<select>` durum menüsü.
  - `POST /api/talepler/{id}/durum` — gövde JSON `{"status": "in_review"}`, başlık `X-CSRF-Token`; yanıt `200 {"ok": true, "status": "in_review", "label": "Talep İnceleniyor", "sla": {"level": ..., "label": ...}}`; geçersiz geçiş `422 {"ok": false, "error": "<mesaj>"}`; yetkisiz `403 {"ok": false, "error": "Bu işlem için yetkiniz yok."}`.
  - `GET /api/sla?ids=1,2,3` — `{"1": {"level": "warning", "label": "45 dk kaldı", "css": "sla-warning"}, ...}`; yalnız görüntüleyebildiği talepler.
  - `kanban.js`: `dragstart/dragover/drop` → `fetch` POST; başarısızlıkta kartı eski sütuna geri koyar ve hata kutusunda `error` gösterir. `sla.js`: sayfadaki `[data-ticket-id].sla-badge` öğelerini 60 sn'de bir `/api/sla` ile günceller.

- [x] **Adım 1: Başarısız testler:** `test_move_taken_to_in_review_returns_json`, `test_invalid_move_returns_422_with_reason` (taken → resolved), `test_other_agents_ticket_returns_403_json`, `test_missing_csrf_header_403`, `test_sla_endpoint_returns_levels_for_visible_tickets_only` (FixedClock ile bir talep `warning`, bir talep `breached`; başka müşterinin talebi yanıtta yok).
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan; elle: personel girişi, kartı sürükle, sayfa yenilenmeden durum değişir; geçersiz taşıma geri döner.
- [x] **Adım 5:** Commit: `AJAX sürükle-bırak Kanban panosu ve canlı SLA uyarı göstergesi eklendi`

---

### Görev 19: Yönetici talep listesi ve Aşama 2 özet ekranı

**Dal:** `git switch -c ozellik/ozet-raporu`

**Dosyalar:**
- Oluştur: `src/Service/ReportService.php`, `src/Controller/AdminController.php`, `views/admin/{tickets,summary}.php`
- Değiştir: `src/routes.php`, `views/layout.php`
- Test: `tests/Unit/Service/ReportServiceTest.php`, `tests/Feature/AdminPagesTest.php`

**Arayüzler:**
- Sağlar:
  - `ReportService::__construct(TicketRepositoryInterface, UserRepositoryInterface, SlaStatusService, SlaCalculatorService)`; `summary(): array{byStatus: array<string,int>, byPriority: array<int,int>, warning: list<Ticket>, breached: list<Ticket>, workload: list<array{agent: User, active: int}>, avgResolutionMinutes: ?int, resolvedCount: int}` — `byStatus` tüm 6 durumu (0 dahil) içerir; ortalama `businessMinutesBetween(createdAt, resolvedAt)` üzerinden, çözülmüş talep yoksa `null`.
  - `GET /yonetici` (tüm talepler; durum, öncelik, personel filtresi + arama), `GET /yonetici/ozet` (kartlar: toplam çözülen, ortalama çözüm süresi "2 sa 15 dk", şu an uyarıda / aşılmış sayısı; tablolar: durum, öncelik, personel iş yükü, uyarıdaki ve aşılmış talepler listesi).

- [x] **Adım 1: Başarısız testler:** `test_summary_counts_and_average` (elle kurulmuş 6 talep: 2 çözülmüş, çözüm süreleri 60 ve 180 iş dakikası → ortalama 120; 1 warning, 1 breached), `test_summary_with_no_resolved_has_null_average`, `test_admin_pages_render` (200, "SLA" ve "Ortalama Çözüm Süresi" içerir), `test_agent_cannot_open_summary` (403).
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil + PHPStan.
- [x] **Adım 5:** Commit: `Yönetici talep listesi ve Aşama 2 özet raporu ekranı eklendi`

---

### Görev 20: Tam örnek veri, belgeler ve son kalite kontrolü

**Dal:** `git switch -c ozellik/belgeler-ve-kalite`

**Dosyalar:**
- Değiştir: `src/Seed/DemoSeeder.php` (`seedTickets()`), `README.md`
- Oluştur: `docs/izlenebilirlik.md`, `tests/Feature/SeedTest.php`

**Arayüzler:**
- Sağlar: `DemoSeeder::seedTickets()` — `$kernel->ticketService()` üzerinden (gerçek akışla) 28 talep: her durumdan en az 3, en az 3 talep `warning`, en az 3 `breached` olacak şekilde `FixedClock`'u geçmişe kurarak açar, sonra eylemleri uygular; her talepte 1–3 yanıt; bazı taleplerde anahtar kelimeyle yükseltilmiş öncelik; havuzda en az 3 sahipsiz talep (seeder önce personelsiz açar).

- [x] **Adım 1: Başarısız test** `SeedTest::test_seed_produces_demo_ready_data`: kullanıcı sayıları (1/3/5), talep ≥ 28, her `TicketStatus` için ≥ 3 (cancelled dahil), `warning` ≥ 3, `breached` ≥ 3, havuz ≥ 3, her talebin `ticket_logs` kaydı var.
- [x] **Adım 2–4:** test kırmızı → kodu yaz → test yeşil.
- [x] **Adım 5:** `README.md`: gereksinimler (PHP 8.2+, Composer, GD/fileinfo), kurulum (`composer install`, `php bin/seed.php`, `php -S localhost:8000 -t public` veya WAMP sanal hostu `public/`), giriş bilgileri tablosu, testler/analiz komutları, klasör yapısı, Aşama 3 için ER'ye eklenen 4 alan notu.
- [x] **Adım 6:** `docs/izlenebilirlik.md`: tablo `Gereksinim | Açıklama | Test sınıfı::metot | Durum` — FR-01..07, 09, 19, NFR-01, 03, 04, 05 (elle kontrol), 06, 07, 08, 10 ve US-06; Aşama 3'e kalanlar "Aşama 3" olarak işaretli.
- [x] **Adım 7:** Tam doğrulama: `php vendor/bin/phpunit` → tümü OK (çekirdek SLA testleri ≥ 12); `php vendor/bin/phpstan analyse` → `[OK] No errors`; elle 375 px genişlikte (tarayıcı geliştirici araçları) Taleplerim, talep formu, detay, Kanban, özet sayfalarında yatay kaydırma yok.
- [x] **Adım 8:** Commit: `Demo örnek verisi, README kurulum talimatları ve gereksinim-test izlenebilirlik tablosu eklendi`

---

## Uygulamada yapılan değişiklikler

Plan kodlamadan önce yazıldı. Bağımsız kod denetimlerinde bulunan sorunlar nedeniyle bazı noktalarda plandan ayrılındı:

- **Türkçe harf karşılaştırması (Görev 7 ve 11):** plandaki `str_replace(['İ','I'], ['i','ı'])` + `mb_strtolower` yöntemi "Internet" ile "internet"i eşleştirmiyordu; gizli soru cevabı ve anahtar kelime aramasında ortak `App\Core\Text::fold` kullanıldı.
- **Eşzamanlı durum değişikliği (Görev 13):** yalnız havuzdan almada değil, bütün durum eylemlerinde koşullu kayıt yapılır (`TicketRepositoryInterface::saveIfStatus`); talep bu arada değiştiyse "Talep bu arada değişti" uyarısı verilir.
- **Dosya yükleme (Görev 15):** görselin çözünürlüğü dosya açılmadan kontrol edilir (JPEG en fazla 24, PNG en fazla 13 milyon piksel); PHP'nin yükleme sınırı aşıldığında yanıltıcı CSRF hatası yerine doğru mesaj gösterilir.
- **Oturum ve güvenlik (Görev 3 ve 8):** beklenmeyen hatalar genel 500 sayfasına çevrilir; girişte CSRF anahtarı yenilenir; oturum süresi dolduktan sonra gönderilen form giriş sayfasına yönlendirir; oturumu olmayan API isteği 401 alır.
- **Yönetici listesi (Görev 19):** filtreleme controller yerine `TicketService::searchAll` içinde yapılır.
- **Örnek veri (Görev 20):** sarı (SLA %80) örnek talepler uzun SLA'lı seçildi; böylece seed'den saatler sonra da sarı kalırlar.

Son durum: 221 test, PHPStan 0 hata.
