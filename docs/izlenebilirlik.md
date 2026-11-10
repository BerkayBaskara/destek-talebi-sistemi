# Gereksinim – Test İzlenebilirlik Tablosu (Aşama 2)

Gereksinim numaraları `docs/srs.md`'den. Testler `tests/` altında; hepsini çalıştırmak için `php vendor/bin/phpunit`, bir sınıfı adlarıyla görmek için `php vendor/bin/phpunit --testdox --filter <SınıfAdı>`.

Durum: ✅ test ile doğrulandı · 👁 elle doğrulandı · ⏭ Aşama 3 (MySQL / Boran)

## İşlevsel gereksinimler

| Gereksinim | Açıklama | Test sınıfı :: metot | Durum |
|---|---|---|---|
| FR-01 | Kategori, öncelik, başlık, mesajlı talep formu | `TicketCreateTest::test_customer_creates_ticket_and_is_redirected`, `test_create_form_lists_categories_and_priorities`, `test_invalid_form_keeps_values_422`; `TicketServiceTest::test_create_validation_errors` | ✅ |
| FR-02 | JPG/PNG/PDF + MP4/WebM, MIME ve boyut kontrolü | `AttachmentServiceTest::test_accepts_jpg_png_pdf`, `test_rejects_php_disguised_as_jpg`, `test_rejects_oversized_image`, `test_rejects_image_with_too_many_pixels_before_decoding`, `test_png_has_lower_pixel_limit_than_jpeg`, `test_accepts_mp4_and_webm_video`, `test_video_over_video_limit_rejected`, `test_php_ini_size_error_says_file_is_too_big`, `test_validate_many_checks_count_and_skips_empty_slots`; `AttachmentAccessTest::test_broken_upload_rejected_before_ticket_is_created` | ✅ |
| FR-03 | Anahtar kelimeyle akıllı öncelik | `PriorityDetectorTest` (9 test, Türkçe büyük harf dahil); `TicketServiceTest::test_create_uses_keyword_priority`; `TicketDetailTest::test_priority_raised_note` | ✅ |
| FR-04 | En az iş yüklü personele otomatik atama | `AssignmentServiceTest` (5 test); `TicketServiceTest::test_create_auto_assigns_least_loaded_and_logs`, `test_create_without_agents_stays_in_pool` | ✅ |
| FR-05 | Mesai, öğle arası ve tatile duyarlı SLA bitişi (**çekirdek**) | `SlaCalculatorServiceTest::test_deadline_matches_case_table` (12 vaka: hocanın Cuma 17:30 örneği, öğle arası, molada/mesai öncesi/sonrası/hafta sonu/tatilde açılan, çok günlü), `test_corrupt_calendar_uses_default_hours`, `test_business_minutes_between_skips_break_and_weekend`; `TicketServiceTest::test_create_computes_deadline_with_policy`, `test_create_without_policy_uses_priority_default` | ✅ |
| FR-06 | State deseniyle durum geçişlerinin kısıtlanması | `TicketStateTest` (izinli 6, izinsiz 8 geçiş + mesaj); `TicketServiceTest::test_customer_cancels_pending_but_not_others_ticket`, `test_reject_increments_attempt_and_returns_to_review`; `TicketDetailTest::test_invalid_action_shows_reason` | ✅ |
| FR-07 | SLA %80 sarı, %100 kırmızı | `SlaStatusServiceTest` (13 test: tam %80, %80 altı, bitiş anı, çözülmüş/geç çözülmüş); `KanbanApiTest::test_sla_endpoint_returns_levels_for_visible_tickets_only` | ✅ |
| FR-08 | SLA dolunca otomatik eskalasyon | Observer altyapısı hazır (`TicketServiceTest::test_observer_receives_events`); `EscalationObserver` | ⏭ |
| FR-09 | AJAX Kanban (sayfa yenilemeden) | `KanbanApiTest::test_kanban_page_shows_three_columns_with_cards`, `test_move_taken_to_in_review_returns_json`, `test_invalid_move_returns_422_with_reason`, `test_other_agents_ticket_returns_403_json`; `TicketServiceTest::test_move_on_kanban`; tarayıcıda sürükle-bırak ve mobil menü | ✅ 👁 |
| FR-10 – FR-18 | SLA kural yönetimi, tatil arayüzü, rapor, dinamik mesai ekranı, ek süre, puan, yönetici talebi, oto-kapanış, bildirimler | – | ⏭ |
| FR-19 | Kronolojik durum geçmişi | `TicketServiceTest::test_create_auto_assigns_least_loaded_and_logs`, `test_full_flow_resolve_sets_resolved_at_and_approve_closes`; `TicketDetailTest::test_owner_sees_messages_timeline_and_sla`, `test_agent_works_and_customer_rejects_then_approves` | ✅ |
| FR-20 – FR-22 | İzinli personel, 3 hak kuralı, soru hakkı | `attempt_count` ve `is_question` alanları hazır | ⏭ |

## Kullanıcı hikayeleri (Aşama 2'de karşılananlar)

| Hikaye | Test | Durum |
|---|---|---|
| US-01, US-05 talep açma ve öncelik | `TicketCreateTest` | ✅ |
| US-02 ek dosya | `AttachmentServiceTest`, `AttachmentAccessTest` | ✅ |
| US-03 taleplerin durumunu listeleme (arama + filtre) | `TicketCreateTest::test_list_filter_and_search`, `test_list_shows_only_own_tickets`; `TicketServiceTest::test_search_*` | ✅ |
| US-04, US-10 yanıtlaşma | `TicketServiceTest::test_reply_rules`; `TicketDetailTest::test_agent_works_and_customer_rejects_then_approves`, `test_reply_is_escaped`, `test_empty_reply_shows_error` | ✅ |
| US-06 ad-soyad-şifre-gizli soru ile kayıt, şifre sıfırlama | `AuthServiceTest` (10 test); `AuthFlowTest::test_register_shows_customer_number_then_login_works`, `test_forgot_password_flow` | ✅ |
| US-07 bekleyen talebi iptal | `TicketDetailTest::test_customer_cancels_pending_ticket` | ✅ |
| US-08, US-09 Kanban | `KanbanApiTest` | ✅ |
| US-11, US-12 SLA geri sayımı ve renkli uyarı | `SlaStatusServiceTest`, `KanbanApiTest::test_sla_endpoint_*` | ✅ |

## İşlevsel olmayan gereksinimler

| Gereksinim | Açıklama | Test / doğrulama | Durum |
|---|---|---|---|
| NFR-01 | 5 yıllık tatil verisiyle SLA hesabı < 500 ms | `SlaCalculatorServiceTest::test_five_years_of_holidays_is_fast` (1000 hesap toplamı < 500 ms) | ✅ |
| NFR-02 | 100 aktif talepte Kanban < 1 sn | `KanbanPerformanceTest::test_kanban_with_100_active_tickets_renders_under_one_second` | ✅ |
| NFR-03 | Ekler genel URL'den indirilemez, yalnız yetkiliye | `AttachmentAccessTest::test_other_customers_attachment_403`, `test_unknown_attachment_404_and_guest_redirect`, `test_owner_downloads_with_safe_headers` (dosyalar `storage/uploads`, web kökü dışında) | ✅ |
| NFR-04 | Aynı talebi iki personel aynı anda alamaz | `JsonStoreTest::test_transaction_conditional_update_only_first_wins`; `JsonTicketRepositoryTest::test_assign_if_unassigned_second_call_fails`, `test_save_if_status_*`; `TicketServiceTest::test_take_twice_second_conflicts`, `test_perform_on_stale_ticket_conflicts_instead_of_overwriting`; `PoolTest::test_second_take_shows_conflict_message` | ✅ |
| NFR-05 | 375–1920 px'te yatay kaydırma yok | Tarayıcıda 375 px: giriş, Taleplerim, talep formu, detay, Kanban, özet rapor (`scrollWidth == innerWidth`) | 👁 |
| NFR-06 | Her form CSRF jetonlu; geçersizse 403 | `CsrfTest`; `KernelTest::test_post_without_csrf_returns_403`; `KanbanApiTest::test_missing_csrf_header_403`; `AuthFlowTest::test_csrf_token_is_rotated_on_login` | ✅ |
| NFR-07 | Takvim JSON'u bozuk/silik olsa da çökmeden varsayılan mesai | `JsonCalendarRepositoryTest::test_missing_file_falls_back_to_default_and_logs`, `test_corrupt_file_falls_back_to_default_and_logs`, `test_calendar_without_any_working_hours_falls_back_to_default`, `test_bom_prefixed_file_is_read`; `SlaCalculatorServiceTest::test_corrupt_calendar_uses_default_hours` | ✅ |
| NFR-08 | Görseller en fazla 1920×1080'e küçültülür | `AttachmentServiceTest::test_large_png_is_downscaled_to_1920x1080`; tarayıcıda 2400×1600 → 1620×1080 | ✅ 👁 |
| NFR-09 | PSR-12, PHPStan 0 hata | `php vendor/bin/phpstan analyse` (seviye 6) → `[OK] No errors` | ✅ |
| NFR-10 | 30 dk hareketsizlikte oturum kapanır | `AuthFlowTest::test_idle_30_minutes_logs_out`, `test_idle_timeout_on_form_post_redirects_to_login_not_csrf_error`, `test_login_form_posted_after_idle_expiry_still_logs_in`; `KanbanApiTest::test_sla_polling_does_not_keep_session_alive` | ✅ |
| NFR-11 | 50 MB video sınırı | `AttachmentServiceTest::test_accepts_mp4_and_webm_video`, `test_video_over_video_limit_rejected` (`settings.json` `max_video_bytes`); `public/.htaccess` ve README'deki `-d` ayarları PHP sınırını 50 MB'a çıkarır | ✅ (50 MB'lık gerçek video yüklemesi süre açısından elle ölçülmedi) |

## Güvenlik (hocanın ortak şartları)

| Şart | Test |
|---|---|
| `password_hash` | `AuthServiceTest::test_register_creates_customer_with_M_number` |
| Çıktı kaçışı (XSS) | `TicketCreateTest::test_invalid_form_keeps_values_422`, `TicketDetailTest::test_reply_is_escaped` |
| `session_regenerate_id` | `AuthFlowTest::test_session_id_regenerated_on_login` |
| Her işlemde yetki kontrolü | `AuthFlowTest::test_customer_cannot_open_admin_page`, `TicketDetailTest::test_other_customer_gets_403_and_no_content`, `test_other_agent_cannot_reply_or_act`, `PoolTest::test_customer_cannot_see_pool_or_take`, `AdminPagesTest::test_agent_and_customer_cannot_open_admin_pages` |
| Güvenli dosya yükleme | `AttachmentServiceTest::test_rejects_php_disguised_as_jpg`, `test_stored_name_is_random_and_has_correct_extension` |
| Beklenmeyen hata bilgi sızdırmaz; bozuk veride dostça 500 | `KernelTest::test_wrong_shaped_data_gives_friendly_500_without_leaking_input`, `test_logged_in_user_with_corrupt_data_sees_friendly_500_page` |
| Örnek veri sunuma hazır | `SeedTest` (4 farklı anda: her durumdan ≥3, sarı ≥3 — 2 iş saati sonra da ≥3 —, kırmızı ≥3, havuz ≥3) |
| Yönetici listesi filtre + arama | `TicketServiceTest::test_search_all_filters_for_admin`, `AdminPagesTest::test_admin_list_filters` |
