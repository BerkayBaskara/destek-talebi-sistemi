# Gereksinim – Test İzlenebilirlik Tablosu (Aşama 2)

Gereksinim numaraları `docs/srs.md`'den. Testler `tests/` altında; hepsini çalıştırmak için `php vendor/bin/phpunit`, bir sınıfı adlarıyla görmek için `php vendor/bin/phpunit --testdox --filter <Sınıf>`. Ekibin test planındaki 344 senaryonun tek tek eşlemesi `docs/test-senaryolari.md` dosyasındadır.

Durum: ✅ test ile doğrulandı · 👁 elle doğrulandı · ◐ kısmen ya da farklı bir tasarımla karşılandı (açıklama satırda) · ⏭ Aşama 3 (MySQL) · ✖ uygulanmadı (gerekçe satırda)

## İşlevsel gereksinimler

| Gereksinim | Açıklama | Test sınıfı :: metot | Durum |
|---|---|---|---|
| FR-01 | Talep oluşturma | `TicketCreateTest::test_customer_creates_ticket_and_is_redirected`, `test_invalid_form_keeps_values_422`; `TicketServiceTest::test_create_computes_deadline_with_policy` | ✅ |
| FR-02 | Dosya ekleme (JPG, PNG, PDF, MP4, WebM; tür içerikten, boyut ayardan) | `AttachmentServiceTest` (20 test); `SecurityAndLimitsTest::test_admin_changes_file_limits_and_ticket_form_follows` | ✅ |
| FR-03 | Anahtar kelimeyle otomatik öncelik | `PriorityDetectorTest`; `TicketServiceTest::test_create_uses_keyword_priority` | ✅ |
| FR-04 | Otomatik personel atama | `AssignmentServiceTest`; `TicketServiceTest::test_create_auto_assigns_least_loaded_and_logs`, `test_resolving_a_ticket_pulls_oldest_pool_ticket` | ✅ |
| FR-05 | Mesai, öğle arası ve tatile duyarlı SLA hesabı | `SlaCalculatorServiceTest::test_deadline_matches_case_table`; `SlaEdgeCasesTest` (yıl değişimi, artık yıl, saat dilimi) | ✅ |
| FR-06 | State deseniyle durum yönetimi | `TicketStateTest`; `TicketServiceTest::test_customer_cancels_pending_but_not_others_ticket` | ✅ |
| FR-07 | SLA %80 sarı, %100 kırmızı | `SlaStatusServiceTest`; `KanbanApiTest::test_sla_endpoint_returns_levels_for_visible_tickets_only` | ✅ |
| FR-08 | SLA ihlalinde eskalasyon (önce müşteriye sorulur, varsayılan 1 iş günü beklenir; süre yöneticiye açık) | `EscalationTest` (14 test) | ✅ |
| FR-09 | AJAX Kanban | `KanbanApiTest` | ✅ |
| FR-10 | SLA kurallarının yönetimi | `CatalogServiceTest`; `SystemConfigTest::test_sla_grid_saves_and_clears` | ✅ |
| FR-11 | Resmî tatil yönetimi | `CalendarServiceTest::test_holiday_add_remove_and_affects_sla`, `test_duplicate_or_invalid_holiday_rejected` | ✅ |
| FR-12 | Raporlama (dağılımlar, uyarı ve ihlaller, iş yükü, ortalama süre, uyum oranı) | `ReportServiceTest`; `AdvancedAdminTest::test_report_with_filters_and_per_agent_stats`; `DetailedReportServiceTest` | ✅ |
| FR-13 | Şirkete özel çalışma takvimi | `CalendarServiceTest`; `CalendarAdvancedTest` | ✅ |
| FR-13.1 | Takvim dosyası yükleme (JSON) | `CalendarAdvancedTest::test_calendar_file_import_replaces_calendar_and_rejects_invalid_file` | ✅ |
| FR-13.2 | Takvimi görüntüleme ve gün bazında düzenleme | `CalendarAdvancedTest::test_month_view_shows_day_types`, `test_special_day_validation`, `test_saturday_special_working_day_counts` — aylık görünüm var, ayrı yıllık görünüm yok | ◐ |
| FR-13.3 | Takvime göre SLA | `CalendarServiceTest::test_saved_hours_change_sla`; `CalendarAdvancedTest::test_half_day_shortens_sla_window` | ✅ |
| FR-13.4 | Takvim değişikliğinin açık taleplere uygulanması (yönetici seçer) | `CalendarAdvancedTest::test_calendar_change_applies_to_open_tickets_only_when_enabled` | ✅ |
| FR-13.5 | Takvim doğrulama | `CalendarServiceTest::test_save_rejects_calendar_without_working_days_and_keeps_old`, `test_half_filled_break_is_rejected`; `JsonCalendarRepositoryTest::test_break_outside_day_is_rejected` | ✅ |
| FR-14 | Ek SLA | `ResolutionTest::test_did_not_work_applies_extra_sla`; `EscalationTest::test_customer_continue_returns_to_same_agent_with_extra_sla` | ✅ |
| FR-15 | Performans puanı (%100 / %70 / %40, eskalasyon bonusu %20) | `ScoreAndRestoreTest::test_score_depends_on_attempts_and_escalation_bonus` | ✅ |
| FR-16 | Yöneticinin talep açması | `ConfigCompletionTest::test_admin_opens_ticket_for_customer_and_for_self`, `test_agent_cannot_open_ticket` | ✅ |
| FR-17 | Çözüm onayı ve otomatik tamamlama | `ResolutionTest::test_unanswered_resolution_is_auto_completed`, `test_resolution_is_not_auto_completed_early`; `ScenarioCoverageTest::test_auto_complete_runs_once` | ✅ |
| FR-18 | Uygulama içi bildirimler | `NotificationTest`; `ScenarioGapsTest`; `ConfigCompletionTest::test_disabled_notification_type_is_not_sent` | ✅ |
| FR-19 | Talep işlem geçmişi | `TicketServiceTest::test_assignment_and_reply_are_recorded_as_activity`; `TicketDetailTest::test_detail_and_lists_show_ticket_code_and_activity` | ✅ |
| FR-20 | Aktiflik, izin, yetkinlik ve kapasite kontrolü | `AssignmentServiceTest::test_inactive_and_full_agents_are_skipped`, `test_agent_on_leave_is_skipped`; `HierarchyAndLeaveTest` | ✅ |
| FR-21 | Müşteri çözüm geri bildirimi | `ResolutionTest::test_customer_sees_worked_and_did_not_work_buttons` | ✅ |
| FR-22 | Personelin soru hakkı (varsayılan 2), soru SLA'yı durdurur | `QuestionTest` | ✅ |
| FR-23 | Müşteri kaydı | `AuthFlowTest::test_register_shows_customer_number_then_login_works`; `AuthServiceTest::test_register_validation_messages` | ✅ |
| FR-24 | Personel hesabını yalnız yönetici açar | `StaffServiceTest::test_create_staff_gets_P_number_and_policy`; `SystemConfigTest::test_non_admin_gets_403_on_every_config_page` | ✅ |
| FR-25 | İlk yönetici komut satırından | `AuthFlowTest::test_create_admin_script_refuses_web`; `php bin/create-admin.php` elle çalıştırıldı | ✅ 👁 |
| FR-26 | M, P, Y önekli benzersiz numara | `ScenarioCoverageTest::test_role_prefixes`, `test_generated_numbers_are_unique_and_prefixed` | ✅ |
| FR-27 | Güvenlik sorusuyla parola kurtarma | `AuthFlowTest::test_forgot_password_flow` | ✅ |
| FR-28 | Yönetici listesinden soru seçme ya da kendi sorusunu yazma (kayıtta) | `SecurityAndLimitsTest::test_customer_registers_with_own_question_and_reset_shows_it` | ✅ |
| FR-29 | Güvenlik sorusunda %90 benzerlik | Kurtarmada kayıtlı soru ekranda gösterilir, kullanıcı yalnız cevabı yazar; karşılaştırılacak serbest soru metni olmadığı için benzerlik ölçümü yok | ✖ |
| FR-30 | Cevabı normalize edip hash ile karşılaştırma | `AuthServiceTest::test_reset_password_with_answer_case_insensitive`, `test_reset_password_turkish_uppercase_answer`, `test_reset_password_wrong_answer_returns_false` | ✅ |
| FR-31 | Kurtarma deneme sınırı | `SecurityAndLimitsTest::test_reset_is_blocked_after_five_wrong_answers_for_configured_minutes` | ✅ |
| FR-32 | Güvenlik sorularının yönetimi | `SecurityAndLimitsTest::test_admin_edits_security_questions_and_register_lists_them` | ✅ |
| FR-33 | Kullanıcı ve personel aktiflik yönetimi | `CustomerAccountsTest`; `StaffServiceTest::test_deactivate_and_activate_agent`; `AuthFlowTest::test_inactive_user_cannot_login_with_message` | ✅ |
| FR-34 | 6 karakterli talep kodu | `TicketCodeTest`; `TicketServiceTest::test_created_ticket_gets_unique_code_and_is_found_by_it` | ✅ |
| FR-35 | Dahili sayısal kimlik koddan ayrı | `TicketCodeTest::test_new_fields_round_trip_and_old_rows_still_load` | ✅ |
| FR-36, FR-38 | Dinamik form, kategoriye bağlı | Form, kategorinin ek alanlarıdır: `AdvancedAdminTest::test_category_extra_field_is_required_shown_and_stored` | ✅ |
| FR-37 | Form alanı ekleme, düzenleme, silme; tür, etiket, zorunluluk, kurallar | `SubcategoryAndFieldTypesTest::test_select_email_and_max_length_fields_are_validated`; `ScenarioGapsTest::test_duplicate_field_is_rejected_and_removed_field_keeps_old_values` | ✅ |
| FR-39, FR-40 | Formu doldurma ve doğrulama | `ScenarioCoverageTest::test_number_field_rejects_text`; `SubcategoryAndFieldTypesTest::test_select_email_and_max_length_fields_are_validated` | ✅ |
| FR-41 | Form cevaplarını talep ile saklama | `AdvancedAdminTest::test_category_extra_field_is_required_shown_and_stored` | ✅ |
| FR-42 | Form sürüm geçmişi | Ayrı sürüm numarası tutulmaz; her talep doldurulduğu andaki alan etiketleri ve değerleriyle saklanır, form değişikliği eski talebi değiştirmez: `ScenarioGapsTest::test_duplicate_field_is_rejected_and_removed_field_keeps_old_values` | ◐ |
| FR-43 | Kategori bazlı personel hiyerarşisi | `HierarchyAndLeaveTest::test_admin_sets_levels_and_new_ticket_goes_to_lowest_level`, `test_invalid_level_is_rejected_and_nothing_saved` | ✅ |
| FR-44 | Personel yetkinliği (hiyerarşiye eklenen kategori) | `HierarchyAndLeaveTest::test_agent_outside_category_hierarchy_cannot_take_pool_ticket` | ✅ |
| FR-45, FR-46 | Kapasite ve iş yükü | `StaffServiceTest::test_capacity_validation`; `JsonTicketRepositoryTest::test_count_active_by_agent`, `test_concurrent_assign_cannot_exceed_capacity` | ✅ |
| FR-47 | İzin ve müsaitlik | `HierarchyAndLeaveTest::test_leave_page_adds_and_removes_and_agent_on_leave_gets_no_ticket`; `ScenarioCoverageTest::test_future_and_finished_leave_do_not_block_assignment` | ✅ |
| FR-48 | Hiyerarşik otomatik atama | `AssignmentServiceTest::test_hierarchy_prefers_lowest_level_then_next_level_when_full`, `test_above_level_skips_failing_level_and_lower` | ✅ |
| FR-49 | Hareketsiz personelin pasif yapılması | `ConfigCompletionTest::test_inactive_staff_is_deactivated_after_configured_days`; `ScenarioCoverageTest::test_recently_active_staff_is_not_deactivated` | ✅ |
| FR-50 | İşten ayrılan personel | `StaffServiceTest::test_deactivate_and_activate_agent`; `SystemConfigTest::test_inactive_agent_with_open_tickets_shows_warning` | ✅ |
| FR-51, FR-52 | Manuel atama, gerekçeli kapasite istisnası | `ResolutionTest::test_admin_assigns_waiting_ticket_and_capacity_exception_needs_reason` | ✅ |
| FR-53 | Kategori bazlı SLA | `CatalogServiceTest::test_set_sla_overrides_default_for_new_tickets_only` | ✅ |
| FR-54 | SLA ihlali takibi (cron yok; her istekte en çok dakikada bir, `php bin/sla-monitor.php` ile elle) | `EscalationTest::test_breach_puts_ticket_on_hold_in_pool_and_asks_customer`; `SlaEdgeCasesTest::test_job_lock_allows_one_run_per_interval_and_expires` | ✅ |
| FR-55 | Manuel eskalasyon | `ResolutionTest::test_agent_escalates_manually_with_reason`; `ScenarioCoverageTest::test_customer_cannot_escalate_and_only_admin_closes_as_unresolvable` | ✅ |
| FR-56 | Hiyerarşiyle eskalasyon ataması | `EscalationTest::test_no_response_escalates_to_next_level_with_extra_sla` | ✅ |
| FR-57 | Uygun personel yoksa havuz + yönetici bildirimi | `EscalationTest::test_no_upper_level_leaves_ticket_for_admin` | ✅ |
| FR-58 | Eskalasyon geçmişi (neden, önceki/yeni personel, tarih) | `ScenarioGapsTest::test_report_lists_escalations`; `ResolutionTest::test_asking_for_another_agent_escalates_to_next_level` | ✅ |
| FR-59 | Ek SLA ilk SLA'dan ayrı saklanır | `ScenarioCoverageTest::test_extra_sla_accumulates_and_first_deadline_is_kept`; `DetailedReportServiceTest::test_sla_compliance_uses_first_deadline` | ✅ |
| FR-60 | SLA'sı dolmuş ya da çözülmüş talebe personel yanıtı engellenir | `TicketServiceTest::test_agent_cannot_reply_to_breached_or_resolved`; `ScenarioCoverageTest::test_agent_sees_why_breached_ticket_cannot_be_answered` | ✅ |
| FR-61, FR-62 | Çözüm onayı arayüzü ve geri bildirim | `ResolutionTest::test_customer_sees_worked_and_did_not_work_buttons`; `TicketDetailTest::test_agent_works_and_customer_rejects_then_approves` | ✅ |
| FR-63 | Aynı personelle devam ya da başka personel | `ResolutionTest::test_did_not_work_offers_same_or_another_agent`; `EscalationTest::test_customer_continue_returns_to_same_agent_with_extra_sla` | ✅ |
| FR-64 | Başka personel isteğinde eskalasyon | `ResolutionTest::test_asking_for_another_agent_escalates_to_next_level`, `test_another_agent_request_needs_owner_and_resolved_status`, `test_third_rejection_escalates_to_next_level` | ✅ |
| FR-65, FR-66 | Otomatik tamamlama ve süresinin yönetimi | `ResolutionTest::test_unanswered_resolution_is_auto_completed`; `EscalationTest::test_admin_changes_sla_rules_with_validation` | ✅ |
| FR-67 | Ek SLA ilk SLA'nın üçte biri (oran yöneticide) | `ResolutionTest::test_did_not_work_applies_extra_sla`; `EscalationTest::test_admin_changes_sla_rules_with_validation` | ✅ |
| FR-68, FR-69 | Gerekçeli "çözülemedi" kapanışı ve kapanış türü | `ResolutionTest::test_admin_closes_as_unresolvable_with_reason`; `ScenarioCoverageTest::test_unresolvable_close_needs_reason_of_at_least_five_characters` | ✅ |
| FR-70 | Yeni atama bildirimi | `NotificationTest::test_assignment_notifies_agent_and_bell_shows_count` | ✅ |
| FR-71 | SLA uyarı ve ihlal bildirimleri | `ScenarioGapsTest::test_sla_warning_notification_is_sent_once`; `EscalationTest::test_breach_puts_ticket_on_hold_in_pool_and_asks_customer` | ✅ |
| FR-72 | Eskalasyon ve çözüm bildirimleri | `EscalationTest::test_no_response_escalates_to_next_level_with_extra_sla`; `NotificationTest::test_replies_and_resolution_notify_the_other_side` | ✅ |
| FR-73 | Yönetici müdahale bildirimi | `EscalationTest::test_no_upper_level_leaves_ticket_for_admin` | ✅ |
| FR-74 | Bildirim okundu / okunmadı | `NotificationTest::test_mark_all_read`; `ScenarioGapsTest::test_notification_can_be_marked_unread_and_reading_twice_is_consistent` | ✅ |
| FR-75 | Site adı ve logo | `SystemConfigTest::test_site_title_change_appears_in_header`; `BrandingServiceTest` | ✅ |
| FR-76 | Ana sayfa metni ve görseli | `UiTest::test_guest_sees_public_home_with_admin_text`; `ConfigCompletionTest::test_home_image_upload_shows_on_home_page` | ✅ |
| FR-77 | Öncelikler ve varsayılan SLA süreleri | `PriorityServiceTest`; `SystemConfigTest::test_priority_default_sla_change_applies_to_new_ticket_in_seeded_category` | ✅ |
| FR-78 | Kategori (alt kategori dahil) ve personel yönetimi | `CatalogServiceTest`; `SubcategoryAndFieldTypesTest::test_subcategory_is_created_under_parent_and_listed_with_path`; `ConfigCompletionTest::test_admin_edits_staff_name` | ✅ |
| FR-79 | Dosya limitleri ve otomatik tamamlama süresi | `SecurityAndLimitsTest::test_admin_changes_file_limits_and_ticket_form_follows`; `EscalationTest::test_admin_changes_sla_rules_with_validation` | ✅ |
| FR-80 | Ayrıntılı rapor (talep, personel puanı, SLA, eskalasyon) | `AdvancedAdminTest::test_report_with_filters_and_per_agent_stats`; `ScoreAndRestoreTest::test_score_depends_on_attempts_and_escalation_bonus`; `ScenarioGapsTest::test_report_lists_escalations` | ✅ |
| FR-81 | Rapor filtreleri | `ScenarioCoverageTest::test_report_date_and_combined_filters`; `AdvancedAdminTest::test_report_with_filters_and_per_agent_stats` | ✅ |
| FR-82 | CSV dışa aktarma | `AdvancedAdminTest::test_csv_export_uses_same_filters`, `test_csv_cells_are_protected_against_formula_injection` | ✅ |
| FR-83 | SSE ile canlı rapor | Yerleşik PHP sunucusu tek iş parçacıklıdır, açık SSE bağlantısı diğer istekleri bekletir; SLA durumu ve bildirimler kısa aralıklı sorguyla sayfa yenilenmeden güncellenir (`KanbanApiTest`, `NotificationTest::test_notification_poll_does_not_keep_session_alive`) | ✖ |
| FR-84, FR-85, FR-86 | MySQL, JSON'dan aktarım, migration | Aşama 3 | ⏭ |
| FR-87, FR-88 | Yedekleme ve geri yükleme | `AdvancedAdminTest::test_backup_download_is_zip_and_admin_only`; `ScoreAndRestoreTest::test_restore_brings_back_data_and_keeps_automatic_backup`, `test_invalid_backup_is_rejected_and_data_untouched`, `test_agent_cannot_restore` | ✅ |
| FR-89 | Zamanlanmış işler (SLA, eskalasyon, otomatik tamamlama, pasifleştirme) | `EscalationTest`; `ResolutionTest::test_unanswered_resolution_is_auto_completed`; `ConfigCompletionTest::test_inactive_staff_is_deactivated_after_configured_days` | ✅ |
| FR-90 | Aynı işin iki kez çalışmaması | `SlaEdgeCasesTest::test_job_lock_allows_one_run_per_interval_and_expires`; `EscalationTest::test_same_breach_is_handled_once` | ✅ |
| FR-91 | Denetim kayıtları | `AuditLoggerTest`; `SettingsServiceTest::test_update_records_old_and_new_value`; `SystemConfigTest::test_audit_page_lists_changes` | ✅ |

## İşlevsel olmayan gereksinimler

| Gereksinim | Açıklama | Test / doğrulama | Durum |
|---|---|---|---|
| NFR-01 | 5 yıllık tatil verisiyle SLA hesabı < 500 ms | `SlaCalculatorServiceTest::test_five_years_of_holidays_is_fast` | ✅ |
| NFR-02 | 100 aktif talepte Kanban < 1 sn, taşıma < 200 ms | `KanbanPerformanceTest::test_kanban_with_100_active_tickets_renders_under_one_second`; `PerformanceScenarioTest::test_kanban_move_is_fast` | ✅ |
| NFR-03 | Ekler yalnız yetkiliye, 1 sn içinde | `AttachmentAccessTest` | ✅ |
| NFR-04 | Aynı talebi iki personel alamaz; ikincisi 100 ms içinde reddedilir | `JsonTicketRepositoryTest::test_assign_if_unassigned_second_call_fails`; `PerformanceScenarioTest::test_second_take_is_rejected_quickly` | ✅ |
| NFR-05 | 375–1920 px'te yatay kaydırma yok | Tarayıcıda 375, 768 ve 1920 px genişlikte ekranlar (`scrollWidth == innerWidth`) | 👁 |
| NFR-06 | CSRF; geçersizse 50 ms içinde 403 | `CsrfTest`; `KernelTest::test_post_without_csrf_returns_403`; `PerformanceScenarioTest::test_invalid_csrf_is_rejected_quickly` | ✅ |
| NFR-07 | Bozuk takvimde 100 ms içinde varsayılana geçiş | `JsonCalendarRepositoryTest::test_corrupt_file_falls_back_to_default_and_logs`; `PerformanceScenarioTest::test_corrupt_calendar_falls_back_quickly` | ✅ |
| NFR-08 | Dosya işleme güvenliği, 1920×1080'e küçültme | `AttachmentServiceTest::test_large_png_is_downscaled_to_1920x1080`, `test_double_extension_is_stored_safely_or_rejected`, `test_corrupt_image_is_rejected` | ✅ |
| NFR-09 | Statik analiz ve sözdizimi | `php vendor/bin/phpstan analyse` (seviye 6) → `[OK] No errors` | ✅ |
| NFR-10 | Oturum güvenliği, 30 dk hareketsizlik (süre yöneticide) | `AuthFlowTest::test_session_id_regenerated_on_login`, `test_idle_30_minutes_logs_out`; `ConfigCompletionTest::test_admin_changes_attempt_limit_and_idle_timeout` | ✅ |
| NFR-11 | Dosya yükleme performansı | `AttachmentServiceTest::test_downscale_finishes_within_two_seconds`, `test_large_video_is_stored_quickly` | ✅ |
| NFR-12 | Parola ve cevaplar `password_hash` ile | `AuthServiceTest::test_register_creates_customer_with_M_number`; `PasswordPolicyTest` | ✅ |
| NFR-13 | Sunucu tarafında rol denetimi | `ScenarioCoverageTest::test_non_admin_cannot_post_admin_actions`; `SystemConfigTest::test_non_admin_gets_403_on_every_config_page` | ✅ |
| NFR-14 | IDOR koruması | `TicketDetailTest::test_other_customer_gets_403_and_no_content`; `AttachmentAccessTest::test_other_customers_attachment_403`; `NotificationTest::test_opening_notification_marks_read_and_others_cannot_open_it` | ✅ |
| NFR-15 | SQL Injection | Aşama 2'de SQL yok (JSON depolama); parametreli sorgular Aşama 3'te | ⏭ |
| NFR-16 | XSS koruması | `TicketDetailTest::test_reply_is_escaped`; `ScenarioCoverageTest::test_script_and_event_attributes_are_shown_harmless` | ✅ |
| NFR-19 | Brute-force (giriş ve kurtarma) | `ConfigCompletionTest::test_login_is_blocked_after_configured_failed_attempts`; `SecurityAndLimitsTest::test_reset_is_blocked_after_five_wrong_answers_for_configured_minutes` | ✅ |
| NFR-20 | Denetim kayıtları değiştirilemez | `AuditLoggerTest::test_repository_has_no_update_or_delete` | ✅ |
| NFR-22 | CSV formül enjeksiyonu | `AdvancedAdminTest::test_csv_cells_are_protected_against_formula_injection` | ✅ |
| NFR-23, NFR-24 | İlişkisel bütünlük, 3NF | Aşama 3 (MySQL şeması) | ⏭ |
| NFR-25 | Transaction | JSON depoda tablo kilitli işlem: `JsonStoreTest::test_transaction_conditional_update_only_first_wins`; MySQL transaction Aşama 3'te | ◐ |
| NFR-26 | Eşzamanlılık (çift atama ve kapasite aşımı yok) | `JsonTicketRepositoryTest::test_concurrent_assign_cannot_exceed_capacity`; `TicketServiceTest::test_perform_on_stale_ticket_conflicts_instead_of_overwriting` | ✅ |
| NFR-27 | Idempotency | `EscalationTest::test_same_breach_is_handled_once`; `ScenarioCoverageTest::test_auto_complete_runs_once`, `test_other_customer_cannot_approve_and_double_approval_is_refused` | ✅ |
| NFR-28 | Veri taşıma güvenilirliği | Aşama 3 | ⏭ |
| NFR-29 | Yedeklerin geri yüklenebilmesi | `ScoreAndRestoreTest::test_restore_brings_back_data_and_keeps_automatic_backup` | ✅ |
| NFR-30 | Hata yönetimi, bilgi sızdırmama | `KernelTest::test_wrong_shaped_data_gives_friendly_500_without_leaking_input`, `test_logged_in_user_with_corrupt_data_sees_friendly_500_page` | ✅ |
| NFR-31 | Zamanlanmış iş güvenilirliği | `SlaEdgeCasesTest::test_job_lock_allows_one_run_per_interval_and_expires`; `ScenarioCoverageTest::test_monitor_does_nothing_before_deadline` | ✅ |
| NFR-32 | Veritabanı performansı | Aşama 3 | ⏭ |
| NFR-33 | Sayfa başına en fazla 50 kayıt | `PaginationTest`; `CustomerAccountsTest::test_list_is_paginated_by_fifty`; `TicketServiceTest::test_search_filters_by_status_and_text_and_paginates` | ✅ |
| NFR-34 | 10.000 talepte rapor | `PerformanceScenarioTest::test_report_with_ten_thousand_tickets` | ✅ |
| NFR-35 | SSE güvenilirliği | FR-83 ile aynı gerekçe; canlı veri uçları oturumsuz isteğe 401 verir (`KanbanApiTest::test_api_without_session_returns_401_json`) | ✖ |
| NFR-36 | Kullanılabilirlik, açık hata mesajları | Form hataları alan altında, değerler korunur: `TicketCreateTest::test_invalid_form_keeps_values_422`, `TicketDetailTest::test_invalid_action_shows_reason`; 5 kullanıcılı deneme sunumda | ◐ |
| NFR-37 | Katmanlı mimari | Controller → Service → Repository arayüzü → JSON deposu; iş kuralları servislerde (`docs/asama-2/tasarim-belgesi.md`) | ✅ |
| NFR-38 | Otomatik kod standardı denetimi | PSR-12'ye uygun yazıldı; otomatik denetim aracı (PHP_CodeSniffer) henüz projeye eklenmedi | ◐ |
| NFR-39, NFR-40 | Birim ve entegrasyon testleri | `tests/Unit` (servis, depo, model), `tests/Feature` (uçtan uca istek) | ✅ |
| NFR-41 | Tekrarlanabilir test ortamı | Her test geçici bir klasörde boş veriyle ve sabit saatle (`FixedClock`) başlar: `tests/Feature/FeatureTestCase.php` | ✅ |
| NFR-42 | Tarayıcı uyumluluğu | Chromium tabanlı tarayıcıda masaüstü ve 375 px mobil görünüm denendi; Firefox ve Safari denenmedi | ◐ |
| NFR-43 | Yapılandırılabilirlik | `SettingsServiceTest`; `ConfigCompletionTest`; `QuestionTest::test_question_limit_is_enforced_and_configurable` | ✅ |

## Güvenlik (hocanın ortak şartları)

| Şart | Test |
|---|---|
| Şifre kuralı (en az 8 karakter, harf, rakam ve noktalama) | `PasswordPolicyTest`; `AuthFlowTest::test_register_rejects_weak_password_and_keeps_values`, `test_register_page_shows_live_password_rules` |
| `password_hash` | `AuthServiceTest::test_register_creates_customer_with_M_number` |
| Çıktı kaçışı (XSS) | `TicketDetailTest::test_reply_is_escaped`; `ScenarioCoverageTest::test_script_and_event_attributes_are_shown_harmless` |
| `session_regenerate_id` | `AuthFlowTest::test_session_id_regenerated_on_login`; `NativeSessionTest::test_set_get_regenerate_and_destroy` |
| Her işlemde yetki kontrolü | `SystemConfigTest::test_non_admin_gets_403_on_every_config_page`; `ScenarioCoverageTest::test_non_admin_cannot_post_admin_actions`; `TicketDetailTest::test_other_agent_cannot_reply_or_act` |
| Güvenli dosya yükleme | `AttachmentServiceTest::test_rejects_php_disguised_as_jpg`, `test_rejects_exe`, `test_stored_name_is_random_and_has_correct_extension` |
| Yönetici hesabı yalnız komut satırından | `AuthFlowTest::test_create_admin_script_refuses_web` |
| Yönetici değişiklikleri kayıt altında | `AuditLoggerTest`; `SystemConfigTest::test_audit_page_lists_changes` |
| Örnek veri sunuma hazır | `SeedTest` |
