# Gereksinim – Test İzlenebilirlik Tablosu (Aşama 2)

Gereksinim numaraları `docs/srs.md`'den. Testler `tests/` altında; hepsini çalıştırmak için `php vendor/bin/phpunit`, bir sınıfı adlarıyla görmek için `php vendor/bin/phpunit --testdox --filter <SınıfAdı>`.

Durum: ✅ test ile doğrulandı · 👁 elle doğrulandı · ⏭ Aşama 3 (MySQL / Boran)

## İşlevsel gereksinimler

| Gereksinim | Açıklama | Test sınıfı :: metot | Durum |
|---|---|---|---|
| FR-01 | Kategori, öncelik, başlık, mesajlı talep formu | `TicketCreateTest::test_customer_creates_ticket_and_is_redirected`, `test_create_form_lists_categories_and_priorities`, `test_invalid_form_keeps_values_422`; `TicketServiceTest::test_create_validation_errors` | ✅ |
| FR-02 | JPG/PNG/PDF + MP4/WebM, MIME ve boyut kontrolü | `AttachmentServiceTest::test_accepts_jpg_png_pdf`, `test_rejects_php_disguised_as_jpg`, `test_rejects_oversized_image`, `test_rejects_image_with_too_many_pixels_before_decoding`, `test_png_has_lower_pixel_limit_than_jpeg`, `test_accepts_mp4_and_webm_video`, `test_video_over_video_limit_rejected`, `test_php_ini_size_error_says_file_is_too_big`, `test_validate_many_checks_count_and_skips_empty_slots`; `AttachmentAccessTest::test_broken_upload_rejected_before_ticket_is_created` | ✅ |
| FR-03 | Anahtar kelimeyle akıllı öncelik (anahtar kelimeler öncelik seviyesinde, yönetici düzenler) | `PriorityDetectorTest`; `PriorityServiceTest::test_keywords_are_parsed_from_comma_text`; `TicketServiceTest::test_create_uses_keyword_priority`; `TicketDetailTest::test_priority_star_shown_only_when_raised` | ✅ |
| FR-04 | En az iş yüklü, aktif ve kapasitesi dolmamış personele otomatik atama; yer açılınca havuzdan atama | `AssignmentServiceTest` (7 test, `test_inactive_and_full_agents_are_skipped`, `test_all_full_leaves_ticket_in_pool` dahil); `TicketServiceTest::test_create_auto_assigns_least_loaded_and_logs`, `test_create_without_agents_stays_in_pool`, `test_resolving_a_ticket_pulls_oldest_pool_ticket`, `test_activating_agent_fills_pool`, `test_take_respects_capacity`; `JsonTicketRepositoryTest::test_concurrent_assign_cannot_exceed_capacity`; `SystemConfigTest::test_activating_agent_from_staff_page_assigns_pool_ticket` | ✅ |
| FR-05 | Mesai, öğle arası ve tatile duyarlı SLA bitişi (**çekirdek**) | `SlaCalculatorServiceTest::test_deadline_matches_case_table` (12 vaka: hocanın Cuma 17:30 örneği, öğle arası, molada/mesai öncesi/sonrası/hafta sonu/tatilde açılan, çok günlü), `test_corrupt_calendar_uses_default_hours`, `test_business_minutes_between_skips_break_and_weekend`; `TicketServiceTest::test_create_computes_deadline_with_policy`, `test_create_without_policy_uses_priority_default` | ✅ |
| FR-06 | State deseniyle durum geçişlerinin kısıtlanması | `TicketStateTest` (izinli 6, izinsiz 8 geçiş + mesaj); `TicketServiceTest::test_customer_cancels_pending_but_not_others_ticket`, `test_reject_increments_attempt_and_returns_to_review`; `TicketDetailTest::test_invalid_action_shows_reason` | ✅ |
| FR-07 | SLA %80 sarı, %100 kırmızı | `SlaStatusServiceTest` (13 test: tam %80, %80 altı, bitiş anı, çözülmüş/geç çözülmüş); `KanbanApiTest::test_sla_endpoint_returns_levels_for_visible_tickets_only` | ✅ |
| FR-08 | SLA aşımında müşteriye sorma, bekleme ve üst seviyeye otomatik eskalasyon | `EscalationTest` (9 test: havuza alma ve soru, aynı aşımın bir kez işlenmesi, "devam" ile ek SLA, "sorun çözüldü" ile kapanış, yanıtsızlıkta üst seviye, üst seviye yoksa yöneticiye kalma, bekleme 0 ile hemen eskalasyon, yalnız talep sahibinin kararı, kural ayarları) | ✅ |
| FR-09 | AJAX Kanban (sayfa yenilemeden) | `KanbanApiTest::test_kanban_page_shows_three_columns_with_cards`, `test_move_taken_to_in_review_returns_json`, `test_invalid_move_returns_422_with_reason`, `test_other_agents_ticket_returns_403_json`; `TicketServiceTest::test_move_on_kanban`; tarayıcıda sürükle-bırak ve mobil menü | ✅ 👁 |
| FR-10 | SLA kurallarının yönetimi (kategori × öncelik tablosu) | `CatalogServiceTest::test_set_sla_overrides_default_for_new_tickets_only`, `test_clearing_sla_falls_back_to_priority_default`, `test_sla_hours_out_of_range_rejected`; `SystemConfigTest::test_sla_grid_saves_and_clears` | ✅ |
| FR-11 | Tarih seçerek resmi tatil ekleme | `CalendarServiceTest::test_holiday_add_remove_and_affects_sla`, `test_duplicate_or_invalid_holiday_rejected`; `SystemConfigTest::test_calendar_rejects_no_working_day_and_keeps_old` | ✅ |
| FR-12 | Personel bazında SLA uyum oranı ve ortalama çözüm süresi | – | ⏭ |
| FR-13 | SLA hesabında yöneticinin girdiği mesai günleri ve saatleri | `CalendarServiceTest::test_saved_hours_change_sla`, `test_save_rejects_calendar_without_working_days_and_keeps_old`, `test_save_rejects_break_outside_day`, `test_empty_tables_use_legacy_file_until_admin_saves` | ✅ |
| FR-14 | Ek SLA (eskalasyon ve müşteri itirazında) | `EscalationTest::test_customer_continue_returns_to_same_agent_with_extra_sla`, `test_no_response_escalates_to_next_level_with_extra_sla`; `ResolutionTest::test_did_not_work_applies_extra_sla` | ✅ |
| FR-15, FR-16 | Puanlama, yöneticinin talep açması | – | ⏭ |
| FR-17, FR-21 | Çözüm onayı (İşe yaradı / İşe yaramadı), yanıtsız çözümün otomatik tamamlanması, 3. retten sonra eskalasyon | `ResolutionTest::test_customer_sees_worked_and_did_not_work_buttons`, `test_third_rejection_escalates_to_next_level`, `test_unanswered_resolution_is_auto_completed`, `test_resolution_is_not_auto_completed_early` | ✅ |
| FR-18 | Uygulama içi bildirimler | `NotificationTest` (5 test) | ✅ |
| FR-20 | Personel izinleri ve aktiflik | `AssignmentServiceTest::test_agent_on_leave_is_skipped`; `HierarchyAndLeaveTest::test_leave_page_adds_and_removes_and_agent_on_leave_gets_no_ticket` | ✅ |
| Hiyerarşi | Kategori bazlı hiyerarşi ve hiyerarşik atama/eskalasyon | `AssignmentServiceTest::test_hierarchy_prefers_lowest_level_then_next_level_when_full`, `test_above_level_skips_failing_level_and_lower`; `HierarchyAndLeaveTest` | ✅ |
| Manuel işlemler | Manuel eskalasyon, yöneticinin elle ataması (kapasite istisnası gerekçeli), çözülemez kapatma | `ResolutionTest::test_agent_escalates_manually_with_reason`, `test_admin_assigns_waiting_ticket_and_capacity_exception_needs_reason`, `test_admin_closes_as_unresolvable_with_reason` | ✅ |
| Talep kodu, işlem geçmişi | 6 karakterli kod, Günlük'te atama/yanıt/eskalasyon/SLA uzatma/kapanış | `TicketCodeTest`; `TicketServiceTest::test_created_ticket_gets_unique_code_and_is_found_by_it`, `test_assignment_and_reply_are_recorded_as_activity`; `TicketDetailTest::test_detail_and_lists_show_ticket_code_and_activity` | ✅ |
| Rapor, ek alanlar, yedek | Ayrıntılı rapor ve CSV, kategoriye özel ek alanlar, veri yedeği | `AdvancedAdminTest` (5 test) | ✅ |
| Dosya sınırları, güvenlik soruları | Yönetici ayarı, kendi güvenlik sorusu, şifre sıfırlama deneme sınırı | `SecurityAndLimitsTest` (4 test) | ✅ |
| FR-19 | Kronolojik durum geçmişi | `TicketServiceTest::test_create_auto_assigns_least_loaded_and_logs`, `test_full_flow_resolve_sets_resolved_at_and_approve_closes`; `TicketDetailTest::test_owner_sees_messages_timeline_and_sla`, `test_agent_works_and_customer_rejects_then_approves` | ✅ |
| FR-22 | Personelin soru hakkı | `is_question` alanı hazır | ⏭ |

## Kullanıcı hikayeleri (Aşama 2'de karşılananlar)

| Hikaye | Test | Durum |
|---|---|---|
| US-01, US-05 talep açma ve öncelik | `TicketCreateTest` | ✅ |
| US-02 ek dosya | `AttachmentServiceTest`, `AttachmentAccessTest` | ✅ |
| US-03 taleplerin durumunu listeleme (arama + filtre) | `TicketCreateTest::test_list_filter_and_search`, `test_list_shows_only_own_tickets`; `TicketServiceTest::test_search_*` | ✅ |
| US-04, US-10 yanıtlaşma (Çözüldü durumunda sıra müşteride; SLA aşılmış talebe yazmak serbest) | `TicketServiceTest::test_reply_rules`, `test_agent_cannot_reply_to_resolved_but_can_to_breached`; `TicketDetailTest::test_agent_works_and_customer_rejects_then_approves`, `test_reply_is_escaped`, `test_empty_reply_shows_error`, `test_agent_cannot_reply_to_resolved_but_customer_can` | ✅ |
| US-06 ad-soyad-şifre-gizli soru ile kayıt, şifre sıfırlama | `AuthServiceTest` (10 test); `AuthFlowTest::test_register_shows_customer_number_then_login_works`, `test_forgot_password_flow` | ✅ |
| US-07 bekleyen talebi iptal | `TicketDetailTest::test_customer_cancels_pending_ticket` | ✅ |
| US-08, US-09 Kanban (taşımadan önce onay penceresi) | `KanbanApiTest`; tarayıcıda onay, vazgeçince kartın geri dönmesi | ✅ 👁 |
| US-15 personel hesabı yönetimi | `StaffServiceTest`; `SystemConfigTest::test_staff_create_shows_P_number`, `test_inactive_agent_with_open_tickets_shows_warning`; `AuthFlowTest::test_inactive_user_cannot_login_with_message`, `test_deactivated_user_session_ends_on_next_request` | ✅ |
| US-17, US-18, US-20 SLA, tatil ve mesai yönetimi | `CatalogServiceTest`, `CalendarServiceTest`, `SystemConfigTest` | ✅ |
| US-11, US-12 SLA geri sayımı ve renkli uyarı | `SlaStatusServiceTest`, `KanbanApiTest::test_sla_endpoint_*` | ✅ |

## İşlevsel olmayan gereksinimler

| Gereksinim | Açıklama | Test / doğrulama | Durum |
|---|---|---|---|
| NFR-01 | 5 yıllık tatil verisiyle SLA hesabı < 500 ms | `SlaCalculatorServiceTest::test_five_years_of_holidays_is_fast` (1000 hesap toplamı < 500 ms) | ✅ |
| NFR-02 | 100 aktif talepte Kanban < 1 sn | `KanbanPerformanceTest::test_kanban_with_100_active_tickets_renders_under_one_second` | ✅ |
| NFR-03 | Ekler genel URL'den indirilemez, yalnız yetkiliye | `AttachmentAccessTest::test_other_customers_attachment_403`, `test_unknown_attachment_404_and_guest_redirect`, `test_owner_downloads_with_safe_headers` (dosyalar `storage/uploads`, web kökü dışında) | ✅ |
| NFR-04 | Aynı talebi iki personel aynı anda alamaz | `JsonStoreTest::test_transaction_conditional_update_only_first_wins`; `JsonTicketRepositoryTest::test_assign_if_unassigned_second_call_fails`, `test_save_if_status_*`; `TicketServiceTest::test_take_twice_second_conflicts`, `test_perform_on_stale_ticket_conflicts_instead_of_overwriting`; `PoolTest::test_second_take_shows_conflict_message` | ✅ |
| NFR-05 | 375–1920 px'te yatay kaydırma yok | Tarayıcıda 375, 768 ve 1280 px: ana sayfa, giriş, Taleplerim, talep formu, detay, Kanban, özet rapor, Sistem Yapılandırması ekranları (`scrollWidth == innerWidth`) | 👁 |
| NFR-06 | Her form CSRF jetonlu; geçersizse 403 | `CsrfTest`; `KernelTest::test_post_without_csrf_returns_403`; `KanbanApiTest::test_missing_csrf_header_403`; `AuthFlowTest::test_csrf_token_is_rotated_on_login` | ✅ |
| NFR-07 | Takvim JSON'u bozuk veya silinmiş olsa da çökmeden varsayılan mesai | `JsonCalendarRepositoryTest::test_missing_file_falls_back_to_default_and_logs`, `test_corrupt_file_falls_back_to_default_and_logs`, `test_calendar_without_any_working_hours_falls_back_to_default`, `test_bom_prefixed_file_is_read`; `SlaCalculatorServiceTest::test_corrupt_calendar_uses_default_hours` | ✅ |
| NFR-08 | Görseller en fazla 1920×1080'e küçültülür | `AttachmentServiceTest::test_large_png_is_downscaled_to_1920x1080`; tarayıcıda 2400×1600 → 1620×1080 | ✅ 👁 |
| NFR-09 | PSR-12, PHPStan 0 hata | `php vendor/bin/phpstan analyse` (seviye 6) → `[OK] No errors` | ✅ |
| NFR-10 | 30 dk hareketsizlikte oturum kapanır | `AuthFlowTest::test_idle_30_minutes_logs_out`, `test_idle_timeout_on_form_post_redirects_to_login_not_csrf_error`, `test_login_form_posted_after_idle_expiry_still_logs_in`; `KanbanApiTest::test_sla_polling_does_not_keep_session_alive` | ✅ |
| NFR-11 | 50 MB video, 5 MB görsel/PDF sınırı | `AttachmentServiceTest::test_accepts_mp4_and_webm_video`, `test_video_over_video_limit_rejected` (`settings.json` `max_video_bytes`); `public/.htaccess` ve README'deki `-d` ayarları PHP sınırını 50 MB'a çıkarır | ✅ (50 MB'lık gerçek video yüklemesi süre açısından elle ölçülmedi) |

## Güvenlik (hocanın ortak şartları)

| Şart | Test |
|---|---|
| Şifre kuralı (en az 8 karakter, harf, rakam ve noktalama) | `PasswordPolicyTest`; `AuthFlowTest::test_register_rejects_weak_password_and_keeps_values`, `test_register_page_shows_live_password_rules`; `SystemConfigTest::test_staff_create_shows_P_number` |
| `password_hash` | `AuthServiceTest::test_register_creates_customer_with_M_number` |
| Çıktı kaçışı (XSS) | `TicketCreateTest::test_invalid_form_keeps_values_422`, `TicketDetailTest::test_reply_is_escaped` |
| `session_regenerate_id` | `AuthFlowTest::test_session_id_regenerated_on_login` |
| Her işlemde yetki kontrolü | `SystemConfigTest::test_non_admin_gets_403_on_every_config_page`, `AuthFlowTest::test_customer_cannot_open_admin_page`, `TicketDetailTest::test_other_customer_gets_403_and_no_content`, `test_other_agent_cannot_reply_or_act`, `PoolTest::test_customer_cannot_see_pool_or_take`, `AdminPagesTest::test_agent_and_customer_cannot_open_admin_pages` |
| Güvenli dosya yükleme | `AttachmentServiceTest::test_rejects_php_disguised_as_jpg`, `test_stored_name_is_random_and_has_correct_extension`; `BrandingServiceTest::test_rejects_fake_png_and_large_file_and_keeps_old_logo` |
| Yönetici hesabı yalnız komut satırından | `AuthFlowTest::test_create_admin_script_refuses_web` |
| Yönetici değişiklikleri kayıt altında | `AuditLoggerTest`, `SettingsServiceTest::test_update_records_old_and_new_value`, `SystemConfigTest::test_audit_page_lists_changes` |
| Beklenmeyen hata bilgi sızdırmaz; bozuk veride dostça 500 | `KernelTest::test_wrong_shaped_data_gives_friendly_500_without_leaking_input`, `test_logged_in_user_with_corrupt_data_sees_friendly_500_page` |
| Örnek veri sunuma hazır | `SeedTest` (4 farklı anda: her durumdan ≥3, sarı ≥3 — 2 iş saati sonra da ≥3 —, kırmızı ≥3, havuz ≥3; 5 öncelik, takvim tabloları, ayarlar ve kapasite yüzünden havuzda bekleyen talepler) |
| Yönetici listesi filtre + arama | `TicketServiceTest::test_search_all_filters_for_admin`, `AdminPagesTest::test_admin_list_filters` |
