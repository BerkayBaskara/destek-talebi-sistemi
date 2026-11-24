# Test Raporu (Aşama 2)

Bu rapor, ekibin test görevindeki sekiz aşamanın Aşama 2 kodu (JSON depolama) üzerindeki sonucudur. Ayrıntılar üç belgede:

- `docs/test-senaryolari.md`: ekibin 344 test senaryosunun her biri hangi teste bağlı, durumu ne.
- `docs/test-sonuclari.md`: çalışan her test için beklenen sonuç, gerçek sonuç ve PASS/FAIL (JUnit çıktısından).
- `docs/izlenebilirlik.md`: SRS'teki her gereksinim hangi testlerle doğrulandı.

Bütün testler geliştirme bilgisayarında çalıştırıldı; üretim ortamı ve gerçek kullanıcı verisi kullanılmadı.

## 1. Proje analizi

**Kod:** `src/` altında 135 PHP dosyası: 15 denetleyici, 31 servis (5'i gözlemci), 40 depo (arayüz + JSON), 25 model, 16 çekirdek sınıf, 5 istisna, örnek veri ve yönlendirme. Katmanlar: Controller → Service → Repository arayüzü → JSON deposu (`JsonStore`, dosya kilidi ve geçici dosya + yeniden adlandırma ile yazma). Durum geçişleri State, olaylar Observer deseniyle.

**SRS eşleşmesi (`docs/srs.md`):**

| | ✅ test | 👁 elle | ◐ kısmen | ✖ uygulanmadı | ⏭ Aşama 3 | Toplam |
|---|---|---|---|---|---|---|
| İşlevsel (FR) | 89 | – | 2 | 2 | 3 | 96 |
| İşlevsel olmayan (NFR) | 28 | 1 | 5 | 1 | 5 | 40 |

**Dosya eşleşmesi:** test planındaki 131 farklı dosya adının 33'ü projede aynı adla var (ör. `AuthService.php`, `SlaCalculatorService.php`, `TicketState.php`). Kalan 98 dosya adı ayrı sınıf olarak yok; davranışları daha az sayıda sınıfta toplanmıştır. İki ad yazım hatalıdır (`cketReplyPermissionService.php`, `otificationReadService.php`). Eşleşme:

| Planlanan dosyalar | Davranışın bulunduğu yer |
|---|---|
| CreateAdminCommand, RateLimiter, SecurityAnswerHasher, UserIdGenerator, SecurityQuestionService, SecurityQuestionController | `bin/create-admin.php`, `AuthService` (+ `JsonAttemptRepository`), `SettingsService` / `SettingsController` |
| UserService, UserController, UserStatusService, StaffActivityService, StaffDeactivationService | `StaffService`, `StaffController`, `CustomerController` |
| AuthorizationService, SessionSecurity, ExceptionHandler, HtmlSanitizer | `Kernel` (`requireRole`, oturum süresi, hata sayfası), `TicketService::findVisible`, `NativeSession`, `e()` yardımcısı |
| Scheduler, SlaMonitoringJob, StaffInactivityJob, TicketAutoCloseJob, CustomerResponseTimeoutJob, JobLockService | `SlaMonitorService::runIfDue` (her istekte en çok dakikada bir), `bin/sla-monitor.php`, `JsonRuntimeStateRepository::claim` |
| TicketDetailService, TicketHistoryController, TicketPermissionService, TicketReplyPermissionService, TicketCodeGenerator, TicketResolutionService, CustomerResolutionService, ResolutionFeedbackController, TicketClosureService | `TicketService`, `TicketController`, `Ticket` |
| CategoryService, CategoryController, PriorityController | `CatalogService`, `CatalogController`, `PriorityService` |
| FormBuilderService, FormBuilderController, FormFieldService, FormFieldType, CategoryFormService, DynamicFormController, DynamicFormValidator, FormResponseService | `CatalogService` (ek alanlar, `FIELD_TYPES`), `TicketService` (ek alan doğrulama), `CatalogController` |
| AssignmentOverrideService, ManualAssignmentService, EscalationAssignmentService, ManualEscalationService, ResolutionEscalationService, SameStaffContinuationService, UnresolvableTicketService, UnresolvableReasonValidator | `EscalationService` (`assignManually`, `escalateManually`, `escalate`, `decide`, `onRejected`, `closeUnresolvable`) |
| StaffHierarchyService, StaffHierarchyController, StaffSkillService, StaffCategoryService, StaffCapacityService, StaffWorkloadService, StaffAvailabilityService | `HierarchyService`, `HierarchyController`, `StaffService`, `LeaveService`, `AssignmentService`, `JsonTicketRepository::countActiveByAgent` |
| SlaService, SlaConfigurationService, SlaConfigurationController, SlaHistoryService, AdditionalSlaService, AutoCloseConfigurationService, AutoCloseConfigurationController | `TicketService`, `CatalogService`, `ExtraSlaPolicy`, `QuestionPolicy`, `SettingsService`, `CatalogController`; ilk SLA `Ticket::originalDeadline`, ek SLA kayıtları işlem geçmişinde |
| NotificationReadService, AssignmentNotificationListener, EscalationNotificationListener, SlaNotificationListener, ResolutionNotificationListener, EscalationNotificationService, AdminNotificationService | `NotificationService`, `NotificationObserver`, `JsonNotificationRepository`, `NotificationController`, `SlaMonitorService` |
| TicketReportService, StaffPerformanceReportService, SlaReportService, EscalationReportService, ReportFilterService, CsvExportService, CsvSecuritySanitizer | `DetailedReportService`, `ReportService`, `ReportController` |
| SystemSettingsService, SystemConfigurationValidator, UploadLimitService, UploadConfigurationController, SiteBrandingService, HomepageContentService, AdminAuditService, AuditLogService | `SettingsService`, `SettingsController`, `BrandingService`, `AuditLogger` |
| AttachmentPermissionService, FileExtensionValidator, MimeTypeValidator, FileContentValidator | `AttachmentController`, `AttachmentService` |
| RestoreService | `BackupService::restore` |
| QuestionSimilarityMatcher, FormVersionService, ReportStreamController, SseConnectionService | Yok (FR-29, FR-42, FR-83; gerekçeler `docs/izlenebilirlik.md`) |
| Database, DatabaseConnection, MigrationRunner, JsonMigrationService | Aşama 3 (MySQL) |

**Eksik ya da kısmen karşılanan gereksinimler:** FR-29 (%90 soru benzerliği), FR-42 (form sürüm numarası), FR-83 ve NFR-35 (SSE), FR-13.2 (yıllık takvim görünümü), kullanıcı rolü değiştirme (US-27), NFR-38 (otomatik kod standardı aracı projede yok), NFR-33 (denetim kaydı, talep geçmişi ve eskalasyon tablosu sayfalanmıyor), NFR-42 (Firefox ve Safari denenmedi). Bunlar ekip kararı gerektirir.

**Mimari ve güvenlik riskleri:**

| Risk | Etki | Durum |
|---|---|---|
| JSON dosya depolama tek sunucuya bağlı; dosya kilidi yalnız aynı makinedeki süreçleri korur | Birden çok sunucuda çift atama | Aşama 3'te MySQL ile kalkar |
| PHP'nin yerleşik sunucusu istekleri sırayla işler | Eşzamanlı yükte bekleme | Sunumda ve yük testinde Apache (WAMP) kullanılmalı |
| Zamanlanmış işler cron yerine isteklerle tetikleniyor | Hiç istek gelmezse izleme çalışmaz | `bin/sla-monitor.php` ile elle ya da cron ile çalıştırılabilir |
| Giriş deneme sınırı hesap bazlı; deneme kayıtları budanmıyor | İstemci bazlı kısıt yok, tablo büyür | Kayıtta küçük bulgu |
| Yedekten geri yüklemede diğer kullanıcıların oturumu kapanmıyor | Eski oturum yeni tablodaki aynı kimliğe bağlanabilir | Kayıtta karar; yöneticinin hesabı değiştiyse onun oturumu kapanır |

## 2. Test planı

Her test metodu, içeriğine bakılarak türlere ayrıldı: birim (tek sınıf), entegrasyon (JSON deposu ya da birden çok sınıf), işlevsel (HTTP isteğinden yanıta), negatif (red, 4xx, `assertFalse`), sınır değer (tam %80, 50 kayıt, yıl değişimi, artık yıl, sınırın bir altı ve üstü), güvenlik (401/403, CSRF, kaçış, yol geçişi), istisna (`expectException`), performans (süre ölçümü). Bir test birden çok türe girebilir. Regresyon: bütün takım her değişiklikten sonra baştan çalıştırıldı; bulunan her hata için önce hatayı gösteren test yazıldı.

| Modül | Test | Birim | Entegrasyon | İşlevsel | Negatif | Sınır değer | Güvenlik | İstisna | Performans |
|---|---|---|---|---|---|---|---|---|---|
| Kimlik doğrulama | 44 | 16 | 28 | 28 | 20 | 9 | 26 | 1 | – |
| Talep yönetimi | 98 | 54 | 46 | 44 | 45 | 5 | 21 | 12 | – |
| Dinamik formlar | 5 | – | 5 | 5 | 5 | 2 | – | – | – |
| Atama ve hiyerarşi | 22 | 15 | 7 | 7 | 10 | 5 | 2 | 1 | – |
| SLA hesabı | 54 | 44 | 21 | 10 | 21 | 14 | 5 | 4 | 1 |
| Eskalasyon | 14 | – | 14 | 14 | 7 | 4 | 1 | – | – |
| Çözüm ve otomatik kapanış | 12 | – | 12 | 12 | 11 | – | 3 | – | – |
| Ek dosyalar | 29 | 23 | 6 | 6 | 16 | 6 | 5 | 10 | 3 |
| Bildirimler | 5 | – | 5 | 5 | 3 | 1 | 2 | – | – |
| Raporlama | 8 | 3 | 6 | 5 | 2 | 3 | 2 | – | – |
| Veri katmanı (JSON) | 26 | 26 | 17 | – | 13 | 1 | – | 1 | – |
| Yedek ve geri yükleme | 5 | – | 5 | 5 | 3 | – | 4 | – | – |
| Yönetim ve ayarlar | 41 | 15 | 26 | 26 | 22 | 12 | 5 | 1 | – |
| Çekirdek (yönlendirme, hata, saat) | 14 | 6 | 8 | 8 | 8 | 5 | 6 | – | – |
| Senaryo ve performans testleri | 36 | – | 36 | 36 | 19 | 11 | 6 | – | 7 |
| **Toplam** | **413** | **202** | **242** | **211** | **205** | **78** | **88** | **30** | **11** |

413 test metodu, veri sağlayıcılı vakalarla birlikte 449 test çalışır. Ayrı birim testi olmayan sınıflar (modeller, State sınıfları, JSON depolarının çoğu, `EscalationService`, `BackupService`, `SlaMonitorService`, gözlemciler) uçtan uca testlerle sınanır.

## 3. Test otomasyonu

- **Altyapı:** PHPUnit 11, PHPStan 2 (seviye 6). `phpunit.xml` iki takım tanımlar: `Unit` ve `Feature`.
- **Bağımsızlık:** her test kendi geçici klasöründe boş veriyle başlar ve bitince klasörü siler. Saat sabittir (`FixedClock`, 9 Kasım 2026 Pazartesi 10:00), oturum bellektedir (`ArraySession`). Testler sırası değişse de aynı sonucu verir.
- **Test verisi:** `FeatureTestCase` yardımcıları örnek katalog (`seedCatalog`), müşteri kaydı (`registerCustomer`) ve giriş (`loginAs`) hazırlar; personel `createStaff` ile oluşturulur. Örnek takvim `storage/config` altından kopyalanır.
- **Mock ve stub:** saat ve oturum için sahte nesneler; `TicketServiceTest`'te eski kopya döndüren bir depo sarmalayıcısı (bayat veriyle yazma denemesi); `BrandingService` için dosya taşıma işlevi dışarıdan verilir. Depolar sahte değil, gerçek JSON dosyasıyla sınanır; böylece kilit ve dosya yazma da test edilir.
- **Gerçek eşzamanlılık:** `ConcurrencyTest` ayrı PHP süreçlerini ortak bir başlama anında çalıştırır (`tests/Concurrency/take_worker.php`).

## 4. Testlerin çalıştırılması

`php vendor/bin/phpunit` → **449 test, 2718 doğrulama, 449 PASS, 0 FAIL** (yaklaşık 3 dakika). `php vendor/bin/phpstan analyse` → hata yok. Test başına sonuç ve kanıt: `docs/test-sonuclari.md`.

**Testin gerçekten hata yakaladığının kanıtı:** `JsonStore`'daki dosya kilidi geçici olarak kapatılıp `ConcurrencyTest` üç kez çalıştırıldı. Sonuçlar:

| Çalıştırma | Aynı talebi "alan" personel (beklenen 1) | Kapasitesi 3 olan personelde başarılı alma (beklenen tam 3) |
|---|---|---|
| 1 | 7 (FAIL) | 3 (PASS) |
| 2 | 3 (FAIL) | 2 (FAIL: yazmalar birbirini ezdi) |
| 3 | 2 (FAIL) | 6 (FAIL: kapasite aşıldı) |

Kilit geri açılınca iki test de geçti.

## 5. Güvenlik

| Saldırı | Sonuç | Kanıt |
|---|---|---|
| SQL Injection | Test edilemez: Aşama 2'de SQL yok, veri JSON dosyalarında | Aşama 3 (MySQL, parametreli sorgu) |
| XSS | PASS: talep, yanıt, ana sayfa metni ve form değerleri kaçışlı | `TicketDetailTest::test_reply_is_escaped`, `ScenarioCoverageTest::test_script_and_event_attributes_are_shown_harmless`, `TicketCreateTest::test_invalid_form_keeps_values_422` |
| CSRF | PASS: anahtarsız ya da yanlış anahtarlı istek 403 (50 ms altında); girişte anahtar yenilenir | `KernelTest::test_post_without_csrf_returns_403`, `CsrfTest`, `KanbanApiTest::test_missing_csrf_header_403`, `PerformanceScenarioTest::test_invalid_csrf_is_rejected_quickly` |
| IDOR | PASS: başka müşterinin talebi, eki, bildirimi ve onay işlemi 403 | `TicketDetailTest::test_other_customer_gets_403_and_no_content`, `AttachmentAccessTest::test_other_customers_attachment_403`, `NotificationTest::test_opening_notification_marks_read_and_others_cannot_open_it`, `ScenarioCoverageTest::test_other_customer_cannot_approve_and_double_approval_is_refused` |
| Session Fixation | PASS: girişte oturum kimliği yenilenir; 30 dakika hareketsizlikte oturum kapanır | `AuthFlowTest::test_session_id_regenerated_on_login`, `test_idle_30_minutes_logs_out`, `NativeSessionTest` |
| Brute Force | PASS: giriş ve şifre sıfırlamada deneme sınırı ve engel süresi (yönetici ayarı) | `ConfigCompletionTest::test_login_is_blocked_after_configured_failed_attempts`, `SecurityAndLimitsTest::test_reset_is_blocked_after_five_wrong_answers_for_configured_minutes` |
| Path Traversal | PASS: yedek zip'inde `../` adlı dosya reddedilir; ekler rastgele adla web kökü dışında saklanır | `ScoreAndRestoreTest::test_invalid_backup_is_rejected_and_data_untouched`, `AttachmentServiceTest::test_stored_name_is_random_and_has_correct_extension` |
| File Upload Attack | PASS: tür içerikten belirlenir; görsel kılığında PHP, EXE, çift uzantı, bozuk görsel ve sahte MP4 reddedilir | `AttachmentServiceTest` (`test_rejects_php_disguised_as_jpg`, `test_rejects_exe`, `test_double_extension_is_stored_safely_or_rejected`, `test_corrupt_image_is_rejected`, `test_fake_mp4_is_rejected`) |
| Privilege Escalation | PASS: personel ve müşteri yönetici işlemlerinde 403; müşteri havuza ve eskalasyona erişemez | `ScenarioCoverageTest::test_non_admin_cannot_post_admin_actions`, `SystemConfigTest::test_non_admin_gets_403_on_every_config_page`, `PoolTest::test_customer_cannot_see_pool_or_take` |
| Race Condition | PASS: 10 eşzamanlı süreçte çift atama ve kapasite aşımı yok | `ConcurrencyTest` (yukarıdaki kilit deneyi) |
| Zip bombası, bozuk yedek | PASS: açılmış hâli 50 MB'ı aşan ya da aktif yöneticisi olmayan yedek reddedilir | `ReviewFixesTest::test_restore_rejects_oversized_uncompressed_content`, `test_restore_rejects_users_table_without_active_admin` |

OWASP ZAP ve Burp Suite ile otomatik tarama yapılmadı.

## 6. İş kuralları

| Modül | Derinlemesine sınanan kurallar |
|---|---|
| Kimlik doğrulama | Kayıt, giriş, M/P/Y numaraları, gizli soruyla sıfırlama (Türkçe harf duyarsız), deneme sınırı, pasif hesap, oturum süresi |
| Talep yönetimi | State geçişleri (izinli 6, izinsiz 8), iptal, arama ve süzgeç, talep kodu, öncelik anahtar kelimesi |
| Dinamik formlar | Zorunlu alan, sayı, tarih, seçim, e-posta, en çok uzunluk, aynı adlı alan, silinen alanın eski talepteki değeri |
| Atama ve hiyerarşi | En az iş yükü, eşitlikte en küçük kimlik, kapasite, izin (bugün, yarın, bitmiş), hiyerarşi seviyesi, eşzamanlı alma |
| SLA hesabı | 12 vakalık tablo (öğle arası, gün devri, hafta sonu, tatil), yıl değişimi, artık yıl, saat dilimi, bozuk takvim, 5 yıllık tatil, özel gün, soru duraklaması |
| Eskalasyon | Aşımda müşteriye sorma, 1 iş günü bekleme, ek SLA, üst seviye, uygun personel yoksa yönetici, aynı aşımın bir kez işlenmesi, manuel eskalasyon |
| Çözüm ve otomatik kapanış | İşe yaradı, aynı personelle devam, başka personel, 3. retten sonra eskalasyon, 3 iş günü sonra otomatik tamamlama, itirazda durma, çift çalışmada tek kapanış |
| Ek dosyalar | Biçim, boyut, sayı, 1920×1080 küçültme, yetkili indirme |
| Bildirimler | Atama, yanıt, çözüm, %80 uyarı (bir kez), eskalasyon, yönetici müdahalesi, okundu/okunmadı, kapatılan bildirim türü |
| Raporlama | Durum ve öncelik dağılımı, SLA uyumu (ilk bitişe göre, duraklama düşülür), puan, süzgeçler, CSV (Türkçe karakter, formül koruması), eskalasyon kayıtları, sayfalama |
| Veri katmanı | Ekleme, güncelleme, silme, bozuk dosya, karşılaştır-yaz, kilitli işlem (MySQL Aşama 3) |
| Yedek ve geri yükleme | Zip indirme, geri yükleme, otomatik ön yedek, bozuk, eksik ya da tehlikeli yedeğin reddi |

## 7. Performans

| Ölçüm | Sınır | Sonuç | Kanıt |
|---|---|---|---|
| 5 yıllık tatille SLA hesabı | 500 ms | PASS | `SlaCalculatorServiceTest::test_five_years_of_holidays_is_fast` |
| 100 aktif talepte Kanban | 1 sn | PASS | `KanbanPerformanceTest` |
| Kanban taşıma | 200 ms | PASS | `PerformanceScenarioTest::test_kanban_move_is_fast` |
| Yetkili dosya indirme | 1 sn | PASS | `AttachmentAccessTest::test_authorized_download_is_under_one_second` |
| İkinci alma isteğinin reddi | 100 ms | PASS | `PerformanceScenarioTest::test_second_take_is_rejected_quickly` |
| Geçersiz CSRF reddi | 50 ms | PASS | `PerformanceScenarioTest::test_invalid_csrf_is_rejected_quickly` |
| Bozuk takvimde varsayılana geçiş | 100 ms | PASS | `PerformanceScenarioTest::test_corrupt_calendar_falls_back_quickly` |
| Görsel küçültme | 2 sn | PASS | `AttachmentServiceTest::test_downscale_finishes_within_two_seconds` |
| 50 MB video | 1600 ms | PASS | `AttachmentServiceTest::test_large_video_is_stored_quickly` |
| 10.000 talepte rapor | ölçülür | PASS (5 sn altında) | `PerformanceScenarioTest::test_report_with_ten_thousand_tickets` |

Eşzamanlı kullanıcı yükü (k6): henüz çalıştırılmadı.

## 8. Bulunan hatalar

Bu aşamada bulunan ve düzeltilen hatalar; her biri için önce hatayı gösteren test yazıldı, sonra bütün takım yeniden çalıştırıldı:

| Hata | Bulan | Test |
|---|---|---|
| UTC ile verilen başlangıçta SLA bitişi 2 saat kayıyordu | SLA-010 | `SlaEdgeCasesTest::test_utc_input_gives_same_moment_as_local_input` |
| Personel SLA'sı dolmuş talebe yanıt yazabiliyordu (FR-60) | SLA-036 | `TicketServiceTest::test_agent_cannot_reply_to_breached_or_resolved` |
| Çözüm işe yaramadığında başka personel istenemiyordu (FR-63/64) | RES-011 | `ResolutionTest::test_asking_for_another_agent_escalates_to_next_level` |
| Müşteri hesapları pasif yapılamıyordu (FR-33) | SRS karşılaştırması | `CustomerAccountsTest` |
| Rapor 200, bildirimler sınırsız satır gösteriyordu (NFR-33) | SRS karşılaştırması | `PaginationTest` |
| Soru beklerken çözülen ya da aktarılan talepte SLA duraklaması kapanmıyordu | Kod incelemesi | `ReviewFixesTest::test_escalation_ends_question_pause`, `test_closed_ticket_is_not_touched_when_question_time_ends` |
| Yanıtsız soruda SLA, izlemenin çalıştığı andan devam ediyordu | Kod incelemesi | `ReviewFixesTest::test_unanswered_question_resumes_from_reply_due_time` |
| SLA uyumunda soru nedeniyle duran süre personelin aleyhine sayılıyordu | Kod incelemesi | `ReviewFixesTest::test_paused_time_does_not_count_against_sla_compliance` |
| Yeniden aktif yapılan personel bir dakika içinde yine pasif oluyordu | Kod incelemesi | `ReviewFixesTest::test_reactivated_staff_is_not_deactivated_again` |
| Devralan personelin puanı öncekinin retleriyle düşüyordu | Kod incelemesi | `ReviewFixesTest::test_score_of_receiving_agent_ignores_previous_rejections` |
| Geri yükleme kilitsizdi ve yöneticisiz ya da çok büyük yedeği kabul ediyordu | Kod incelemesi | `ReviewFixesTest::test_restore_rejects_*` |
| İzleme ve giriş, aradaki başka değişikliği eski veriyle ezebiliyordu | Kod incelemesi | `JsonUserRepositoryTest::test_record_login_writes_only_last_login` |

Açık kalan küçük bulgular: giriş kilidinin istemci bazlı olmaması ve deneme kayıtlarının budanmaması; takvim dosyasında yalnız ilk molanın alınması; personel güncellemesinde kapasite hatası olsa da ad değişikliğinin kaydedilmesi; yöneticinin pasif müşteri adına talep açabilmesi.

## 9. Test edilemeyenler

- SQL Injection, veritabanı bağlantısı, migration ve JSON'dan MySQL'e aktarım: Aşama 3.
- OWASP ZAP ve Burp Suite taraması: yapılmadı.
- Firefox ve Safari: denenmedi; Chromium tabanlı tarayıcıda 375, 768 ve 1920 piksel genişlikte 20 ekran denetlendi, yatay taşma yok.
- 5 kullanıcılı kullanılabilirlik denemesi (NFR-36): sunumda yapılabilir.
