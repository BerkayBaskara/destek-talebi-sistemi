# Test Sonuçları

Bu tablo PHPUnit'in JUnit çıktısından üretilmiştir; her satır terminalde gerçekten çalışan bir testtir. Beklenen sonuç, testin karşıladığı senaryo varsa ekibin test planındaki ifadedir (`docs/test-senaryolari.md`), yoksa testin açıklamasıdır.

## Çalıştırma kanıtı

| Alan | Değer |
|---|---|
| Tarih | 24.11.2026 |
| Commit | `c9679f4` (dal `ozellik/yapilandirma-tamamlama`) |
| Ortam | PHP 8.3.28, Windows 11, geliştirme bilgisayarı (üretim ortamı değil) |
| Komut | `php vendor/bin/phpunit --log-junit junit.xml` |
| Sonuç | 449 test, 2718 doğrulama, 449 PASS, 0 FAIL, toplam 173.0 sn |
| Statik analiz | `php vendor/bin/phpstan analyse` (seviye 6): No errors |

Yeniden üretmek için aynı komut çalıştırılır; testler her seferinde geçici bir klasörde boş veriyle ve sabit saatle başlar.

## Sonuçlar

| Test ID | Sınıf | Test adı | Beklenen sonuç | Gerçek sonuç | Durum | Hata açıklaması |
|---|---|---|---|---|---|---|
| T-001 | ClockTest | `test_system_clock_uses_istanbul_timezone` | Sistem saati Europe/Istanbul saat diliminde | 1 doğrulama sağlandı (6 ms) | PASS | — |
| T-002 | ClockTest | `test_fixed_clock_returns_and_advances` | Sabit saat verilen anı döner ve ileri alınabilir | 3 doğrulama sağlandı (1 ms) | PASS | — |
| T-003 | CsrfTest | `test_token_is_stable_within_session_and_validates` | SEC-004: Kabul edilmeli | 3 doğrulama sağlandı (1 ms) | PASS | — |
| T-004 | CsrfTest | `test_rejects_null_and_wrong_token` | SEC-006: 403 | 3 doğrulama sağlandı (0 ms) | PASS | — |
| T-005 | CsrfTest | `test_new_session_gets_different_token` | SEC-007: Reddedilmeli | 1 doğrulama sağlandı (1 ms) | PASS | — |
| T-006 | JsonStoreTest | `test_insert_assigns_incrementing_ids` | SEC-022: Veri doğru yazılmalı | 6 doğrulama sağlandı (27 ms) | PASS | — |
| T-007 | JsonStoreTest | `test_update_merges_fields` | SEC-023: Veri doğru güncellenmeli | 1 doğrulama sağlandı (11 ms) | PASS | — |
| T-008 | JsonStoreTest | `test_delete_removes_row_and_ids_are_not_reused` | Silinen satır kalkar, kimliği yeniden verilmez | 2 doğrulama sağlandı (30 ms) | PASS | — |
| T-009 | JsonStoreTest | `test_update_missing_row_throws` | Olmayan satırı güncellemek hata verir | 1 doğrulama sağlandı (4 ms) | PASS | — |
| T-010 | JsonStoreTest | `test_missing_or_empty_file_reads_as_empty_list` | SEC-021: Veri doğru okunmalı | 2 doğrulama sağlandı (3 ms) | PASS | — |
| T-011 | JsonStoreTest | `test_corrupt_file_throws_and_is_not_overwritten` | SEC-024: Kontrollü hata | 2 doğrulama sağlandı (15 ms) | PASS | — |
| T-012 | JsonStoreTest | `test_transaction_conditional_update_only_first_wins` | AUTH-028: Tekrarlanan ID oluşmamalı; SEC-025: Veri kaybı olmamalı | 3 doğrulama sağlandı (14 ms) | PASS | — |
| T-013 | JsonStoreTest | `test_unicode_is_stored_readably` | SEC-021: Veri doğru okunmalı | 1 doğrulama sağlandı (15 ms) | PASS | — |
| T-014 | NativeSessionTest | `test_set_get_regenerate_and_destroy` | AUTH-015: Veri doğru saklanmalı; AUTH-016: Doğru veri dönmeli; AUTH-017: Session ID yenilenmeli … | 8 doğrulama sağlandı (9 ms) | PASS | — |
| T-015 | RouterTest | `test_matches_numeric_param` | Sayısal yol parametresi eşleşir | 1 doğrulama sağlandı (2 ms) | PASS | — |
| T-016 | RouterTest | `test_param_rejects_non_numeric` | Sayı olmayan parametre eşleşmez | 1 doğrulama sağlandı (1 ms) | PASS | — |
| T-017 | RouterTest | `test_wrong_method_is_405` | Yanlış HTTP yöntemi 405 döner | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-018 | RouterTest | `test_trailing_slash_is_ignored` | Sondaki eğik çizgi yok sayılır | 2 doğrulama sağlandı (0 ms) | PASS | — |
| T-019 | TicketCodeTest | `test_generated_code_is_six_unambiguous_characters` | TKT-020: Benzersiz kod üretilmeli | 200 doğrulama sağlandı (2 ms) | PASS | — |
| T-020 | TicketCodeTest | `test_normalize_accepts_hash_and_lowercase` | Talep kodu # işaretli ya da küçük harfli yazılsa da bulunur | 3 doğrulama sağlandı (0 ms) | PASS | — |
| T-021 | TicketCodeTest | `test_new_fields_round_trip_and_old_rows_still_load` | FRM-022: Veriler doğru kaydedilmeli | 9 doğrulama sağlandı (1 ms) | PASS | — |
| T-022 | TicketStateTest | `test_allowed_transition with data set "havuzdan_alma"` | TKT-010: Geçerli geçiş uygulanmalı; TKT-024: Kurallara uyulmalı (vaka: havuzdan alma) | 1 doğrulama sağlandı (1 ms) | PASS | — |
| T-023 | TicketStateTest | `test_allowed_transition with data set "musteri_iptali"` | TKT-010: Geçerli geçiş uygulanmalı; TKT-024: Kurallara uyulmalı (vaka: musteri iptali) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-024 | TicketStateTest | `test_allowed_transition with data set "incelemeye_alma"` | TKT-010: Geçerli geçiş uygulanmalı; TKT-024: Kurallara uyulmalı (vaka: incelemeye alma) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-025 | TicketStateTest | `test_allowed_transition with data set "cozum"` | TKT-010: Geçerli geçiş uygulanmalı; TKT-024: Kurallara uyulmalı (vaka: cozum) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-026 | TicketStateTest | `test_allowed_transition with data set "musteri_onayi"` | TKT-010: Geçerli geçiş uygulanmalı; TKT-024: Kurallara uyulmalı (vaka: musteri onayi) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-027 | TicketStateTest | `test_allowed_transition with data set "musteri_reddi"` | TKT-010: Geçerli geçiş uygulanmalı; TKT-024: Kurallara uyulmalı (vaka: musteri reddi) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-028 | TicketStateTest | `test_forbidden_transition_throws with data set "kapali_tekrar_islemde"` | TKT-011: İşlem engellenmeli; TKT-024: Kurallara uyulmalı; TKT-030: Engellenmeli … (vaka: kapali tekrar islemde) | 1 doğrulama sağlandı (1 ms) | PASS | — |
| T-029 | TicketStateTest | `test_forbidden_transition_throws with data set "iptal_alinamaz"` | TKT-011: İşlem engellenmeli; TKT-024: Kurallara uyulmalı; TKT-030: Engellenmeli … (vaka: iptal alinamaz) | 1 doğrulama sağlandı (1 ms) | PASS | — |
| T-030 | TicketStateTest | `test_forbidden_transition_throws with data set "alinmis_iptal_edilemez"` | TKT-011: İşlem engellenmeli; TKT-024: Kurallara uyulmalı; TKT-030: Engellenmeli … (vaka: alinmis iptal edilemez) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-031 | TicketStateTest | `test_forbidden_transition_throws with data set "incelemede_kapatilamaz"` | TKT-011: İşlem engellenmeli; TKT-024: Kurallara uyulmalı; TKT-030: Engellenmeli … (vaka: incelemede kapatilamaz) | 1 doğrulama sağlandı (1 ms) | PASS | — |
| T-032 | TicketStateTest | `test_forbidden_transition_throws with data set "bekleyen_cozulemez"` | TKT-011: İşlem engellenmeli; TKT-024: Kurallara uyulmalı; TKT-030: Engellenmeli … (vaka: bekleyen cozulemez) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-033 | TicketStateTest | `test_forbidden_transition_throws with data set "cozulmus_iptal_edilemez"` | TKT-011: İşlem engellenmeli; TKT-024: Kurallara uyulmalı; TKT-030: Engellenmeli … (vaka: cozulmus iptal edilemez) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-034 | TicketStateTest | `test_forbidden_transition_throws with data set "kapali_reddedilemez"` | TKT-011: İşlem engellenmeli; TKT-024: Kurallara uyulmalı; TKT-030: Engellenmeli … (vaka: kapali reddedilemez) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-035 | TicketStateTest | `test_forbidden_transition_throws with data set "alinmis_tekrar_alinamaz"` | TKT-011: İşlem engellenmeli; TKT-024: Kurallara uyulmalı; TKT-030: Engellenmeli … (vaka: alinmis tekrar alinamaz) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-036 | TicketStateTest | `test_message_names_current_status` | TKT-024: Kurallara uyulmalı | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-037 | TicketStateTest | `test_final_statuses` | TKT-023: Kapalı durumlar doğru algılanmalı | 3 doğrulama sağlandı (0 ms) | PASS | — |
| T-038 | TicketStateTest | `test_hoca_labels` | TKT-022: Bütün durumlar doğru tanımlanmalı | 6 doğrulama sağlandı (0 ms) | PASS | — |
| T-039 | TicketStateTest | `test_priority_labels_and_default_hours` | TKT-032: Tanımlı öncelikler dönmeli; TKT-033: Doğru süre dönmeli | 3 doğrulama sağlandı (1 ms) | PASS | — |
| T-040 | TicketStateTest | `test_ticket_round_trips_array` | Talep diziye ve geri dönüştürülünce aynı kalır | 4 doğrulama sağlandı (1 ms) | PASS | — |
| T-041 | TicketStateTest | `test_set_status_and_increment_attempt` | Durum değişir, deneme sayısı artar | 2 doğrulama sağlandı (0 ms) | PASS | — |
| T-042 | JsonCalendarRepositoryTest | `test_reads_intervals_with_break_removed` | Mesai aralıkları öğle arası çıkarılarak okunur | 1 doğrulama sağlandı (2 ms) | PASS | — |
| T-043 | JsonCalendarRepositoryTest | `test_weekend_and_holiday_have_no_intervals` | Hafta sonu ve tatilde çalışma aralığı yok | 2 doğrulama sağlandı (0 ms) | PASS | — |
| T-044 | JsonCalendarRepositoryTest | `test_business_hours_and_holidays_accessors` | Mesai saatleri ve tatiller doğru okunur | 3 doğrulama sağlandı (1 ms) | PASS | — |
| T-045 | JsonCalendarRepositoryTest | `test_missing_file_falls_back_to_default_and_logs` | Takvim dosyası yoksa varsayılan mesaiye geçilir ve kayıt düşülür | 3 doğrulama sağlandı (1 ms) | PASS | — |
| T-046 | JsonCalendarRepositoryTest | `test_corrupt_file_falls_back_to_default_and_logs` | SLA-011: Varsayılan takvime geçilmeli; ADM-026: Kontrollü varsayılan davranış | 3 doğrulama sağlandı (1 ms) | PASS | — |
| T-047 | JsonCalendarRepositoryTest | `test_calendar_without_any_working_hours_falls_back_to_default` | Hiç çalışma saati olmayan takvimde varsayılana geçilir | 2 doğrulama sağlandı (9 ms) | PASS | — |
| T-048 | JsonCalendarRepositoryTest | `test_bom_prefixed_file_is_read` | BOM ile kaydedilmiş takvim dosyası okunur | 1 doğrulama sağlandı (3 ms) | PASS | — |
| T-049 | JsonCalendarRepositoryTest | `test_break_outside_day_is_rejected` | Mesai dışına taşan öğle arası reddedilir | 1 doğrulama sağlandı (1 ms) | PASS | — |
| T-050 | JsonCalendarRepositoryTest | `test_bad_time_format_is_rejected` | Hatalı saat biçimi reddedilir | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-051 | JsonCatalogRepositoriesTest | `test_categories` | Kategoriler kaydedilir ve okunur | 3 doğrulama sağlandı (17 ms) | PASS | — |
| T-052 | JsonCatalogRepositoriesTest | `test_sla_policy_lookup_by_category_and_priority` | SLA kuralı kategori ve önceliğe göre bulunur | 4 doğrulama sağlandı (30 ms) | PASS | — |
| T-053 | JsonCatalogRepositoriesTest | `test_replies_sorted_oldest_first` | Yanıtlar eskiden yeniye sıralanır | 2 doğrulama sağlandı (29 ms) | PASS | — |
| T-054 | JsonCatalogRepositoriesTest | `test_ticket_logs` | TKT-040: Log oluşturulmalı | 4 doğrulama sağlandı (20 ms) | PASS | — |
| T-055 | JsonTicketRepositoryTest | `test_save_and_find_round_trip` | Kaydedilen talep aynen okunur | 5 doğrulama sağlandı (15 ms) | PASS | — |
| T-056 | JsonTicketRepositoryTest | `test_save_existing_updates` | Var olan talep güncellenir | 2 doğrulama sağlandı (15 ms) | PASS | — |
| T-057 | JsonTicketRepositoryTest | `test_pool_contains_only_unassigned_pending_oldest_first` | Havuzda yalnız atanmamış bekleyen talepler, en eskisi önce | 1 doğrulama sağlandı (42 ms) | PASS | — |
| T-058 | JsonTicketRepositoryTest | `test_find_by_creator_and_agent` | Talepler açana ve personele göre bulunur | 2 doğrulama sağlandı (19 ms) | PASS | — |
| T-059 | JsonTicketRepositoryTest | `test_count_active_by_agent` | ASN-015: Doğru hesaplanmalı | 1 doğrulama sağlandı (26 ms) | PASS | — |
| T-060 | JsonTicketRepositoryTest | `test_assign_if_unassigned_second_call_fails` | Aynı talebe ikinci atama başarısız olur | 4 doğrulama sağlandı (7 ms) | PASS | — |
| T-061 | JsonTicketRepositoryTest | `test_concurrent_assign_cannot_exceed_capacity` | ASN-014: Engellenmeli veya istisna uygulanmalı; SEC-025: Veri kaybı olmamalı | 4 doğrulama sağlandı (27 ms) | PASS | — |
| T-062 | JsonTicketRepositoryTest | `test_save_if_status_is_compare_and_set` | Durum değişmişse kayıt yapılmaz (karşılaştır-yaz) | 4 doğrulama sağlandı (9 ms) | PASS | — |
| T-063 | JsonTicketRepositoryTest | `test_save_if_status_also_requires_same_agent` | Personel değişmişse kayıt yapılmaz | 3 doğrulama sağlandı (16 ms) | PASS | — |
| T-064 | JsonTicketRepositoryTest | `test_assign_if_unassigned_rejects_cancelled_or_missing` | İptal edilmiş ya da olmayan talep atanmaz | 2 doğrulama sağlandı (6 ms) | PASS | — |
| T-065 | JsonUserRepositoryTest | `test_save_then_find_by_customer_no` | Kullanıcı numarasıyla bulunur | 6 doğrulama sağlandı (101 ms) | PASS | — |
| T-066 | JsonUserRepositoryTest | `test_save_existing_updates` | Var olan kullanıcı güncellenir | 2 doğrulama sağlandı (107 ms) | PASS | — |
| T-067 | JsonUserRepositoryTest | `test_find_by_role` | Kullanıcılar role göre listelenir | 1 doğrulama sağlandı (277 ms) | PASS | — |
| T-068 | JsonUserRepositoryTest | `test_record_login_writes_only_last_login` | Girişte yalnız son giriş zamanı yazılır; aynı anda yapılan pasifleştirme geri alınmaz. | 4 doğrulama sağlandı (121 ms) | PASS | — |
| T-069 | AssignmentServiceTest | `test_picks_least_loaded` | ASN-001: Doğru personel seçilmeli; ASN-020: Atama havuzunda bulunmalı | 1 doğrulama sağlandı (75 ms) | PASS | — |
| T-070 | AssignmentServiceTest | `test_tie_picks_lowest_id` | ASN-002: Tanımlı eşitlik bozma kuralı uygulanmalı | 1 doğrulama sağlandı (22 ms) | PASS | — |
| T-071 | AssignmentServiceTest | `test_agent_without_tickets_counts_zero` | Talebi olmayan personelin iş yükü sıfır sayılır | 1 doğrulama sağlandı (12 ms) | PASS | — |
| T-072 | AssignmentServiceTest | `test_no_agents_returns_null` | ASN-003: Talep havuzda kalmalı | 1 doğrulama sağlandı (6 ms) | PASS | — |
| T-073 | AssignmentServiceTest | `test_resolved_and_closed_do_not_count` | ASN-016: Aktif iş yüküne dahil edilmemeli | 1 doğrulama sağlandı (117 ms) | PASS | — |
| T-074 | AssignmentServiceTest | `test_inactive_and_full_agents_are_skipped` | ASN-005: Atanmamalı; ASN-021: Atama havuzunda bulunmamalı | 3 doğrulama sağlandı (57 ms) | PASS | — |
| T-075 | AssignmentServiceTest | `test_all_full_leaves_ticket_in_pool` | Herkes doluysa talep havuzda kalır | 3 doğrulama sağlandı (30 ms) | PASS | — |
| T-076 | AssignmentServiceTest | `test_hierarchy_prefers_lowest_level_then_next_level_when_full` | ASN-010: İlgili kategoriye atanmamalı | 6 doğrulama sağlandı (56 ms) | PASS | — |
| T-077 | AssignmentServiceTest | `test_above_level_skips_failing_level_and_lower` | Eskalasyonda aynı ve alt seviyeler atlanır | 6 doğrulama sağlandı (41 ms) | PASS | — |
| T-078 | AssignmentServiceTest | `test_agent_on_leave_is_skipped` | ASN-004: Atamaya dahil edilmemeli | 2 doğrulama sağlandı (17 ms) | PASS | — |
| T-079 | AttachmentServiceTest | `test_accepts_jpg_png_pdf` | ATT-001: Kabul edilmeli; ATT-002: Kabul edilmeli | 4 doğrulama sağlandı (57 ms) | PASS | — |
| T-080 | AttachmentServiceTest | `test_rejects_php_disguised_as_jpg` | ATT-006: Reddedilmeli; ATT-016: İçerikle uyumlu olmalı; ATT-017: Reddedilmeli | 2 doğrulama sağlandı (16 ms) | PASS | — |
| T-081 | AttachmentServiceTest | `test_rejects_oversized_image` | ATT-007: Reddedilmeli | 2 doğrulama sağlandı (7 ms) | PASS | — |
| T-082 | AttachmentServiceTest | `test_stored_name_is_random_and_has_correct_extension` | ATT-009: Güvenli isim üretilmeli; ATT-010: Üzerine yazılmamalı | 3 doğrulama sağlandı (18 ms) | PASS | — |
| T-083 | AttachmentServiceTest | `test_large_png_is_downscaled_to_1920x1080` | ATT-011: Maksimum 1920x1080 | 6 doğrulama sağlandı (357 ms) | PASS | — |
| T-084 | AttachmentServiceTest | `test_rejects_image_with_too_many_pixels_before_decoding` | Çok büyük çözünürlüklü görsel açılmadan reddedilir | 1 doğrulama sağlandı (14 ms) | PASS | — |
| T-085 | AttachmentServiceTest | `test_png_has_lower_pixel_limit_than_jpeg` | PNG için piksel sınırı JPEG'den düşüktür | 2 doğrulama sağlandı (26 ms) | PASS | — |
| T-086 | AttachmentServiceTest | `test_accepts_mp4_and_webm_video` | ATT-003: Kabul edilmeli; ATT-004: Kabul edilmeli | 2 doğrulama sağlandı (32 ms) | PASS | — |
| T-087 | AttachmentServiceTest | `test_video_over_video_limit_rejected` | ATT-007: Reddedilmeli | 2 doğrulama sağlandı (16 ms) | PASS | — |
| T-088 | AttachmentServiceTest | `test_php_ini_size_error_says_file_is_too_big` | ATT-007: Reddedilmeli | 2 doğrulama sağlandı (4 ms) | PASS | — |
| T-089 | AttachmentServiceTest | `test_upload_error_code_message` | Yükleme hata kodu anlaşılır mesaja çevrilir | 2 doğrulama sağlandı (4 ms) | PASS | — |
| T-090 | AttachmentServiceTest | `test_validate_many_checks_count_and_skips_empty_slots` | ATT-008: Reddedilmeli | 3 doğrulama sağlandı (10 ms) | PASS | — |
| T-091 | AttachmentServiceTest | `test_normalize_files_array` | Çoklu dosya dizisi tek tek dosyalara ayrılır | 3 doğrulama sağlandı (3 ms) | PASS | — |
| T-092 | AttachmentServiceTest | `test_rejects_exe` | ATT-005: Reddedilmeli | 1 doğrulama sağlandı (5 ms) | PASS | — |
| T-093 | AttachmentServiceTest | `test_uppercase_extension_is_accepted_by_content` | ATT-014: Tanımlı kurala göre kabul | 2 doğrulama sağlandı (13 ms) | PASS | — |
| T-094 | AttachmentServiceTest | `test_double_extension_is_stored_safely_or_rejected` | ATT-015: Reddedilmeli | 3 doğrulama sağlandı (19 ms) | PASS | — |
| T-095 | AttachmentServiceTest | `test_corrupt_image_is_rejected` | ATT-018: Reddedilmeli | 1 doğrulama sağlandı (16 ms) | PASS | — |
| T-096 | AttachmentServiceTest | `test_fake_mp4_is_rejected` | ATT-019: Reddedilmeli | 1 doğrulama sağlandı (15 ms) | PASS | — |
| T-097 | AttachmentServiceTest | `test_downscale_finishes_within_two_seconds` | PERF-008: ≤ 2 saniye | 1 doğrulama sağlandı (267 ms) | PASS | — |
| T-098 | AttachmentServiceTest | `test_large_video_is_stored_quickly` | PERF-009: ≤ 1600 ms | 3 doğrulama sağlandı (27 ms) | PASS | — |
| T-099 | AuditLoggerTest | `test_record_stores_actor_time_and_values` | SEC-014: Log oluşturulmalı | 8 doğrulama sağlandı (12 ms) | PASS | — |
| T-100 | AuditLoggerTest | `test_latest_returns_newest_first_and_limits` | Son denetim kayıtları yeniden eskiye ve sınırlı sayıda döner | 3 doğrulama sağlandı (31 ms) | PASS | — |
| T-101 | AuditLoggerTest | `test_repository_has_no_update_or_delete` | SEC-015: Yetkisiz değişiklik engellenmeli | 6 doğrulama sağlandı (2 ms) | PASS | — |
| T-102 | AuthServiceTest | `test_register_creates_customer_with_M_number` | AUTH-010: Kullanıcı doğru kaydedilmeli | 5 doğrulama sağlandı (135 ms) | PASS | — |
| T-103 | AuthServiceTest | `test_register_validation_messages` | Kayıtta hatalı alanlar için doğru mesajlar | 5 doğrulama sağlandı (2 ms) | PASS | — |
| T-104 | AuthServiceTest | `test_login_success_and_wrong_password` | AUTH-003: Giriş reddedilmeli; AUTH-005: Giriş reddedilmeli | 3 doğrulama sağlandı (186 ms) | PASS | — |
| T-105 | AuthServiceTest | `test_login_accepts_lowercase_and_spaces` | AUTH-005: Giriş reddedilmeli | 1 doğrulama sağlandı (146 ms) | PASS | — |
| T-106 | AuthServiceTest | `test_secret_question_lookup` | Numaraya göre gizli soru bulunur | 2 doğrulama sağlandı (95 ms) | PASS | — |
| T-107 | AuthServiceTest | `test_reset_password_with_answer_case_insensitive` | AUTH-014: Büyük/küçük harf ve boşluk kuralları uygulanmalı; AUTH-033: Düz metin saklanmamalı; AUTH-034: Doğrulama başarılı | 3 doğrulama sağlandı (280 ms) | PASS | — |
| T-108 | AuthServiceTest | `test_reset_password_turkish_uppercase_answer` | AUTH-014: Büyük/küçük harf ve boşluk kuralları uygulanmalı | 1 doğrulama sağlandı (196 ms) | PASS | — |
| T-109 | AuthServiceTest | `test_reset_password_ascii_i_answer` | AUTH-014: Büyük/küçük harf ve boşluk kuralları uygulanmalı | 2 doğrulama sağlandı (292 ms) | PASS | — |
| T-110 | AuthServiceTest | `test_reset_password_wrong_answer_returns_false` | AUTH-009: İşlem reddedilmeli; AUTH-035: Doğrulama başarısız | 2 doğrulama sağlandı (184 ms) | PASS | — |
| T-111 | AuthServiceTest | `test_reset_password_short_new_password_rejected` | Kısa yeni şifre reddedilir | 1 doğrulama sağlandı (99 ms) | PASS | — |
| T-112 | BrandingServiceTest | `test_rejects_fake_png_and_large_file_and_keeps_old_logo` | ATT-006: Reddedilmeli; ATT-016: İçerikle uyumlu olmalı; ATT-017: Reddedilmeli | 5 doğrulama sağlandı (56 ms) | PASS | — |
| T-113 | BrandingServiceTest | `test_missing_file_is_reported` | Seçilmeyen logo dosyası bildirilir | 2 doğrulama sağlandı (3 ms) | PASS | — |
| T-114 | BrandingServiceTest | `test_new_logo_replaces_old_file` | ADM-007: Logo doğru görüntülenmeli | 5 doğrulama sağlandı (49 ms) | PASS | — |
| T-115 | CalendarServiceTest | `test_reads_same_intervals_as_file_calendar` | Tablodan okunan takvim dosyadakiyle aynı aralıkları verir | 5 doğrulama sağlandı (33 ms) | PASS | — |
| T-116 | CalendarServiceTest | `test_empty_tables_fall_back_to_default_and_log` | Takvim tabloları boşsa varsayılana geçilir ve kayıt düşülür | 3 doğrulama sağlandı (2 ms) | PASS | — |
| T-117 | CalendarServiceTest | `test_empty_tables_use_legacy_file_until_admin_saves` | Yönetici kaydedene kadar eski takvim dosyası kullanılır | 2 doğrulama sağlandı (3 ms) | PASS | — |
| T-118 | CalendarServiceTest | `test_save_rejects_calendar_without_working_days_and_keeps_old` | ADM-025: Hata üretilmeli | 2 doğrulama sağlandı (27 ms) | PASS | — |
| T-119 | CalendarServiceTest | `test_save_rejects_break_outside_day` | Mesai dışı öğle arası kaydedilmez | 1 doğrulama sağlandı (3 ms) | PASS | — |
| T-120 | CalendarServiceTest | `test_saved_hours_change_sla` | Kaydedilen mesai saatleri SLA hesabını değiştirir | 1 doğrulama sağlandı (40 ms) | PASS | — |
| T-121 | CalendarServiceTest | `test_holiday_add_remove_and_affects_sla` | SLA-004: Tatil hesaba katılmamalı | 3 doğrulama sağlandı (50 ms) | PASS | — |
| T-122 | CalendarServiceTest | `test_duplicate_or_invalid_holiday_rejected` | Tekrarlanan ya da geçersiz tatil reddedilir | 3 doğrulama sağlandı (15 ms) | PASS | — |
| T-123 | CalendarServiceTest | `test_screen_shows_legacy_calendar_until_first_save` | İlk kayda kadar ekranda eski takvim gösterilir | 4 doğrulama sağlandı (4 ms) | PASS | — |
| T-124 | CalendarServiceTest | `test_holiday_added_before_first_save_keeps_legacy_calendar` | İlk kayıttan önce eklenen tatil eski takvimi korur | 4 doğrulama sağlandı (38 ms) | PASS | — |
| T-125 | CalendarServiceTest | `test_half_filled_break_is_rejected` | Yarım doldurulmuş öğle arası reddedilir | 2 doğrulama sağlandı (29 ms) | PASS | — |
| T-126 | CatalogServiceTest | `test_inactive_category_hidden_from_form_and_rejected_on_create` | TKT-038: Belirlenen kurala göre engellenmeli; ADM-014: Veri bütünlüğü korunmalı | 3 doğrulama sağlandı (112 ms) | PASS | — |
| T-127 | CatalogServiceTest | `test_cannot_deactivate_last_active_category` | ADM-014: Veri bütünlüğü korunmalı | 2 doğrulama sağlandı (38 ms) | PASS | — |
| T-128 | CatalogServiceTest | `test_category_name_is_required_and_unique` | TKT-037: Kategori kaydedilmeli; ADM-013: Güncellenmeli | 3 doğrulama sağlandı (60 ms) | PASS | — |
| T-129 | CatalogServiceTest | `test_set_sla_overrides_default_for_new_tickets_only` | SLA-021: Doğru kural uygulanmalı; ADM-016: Yeni kural uygulanmalı | 4 doğrulama sağlandı (82 ms) | PASS | — |
| T-130 | CatalogServiceTest | `test_clearing_sla_falls_back_to_priority_default` | SLA-022: Tanımlı varsayılan davranış | 2 doğrulama sağlandı (64 ms) | PASS | — |
| T-131 | CatalogServiceTest | `test_sla_hours_out_of_range_rejected` | Aralık dışı SLA süresi reddedilir | 2 doğrulama sağlandı (33 ms) | PASS | — |
| T-132 | DetailedReportServiceTest | `test_sla_compliance_uses_first_deadline` | RPT-007: Doğru hesaplanmalı; RPT-010: SLA geçmişi doğru | 2 doğrulama sağlandı (18 ms) | PASS | — |
| T-133 | PasswordPolicyTest | `test_policy with data set "örnek veri şifresi"` | Şifre kuralı (en az 8 karakter, harf, rakam, noktalama) doğru uygulanır (vaka: örnek veri şifresi) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-134 | PasswordPolicyTest | `test_policy with data set "Türkçe harfli"` | Şifre kuralı (en az 8 karakter, harf, rakam, noktalama) doğru uygulanır (vaka: Türkçe harfli) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-135 | PasswordPolicyTest | `test_policy with data set "tam 8 karakter"` | Şifre kuralı (en az 8 karakter, harf, rakam, noktalama) doğru uygulanır (vaka: tam 8 karakter) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-136 | PasswordPolicyTest | `test_policy with data set "noktalama yok"` | Şifre kuralı (en az 8 karakter, harf, rakam, noktalama) doğru uygulanır (vaka: noktalama yok) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-137 | PasswordPolicyTest | `test_policy with data set "rakam yok"` | Şifre kuralı (en az 8 karakter, harf, rakam, noktalama) doğru uygulanır (vaka: rakam yok) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-138 | PasswordPolicyTest | `test_policy with data set "harf yok"` | Şifre kuralı (en az 8 karakter, harf, rakam, noktalama) doğru uygulanır (vaka: harf yok) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-139 | PasswordPolicyTest | `test_policy with data set "kısa"` | Şifre kuralı (en az 8 karakter, harf, rakam, noktalama) doğru uygulanır (vaka: kısa) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-140 | PasswordPolicyTest | `test_policy with data set "boşluk noktalama sayılmaz"` | Şifre kuralı (en az 8 karakter, harf, rakam, noktalama) doğru uygulanır (vaka: boşluk noktalama sayılmaz) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-141 | PasswordPolicyTest | `test_message_text` | Şifre kuralı mesajı doğru | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-142 | PriorityDetectorTest | `test_detects_urgent_keyword` | TKT-034: Belirlenen öncelik tespit edilmeli | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-143 | PriorityDetectorTest | `test_turkish_uppercase_is_detected` | TKT-035: Tespit tutarlı olmalı | 2 doğrulama sağlandı (0 ms) | PASS | — |
| T-144 | PriorityDetectorTest | `test_dotless_capital_i_is_detected` | TKT-035: Tespit tutarlı olmalı | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-145 | PriorityDetectorTest | `test_highest_level_wins` | TKT-034: Belirlenen öncelik tespit edilmeli | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-146 | PriorityDetectorTest | `test_effective_never_lowers` | Anahtar kelime seçilen önceliği düşürmez | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-147 | PriorityDetectorTest | `test_effective_raises` | Anahtar kelime önceliği yükseltir | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-148 | PriorityDetectorTest | `test_no_keyword_returns_null_and_keeps_choice` | TKT-036: Varsayılan öncelik uygulanmalı | 2 doğrulama sağlandı (0 ms) | PASS | — |
| T-149 | PriorityDetectorTest | `test_inactive_priorities_and_empty_list_detect_nothing` | Pasif öncelik ve boş listede tespit yapılmaz | 2 doğrulama sağlandı (0 ms) | PASS | — |
| T-150 | PriorityServiceTest | `test_defaults_are_five_levels_with_stable_ids` | TKT-032: Tanımlı öncelikler dönmeli | 4 doğrulama sağlandı (21 ms) | PASS | — |
| T-151 | PriorityServiceTest | `test_create_validates_and_records_audit` | ADM-010: Kaydedilmeli | 5 doğrulama sağlandı (37 ms) | PASS | — |
| T-152 | PriorityServiceTest | `test_name_and_rank_are_unique_case_and_turkish_insensitive` | Öncelik adı ve sırası büyük/küçük harf ve Türkçe karakterden bağımsız olarak benzersiz | 3 doğrulama sağlandı (18 ms) | PASS | — |
| T-153 | PriorityServiceTest | `test_cannot_deactivate_last_active_priority` | ADM-011: Bağımlılık kontrolü yapılmalı | 6 doğrulama sağlandı (62 ms) | PASS | — |
| T-154 | PriorityServiceTest | `test_keywords_are_parsed_from_comma_text` | Virgülle yazılan anahtar kelimeler ayrıştırılır | 1 doğrulama sağlandı (29 ms) | PASS | — |
| T-155 | ReportServiceTest | `test_summary_counts_and_average` | TKT-046: Sayısal değerler doğru olmalı; ADM-002: Sayısal değerler doğru; RPT-001: Sayılar doğru … | 9 doğrulama sağlandı (109 ms) | PASS | — |
| T-156 | ReportServiceTest | `test_summary_with_no_resolved_has_null_average` | RPT-001: Sayılar doğru; RPT-002: Sayılar doğru; RPT-003: Sayılar doğru … | 3 doğrulama sağlandı (35 ms) | PASS | — |
| T-157 | SettingsServiceTest | `test_unknown_key_falls_back_to_default` | ADM-025: Hata üretilmeli | 2 doğrulama sağlandı (4 ms) | PASS | — |
| T-158 | SettingsServiceTest | `test_update_persists_and_returns_typed_values` | ADM-003: Kaydedilmeli; ADM-004: Güncellenmeli; ADM-024: Kabul edilmeli | 2 doğrulama sağlandı (32 ms) | PASS | — |
| T-159 | SettingsServiceTest | `test_invalid_value_writes_nothing` | ADM-005: Reddedilmeli | 3 doğrulama sağlandı (3 ms) | PASS | — |
| T-160 | SettingsServiceTest | `test_capacity_must_be_whole_number_in_range` | ASN-013: Kapasite kaydedilmeli | 4 doğrulama sağlandı (2 ms) | PASS | — |
| T-161 | SettingsServiceTest | `test_update_records_old_and_new_value` | SEC-016: Eski/yeni değer kaydedilmeli | 4 doğrulama sağlandı (14 ms) | PASS | — |
| T-162 | SettingsServiceTest | `test_unknown_key_is_rejected` | ADM-005: Reddedilmeli | 1 doğrulama sağlandı (2 ms) | PASS | — |
| T-163 | SlaCalculatorServiceTest | `test_deadline_matches_case_table with data set "mola_oncesi"` | SLA-001: SLA doğru hesaplanmalı; SLA-002: Sonraki çalışma aralığı esas alınmalı; SLA-003: Çalışma takvimi uygulanmalı … (vaka: mola oncesi) | 1 doğrulama sağlandı (1 ms) | PASS | — |
| T-164 | SlaCalculatorServiceTest | `test_deadline_matches_case_table with data set "ogle_arasi_atlanir"` | SLA-001: SLA doğru hesaplanmalı; SLA-002: Sonraki çalışma aralığı esas alınmalı; SLA-003: Çalışma takvimi uygulanmalı … (vaka: ogle arasi atlanir) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-165 | SlaCalculatorServiceTest | `test_deadline_matches_case_table with data set "molada_acilan"` | SLA-001: SLA doğru hesaplanmalı; SLA-002: Sonraki çalışma aralığı esas alınmalı; SLA-003: Çalışma takvimi uygulanmalı … (vaka: molada acilan) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-166 | SlaCalculatorServiceTest | `test_deadline_matches_case_table with data set "gun_sonu_devri"` | SLA-001: SLA doğru hesaplanmalı; SLA-002: Sonraki çalışma aralığı esas alınmalı; SLA-003: Çalışma takvimi uygulanmalı … (vaka: gun sonu devri) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-167 | SlaCalculatorServiceTest | `test_deadline_matches_case_table with data set "cuma_1730_hocanin_ornegi"` | SLA-001: SLA doğru hesaplanmalı; SLA-002: Sonraki çalışma aralığı esas alınmalı; SLA-003: Çalışma takvimi uygulanmalı … (vaka: cuma 1730 hocanin ornegi) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-168 | SlaCalculatorServiceTest | `test_deadline_matches_case_table with data set "hafta_sonu_acilan"` | SLA-001: SLA doğru hesaplanmalı; SLA-002: Sonraki çalışma aralığı esas alınmalı; SLA-003: Çalışma takvimi uygulanmalı … (vaka: hafta sonu acilan) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-169 | SlaCalculatorServiceTest | `test_deadline_matches_case_table with data set "mesai_sonrasi_acilan"` | SLA-001: SLA doğru hesaplanmalı; SLA-002: Sonraki çalışma aralığı esas alınmalı; SLA-003: Çalışma takvimi uygulanmalı … (vaka: mesai sonrasi acilan) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-170 | SlaCalculatorServiceTest | `test_deadline_matches_case_table with data set "tatil_oncesi_gun"` | SLA-001: SLA doğru hesaplanmalı; SLA-002: Sonraki çalışma aralığı esas alınmalı; SLA-003: Çalışma takvimi uygulanmalı … (vaka: tatil oncesi gun) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-171 | SlaCalculatorServiceTest | `test_deadline_matches_case_table with data set "tatilde_acilan"` | SLA-001: SLA doğru hesaplanmalı; SLA-002: Sonraki çalışma aralığı esas alınmalı; SLA-003: Çalışma takvimi uygulanmalı … (vaka: tatilde acilan) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-172 | SlaCalculatorServiceTest | `test_deadline_matches_case_table with data set "tam_gun_mesai_sonunda_biter"` | SLA-001: SLA doğru hesaplanmalı; SLA-002: Sonraki çalışma aralığı esas alınmalı; SLA-003: Çalışma takvimi uygulanmalı … (vaka: tam gun mesai sonunda biter) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-173 | SlaCalculatorServiceTest | `test_deadline_matches_case_table with data set "cok_gunlu"` | SLA-001: SLA doğru hesaplanmalı; SLA-002: Sonraki çalışma aralığı esas alınmalı; SLA-003: Çalışma takvimi uygulanmalı … (vaka: cok gunlu) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-174 | SlaCalculatorServiceTest | `test_deadline_matches_case_table with data set "mesai_oncesi_acilan"` | SLA-001: SLA doğru hesaplanmalı; SLA-002: Sonraki çalışma aralığı esas alınmalı; SLA-003: Çalışma takvimi uygulanmalı … (vaka: mesai oncesi acilan) | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-175 | SlaCalculatorServiceTest | `test_zero_duration_outside_hours_moves_to_next_working_moment` | SLA-002: Sonraki çalışma aralığı esas alınmalı | 2 doğrulama sağlandı (1 ms) | PASS | — |
| T-176 | SlaCalculatorServiceTest | `test_corrupt_calendar_uses_default_hours` | SLA-011: Varsayılan takvime geçilmeli | 1 doğrulama sağlandı (1 ms) | PASS | — |
| T-177 | SlaCalculatorServiceTest | `test_business_minutes_between_skips_break_and_weekend` | İki an arası iş dakikası öğle arası ve hafta sonunu atlar | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-178 | SlaCalculatorServiceTest | `test_business_minutes_reverse_order_is_zero` | Ters sıralı anlarda iş dakikası sıfır | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-179 | SlaCalculatorServiceTest | `test_calendar_without_workdays_throws` | Çalışma günü olmayan takvimde hata verilir | 1 doğrulama sağlandı (3 ms) | PASS | — |
| T-180 | SlaCalculatorServiceTest | `test_five_years_of_holidays_is_fast` | SLA-005: Süreler doğru atlanmalı; SLA-012: Sonuç doğru ve 500 ms altında olmalı; PERF-001: ≤ 500 ms | 1 doğrulama sağlandı (18 ms) | PASS | — |
| T-181 | SlaEdgeCasesTest | `test_year_change_skips_new_year_holiday` | SLA-005: Süreler doğru atlanmalı; SLA-008: Takvim doğru ilerlemeli | 1 doğrulama sağlandı (4 ms) | PASS | — |
| T-182 | SlaEdgeCasesTest | `test_leap_day_is_a_normal_working_day` | SLA-009: 29 Şubat doğru işlenmeli | 1 doğrulama sağlandı (3 ms) | PASS | — |
| T-183 | SlaEdgeCasesTest | `test_utc_input_gives_same_moment_as_local_input` | SLA-010: UTC / yerel saat tutarlı olmalı | 2 doğrulama sağlandı (4 ms) | PASS | — |
| T-184 | SlaEdgeCasesTest | `test_job_lock_allows_one_run_per_interval_and_expires` | SEC-033: Çakışma önlenmeli; SEC-034: Kilit kontrollü serbest bırakılmalı | 3 doğrulama sağlandı (15 ms) | PASS | — |
| T-185 | SlaStatusServiceTest | `test_normal_below_80_percent` | SLA-013: Başlangıç durumu doğru olmalı; SLA-014: Normal durum | 5 doğrulama sağlandı (1 ms) | PASS | — |
| T-186 | SlaStatusServiceTest | `test_warning_at_exactly_80_percent` | SLA-015: Sarı uyarı; SLA-016: Sarı uyarı | 4 doğrulama sağlandı (0 ms) | PASS | — |
| T-187 | SlaStatusServiceTest | `test_just_below_80_percent_is_normal` | SLA-013: Başlangıç durumu doğru olmalı; SLA-014: Normal durum | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-188 | SlaStatusServiceTest | `test_breached_after_deadline` | SLA-018: İhlal durumu korunmalı | 6 doğrulama sağlandı (1 ms) | PASS | — |
| T-189 | SlaStatusServiceTest | `test_breached_exactly_at_deadline` | SLA-017: Kırmızı ihlal | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-190 | SlaStatusServiceTest | `test_resolved_in_time_is_met` | Süresinde çözülen talep 'uyuldu' görünür | 2 doğrulama sağlandı (0 ms) | PASS | — |
| T-191 | SlaStatusServiceTest | `test_resolved_late_is_missed` | Geç çözülen talep 'aşıldı' görünür | 2 doğrulama sağlandı (0 ms) | PASS | — |
| T-192 | SlaStatusServiceTest | `test_hidden_status_shows_none` | SLA gösterilmeyen durumda gösterge yok | 3 doğrulama sağlandı (0 ms) | PASS | — |
| T-193 | SlaStatusServiceTest | `test_remaining_excludes_weekend` | Kalan süre hafta sonunu saymaz | 2 doğrulama sağlandı (0 ms) | PASS | — |
| T-194 | SlaStatusServiceTest | `test_cancelled_ticket_is_none` | İptal edilen talepte SLA göstergesi yok | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-195 | SlaStatusServiceTest | `test_closed_resolved_ticket_shows_met` | Kapanan ve süresinde çözülen talep 'uyuldu' görünür | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-196 | SlaStatusServiceTest | `test_open_ticket_uses_clock` | Açık talepte kalan süre şimdiki saate göre hesaplanır | 1 doğrulama sağlandı (0 ms) | PASS | — |
| T-197 | SlaStatusServiceTest | `test_format_minutes` | Dakikalar okunur biçimde yazılır | 4 doğrulama sağlandı (0 ms) | PASS | — |
| T-198 | StaffServiceTest | `test_create_staff_gets_P_number_and_policy` | AUTH-012: Personel rolü doğru atanmalı | 5 doğrulama sağlandı (196 ms) | PASS | — |
| T-199 | StaffServiceTest | `test_capacity_validation` | ASN-013: Kapasite kaydedilmeli | 6 doğrulama sağlandı (217 ms) | PASS | — |
| T-200 | StaffServiceTest | `test_parse_capacity_from_form` | Formdaki kapasite değeri doğru ayrıştırılır | 5 doğrulama sağlandı (97 ms) | PASS | — |
| T-201 | StaffServiceTest | `test_deactivate_and_activate_agent` | AUTH-041: Kullanıcı yeniden erişebilmeli; ASN-024: Pasifleştirilmeli; ADM-018: Durum değişmeli | 4 doğrulama sağlandı (221 ms) | PASS | — |
| T-202 | StaffServiceTest | `test_cannot_deactivate_admin_or_self` | Yönetici ve kişinin kendisi pasif yapılamaz | 1 doğrulama sağlandı (100 ms) | PASS | — |
| T-203 | TicketServiceTest | `test_create_computes_deadline_with_policy` | TKT-008: Kayıt doğru oluşmalı; SLA-019: Başlangıç tarihi kaydedilmeli; SLA-020: Bitiş tarihi kaydedilmeli | 2 doğrulama sağlandı (88 ms) | PASS | — |
| T-204 | TicketServiceTest | `test_create_without_policy_uses_priority_default` | SLA-022: Tanımlı varsayılan davranış | 1 doğrulama sağlandı (52 ms) | PASS | — |
| T-205 | TicketServiceTest | `test_create_uses_keyword_priority` | Anahtar kelime talep önceliğini yükseltir | 3 doğrulama sağlandı (50 ms) | PASS | — |
| T-206 | TicketServiceTest | `test_description_is_saved_as_first_reply` | TKT-012: Yanıt geçmişe eklenmeli | 3 doğrulama sağlandı (52 ms) | PASS | — |
| T-207 | TicketServiceTest | `test_create_auto_assigns_least_loaded_and_logs` | TKT-009: Atama mekanizması çalışmalı | 4 doğrulama sağlandı (150 ms) | PASS | — |
| T-208 | TicketServiceTest | `test_create_without_agents_stays_in_pool` | ASN-003: Talep havuzda kalmalı | 3 doğrulama sağlandı (61 ms) | PASS | — |
| T-209 | TicketServiceTest | `test_create_validation_errors` | Hatalı talep formu doğru hatalarla reddedilir | 5 doğrulama sağlandı (39 ms) | PASS | — |
| T-210 | TicketServiceTest | `test_agent_cannot_create_ticket` | Personel talep açamaz | 1 doğrulama sağlandı (45 ms) | PASS | — |
| T-211 | TicketServiceTest | `test_take_twice_second_conflicts` | Aynı talebi ikinci alma çakışma hatası verir | 3 doğrulama sağlandı (82 ms) | PASS | — |
| T-212 | TicketServiceTest | `test_take_missing_ticket_is_not_found` | Olmayan talep alınamaz (bulunamadı) | 1 doğrulama sağlandı (44 ms) | PASS | — |
| T-213 | TicketServiceTest | `test_customer_cannot_resolve` | RES-002: Engellenmeli | 1 doğrulama sağlandı (77 ms) | PASS | — |
| T-214 | TicketServiceTest | `test_other_agent_cannot_start_review` | RES-002: Engellenmeli | 1 doğrulama sağlandı (69 ms) | PASS | — |
| T-215 | TicketServiceTest | `test_full_flow_resolve_sets_resolved_at_and_approve_closes` | TKT-027: Geçiş başarılı; TKT-028: Geçiş başarılı; RES-001: Durum güncellenmeli | 4 doğrulama sağlandı (96 ms) | PASS | — |
| T-216 | TicketServiceTest | `test_reject_increments_attempt_and_returns_to_review` | RES-004: Ret kaydedilmeli | 3 doğrulama sağlandı (94 ms) | PASS | — |
| T-217 | TicketServiceTest | `test_customer_cancels_pending_but_not_others_ticket` | TKT-018: Engellenmeli; TKT-019: Engellenmeli | 1 doğrulama sağlandı (70 ms) | PASS | — |
| T-218 | TicketServiceTest | `test_unknown_action_is_rejected` | Bilinmeyen işlem reddedilir | 1 doğrulama sağlandı (51 ms) | PASS | — |
| T-219 | TicketServiceTest | `test_move_on_kanban` | TKT-013: Durum güncellenmeli | 3 doğrulama sağlandı (82 ms) | PASS | — |
| T-220 | TicketServiceTest | `test_reply_rules` | TKT-006: Yanıt kaydedilmeli; TKT-012: Yanıt geçmişe eklenmeli; SLA-035: İzin verilmeli | 3 doğrulama sağlandı (102 ms) | PASS | — |
| T-221 | TicketServiceTest | `test_find_visible_rules` | TKT-014: Sadece yetkili kullanıcı görmeli | 4 doğrulama sağlandı (107 ms) | PASS | — |
| T-222 | TicketServiceTest | `test_search_filters_by_status_and_text_and_paginates` | TKT-015: İlgili kayıtlar bulunmalı | 8 doğrulama sağlandı (388 ms) | PASS | — |
| T-223 | TicketServiceTest | `test_search_folds_turkish_and_ascii_i` | TKT-015: İlgili kayıtlar bulunmalı | 3 doğrulama sağlandı (68 ms) | PASS | — |
| T-224 | TicketServiceTest | `test_perform_on_stale_ticket_conflicts_instead_of_overwriting` | Bu arada değişen talepte eski veri yazılmaz, çakışma bildirilir | 3 doğrulama sağlandı (60 ms) | PASS | — |
| T-225 | TicketServiceTest | `test_search_all_filters_for_admin` | TKT-016: Filtrelerin kesişimi doğru olmalı | 6 doğrulama sağlandı (129 ms) | PASS | — |
| T-226 | TicketServiceTest | `test_observer_receives_events` | TKT-040: Log oluşturulmalı | 1 doğrulama sağlandı (71 ms) | PASS | — |
| T-227 | TicketServiceTest | `test_resolving_a_ticket_pulls_oldest_pool_ticket` | Talep çözülünce havuzdaki en eski talep personele atanır | 7 doğrulama sağlandı (136 ms) | PASS | — |
| T-228 | TicketServiceTest | `test_activating_agent_fills_pool` | Personel aktif olunca havuzdaki talepler atanır | 4 doğrulama sağlandı (65 ms) | PASS | — |
| T-229 | TicketServiceTest | `test_take_respects_capacity` | ASN-014: Engellenmeli veya istisna uygulanmalı | 2 doğrulama sağlandı (79 ms) | PASS | — |
| T-230 | TicketServiceTest | `test_agent_cannot_reply_to_breached_or_resolved` | SLA-036: Tanımlı kurala göre engellenmeli | 8 doğrulama sağlandı (107 ms) | PASS | — |
| T-231 | TicketServiceTest | `test_created_ticket_gets_unique_code_and_is_found_by_it` | TKT-020: Benzersiz kod üretilmeli; TKT-021: Kod çakışmamalı | 6 doğrulama sağlandı (62 ms) | PASS | — |
| T-232 | TicketServiceTest | `test_assignment_and_reply_are_recorded_as_activity` | TKT-041: Olay kaydedilmeli | 1 doğrulama sağlandı (101 ms) | PASS | — |
| T-233 | AdminPagesTest | `test_admin_pages_render` | FRM-012: Yönetici erişebilmeli; ADM-002: Sayısal değerler doğru; SEC-001: İzin verilmeli | 10 doğrulama sağlandı (834 ms) | PASS | — |
| T-234 | AdminPagesTest | `test_admin_list_filters` | TKT-045: Yetkili tüm kayıtları görebilmeli; ADM-001: Tüm yetkili kayıtlar | 6 doğrulama sağlandı (721 ms) | PASS | — |
| T-235 | AdminPagesTest | `test_agent_and_customer_cannot_open_admin_pages` | SEC-002: Engellenmeli | 5 doğrulama sağlandı (865 ms) | PASS | — |
| T-236 | AdvancedAdminTest | `test_report_with_filters_and_per_agent_stats` | RPT-005: Sayılar doğru; RPT-008: Kayıtlar doğru; RPT-013: Doğru kayıtlar | 8 doğrulama sağlandı (817 ms) | PASS | — |
| T-237 | AdvancedAdminTest | `test_csv_export_uses_same_filters` | RPT-016: Dosya oluşturulmalı; RPT-017: Karakterler korunmalı | 9 doğrulama sağlandı (732 ms) | PASS | — |
| T-238 | AdvancedAdminTest | `test_csv_cells_are_protected_against_formula_injection` | RPT-018: Formül etkisizleştirilmeli; RPT-019: Güvenli hale getirilmeli | 3 doğrulama sağlandı (680 ms) | PASS | — |
| T-239 | AdvancedAdminTest | `test_category_extra_field_is_required_shown_and_stored` | FRM-001: Form kaydedilmeli; FRM-004: Alan eklenmeli; FRM-008: Doğru tür gösterilmeli … | 10 doğrulama sağlandı (799 ms) | PASS | — |
| T-240 | AdvancedAdminTest | `test_backup_download_is_zip_and_admin_only` | SEC-028: Yedek oluşmalı | 11 doğrulama sağlandı (810 ms) | PASS | — |
| T-241 | AttachmentAccessTest | `test_authorized_download_is_under_one_second` | PERF-004: < 1 saniye | 3 doğrulama sağlandı (405 ms) | PASS | — |
| T-242 | AttachmentAccessTest | `test_owner_downloads_with_safe_headers` | ATT-012: İndirme başarılı | 6 doğrulama sağlandı (397 ms) | PASS | — |
| T-243 | AttachmentAccessTest | `test_detail_page_links_attachment` | Talep detayında ek dosya bağlantısı görünür | 2 doğrulama sağlandı (410 ms) | PASS | — |
| T-244 | AttachmentAccessTest | `test_other_customers_attachment_403` | ATT-013: 403 / 404; ATT-021: Yetki doğru kontrol edilmeli; ATT-022: Engellenmeli | 4 doğrulama sağlandı (557 ms) | PASS | — |
| T-245 | AttachmentAccessTest | `test_unknown_attachment_404_and_guest_redirect` | ATT-022: Engellenmeli | 3 doğrulama sağlandı (400 ms) | PASS | — |
| T-246 | AttachmentAccessTest | `test_broken_upload_rejected_before_ticket_is_created` | Bozuk yüklemede talep oluşturulmaz | 4 doğrulama sağlandı (398 ms) | PASS | — |
| T-247 | AuthFlowTest | `test_register_shows_customer_number_then_login_works` | AUTH-001: Giriş başarılı | 7 doğrulama sağlandı (231 ms) | PASS | — |
| T-248 | AuthFlowTest | `test_register_password_mismatch_keeps_values` | Şifreler uyuşmazsa kayıt reddedilir, girilen değerler korunur | 4 doğrulama sağlandı (123 ms) | PASS | — |
| T-249 | AuthFlowTest | `test_wrong_password_shows_error_and_keeps_number` | AUTH-002: Giriş reddedilmeli | 3 doğrulama sağlandı (232 ms) | PASS | — |
| T-250 | AuthFlowTest | `test_session_id_regenerated_on_login` | SEC-013: Güvenli yenilenmeli | 1 doğrulama sağlandı (216 ms) | PASS | — |
| T-251 | AuthFlowTest | `test_customer_cannot_open_admin_page` | Müşteri yönetici sayfasını açamaz | 3 doğrulama sağlandı (226 ms) | PASS | — |
| T-252 | AuthFlowTest | `test_guest_redirected_to_login` | AUTH-007: Giriş sayfasına yönlendirilmeli | 2 doğrulama sağlandı (111 ms) | PASS | — |
| T-253 | AuthFlowTest | `test_idle_30_minutes_logs_out` | SEC-012: Oturum kapatılmalı; PERF-010: Oturum sonlandırılmalı | 5 doğrulama sağlandı (245 ms) | PASS | — |
| T-254 | AuthFlowTest | `test_idle_timeout_on_form_post_redirects_to_login_not_csrf_error` | Oturum süresi dolmuşken form gönderilince CSRF hatası yerine girişe yönlenir | 5 doğrulama sağlandı (225 ms) | PASS | — |
| T-255 | AuthFlowTest | `test_csrf_token_is_rotated_on_login` | SEC-007: Reddedilmeli | 1 doğrulama sağlandı (215 ms) | PASS | — |
| T-256 | AuthFlowTest | `test_login_form_posted_after_idle_expiry_still_logs_in` | Süre dolduktan sonra gönderilen giriş formu çalışır | 3 doğrulama sağlandı (271 ms) | PASS | — |
| T-257 | AuthFlowTest | `test_logout_ends_session` | AUTH-006: Oturum sonlandırılmalı | 2 doğrulama sağlandı (217 ms) | PASS | — |
| T-258 | AuthFlowTest | `test_forgot_password_flow` | AUTH-008: Güvenli kurtarma akışı başlamalı | 6 doğrulama sağlandı (363 ms) | PASS | — |
| T-259 | AuthFlowTest | `test_unknown_number_in_forgot_password` | AUTH-009: İşlem reddedilmeli | 2 doğrulama sağlandı (113 ms) | PASS | — |
| T-260 | AuthFlowTest | `test_staff_home_redirects_by_role` | Girişten sonra role göre doğru ana sayfaya yönlenir | 4 doğrulama sağlandı (372 ms) | PASS | — |
| T-261 | AuthFlowTest | `test_inactive_user_cannot_login_with_message` | AUTH-040: Kullanıcı giriş yapamamalı | 2 doğrulama sağlandı (229 ms) | PASS | — |
| T-262 | AuthFlowTest | `test_deactivated_user_session_ends_on_next_request` | AUTH-040: Kullanıcı giriş yapamamalı | 4 doğrulama sağlandı (239 ms) | PASS | — |
| T-263 | AuthFlowTest | `test_register_rejects_weak_password_and_keeps_values` | Zayıf şifre reddedilir, girilen değerler korunur | 3 doğrulama sağlandı (130 ms) | PASS | — |
| T-264 | AuthFlowTest | `test_create_admin_script_refuses_web` | AUTH-020: Erişim mümkün olmamalı | 2 doğrulama sağlandı (118 ms) | PASS | — |
| T-265 | AuthFlowTest | `test_register_page_shows_live_password_rules` | Kayıt sayfasında şifre kuralları canlı gösterilir | 3 doğrulama sağlandı (136 ms) | PASS | — |
| T-266 | CalendarAdvancedTest | `test_half_day_shortens_sla_window` | Yarım gün SLA çalışma süresini kısaltır | 6 doğrulama sağlandı (653 ms) | PASS | — |
| T-267 | CalendarAdvancedTest | `test_special_day_validation` | Hatalı özel gün reddedilir | 6 doğrulama sağlandı (406 ms) | PASS | — |
| T-268 | CalendarAdvancedTest | `test_saturday_special_working_day_counts` | Cumartesi özel çalışma günü SLA'da sayılır | 2 doğrulama sağlandı (372 ms) | PASS | — |
| T-269 | CalendarAdvancedTest | `test_calendar_file_import_replaces_calendar_and_rejects_invalid_file` | Takvim dosyası yüklenince takvim değişir, geçersiz dosya reddedilir | 9 doğrulama sağlandı (666 ms) | PASS | — |
| T-270 | CalendarAdvancedTest | `test_calendar_change_applies_to_open_tickets_only_when_enabled` | Takvim değişikliği açık taleplere yalnız seçilirse uygulanır | 8 doğrulama sağlandı (672 ms) | PASS | — |
| T-271 | CalendarAdvancedTest | `test_month_view_shows_day_types` | Aylık görünüm gün türlerini gösterir | 5 doğrulama sağlandı (414 ms) | PASS | — |
| T-272 | ConcurrencyTest | `test_ten_agents_taking_the_same_ticket_at_once` | NFR-04, NFR-26, SEC-025: gerçek eşzamanlılık. Ayrı PHP süreçleri aynı anda (ortak başlama anında) aynı veri klasörüne yazar; dosya kilidi ve karşılaştır-yaz kuralı çift atamayı ve kapasite aşımını önlemelidir. / final class ConcurrencyTest extends FeatureTestCase { / @param list<array{0: int, 1: int}> $jobs talep id, personel id @return list<string> her sürecin çıktısı / private function runTogether(array $jobs): array { $worker = dirname(__DIR__) . '/Concurrency/take_worker.php'; $startAt = microtime(true) + 1.5; // süreçlerin hepsi açılsın, sonra aynı anda başlasın $procs = []; foreach ($jobs as [$ticketId, $agentId]) { $cmd = [PHP_BINARY, $worker, $this->root, (string) $ticketId, (string) $agentId, sprintf('%.4F', $startAt)]; $pipes = []; $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes); self::assertIsResource($proc); $procs[] = [$proc, $pipes]; } $out = []; foreach ($procs as [$proc, $pipes]) { $out[] = trim((string) stream_get_contents($pipes[1])) . trim((string) stream_get_contents($pipes[2])); fclose($pipes[1]); fclose($pipes[2]); proc_close($proc); } return $out; } private function poolTickets(int $count): void { $this->loginAs($this->registerCustomer(), self::PASSWORD); for ($i = 1; $i <= $count; $i++) { $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Havuz talebi ' . $i, 'description' => 'Personel yokken açılan talep.']); } $this->post('/cikis'); self::assertCount($count, $this->kernel->ticketRepository()->getPoolTickets()); } private function agent(string $name, int $capacity): User { $agent = $this->kernel->authService()->createStaff(Role::Agent, $name, 'Personel', self::PASSWORD); $agent->capacity = $capacity; return $this->kernel->userRepository()->save($agent); } /** NFR-04: on personel aynı talebi aynı anda alır; yalnız biri başarılı olur. | 24 doğrulama sağlandı (3006 ms) | PASS | — |
| T-273 | ConcurrencyTest | `test_capacity_is_not_exceeded_under_concurrent_takes` | NFR-26: kapasitesi 3 olan personel on talebi aynı anda almaya çalışır; en fazla 3'ü atanır. | 15 doğrulama sağlandı (2259 ms) | PASS | — |
| T-274 | ConfigCompletionTest | `test_login_is_blocked_after_configured_failed_attempts` | AUTH-039: Yeni istekler engellenmeli | 8 doğrulama sağlandı (867 ms) | PASS | — |
| T-275 | ConfigCompletionTest | `test_admin_changes_attempt_limit_and_idle_timeout` | Yönetici deneme sınırını ve oturum süresini değiştirir, yeni değer uygulanır | 10 doğrulama sağlandı (906 ms) | PASS | — |
| T-276 | ConfigCompletionTest | `test_disabled_notification_type_is_not_sent` | Kapatılan bildirim türü gönderilmez | 4 doğrulama sağlandı (698 ms) | PASS | — |
| T-277 | ConfigCompletionTest | `test_admin_edits_staff_name` | AUTH-021: Bilgiler güncellenmeli; ADM-019: Bilgiler güncellenmeli | 4 doğrulama sağlandı (492 ms) | PASS | — |
| T-278 | ConfigCompletionTest | `test_admin_opens_ticket_for_customer_and_for_self` | Yönetici müşteri adına ve kendisi için talep açar | 8 doğrulama sağlandı (697 ms) | PASS | — |
| T-279 | ConfigCompletionTest | `test_agent_cannot_open_ticket` | Personel talep açamaz | 2 doğrulama sağlandı (476 ms) | PASS | — |
| T-280 | ConfigCompletionTest | `test_home_image_upload_shows_on_home_page` | ADM-007: Logo doğru görüntülenmeli | 2 doğrulama sağlandı (452 ms) | PASS | — |
| T-281 | ConfigCompletionTest | `test_inactive_staff_is_deactivated_after_configured_days` | ASN-022: Otomatik pasifleştirilmeli; SEC-032: Görev tetiklenmeli | 3 doğrulama sağlandı (497 ms) | PASS | — |
| T-282 | CustomerAccountsTest | `test_admin_lists_and_searches_customers` | Yönetici müşterileri listeler ve arar | 6 doğrulama sağlandı (415 ms) | PASS | — |
| T-283 | CustomerAccountsTest | `test_deactivated_customer_cannot_log_in_until_reactivated` | AUTH-040: Kullanıcı giriş yapamamalı; AUTH-041: Kullanıcı yeniden erişebilmeli | 11 doğrulama sağlandı (444 ms) | PASS | — |
| T-284 | CustomerAccountsTest | `test_only_customers_can_be_changed_here` | Müşteri ekranından yönetici hesabı değiştirilemez (404) | 3 doğrulama sağlandı (331 ms) | PASS | — |
| T-285 | CustomerAccountsTest | `test_non_admin_gets_403` | Yönetici olmayan müşteri ekranına erişemez (403) | 5 doğrulama sağlandı (381 ms) | PASS | — |
| T-286 | CustomerAccountsTest | `test_list_is_paginated_by_fifty` | FR-33: yönetici müşteri hesaplarını listeler, arar, aktif ya da pasif yapar; pasif müşteri giriş yapamaz. */ final class CustomerAccountsTest extends FeatureTestCase { private User $admin; private string $customerNo; protected function setUp(): void { parent::setUp(); $this->admin = $this->kernel->authService()->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD); $this->customerNo = $this->registerCustomer('Ayşe'); $this->loginAs($this->admin->customerNo, self::PASSWORD); } private function customer(): User { $user = $this->kernel->userRepository()->findByCustomerNo($this->customerNo); self::assertNotNull($user); return $user; } public function test_admin_lists_and_searches_customers(): void { $this->registerCustomer('Mehmet'); $page = $this->get('/yonetici/musteriler')->body; self::assertStringContainsString($this->customerNo, $page); self::assertStringContainsString('Ayşe Yılmaz', $page); self::assertStringContainsString('Mehmet Yılmaz', $page); $found = $this->get('/yonetici/musteriler?q=AYŞE')->body; self::assertStringContainsString('Ayşe Yılmaz', $found); self::assertStringNotContainsString('Mehmet Yılmaz', $found); } public function test_deactivated_customer_cannot_log_in_until_reactivated(): void { $id = (int) $this->customer()->id; self::assertSame(302, $this->post('/yonetici/musteriler/' . $id . '/durum', ['active' => '0'])->status); self::assertFalse($this->customer()->active); self::assertStringContainsString('Pasif', $this->get('/yonetici/musteriler')->body); $this->post('/cikis'); $login = $this->post('/giris', ['customer_no' => $this->customerNo, 'password' => self::PASSWORD]); self::assertStringContainsString('Hesabınız pasif durumda', $login->body); $this->loginAs($this->admin->customerNo, self::PASSWORD); $this->post('/yonetici/musteriler/' . $id . '/durum', ['active' => '1']); self::assertTrue($this->customer()->active); $audit = $this->kernel->auditLogRepository()->latest(5); self::assertSame(['activate', 'deactivate'], array_slice(array_map(static fn ($e): string => $e->action, $audit), 0, 2)); } public function test_only_customers_can_be_changed_here(): void { $response = $this->post('/yonetici/musteriler/' . $this->admin->id . '/durum', ['active' => '0']); self::assertSame(404, $response->status); self::assertTrue($this->kernel->userRepository()->findById((int) $this->admin->id)?->active); } public function test_non_admin_gets_403(): void { $this->post('/cikis'); $this->loginAs($this->customerNo, self::PASSWORD); self::assertSame(403, $this->get('/yonetici/musteriler')->status); self::assertSame(403, $this->post('/yonetici/musteriler/' . $this->customer()->id . '/durum', ['active' => '0'])->status); } /** NFR-33: bir sayfada en fazla 50 kayıt. | 3 doğrulama sağlandı (1318 ms) | PASS | — |
| T-287 | EscalationTest | `test_breach_puts_ticket_on_hold_in_pool_and_asks_customer` | SLA-024: Eskalasyon kontrolü yapılmalı; NOT-010: Uyarı bildirimi | 19 doğrulama sağlandı (907 ms) | PASS | — |
| T-288 | EscalationTest | `test_same_breach_is_handled_once` | SLA-026: Tekrarlı kayıt oluşmamalı; NOT-013: Gereksiz çift bildirim oluşmamalı | 3 doğrulama sağlandı (820 ms) | PASS | — |
| T-289 | EscalationTest | `test_customer_continue_returns_to_same_agent_with_extra_sla` | SLA-030: Belirlenen süre eklenmeli; RES-009: Atama korunmalı; RES-014: Otomatik işlem yapılmamalı | 10 doğrulama sağlandı (841 ms) | PASS | — |
| T-290 | EscalationTest | `test_customer_says_resolved_closes_with_reason` | RES-025: Doğru tür saklanmalı | 6 doğrulama sağlandı (824 ms) | PASS | — |
| T-291 | EscalationTest | `test_no_response_escalates_to_next_level_with_extra_sla` | SLA-025: Üst yetkiliye devredilmeli; SLA-029: Doğru personel atanmalı; RES-013: Zaman aşımı işlenmeli … | 11 doğrulama sağlandı (916 ms) | PASS | — |
| T-292 | EscalationTest | `test_no_upper_level_leaves_ticket_for_admin` | SLA-034: Yönetici bildirimi oluşmalı; RES-012: Kontrollü hata; NOT-012: Yöneticiye bildirim | 9 doğrulama sağlandı (905 ms) | PASS | — |
| T-293 | EscalationTest | `test_zero_wait_escalates_immediately` | Bekleme süresi 0 ise aşımda hemen eskalasyon yapılır | 5 doğrulama sağlandı (834 ms) | PASS | — |
| T-294 | EscalationTest | `test_only_owner_decides_and_only_while_waiting` | Kararı yalnız talep sahibi ve yalnız bekleme sırasında verir | 10 doğrulama sağlandı (989 ms) | PASS | — |
| T-295 | EscalationTest | `test_admin_changes_sla_rules_with_validation` | RES-019: Yeni süre uygulanmalı; RES-020: Reddedilmeli; ADM-021: Ayar kaydedilmeli | 7 doğrulama sağlandı (818 ms) | PASS | — |
| T-296 | EscalationTest | `test_cancelled_ticket_on_hold_is_not_revived` | TKT-031: Engellenmeli | 11 doğrulama sağlandı (895 ms) | PASS | — |
| T-297 | EscalationTest | `test_overdue_pool_ticket_gets_extra_sla_when_assigned_and_is_not_pulled_back` | Süresi geçmiş havuz talebi atanınca ek SLA alır, geri çekilmez | 8 doğrulama sağlandı (865 ms) | PASS | — |
| T-298 | EscalationTest | `test_hold_frees_capacity_and_waiting_pool_ticket_is_assigned` | Beklemeye alınan talep kapasite açar, havuzdaki talep atanır | 6 doğrulama sağlandı (891 ms) | PASS | — |
| T-299 | EscalationTest | `test_continue_with_unavailable_agent_goes_to_upper_level` | RES-010: Engellenmeli | 7 doğrulama sağlandı (876 ms) | PASS | — |
| T-300 | EscalationTest | `test_admin_assigning_overdue_ticket_gives_extra_sla` | Yöneticinin atadığı süresi geçmiş talep ek SLA alır | 10 doğrulama sağlandı (943 ms) | PASS | — |
| T-301 | HierarchyAndLeaveTest | `test_admin_sets_levels_and_new_ticket_goes_to_lowest_level` | ASN-006: Doğru sıra oluşmalı; ASN-008: Yetkili yönetici düzenleyebilmeli; ASN-009: Kaydedilmeli … | 6 doğrulama sağlandı (828 ms) | PASS | — |
| T-302 | HierarchyAndLeaveTest | `test_invalid_level_is_rejected_and_nothing_saved` | ASN-007: Engellenmeli | 4 doğrulama sağlandı (575 ms) | PASS | — |
| T-303 | HierarchyAndLeaveTest | `test_leave_page_adds_and_removes_and_agent_on_leave_gets_no_ticket` | ASN-017: Atamadan çıkarılmalı | 9 doğrulama sağlandı (890 ms) | PASS | — |
| T-304 | HierarchyAndLeaveTest | `test_non_admin_cannot_open_hierarchy_or_leave_pages` | ASN-008: Yetkili yönetici düzenleyebilmeli | 3 doğrulama sağlandı (576 ms) | PASS | — |
| T-305 | HierarchyAndLeaveTest | `test_agent_outside_category_hierarchy_cannot_take_pool_ticket` | ASN-012: Engellenmeli | 5 doğrulama sağlandı (783 ms) | PASS | — |
| T-306 | KanbanApiTest | `test_kanban_page_shows_three_columns_with_cards` | TKT-047: Sütunlar doğru yüklenmeli | 8 doğrulama sağlandı (614 ms) | PASS | — |
| T-307 | KanbanApiTest | `test_move_taken_to_in_review_returns_json` | TKT-048: Geçerli geçiş uygulanmalı | 8 doğrulama sağlandı (630 ms) | PASS | — |
| T-308 | KanbanApiTest | `test_invalid_move_returns_422_with_reason` | TKT-048: Geçerli geçiş uygulanmalı | 5 doğrulama sağlandı (616 ms) | PASS | — |
| T-309 | KanbanApiTest | `test_unknown_status_value_returns_422` | Bilinmeyen durum değeri 422 döner | 3 doğrulama sağlandı (609 ms) | PASS | — |
| T-310 | KanbanApiTest | `test_other_agents_ticket_returns_403_json` | TKT-049: Engellenmeli | 5 doğrulama sağlandı (755 ms) | PASS | — |
| T-311 | KanbanApiTest | `test_missing_csrf_header_403` | TKT-049: Engellenmeli | 3 doğrulama sağlandı (591 ms) | PASS | — |
| T-312 | KanbanApiTest | `test_sla_endpoint_returns_levels_for_visible_tickets_only` | TKT-050: Süre doğru gösterilmeli; RPT-021: Engellenmeli | 9 doğrulama sağlandı (1007 ms) | PASS | — |
| T-313 | KanbanApiTest | `test_sla_polling_does_not_keep_session_alive` | SLA yoklaması oturum süresini uzatmaz | 3 doğrulama sağlandı (851 ms) | PASS | — |
| T-314 | KanbanApiTest | `test_api_post_after_logout_returns_401_not_csrf_error` | Çıkıştan sonra API isteği CSRF hatası yerine 401 döner | 3 doğrulama sağlandı (603 ms) | PASS | — |
| T-315 | KanbanApiTest | `test_api_without_session_returns_401_json` | RPT-021: Engellenmeli | 4 doğrulama sağlandı (606 ms) | PASS | — |
| T-316 | KanbanPerformanceTest | `test_kanban_with_100_active_tickets_renders_under_one_second` | PERF-002: < 1 saniye | 4 doğrulama sağlandı (1519 ms) | PASS | — |
| T-317 | KernelTest | `test_unknown_path_returns_404_page` | Bilinmeyen adres 404 sayfası döner | 2 doğrulama sağlandı (143 ms) | PASS | — |
| T-318 | KernelTest | `test_post_without_csrf_returns_403` | SEC-005: 403 | 2 doğrulama sağlandı (149 ms) | PASS | — |
| T-319 | KernelTest | `test_api_without_session_returns_json_401` | Oturumsuz API isteği JSON 401 döner | 3 doğrulama sağlandı (137 ms) | PASS | — |
| T-320 | KernelTest | `test_login_page_renders_layout` | Giriş sayfası ortak düzenle açılır | 3 doğrulama sağlandı (118 ms) | PASS | — |
| T-321 | KernelTest | `test_wrong_shaped_data_gives_friendly_500_without_leaking_input` | SEC-035: Kontrollü hata yanıtı; SEC-036: Hassas bilgiler gizlenmeli | 3 doğrulama sağlandı (111 ms) | PASS | — |
| T-322 | KernelTest | `test_post_body_dropped_by_php_limit_shows_size_message_not_csrf` | PHP boyut sınırını aşan istekte CSRF yerine boyut mesajı gösterilir | 3 doğrulama sağlandı (137 ms) | PASS | — |
| T-323 | KernelTest | `test_logged_in_user_with_corrupt_data_sees_friendly_500_page` | ADM-026: Kontrollü varsayılan davranış; SEC-035: Kontrollü hata yanıtı; SEC-036: Hassas bilgiler gizlenmeli | 4 doğrulama sağlandı (229 ms) | PASS | — |
| T-324 | KernelTest | `test_html_responses_have_security_headers` | HTML yanıtlarında güvenlik başlıkları var | 2 doğrulama sağlandı (122 ms) | PASS | — |
| T-325 | NotificationTest | `test_assignment_notifies_agent_and_bell_shows_count` | NOT-001: Bildirim kaydedilmeli; NOT-003: Kullanıcı bildirimleri dönmeli; NOT-008: Personele bildirim | 5 doğrulama sağlandı (609 ms) | PASS | — |
| T-326 | NotificationTest | `test_replies_and_resolution_notify_the_other_side` | TKT-006: Yanıt kaydedilmeli; NOT-011: Müşteriye bildirim | 8 doğrulama sağlandı (755 ms) | PASS | — |
| T-327 | NotificationTest | `test_opening_notification_marks_read_and_others_cannot_open_it` | NOT-004: Erişim engellenmeli; NOT-005: Durum güncellenmeli | 7 doğrulama sağlandı (655 ms) | PASS | — |
| T-328 | NotificationTest | `test_mark_all_read` | NOT-004: Erişim engellenmeli; NOT-005: Durum güncellenmeli | 4 doğrulama sağlandı (607 ms) | PASS | — |
| T-329 | NotificationTest | `test_notification_poll_does_not_keep_session_alive` | NOT-014: Sayfa yenilenmeden veri dönmeli | 3 doğrulama sağlandı (586 ms) | PASS | — |
| T-330 | PaginationTest | `test_report_ticket_table_shows_fifty_per_page` | Rapor tablosu sayfa başına 50 talep gösterir | 4 doğrulama sağlandı (400 ms) | PASS | — |
| T-331 | PaginationTest | `test_notifications_show_fifty_per_page_and_older_ones_are_reachable` | NOT-001: Bildirim kaydedilmeli; NOT-003: Kullanıcı bildirimleri dönmeli; NOT-008: Personele bildirim | 6 doğrulama sağlandı (781 ms) | PASS | — |
| T-332 | PerformanceScenarioTest | `test_second_take_is_rejected_quickly` | PERF-005: ≤ 100 ms içinde reddedilmeli | 5 doğrulama sağlandı (735 ms) | PASS | — |
| T-333 | PerformanceScenarioTest | `test_kanban_move_is_fast` | PERF-003: < 200 ms | 4 doğrulama sağlandı (688 ms) | PASS | — |
| T-334 | PerformanceScenarioTest | `test_invalid_csrf_is_rejected_quickly` | PERF-006: ≤ 50 ms | 3 doğrulama sağlandı (587 ms) | PASS | — |
| T-335 | PerformanceScenarioTest | `test_corrupt_calendar_falls_back_quickly` | PERF-007: ≤ 100 ms | 2 doğrulama sağlandı (588 ms) | PASS | — |
| T-336 | PerformanceScenarioTest | `test_report_with_ten_thousand_tickets` | PERF-012: Performans ölçülmeli | 3 doğrulama sağlandı (1081 ms) | PASS | — |
| T-337 | PerformanceScenarioTest | `test_hundred_requests_without_errors` | PERF-013: Hata oranı ölçülmeli | 102 doğrulama sağlandı (5604 ms) | PASS | — |
| T-338 | PoolTest | `test_pool_lists_unassigned_tickets` | TKT-043: Personel havuzu görebilmeli | 4 doğrulama sağlandı (647 ms) | PASS | — |
| T-339 | PoolTest | `test_agent_takes_pool_ticket` | TKT-026: Yetkili personel alabilmeli; TKT-044: Talep personelin üzerine geçmeli | 7 doğrulama sağlandı (657 ms) | PASS | — |
| T-340 | PoolTest | `test_second_take_shows_conflict_message` | İkinci alma denemesinde çakışma mesajı gösterilir | 6 doğrulama sağlandı (726 ms) | PASS | — |
| T-341 | PoolTest | `test_customer_cannot_see_pool_or_take` | SEC-003: Engellenmeli | 5 doğrulama sağlandı (753 ms) | PASS | — |
| T-342 | QuestionTest | `test_question_pauses_sla_and_answer_resumes_with_remaining_time` | Soru SLA'yı durdurur, yanıt gelince kalan süreyle devam eder | 14 doğrulama sağlandı (796 ms) | PASS | — |
| T-343 | QuestionTest | `test_unanswered_question_resumes_sla_when_reply_time_ends` | RES-013: Zaman aşımı işlenmeli | 5 doğrulama sağlandı (725 ms) | PASS | — |
| T-344 | QuestionTest | `test_question_limit_is_enforced_and_configurable` | Soru sınırı uygulanır ve ayardan değiştirilebilir | 21 doğrulama sağlandı (1137 ms) | PASS | — |
| T-345 | QuestionTest | `test_customer_cannot_send_question_and_second_question_waits_for_answer` | Müşteri soru gönderemez; ikinci soru ilk yanıtı bekler | 9 doğrulama sağlandı (795 ms) | PASS | — |
| T-346 | ResolutionTest | `test_customer_sees_worked_and_did_not_work_buttons` | RES-006: Olumlu geri bildirim | 7 doğrulama sağlandı (877 ms) | PASS | — |
| T-347 | ResolutionTest | `test_did_not_work_offers_same_or_another_agent` | RES-009: Atama korunmalı | 7 doğrulama sağlandı (874 ms) | PASS | — |
| T-348 | ResolutionTest | `test_asking_for_another_agent_escalates_to_next_level` | RES-011: Eskalasyon başlatılmalı | 13 doğrulama sağlandı (936 ms) | PASS | — |
| T-349 | ResolutionTest | `test_another_agent_request_needs_owner_and_resolved_status` | RES-011: Eskalasyon başlatılmalı | 7 doğrulama sağlandı (817 ms) | PASS | — |
| T-350 | ResolutionTest | `test_did_not_work_applies_extra_sla` | SLA-030: Belirlenen süre eklenmeli; RES-007: Olumsuz geri bildirim; RES-009: Atama korunmalı | 11 doğrulama sağlandı (865 ms) | PASS | — |
| T-351 | ResolutionTest | `test_third_rejection_escalates_to_next_level` | RES-011: Eskalasyon başlatılmalı | 17 doğrulama sağlandı (1197 ms) | PASS | — |
| T-352 | ResolutionTest | `test_unanswered_resolution_is_auto_completed` | RES-015: Talep kapanmalı | 10 doğrulama sağlandı (942 ms) | PASS | — |
| T-353 | ResolutionTest | `test_resolution_is_not_auto_completed_early` | RES-016: Kapanmamalı | 8 doğrulama sağlandı (905 ms) | PASS | — |
| T-354 | ResolutionTest | `test_agent_escalates_manually_with_reason` | SLA-027: Yetkili yönetici yapabilmeli | 10 doğrulama sağlandı (877 ms) | PASS | — |
| T-355 | ResolutionTest | `test_admin_assigns_waiting_ticket_and_capacity_exception_needs_reason` | ASN-026: Atama yapılmalı; ASN-028: İstisna kaydedilmeli | 13 doğrulama sağlandı (901 ms) | PASS | — |
| T-356 | ResolutionTest | `test_admin_closes_as_unresolvable_with_reason` | RES-021: Yetkili işlem yapabilmeli; RES-025: Doğru tür saklanmalı | 9 doğrulama sağlandı (884 ms) | PASS | — |
| T-357 | ResolutionTest | `test_customer_does_not_see_internal_escalation_reason` | Müşteri iç eskalasyon gerekçesini görmez | 7 doğrulama sağlandı (944 ms) | PASS | — |
| T-358 | ReviewFixesTest | `test_escalation_ends_question_pause` | Son incelemede bulunan durumlar: soru duraklamasının kapanması, uyum hesabı, yeniden aktif personel, puan, geri yükleme. */ final class ReviewFixesTest extends FeatureTestCase { private User $admin; private User $agent; private User $senior; private string $customerNo; protected function setUp(): void { parent::setUp(); $this->seedCatalog(); $auth = $this->kernel->authService(); $this->admin = $auth->createStaff(Role::Admin, 'Selin', 'Arslan', self::PASSWORD); $this->agent = $auth->createStaff(Role::Agent, 'Zeynep', 'Kaya', self::PASSWORD); $this->senior = $auth->createStaff(Role::Agent, 'Murat', 'Demir', self::PASSWORD); $this->kernel->hierarchyRepository()->setLevel(1, (int) $this->agent->id, 1); $this->kernel->hierarchyRepository()->setLevel(1, (int) $this->senior->id, 2); $this->customerNo = $this->registerCustomer(); $this->as($this->customerNo); $this->post('/talepler', ['category_id' => '1', 'priority' => '2', 'title' => 'Yazıcı arızası', 'description' => 'Yazıcı kağıt sıkıştırıyor.']); } private function as(string $number): void { $this->post('/cikis'); $this->loginAs($number, self::PASSWORD); } private function at(string $moment): void { $this->clock->set(new DateTimeImmutable($moment, new DateTimeZone('Europe/Istanbul'))); } private function ticket(): Ticket { $ticket = $this->kernel->ticketRepository()->findById(1); self::assertNotNull($ticket); return $ticket; } private function ask(): void { $this->as($this->agent->customerNo); $this->post('/talepler/1/yanit', ['mesaj' => 'Yazıcının modeli nedir?', 'soru' => '1']); self::assertNotNull($this->ticket()->pausedAt, 'Soru SLA\'yı durdurdu.'); } /** Bulgu 1: talep başka personele aktarılınca soru duraklaması kapanır; yeni personel soru sorabilir. | 8 doğrulama sağlandı (850 ms) | PASS | — |
| T-359 | ReviewFixesTest | `test_closed_ticket_is_not_touched_when_question_time_ends` | Bulgu 1: soru beklerken çözülüp kapanan talebin bitişine izleme dokunmaz, personele "yanıtlamadı" bildirimi gitmez. | 11 doğrulama sağlandı (926 ms) | PASS | — |
| T-360 | ReviewFixesTest | `test_unanswered_question_resumes_from_reply_due_time` | Bulgu 6: yanıtlanmayan soruda SLA, izlemenin çalıştığı andan değil yanıt süresinin bittiği andan devam eder. | 8 doğrulama sağlandı (825 ms) | PASS | — |
| T-361 | ReviewFixesTest | `test_paused_time_does_not_count_against_sla_compliance` | Bulgu 5: SLA uyumunda soru nedeniyle duran süre personelin aleyhine sayılmaz. | 10 doğrulama sağlandı (979 ms) | PASS | — |
| T-362 | ReviewFixesTest | `test_reactivated_staff_is_not_deactivated_again` | Bulgu 2: yönetici yeniden aktif yaptığı personel bir sonraki denetimde tekrar pasif yapılmaz. | 4 doğrulama sağlandı (900 ms) | PASS | — |
| T-363 | ReviewFixesTest | `test_score_of_receiving_agent_ignores_previous_rejections` | Bulgu 4: eskalasyonla devralan personelin puanı yalnız kendi denemelerine göre hesaplanır. | 19 doğrulama sağlandı (1324 ms) | PASS | — |
| T-364 | ReviewFixesTest | `test_restore_rejects_users_table_without_active_admin` | Bulgu 3: yöneticisi olmayan ya da kimliksiz satır içeren kullanıcı tablosu geri yüklenmez. | 6 doğrulama sağlandı (806 ms) | PASS | — |
| T-365 | ReviewFixesTest | `test_restore_rejects_oversized_uncompressed_content` | Bulgu 3: açıldığında çok büyüyen dosya (zip bombası) reddedilir. | 6 doğrulama sağlandı (1063 ms) | PASS | — |
| T-366 | ScenarioCoverageTest | `test_empty_login_fields_are_rejected` | AUTH-004: Validasyon hatası | 2 doğrulama sağlandı (626 ms) | PASS | — |
| T-367 | ScenarioCoverageTest | `test_generated_numbers_are_unique_and_prefixed` | AUTH-011: Çakışma engellenmeli; AUTH-013: Benzersiz numara üretilmeli; AUTH-028: Tekrarlanan ID oluşmamalı | 31 doğrulama sağlandı (3577 ms) | PASS | — |
| T-368 | ScenarioCoverageTest | `test_role_prefixes` | AUTH-023: Doğru önek dönmeli; AUTH-024: Doğru önek dönmeli; AUTH-025: Doğru önek dönmeli … | 3 doğrulama sağlandı (613 ms) | PASS | — |
| T-369 | ScenarioCoverageTest | `test_non_admin_cannot_post_admin_actions` | AUTH-032: 403 dönmeli; FRM-013: 403 dönmeli; ASN-027: Engellenmeli … | 13 doğrulama sağlandı (845 ms) | PASS | — |
| T-370 | ScenarioCoverageTest | `test_number_field_rejects_text` | FRM-009: Sayısal doğrulama yapılmalı; FRM-020: Reddedilmeli | 4 doğrulama sağlandı (747 ms) | PASS | — |
| T-371 | ScenarioCoverageTest | `test_recently_active_staff_is_not_deactivated` | ASN-023: Yanlışlıkla pasifleştirilmemeli | 3 doğrulama sağlandı (764 ms) | PASS | — |
| T-372 | ScenarioCoverageTest | `test_extra_sla_accumulates_and_first_deadline_is_kept` | SLA-031: Birikim doğru hesaplanmalı; SLA-032: Geçmiş saklanmalı; SLA-033: Önceki kayıt korunmalı | 8 doğrulama sağlandı (1003 ms) | PASS | — |
| T-373 | ScenarioCoverageTest | `test_other_customer_cannot_approve_and_double_approval_is_refused` | RES-005: Engellenmeli; RES-008: Çift işlem engellenmeli | 7 doğrulama sağlandı (1045 ms) | PASS | — |
| T-374 | ScenarioCoverageTest | `test_objection_stops_auto_close` | RES-017: Kapanış durmalı | 5 doğrulama sağlandı (901 ms) | PASS | — |
| T-375 | ScenarioCoverageTest | `test_auto_complete_runs_once` | RES-018: Çift kapanış olmamalı | 3 doğrulama sağlandı (827 ms) | PASS | — |
| T-376 | ScenarioCoverageTest | `test_unresolvable_close_needs_reason_of_at_least_five_characters` | RES-023: Reddedilmeli; RES-024: Kurala göre reddedilmeli | 4 doğrulama sağlandı (781 ms) | PASS | — |
| T-377 | ScenarioCoverageTest | `test_script_and_event_attributes_are_shown_harmless` | ADM-009: Zararsız gösterilmeli; SEC-008: Zararsız hale getirilmeli; SEC-009: Temizlenmeli | 5 doğrulama sağlandı (780 ms) | PASS | — |
| T-378 | ScenarioCoverageTest | `test_report_date_and_combined_filters` | RPT-012: Doğru kayıtlar; RPT-014: Doğru kayıtlar; RPT-015: Doğru kesişim | 6 doğrulama sağlandı (904 ms) | PASS | — |
| T-379 | ScenarioCoverageTest | `test_updating_unknown_staff_gives_not_found` | AUTH-022: Kontrollü hata dönmeli | 2 doğrulama sağlandı (691 ms) | PASS | — |
| T-380 | ScenarioCoverageTest | `test_monitor_does_nothing_before_deadline` | SLA-023: Eskalasyon yapılmamalı | 4 doğrulama sağlandı (710 ms) | PASS | — |
| T-381 | ScenarioCoverageTest | `test_customer_cannot_escalate_and_only_admin_closes_as_unresolvable` | SLA-028: Engellenmeli; RES-022: Engellenmeli | 6 doğrulama sağlandı (776 ms) | PASS | — |
| T-382 | ScenarioCoverageTest | `test_future_and_finished_leave_do_not_block_assignment` | ASN-018: Gereksinimdeki kurala göre çıkarılmalı; ASN-019: Yeniden uygun olmalı | 2 doğrulama sağlandı (766 ms) | PASS | — |
| T-383 | ScenarioCoverageTest | `test_agent_sees_why_breached_ticket_cannot_be_answered` | SLA-036: Tanımlı kurala göre engellenmeli | 4 doğrulama sağlandı (789 ms) | PASS | — |
| T-384 | ScenarioGapsTest | `test_duplicate_field_is_rejected_and_removed_field_keeps_old_values` | FRM-002: Benzersizlik kuralları uygulanmalı; FRM-005: Alan silinmeli; FRM-007: Kural uygulanmalı … | 7 doğrulama sağlandı (867 ms) | PASS | — |
| T-385 | ScenarioGapsTest | `test_notification_to_unknown_user_is_ignored` | NOT-002: Kontrollü hata | 1 doğrulama sağlandı (610 ms) | PASS | — |
| T-386 | ScenarioGapsTest | `test_notification_can_be_marked_unread_and_reading_twice_is_consistent` | NOT-006: Durum güncellenmeli; NOT-007: Veri tutarlı kalmalı | 7 doğrulama sağlandı (841 ms) | PASS | — |
| T-387 | ScenarioGapsTest | `test_sla_warning_notification_is_sent_once` | NOT-010: Uyarı bildirimi; NOT-013: Gereksiz çift bildirim oluşmamalı; SEC-032: Görev tetiklenmeli | 4 doğrulama sağlandı (731 ms) | PASS | — |
| T-388 | ScenarioGapsTest | `test_report_lists_escalations` | RPT-011: Devir kayıtları doğru | 6 doğrulama sağlandı (881 ms) | PASS | — |
| T-389 | ScoreAndRestoreTest | `test_score_depends_on_attempts_and_escalation_bonus` | RPT-009: Puan ve süre doğru | 4 doğrulama sağlandı (553 ms) | PASS | — |
| T-390 | ScoreAndRestoreTest | `test_restore_brings_back_data_and_keeps_automatic_backup` | SEC-030: Veri geri gelmeli | 5 doğrulama sağlandı (551 ms) | PASS | — |
| T-391 | ScoreAndRestoreTest | `test_invalid_backup_is_rejected_and_data_untouched` | SEC-029: Veri doğrulanmalı; SEC-031: İşlem güvenli biçimde durmalı | 5 doğrulama sağlandı (477 ms) | PASS | — |
| T-392 | ScoreAndRestoreTest | `test_agent_cannot_restore` | Personel yedekten geri yükleme yapamaz | 2 doğrulama sağlandı (467 ms) | PASS | — |
| T-393 | SecurityAndLimitsTest | `test_admin_changes_file_limits_and_ticket_form_follows` | ATT-020: Yeni limit uygulanmalı; ADM-022: Yeni limit uygulanmalı | 8 doğrulama sağlandı (567 ms) | PASS | — |
| T-394 | SecurityAndLimitsTest | `test_admin_edits_security_questions_and_register_lists_them` | AUTH-029: Tanımlı sorular dönmeli; AUTH-030: Yeni kayıtta kullanılamamalı; AUTH-031: Soru oluşturulmalı … | 6 doğrulama sağlandı (402 ms) | PASS | — |
| T-395 | SecurityAndLimitsTest | `test_customer_registers_with_own_question_and_reset_shows_it` | Müşteri kendi sorusuyla kaydolur, şifre sıfırlamada bu soru gösterilir | 4 doğrulama sağlandı (430 ms) | PASS | — |
| T-396 | SecurityAndLimitsTest | `test_reset_is_blocked_after_five_wrong_answers_for_configured_minutes` | AUTH-038: İstekler kabul edilmeli; AUTH-039: Yeni istekler engellenmeli | 8 doğrulama sağlandı (810 ms) | PASS | — |
| T-397 | SeedTest | `test_seed_produces_demo_ready_data with data set "pazartesi_sabah"` | Örnek veri sunuma hazır (her durumdan, sarı ve kırmızı SLA'dan en az 3 talep) (vaka: pazartesi sabah) | 87 doğrulama sağlandı (3250 ms) | PASS | — |
| T-398 | SeedTest | `test_seed_produces_demo_ready_data with data set "cumartesi"` | Örnek veri sunuma hazır (her durumdan, sarı ve kırmızı SLA'dan en az 3 talep) (vaka: cumartesi) | 87 doğrulama sağlandı (2567 ms) | PASS | — |
| T-399 | SeedTest | `test_seed_produces_demo_ready_data with data set "gece_03"` | Örnek veri sunuma hazır (her durumdan, sarı ve kırmızı SLA'dan en az 3 talep) (vaka: gece 03) | 87 doğrulama sağlandı (2495 ms) | PASS | — |
| T-400 | SeedTest | `test_seed_produces_demo_ready_data with data set "cuma_mesai_bitimi"` | Örnek veri sunuma hazır (her durumdan, sarı ve kırmızı SLA'dan en az 3 talep) (vaka: cuma mesai bitimi) | 87 doğrulama sağlandı (2449 ms) | PASS | — |
| T-401 | SeedTest | `test_seeded_accounts_can_log_in` | Örnek hesaplarla giriş yapılabilir | 2 doğrulama sağlandı (2652 ms) | PASS | — |
| T-402 | SeedTest | `test_seed_has_config_and_pool_example with data set "pazartesi_sabah"` | Örnek veride ayarlar ve havuzda bekleyen talep var (vaka: pazartesi sabah) | 53 doğrulama sağlandı (2565 ms) | PASS | — |
| T-403 | SeedTest | `test_seed_has_config_and_pool_example with data set "cumartesi"` | Örnek veride ayarlar ve havuzda bekleyen talep var (vaka: cumartesi) | 53 doğrulama sağlandı (2497 ms) | PASS | — |
| T-404 | SeedTest | `test_seed_has_config_and_pool_example with data set "gece_03"` | Örnek veride ayarlar ve havuzda bekleyen talep var (vaka: gece 03) | 53 doğrulama sağlandı (2539 ms) | PASS | — |
| T-405 | SeedTest | `test_seed_has_config_and_pool_example with data set "cuma_mesai_bitimi"` | Örnek veride ayarlar ve havuzda bekleyen talep var (vaka: cuma mesai bitimi) | 53 doğrulama sağlandı (2476 ms) | PASS | — |
| T-406 | SubcategoryAndFieldTypesTest | `test_subcategory_is_created_under_parent_and_listed_with_path` | ADM-013: Güncellenmeli | 8 doğrulama sağlandı (708 ms) | PASS | — |
| T-407 | SubcategoryAndFieldTypesTest | `test_select_email_and_max_length_fields_are_validated` | FRM-011: Seçenekler doğru gösterilmeli; FRM-019: Reddedilmeli; FRM-021: Limit uygulanmalı | 13 doğrulama sağlandı (612 ms) | PASS | — |
| T-408 | SystemConfigTest | `test_non_admin_gets_403_on_every_config_page` | Yönetici olmayan her yapılandırma sayfasında 403 alır | 29 doğrulama sağlandı (735 ms) | PASS | — |
| T-409 | SystemConfigTest | `test_site_title_change_appears_in_header` | ADM-006: Yeni ad gösterilmeli | 5 doğrulama sağlandı (405 ms) | PASS | — |
| T-410 | SystemConfigTest | `test_invalid_site_form_keeps_values_and_shows_error` | Hatalı site formu değerleri korur ve hatayı gösterir | 6 doğrulama sağlandı (373 ms) | PASS | — |
| T-411 | SystemConfigTest | `test_category_create_and_deactivate_hides_from_form` | TKT-037: Kategori kaydedilmeli; ADM-012: Kaydedilmeli | 9 doğrulama sağlandı (552 ms) | PASS | — |
| T-412 | SystemConfigTest | `test_priority_create_appears_in_ticket_form` | ADM-010: Kaydedilmeli | 7 doğrulama sağlandı (533 ms) | PASS | — |
| T-413 | SystemConfigTest | `test_sla_grid_saves_and_clears` | ADM-015: Kural kaydedilmeli | 8 doğrulama sağlandı (403 ms) | PASS | — |
| T-414 | SystemConfigTest | `test_calendar_rejects_no_working_day_and_keeps_old` | Çalışma günü olmayan takvim reddedilir, eskisi korunur | 10 doğrulama sağlandı (474 ms) | PASS | — |
| T-415 | SystemConfigTest | `test_staff_create_shows_P_number` | ADM-017: Personel oluşturulmalı | 6 doğrulama sağlandı (473 ms) | PASS | — |
| T-416 | SystemConfigTest | `test_activating_agent_from_staff_page_assigns_pool_ticket` | Personel ekranında aktif yapılan personele havuzdaki talep atanır | 7 doğrulama sağlandı (719 ms) | PASS | — |
| T-417 | SystemConfigTest | `test_inactive_agent_with_open_tickets_shows_warning` | ASN-025: Görevler kaybolmamalı | 6 doğrulama sağlandı (897 ms) | PASS | — |
| T-418 | SystemConfigTest | `test_audit_page_lists_changes` | SEC-016: Eski/yeni değer kaydedilmeli | 4 doğrulama sağlandı (376 ms) | PASS | — |
| T-419 | SystemConfigTest | `test_priority_default_sla_change_applies_to_new_ticket_in_seeded_category` | ADM-016: Yeni kural uygulanmalı | 3 doğrulama sağlandı (546 ms) | PASS | — |
| T-420 | TicketCreateTest | `test_customer_creates_ticket_and_is_redirected` | TKT-001: Talep kaydedilmeli; FRM-003: Gerekli alan kontrol edilmeli; FRM-015: Kontrollü varsayılan davranış | 5 doğrulama sağlandı (403 ms) | PASS | — |
| T-421 | TicketCreateTest | `test_create_form_lists_categories_and_priorities` | FRM-016: Form görüntülenmeli | 4 doğrulama sağlandı (371 ms) | PASS | — |
| T-422 | TicketCreateTest | `test_invalid_form_keeps_values_422` | TKT-002: Validasyon hatası | 5 doğrulama sağlandı (373 ms) | PASS | — |
| T-423 | TicketCreateTest | `test_list_shows_only_own_tickets` | TKT-003: Yetkili talepler listelenmeli | 4 doğrulama sağlandı (560 ms) | PASS | — |
| T-424 | TicketCreateTest | `test_list_filter_and_search` | TKT-016: Filtrelerin kesişimi doğru olmalı | 5 doğrulama sağlandı (438 ms) | PASS | — |
| T-425 | TicketCreateTest | `test_agent_cannot_open_create_form` | Personel talep formunu açamaz | 3 doğrulama sağlandı (378 ms) | PASS | — |
| T-426 | TicketCreateTest | `test_create_redirects_to_success_page_and_only_owner_sees_it` | Talep açılınca başarı sayfasına gidilir, sayfayı yalnız sahibi görür | 8 doğrulama sağlandı (554 ms) | PASS | — |
| T-427 | TicketCreateTest | `test_register_done_has_link_and_no_refresh` | Kayıt sonrası sayfada giriş bağlantısı var, yenileme yok | 3 doğrulama sağlandı (313 ms) | PASS | — |
| T-428 | TicketDetailTest | `test_owner_sees_messages_timeline_and_sla` | TKT-004: Doğru detay açılmalı; TKT-039: İlişkili veriler doğru dönmeli | 8 doğrulama sağlandı (517 ms) | PASS | — |
| T-429 | TicketDetailTest | `test_priority_star_shown_only_when_raised` | Öncelik yıldızı yalnız sistem önceliği yükselttiyse görünür | 5 doğrulama sağlandı (462 ms) | PASS | — |
| T-430 | TicketDetailTest | `test_other_customer_gets_403_and_no_content` | TKT-005: 403 / 404; FRM-023: Engellenmeli | 5 doğrulama sağlandı (546 ms) | PASS | — |
| T-431 | TicketDetailTest | `test_unknown_ticket_404` | Olmayan talep 404 döner | 2 doğrulama sağlandı (373 ms) | PASS | — |
| T-432 | TicketDetailTest | `test_customer_cancels_pending_ticket` | TKT-025: İptal başarılı | 5 doğrulama sağlandı (420 ms) | PASS | — |
| T-433 | TicketDetailTest | `test_invalid_action_shows_reason` | İzin verilmeyen işlemde nedeni gösterilir | 3 doğrulama sağlandı (537 ms) | PASS | — |
| T-434 | TicketDetailTest | `test_agent_works_and_customer_rejects_then_approves` | TKT-029: Onay akışı çalışmalı; RES-003: Onay kaydedilmeli | 13 doğrulama sağlandı (845 ms) | PASS | — |
| T-435 | TicketDetailTest | `test_reply_is_escaped` | SEC-008: Zararsız hale getirilmeli | 3 doğrulama sağlandı (412 ms) | PASS | — |
| T-436 | TicketDetailTest | `test_empty_reply_shows_error` | Boş yanıt hata verir | 2 doğrulama sağlandı (416 ms) | PASS | — |
| T-437 | TicketDetailTest | `test_other_agent_cannot_reply_or_act` | TKT-007: İşlem reddedilmeli | 4 doğrulama sağlandı (641 ms) | PASS | — |
| T-438 | TicketDetailTest | `test_detail_two_column_labels` | TKT-039: İlişkili veriler doğru dönmeli | 11 doğrulama sağlandı (524 ms) | PASS | — |
| T-439 | TicketDetailTest | `test_customer_detail_hides_customer_row` | Müşteri kendi talep detayında müşteri satırını görmez | 5 doğrulama sağlandı (576 ms) | PASS | — |
| T-440 | TicketDetailTest | `test_status_forms_have_confirm_attribute` | Durum düğmeleri onay penceresi açar | 4 doğrulama sağlandı (566 ms) | PASS | — |
| T-441 | TicketDetailTest | `test_agent_cannot_reply_to_resolved_but_customer_can` | SLA-037: Yetki ve durum kontrol edilmeli | 7 doğrulama sağlandı (660 ms) | PASS | — |
| T-442 | TicketDetailTest | `test_detail_and_lists_show_ticket_code_and_activity` | TKT-042: Kronolojik geçmiş gösterilmeli | 5 doğrulama sağlandı (514 ms) | PASS | — |
| T-443 | UiTest | `test_guest_sees_public_home_with_admin_text` | ADM-008: Yeni metin görünmeli | 5 doğrulama sağlandı (334 ms) | PASS | — |
| T-444 | UiTest | `test_status_badges_use_new_labels_and_icons` | Durum rozetleri yeni etiket ve simgelerle görünür | 5 doğrulama sağlandı (399 ms) | PASS | — |
| T-445 | UiTest | `test_new_ticket_button_class` | Yeni talep düğmesi doğru görünümde | 2 doğrulama sağlandı (378 ms) | PASS | — |
| T-446 | UiTest | `test_pool_and_tasks_terminology` | Menüde Görev Havuzu ve Görevlerim adları kullanılır | 8 doğrulama sağlandı (376 ms) | PASS | — |
| T-447 | UiTest | `test_logout_shows_no_message` | Çıkışta mesaj gösterilmez | 2 doğrulama sağlandı (378 ms) | PASS | — |
| T-448 | UiTest | `test_no_action_column_on_tasks_page` | Görevlerim sayfasında işlem sütunu yok | 5 doğrulama sağlandı (570 ms) | PASS | — |
| T-449 | UiTest | `test_search_placeholder` | Arama kutusunda doğru yönlendirme metni var | 2 doğrulama sağlandı (382 ms) | PASS | — |
