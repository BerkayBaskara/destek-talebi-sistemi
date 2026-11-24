# Test Senaryoları ve Sonuçları

Ekibin test planındaki senaryoların her biri burada uygulamadaki bir teste ya da elle yapılan bir denetime bağlanır. Senaryo listesindeki "Dosya" sütunu planlanan sınıf adını gösterir; uygulamada aynı davranış başka bir sınıfta olabilir, bu yüzden eşleme davranışa göre yapılmıştır. Gereksinim bazlı tablo `docs/izlenebilirlik.md` dosyasındadır.

## Çalıştırma

```bash
composer install
php vendor/bin/phpunit                       # bütün testler
php vendor/bin/phpunit --filter ScenarioCoverageTest   # tek sınıf
php vendor/bin/phpunit --testdox             # test adlarını okunur listeler
php vendor/bin/phpstan analyse                # statik analiz (seviye 6)
```

Performans testleri geliştirme bilgisayarında uygulamanın içinden ölçer; ağ gecikmesi dahil değildir. Eşzamanlı kullanıcı yükü k6 ile ölçüldü (`docs/test-raporu.md`). OWASP ZAP ve Burp Suite taramaları ile MySQL testleri Aşama 3 işidir.

## Özet

Toplam 344 senaryo.

| Durum | Anlamı | Sayı |
|---|---|---|
| ✅ | otomatik test ile doğrulandı | 321 |
| ◐ | kısmen ya da farklı bir tasarımla karşılandı (notta açıklandı) | 2 |
| 👁 | elle doğrulandı | 4 |
| ⏭ | Aşama 3 (MySQL, yük ve güvenlik tarama araçları) | 8 |
| ✖ | uygulanmadı (gerekçesi yazılı) | 9 |

## Senaryolarla bulunan ve düzeltilen hatalar

- **SLA-010 (saat dilimi):** başlangıç anı UTC olarak verildiğinde SLA bitişi iki saat kayıyordu; hesap takvimin yerel saatiyle değil gelen saat dilimiyle yapılıyordu. `SlaCalculatorService` artık her girdiyi önce takvimin saat dilimine (Europe/Istanbul) çeviriyor. Testi: `SlaEdgeCasesTest::test_utc_input_gives_same_moment_as_local_input`.
- **SLA-036 (SLA süresi dolan talebe yanıt):** personel, SLA süresi dolmuş talebe yanıt yazabiliyordu; SRS FR-60 bunu yasaklıyor. Artık personel yazamaz ve talep ekranında nedenini görür; müşteri ve yönetici yazmaya devam eder. Testleri: `TicketServiceTest::test_agent_cannot_reply_to_breached_or_resolved`, `ScenarioCoverageTest::test_agent_sees_why_breached_ticket_cannot_be_answered`.
- **RES-011 (başka personel isteme):** çözüm işe yaramadığında talep her zaman aynı personele dönüyordu; SRS FR-63 ve FR-64 müşterinin başka personel isteyebilmesini ve talebin üst seviyeye aktarılmasını istiyor. Müşteri artık "İşe yaramadı, aynı personelle devam" ile "İşe yaramadı, başka personel istiyorum" arasında seçim yapıyor; ikincisinde talep hiyerarşide üst seviyeye ek SLA ile aktarılıyor, eskalasyon kaydına "Müşteri başka personel istedi" nedeni yazılıyor. Testleri: `ResolutionTest::test_did_not_work_offers_same_or_another_agent`, `test_asking_for_another_agent_escalates_to_next_level`, `test_another_agent_request_needs_owner_and_resolved_status`.

## 1. Kimlik doğrulama ve kullanıcı yönetimi testleri

| No | Senaryo | Beklenen | Test / denetim | Durum |
|---|---|---|---|---|
| AUTH-001 | Geçerli kullanıcı ile giriş | Giriş başarılı | `AuthFlowTest::test_register_shows_customer_number_then_login_works` | ✅ |
| AUTH-002 | Yanlış parola ile giriş | Giriş reddedilmeli | `AuthFlowTest::test_wrong_password_shows_error_and_keeps_number` | ✅ |
| AUTH-003 | Kayıtlı olmayan kullanıcı | Giriş reddedilmeli | `AuthServiceTest::test_login_success_and_wrong_password` | ✅ |
| AUTH-004 | Boş giriş alanları | Validasyon hatası | `ScenarioCoverageTest::test_empty_login_fields_are_rejected` | ✅ |
| AUTH-005 | Geçersiz müşteri numarası | Giriş reddedilmeli | `AuthServiceTest::test_login_success_and_wrong_password`, `test_login_accepts_lowercase_and_spaces` | ✅ |
| AUTH-006 | Çıkış işlemi | Oturum sonlandırılmalı | `AuthFlowTest::test_logout_ends_session` | ✅ |
| AUTH-007 | Çıkış sonrası korumalı sayfaya erişim | Giriş sayfasına yönlendirilmeli | `AuthFlowTest::test_guest_redirected_to_login` | ✅ |
| AUTH-008 | Parola kurtarma isteği | Güvenli kurtarma akışı başlamalı | `AuthFlowTest::test_forgot_password_flow` | ✅ |
| AUTH-009 | Geçersiz parola sıfırlama bilgisi | İşlem reddedilmeli | `AuthServiceTest::test_reset_password_wrong_answer_returns_false`; `AuthFlowTest::test_unknown_number_in_forgot_password` | ✅ |
| AUTH-010 | Kullanıcı kaydı | Kullanıcı doğru kaydedilmeli | `AuthServiceTest::test_register_creates_customer_with_M_number` | ✅ |
| AUTH-011 | Aynı müşteri numarasıyla kayıt | Çakışma engellenmeli | `ScenarioCoverageTest::test_generated_numbers_are_unique_and_prefixed` (numarayı sistem üretir, kullanıcı seçemez) | ✅ |
| AUTH-012 | Personel hesabı oluşturma | Personel rolü doğru atanmalı | `StaffServiceTest::test_create_staff_gets_P_number_and_policy` | ✅ |
| AUTH-013 | Müşteri numarası üretimi | Benzersiz numara üretilmeli | `ScenarioCoverageTest::test_generated_numbers_are_unique_and_prefixed` | ✅ |
| AUTH-014 | Güvenlik cevabını normalize etme | Büyük/küçük harf ve boşluk kuralları uygulanmalı | `AuthServiceTest::test_reset_password_with_answer_case_insensitive`, `test_reset_password_turkish_uppercase_answer`, `test_reset_password_ascii_i_answer` | ✅ |
| AUTH-015 | Oturum verisi kaydetme | Veri doğru saklanmalı | `NativeSessionTest::test_set_get_regenerate_and_destroy` | ✅ |
| AUTH-016 | Oturum verisi okuma | Doğru veri dönmeli | `NativeSessionTest::test_set_get_regenerate_and_destroy` | ✅ |
| AUTH-017 | Oturum yenileme | Session ID yenilenmeli | `NativeSessionTest::test_set_get_regenerate_and_destroy` | ✅ |
| AUTH-018 | Oturum sonlandırma | Oturum verileri temizlenmeli | `NativeSessionTest::test_set_get_regenerate_and_destroy` | ✅ |
| AUTH-019 | CLI üzerinden yönetici oluşturma | Yönetici oluşturulmalı | `php bin/create-admin.php <ad> <soyad> <şifre>` komut satırında çalıştırıldı; betiğin kullandığı `createStaff(Role::Admin)` testlerde her gün kullanılıyor | 👁 |
| AUTH-020 | Web üzerinden CLI komutuna erişim | Erişim mümkün olmamalı | `AuthFlowTest::test_create_admin_script_refuses_web` | ✅ |
| AUTH-021 | Kullanıcı bilgisi güncelleme | Bilgiler güncellenmeli | `ConfigCompletionTest::test_admin_edits_staff_name` | ✅ |
| AUTH-022 | Olmayan kullanıcıyı güncelleme | Kontrollü hata dönmeli | `ScenarioCoverageTest::test_updating_unknown_staff_gives_not_found` | ✅ |
| AUTH-023 | Müşteri rol öneki | Doğru önek dönmeli | `ScenarioCoverageTest::test_role_prefixes` | ✅ |
| AUTH-024 | Personel rol öneki | Doğru önek dönmeli | `ScenarioCoverageTest::test_role_prefixes` | ✅ |
| AUTH-025 | Yönetici rol öneki | Doğru önek dönmeli | `ScenarioCoverageTest::test_role_prefixes` | ✅ |
| AUTH-026 | Müşteri ID üretimi | Doğru format | `ScenarioCoverageTest::test_role_prefixes` | ✅ |
| AUTH-027 | Personel ID üretimi | Doğru format | `ScenarioCoverageTest::test_role_prefixes` | ✅ |
| AUTH-028 | Eş zamanlı ID üretimi | Tekrarlanan ID oluşmamalı | `ScenarioCoverageTest::test_generated_numbers_are_unique_and_prefixed`; `JsonStoreTest::test_transaction_conditional_update_only_first_wins` (numara kilit altında üretilir) | ✅ |
| AUTH-029 | Güvenlik sorusu listeleme | Tanımlı sorular dönmeli | `SecurityAndLimitsTest::test_admin_edits_security_questions_and_register_lists_them` | ✅ |
| AUTH-030 | Pasif güvenlik sorusu | Yeni kayıtta kullanılamamalı | `SecurityAndLimitsTest::test_admin_edits_security_questions_and_register_lists_them` (listeden çıkarılan soru kayıt formunda gösterilmez) | ✅ |
| AUTH-031 | Yönetici soru ekleme | Soru oluşturulmalı | `SecurityAndLimitsTest::test_admin_edits_security_questions_and_register_lists_them` | ✅ |
| AUTH-032 | Yetkisiz soru ekleme | 403 dönmeli | `ScenarioCoverageTest::test_non_admin_cannot_post_admin_actions` | ✅ |
| AUTH-033 | Güvenlik cevabı hashleme | Düz metin saklanmamalı | `AuthServiceTest::test_reset_password_with_answer_case_insensitive` (cevap `password_hash` ile saklanır, düz metin tutulmaz) | ✅ |
| AUTH-034 | Doğru cevap doğrulama | Doğrulama başarılı | `AuthServiceTest::test_reset_password_with_answer_case_insensitive` | ✅ |
| AUTH-035 | Yanlış cevap doğrulama | Doğrulama başarısız | `AuthServiceTest::test_reset_password_wrong_answer_returns_false` | ✅ |
| AUTH-036 | Aynı anlamdaki benzer soru | Benzerlik tespit edilmeli | FR-29 benzerlik kontrolü yok: parola kurtarmada kayıtlı soru ekranda gösterilir, kullanıcı soruyu yazmaz, yalnız cevabı yazar; karşılaştırılacak serbest soru metni olmadığı için %90 benzerlik ölçümüne gerek kalmıyor | ✖ |
| AUTH-037 | Birbirinden farklı sorular | Yanlış eşleşme yapılmamalı | FR-29 benzerlik kontrolü yok: parola kurtarmada kayıtlı soru ekranda gösterilir, kullanıcı soruyu yazmaz, yalnız cevabı yazar; karşılaştırılacak serbest soru metni olmadığı için %90 benzerlik ölçümüne gerek kalmıyor | ✖ |
| AUTH-038 | İzin verilen kurtarma denemeleri | İstekler kabul edilmeli | `SecurityAndLimitsTest::test_reset_is_blocked_after_five_wrong_answers_for_configured_minutes` | ✅ |
| AUTH-039 | Deneme sınırını aşma | Yeni istekler engellenmeli | `SecurityAndLimitsTest::test_reset_is_blocked_after_five_wrong_answers_for_configured_minutes`; `ConfigCompletionTest::test_login_is_blocked_after_configured_failed_attempts` | ✅ |
| AUTH-040 | Kullanıcıyı pasifleştirme | Kullanıcı giriş yapamamalı | `AuthFlowTest::test_inactive_user_cannot_login_with_message`, `test_deactivated_user_session_ends_on_next_request`; `CustomerAccountsTest::test_deactivated_customer_cannot_log_in_until_reactivated` | ✅ |
| AUTH-041 | Pasif kullanıcıyı aktifleştirme | Kullanıcı yeniden erişebilmeli | `StaffServiceTest::test_deactivate_and_activate_agent`; `CustomerAccountsTest::test_deactivated_customer_cannot_log_in_until_reactivated` | ✅ |

## 2. Talep (Ticket) yönetimi testleri

| No | Senaryo | Beklenen | Test / denetim | Durum |
|---|---|---|---|---|
| TKT-001 | Yeni talep oluşturma | Talep kaydedilmeli | `TicketCreateTest::test_customer_creates_ticket_and_is_redirected` | ✅ |
| TKT-002 | Zorunlu alanları boş gönderme | Validasyon hatası | `TicketCreateTest::test_invalid_form_keeps_values_422` | ✅ |
| TKT-003 | Talep listeleme | Yetkili talepler listelenmeli | `TicketCreateTest::test_list_shows_only_own_tickets` | ✅ |
| TKT-004 | Talep detayına erişme | Doğru detay açılmalı | `TicketDetailTest::test_owner_sees_messages_timeline_and_sla` | ✅ |
| TKT-005 | Başka müşterinin talebini açma | 403 / 404 | `TicketDetailTest::test_other_customer_gets_403_and_no_content` | ✅ |
| TKT-006 | Talebe yanıt gönderme | Yanıt kaydedilmeli | `TicketServiceTest::test_reply_rules`; `NotificationTest::test_replies_and_resolution_notify_the_other_side` | ✅ |
| TKT-007 | Yetkisiz yanıt gönderme | İşlem reddedilmeli | `TicketDetailTest::test_other_agent_cannot_reply_or_act` | ✅ |
| TKT-008 | Talep oluşturma | Kayıt doğru oluşmalı | `TicketServiceTest::test_create_computes_deadline_with_policy` | ✅ |
| TKT-009 | Otomatik atama başlatma | Atama mekanizması çalışmalı | `TicketServiceTest::test_create_auto_assigns_least_loaded_and_logs` | ✅ |
| TKT-010 | Durum değiştirme | Geçerli geçiş uygulanmalı | `TicketStateTest::test_allowed_transition` | ✅ |
| TKT-011 | Geçersiz durum geçişi | İşlem engellenmeli | `TicketStateTest::test_forbidden_transition_throws` | ✅ |
| TKT-012 | Talep yanıtı ekleme | Yanıt geçmişe eklenmeli | `TicketServiceTest::test_description_is_saved_as_first_reply`, `test_reply_rules` | ✅ |
| TKT-013 | Kanban üzerinden taşıma | Durum güncellenmeli | `TicketServiceTest::test_move_on_kanban` | ✅ |
| TKT-014 | Görünürlük kontrolü | Sadece yetkili kullanıcı görmeli | `TicketServiceTest::test_find_visible_rules` | ✅ |
| TKT-015 | Talep arama | İlgili kayıtlar bulunmalı | `TicketServiceTest::test_search_filters_by_status_and_text_and_paginates`, `test_search_folds_turkish_and_ascii_i` | ✅ |
| TKT-016 | Çoklu filtre uygulama | Filtrelerin kesişimi doğru olmalı | `TicketServiceTest::test_search_all_filters_for_admin`; `TicketCreateTest::test_list_filter_and_search` | ✅ |
| TKT-017 | Yetkili kullanıcının silmesi | İzin verilen işlem yapılmalı | Talep silme yok: talepler ve geçmişleri kayıt bütünlüğü için silinmez; müşteri bekleyen talebini iptal eder, yönetici "çözülemedi" olarak kapatır | ✖ |
| TKT-018 | Müşterinin başka talebi silmesi | Engellenmeli | `TicketServiceTest::test_customer_cancels_pending_but_not_others_ticket` (silme olmadığı için başka müşterinin talebine yalnız iptal denenebilir; reddedilir) | ✅ |
| TKT-019 | Yetkisiz talep iptali | Engellenmeli | `TicketServiceTest::test_customer_cancels_pending_but_not_others_ticket` | ✅ |
| TKT-020 | Talep kodu oluşturma | Benzersiz kod üretilmeli | `TicketCodeTest::test_generated_code_is_six_unambiguous_characters`; `TicketServiceTest::test_created_ticket_gets_unique_code_and_is_found_by_it` | ✅ |
| TKT-021 | Eş zamanlı talep oluşturma | Kod çakışmamalı | `TicketServiceTest::test_created_ticket_gets_unique_code_and_is_found_by_it` (kod, talep tablosu kilitliyken benzersizliği denetlenerek yazılır) | ✅ |
| TKT-022 | Durum türleri | Bütün durumlar doğru tanımlanmalı | `TicketStateTest::test_hoca_labels` | ✅ |
| TKT-023 | Son durum kontrolü | Kapalı durumlar doğru algılanmalı | `TicketStateTest::test_final_statuses` | ✅ |
| TKT-024 | Durum geçiş kuralları | Kurallara uyulmalı | `TicketStateTest::test_allowed_transition`, `test_forbidden_transition_throws`, `test_message_names_current_status` | ✅ |
| TKT-025 | Bekleyen talebi iptal etme | İptal başarılı | `TicketDetailTest::test_customer_cancels_pending_ticket` | ✅ |
| TKT-026 | Bekleyen talebi alma | Yetkili personel alabilmeli | `PoolTest::test_agent_takes_pool_ticket` | ✅ |
| TKT-027 | Alınmış talebi incelemeye geçirme | Geçiş başarılı | `TicketServiceTest::test_full_flow_resolve_sets_resolved_at_and_approve_closes` | ✅ |
| TKT-028 | İncelenen talebi çözme | Geçiş başarılı | `TicketServiceTest::test_full_flow_resolve_sets_resolved_at_and_approve_closes` | ✅ |
| TKT-029 | Çözüldü durumunda müşteri onayı | Onay akışı çalışmalı | `TicketDetailTest::test_agent_works_and_customer_rejects_then_approves` | ✅ |
| TKT-030 | Kapalı talebi yeniden açma | Engellenmeli | `TicketStateTest::test_forbidden_transition_throws` | ✅ |
| TKT-031 | İptal edilmiş talebi işleme alma | Engellenmeli | `TicketStateTest::test_forbidden_transition_throws`; `EscalationTest::test_cancelled_ticket_on_hold_is_not_revived` | ✅ |
| TKT-032 | Öncelik türleri | Tanımlı öncelikler dönmeli | `PriorityServiceTest::test_defaults_are_five_levels_with_stable_ids`; `TicketStateTest::test_priority_labels_and_default_hours` | ✅ |
| TKT-033 | Varsayılan SLA süreleri | Doğru süre dönmeli | `TicketStateTest::test_priority_labels_and_default_hours` | ✅ |
| TKT-034 | Kritik kelime içeren mesaj | Belirlenen öncelik tespit edilmeli | `PriorityDetectorTest::test_detects_urgent_keyword`, `test_highest_level_wins` | ✅ |
| TKT-035 | Büyük/küçük harf farklılığı | Tespit tutarlı olmalı | `PriorityDetectorTest::test_turkish_uppercase_is_detected`, `test_dotless_capital_i_is_detected` | ✅ |
| TKT-036 | Anahtar kelime içermeyen mesaj | Varsayılan öncelik uygulanmalı | `PriorityDetectorTest::test_no_keyword_returns_null_and_keeps_choice` | ✅ |
| TKT-037 | Kategori oluşturma | Kategori kaydedilmeli | `CatalogServiceTest::test_category_name_is_required_and_unique`; `SystemConfigTest::test_category_create_and_deactivate_hides_from_form` | ✅ |
| TKT-038 | Pasif kategoride talep oluşturma | Belirlenen kurala göre engellenmeli | `CatalogServiceTest::test_inactive_category_hidden_from_form_and_rejected_on_create` | ✅ |
| TKT-039 | Talep detaylarını hazırlama | İlişkili veriler doğru dönmeli | `TicketDetailTest::test_owner_sees_messages_timeline_and_sla`, `test_detail_two_column_labels` | ✅ |
| TKT-040 | Durum değişikliğinde log | Log oluşturulmalı | `JsonCatalogRepositoriesTest::test_ticket_logs`; `TicketServiceTest::test_observer_receives_events` | ✅ |
| TKT-041 | Yanıt gönderildiğinde log | Olay kaydedilmeli | `TicketServiceTest::test_assignment_and_reply_are_recorded_as_activity` | ✅ |
| TKT-042 | Talep geçmişini görüntüleme | Kronolojik geçmiş gösterilmeli | `TicketDetailTest::test_detail_and_lists_show_ticket_code_and_activity` | ✅ |
| TKT-043 | Ortak havuzu görüntüleme | Personel havuzu görebilmeli | `PoolTest::test_pool_lists_unassigned_tickets` | ✅ |
| TKT-044 | Görev alma | Talep personelin üzerine geçmeli | `PoolTest::test_agent_takes_pool_ticket` | ✅ |
| TKT-045 | Yönetici bütün talepleri görüntüleme | Yetkili tüm kayıtları görebilmeli | `AdminPagesTest::test_admin_list_filters` | ✅ |
| TKT-046 | Yönetici talep özeti | Sayısal değerler doğru olmalı | `ReportServiceTest::test_summary_counts_and_average` | ✅ |
| TKT-047 | Kanban panosu görüntüleme | Sütunlar doğru yüklenmeli | `KanbanApiTest::test_kanban_page_shows_three_columns_with_cards` | ✅ |
| TKT-048 | Sürükle bırak ile durum değiştirme | Geçerli geçiş uygulanmalı | `KanbanApiTest::test_move_taken_to_in_review_returns_json`, `test_invalid_move_returns_422_with_reason` | ✅ |
| TKT-049 | Yetkisiz Kanban değişikliği | Engellenmeli | `KanbanApiTest::test_other_agents_ticket_returns_403_json`, `test_missing_csrf_header_403` | ✅ |
| TKT-050 | SLA bilgisini gösterme | Süre doğru gösterilmeli | `KanbanApiTest::test_sla_endpoint_returns_levels_for_visible_tickets_only` | ✅ |

## 3. Dinamik talep formları testleri

| No | Senaryo | Beklenen | Test / denetim | Durum |
|---|---|---|---|---|
| FRM-001 | Yeni form oluşturma | Form kaydedilmeli | `AdvancedAdminTest::test_category_extra_field_is_required_shown_and_stored` (form, kategorinin ek alanlarından oluşur) | ✅ |
| FRM-002 | Aynı formu tekrar oluşturma | Benzersizlik kuralları uygulanmalı | `ScenarioGapsTest::test_duplicate_field_is_rejected_and_removed_field_keeps_old_values` | ✅ |
| FRM-003 | Boş form oluşturma | Gerekli alan kontrol edilmeli | `TicketCreateTest::test_customer_creates_ticket_and_is_redirected` (ek alanı olmayan kategoride standart form kullanılır) | ✅ |
| FRM-004 | Form alanı ekleme | Alan eklenmeli | `AdvancedAdminTest::test_category_extra_field_is_required_shown_and_stored` | ✅ |
| FRM-005 | Form alanı silme | Alan silinmeli | `ScenarioGapsTest::test_duplicate_field_is_rejected_and_removed_field_keeps_old_values` | ✅ |
| FRM-006 | Alan sıralamasını değiştirme | Yeni sıra korunmalı | Alanlar eklenme sırasıyla gösterilir; sıra değiştirme SRS'te istenmiyor (FR-37 ekleme, düzenleme, silme ister) | ✖ |
| FRM-007 | Aynı alanı iki kez ekleme | Kural uygulanmalı | `ScenarioGapsTest::test_duplicate_field_is_rejected_and_removed_field_keeps_old_values` | ✅ |
| FRM-008 | Metin alanı | Doğru tür gösterilmeli | `AdvancedAdminTest::test_category_extra_field_is_required_shown_and_stored` | ✅ |
| FRM-009 | Sayısal alan | Sayısal doğrulama yapılmalı | `ScenarioCoverageTest::test_number_field_rejects_text` | ✅ |
| FRM-010 | Tarih alanı | Geçerli tarih kabul edilmeli | `AdvancedAdminTest::test_category_extra_field_is_required_shown_and_stored` (tarih alanı türü var ve tarayıcının tarih seçicisiyle doldurulur; sunucu tarafı tarih biçimi ayrıca test edilmedi) | ◐ |
| FRM-011 | Seçim alanı | Seçenekler doğru gösterilmeli | `SubcategoryAndFieldTypesTest::test_select_email_and_max_length_fields_are_validated` | ✅ |
| FRM-012 | Form oluşturma ekranı | Yönetici erişebilmeli | `AdminPagesTest::test_admin_pages_render` | ✅ |
| FRM-013 | Yetkisiz form düzenleme | 403 dönmeli | `ScenarioCoverageTest::test_non_admin_cannot_post_admin_actions` | ✅ |
| FRM-014 | Kategoriye uygun form bulma | Doğru form dönmeli | `AdvancedAdminTest::test_category_extra_field_is_required_shown_and_stored` | ✅ |
| FRM-015 | Formu olmayan kategori | Kontrollü varsayılan davranış | `TicketCreateTest::test_customer_creates_ticket_and_is_redirected` | ✅ |
| FRM-016 | Müşterinin formu açması | Form görüntülenmeli | `TicketCreateTest::test_create_form_lists_categories_and_priorities` | ✅ |
| FRM-017 | Formu gönderme | Cevaplar kaydedilmeli | `AdvancedAdminTest::test_category_extra_field_is_required_shown_and_stored` | ✅ |
| FRM-018 | Zorunlu alanı boş bırakma | Reddedilmeli | `AdvancedAdminTest::test_category_extra_field_is_required_shown_and_stored` | ✅ |
| FRM-019 | Geçersiz e-posta | Reddedilmeli | `SubcategoryAndFieldTypesTest::test_select_email_and_max_length_fields_are_validated` | ✅ |
| FRM-020 | Beklenmeyen veri türü | Reddedilmeli | `ScenarioCoverageTest::test_number_field_rejects_text` | ✅ |
| FRM-021 | Çok uzun metin | Limit uygulanmalı | `SubcategoryAndFieldTypesTest::test_select_email_and_max_length_fields_are_validated` | ✅ |
| FRM-022 | Cevapları saklama | Veriler doğru kaydedilmeli | `AdvancedAdminTest::test_category_extra_field_is_required_shown_and_stored`; `TicketCodeTest::test_new_fields_round_trip_and_old_rows_still_load` | ✅ |
| FRM-023 | Başka kullanıcının cevabını okuma | Engellenmeli | `TicketDetailTest::test_other_customer_gets_403_and_no_content` (cevaplar talebin parçasıdır, talebi göremeyen cevabı da göremez) | ✅ |
| FRM-024 | Form düzenleme | Yeni sürüm oluşmalı | `ScenarioGapsTest::test_duplicate_field_is_rejected_and_removed_field_keeps_old_values` (ayrı sürüm numarası tutulmaz; her talep doldurulduğu andaki alan etiketleri ve değerleriyle saklanır, form değişikliği eski talebi değiştirmez) | ◐ |
| FRM-025 | Eski talebin formunu açma | Eski form sürümü korunmalı | `ScenarioGapsTest::test_duplicate_field_is_rejected_and_removed_field_keeps_old_values` | ✅ |

## 4. Otomatik görev atama ve personel hiyerarşisi testleri

| No | Senaryo | Beklenen | Test / denetim | Durum |
|---|---|---|---|---|
| ASN-001 | En az iş yüküne sahip personeli seçme | Doğru personel seçilmeli | `AssignmentServiceTest::test_picks_least_loaded` | ✅ |
| ASN-002 | Eşit iş yükü | Tanımlı eşitlik bozma kuralı uygulanmalı | `AssignmentServiceTest::test_tie_picks_lowest_id` | ✅ |
| ASN-003 | Aktif personel yok | Talep havuzda kalmalı | `AssignmentServiceTest::test_no_agents_returns_null`; `TicketServiceTest::test_create_without_agents_stays_in_pool` | ✅ |
| ASN-004 | İzinli personel | Atamaya dahil edilmemeli | `AssignmentServiceTest::test_agent_on_leave_is_skipped` | ✅ |
| ASN-005 | Kapasitesi dolmuş personel | Atanmamalı | `AssignmentServiceTest::test_inactive_and_full_agents_are_skipped` | ✅ |
| ASN-006 | Kategori hiyerarşisi oluşturma | Doğru sıra oluşmalı | `HierarchyAndLeaveTest::test_admin_sets_levels_and_new_ticket_goes_to_lowest_level` (yetkinlik, personelin kategori hiyerarşisine eklenmesidir) | ✅ |
| ASN-007 | Döngüsel hiyerarşi | Engellenmeli | `HierarchyAndLeaveTest::test_invalid_level_is_rejected_and_nothing_saved` (seviyeler sayıdır (1, 2, 3…); bir personel kategoride tek seviyede durduğu için döngü kurulamaz) | ✅ |
| ASN-008 | Hiyerarşi düzenleme | Yetkili yönetici düzenleyebilmeli | `HierarchyAndLeaveTest::test_admin_sets_levels_and_new_ticket_goes_to_lowest_level`, `test_non_admin_cannot_open_hierarchy_or_leave_pages` | ✅ |
| ASN-009 | Personel yetkinliği ekleme | Kaydedilmeli | `HierarchyAndLeaveTest::test_admin_sets_levels_and_new_ticket_goes_to_lowest_level` (yetkinlik, personelin kategori hiyerarşisine eklenmesidir) | ✅ |
| ASN-010 | Yetkinliği olmayan personel | İlgili kategoriye atanmamalı | `AssignmentServiceTest::test_hierarchy_prefers_lowest_level_then_next_level_when_full` | ✅ |
| ASN-011 | Kategori yetkisi ekleme | Yetki tanımlanmalı | `HierarchyAndLeaveTest::test_admin_sets_levels_and_new_ticket_goes_to_lowest_level` (yetkinlik, personelin kategori hiyerarşisine eklenmesidir) | ✅ |
| ASN-012 | Yetkisiz kategoriden görev alma | Engellenmeli | `HierarchyAndLeaveTest::test_agent_outside_category_hierarchy_cannot_take_pool_ticket` | ✅ |
| ASN-013 | Kapasite belirleme | Kapasite kaydedilmeli | `StaffServiceTest::test_capacity_validation`; `SettingsServiceTest::test_capacity_must_be_whole_number_in_range` | ✅ |
| ASN-014 | Kapasiteyi aşan atama | Engellenmeli veya istisna uygulanmalı | `TicketServiceTest::test_take_respects_capacity`; `JsonTicketRepositoryTest::test_concurrent_assign_cannot_exceed_capacity` | ✅ |
| ASN-015 | Aktif görev sayısı | Doğru hesaplanmalı | `JsonTicketRepositoryTest::test_count_active_by_agent` | ✅ |
| ASN-016 | Kapalı görevleri sayma | Aktif iş yüküne dahil edilmemeli | `AssignmentServiceTest::test_resolved_and_closed_do_not_count` | ✅ |
| ASN-017 | Bugün izinli personel | Atamadan çıkarılmalı | `HierarchyAndLeaveTest::test_leave_page_adds_and_removes_and_agent_on_leave_gets_no_ticket` | ✅ |
| ASN-018 | Yarın izinli personel | Gereksinimdeki kurala göre çıkarılmalı | `ScenarioCoverageTest::test_future_and_finished_leave_do_not_block_assignment` (kural: yalnız bugünü kapsayan izin atamayı engeller) | ✅ |
| ASN-019 | İzin tarihi bitmiş personel | Yeniden uygun olmalı | `ScenarioCoverageTest::test_future_and_finished_leave_do_not_block_assignment` (kural: yalnız bugünü kapsayan izin atamayı engeller) | ✅ |
| ASN-020 | Aktif personel | Atama havuzunda bulunmalı | `AssignmentServiceTest::test_picks_least_loaded` | ✅ |
| ASN-021 | Pasif personel | Atama havuzunda bulunmamalı | `AssignmentServiceTest::test_inactive_and_full_agents_are_skipped` | ✅ |
| ASN-022 | Uzun süre aktif olmayan personel | Otomatik pasifleştirilmeli | `ConfigCompletionTest::test_inactive_staff_is_deactivated_after_configured_days` | ✅ |
| ASN-023 | Aktif işlem yapan personel | Yanlışlıkla pasifleştirilmemeli | `ScenarioCoverageTest::test_recently_active_staff_is_not_deactivated` | ✅ |
| ASN-024 | İşten ayrılan personel | Pasifleştirilmeli | `StaffServiceTest::test_deactivate_and_activate_agent` | ✅ |
| ASN-025 | Üzerinde açık görev bulunan personel | Görevler kaybolmamalı | `SystemConfigTest::test_inactive_agent_with_open_tickets_shows_warning` | ✅ |
| ASN-026 | Yönetici manuel atama | Atama yapılmalı | `ResolutionTest::test_admin_assigns_waiting_ticket_and_capacity_exception_needs_reason` | ✅ |
| ASN-027 | Yetkisiz manuel atama | Engellenmeli | `ScenarioCoverageTest::test_non_admin_cannot_post_admin_actions` | ✅ |
| ASN-028 | Kapasite istisnası | İstisna kaydedilmeli | `ResolutionTest::test_admin_assigns_waiting_ticket_and_capacity_exception_needs_reason` | ✅ |
| ASN-029 | İstisna yetkisi olmayan kullanıcı | İşlem engellenmeli | `ScenarioCoverageTest::test_non_admin_cannot_post_admin_actions` | ✅ |

## 5. SLA ve eskalasyon testleri

| No | Senaryo | Beklenen | Test / denetim | Durum |
|---|---|---|---|---|
| SLA-001 | Mesai içinde talep açma | SLA doğru hesaplanmalı | `SlaCalculatorServiceTest::test_deadline_matches_case_table` | ✅ |
| SLA-002 | Mesai dışında talep açma | Sonraki çalışma aralığı esas alınmalı | `SlaCalculatorServiceTest::test_deadline_matches_case_table`, `test_zero_duration_outside_hours_moves_to_next_working_moment` | ✅ |
| SLA-003 | Hafta sonu talep açma | Çalışma takvimi uygulanmalı | `SlaCalculatorServiceTest::test_deadline_matches_case_table` | ✅ |
| SLA-004 | Resmi tatilde talep açma | Tatil hesaba katılmamalı | `CalendarServiceTest::test_holiday_add_remove_and_affects_sla` | ✅ |
| SLA-005 | Birden fazla tatil | Süreler doğru atlanmalı | `SlaCalculatorServiceTest::test_five_years_of_holidays_is_fast`; `SlaEdgeCasesTest::test_year_change_skips_new_year_holiday` | ✅ |
| SLA-006 | Mesai ortasında SLA bitişi | Doğru bitiş hesaplanmalı | `SlaCalculatorServiceTest::test_deadline_matches_case_table` | ✅ |
| SLA-007 | SLA bir sonraki güne taşınıyor | Doğru gün ve saat bulunmalı | `SlaCalculatorServiceTest::test_deadline_matches_case_table` | ✅ |
| SLA-008 | Yıl değişimi | Takvim doğru ilerlemeli | `SlaEdgeCasesTest::test_year_change_skips_new_year_holiday` | ✅ |
| SLA-009 | Artık yıl | 29 Şubat doğru işlenmeli | `SlaEdgeCasesTest::test_leap_day_is_a_normal_working_day` | ✅ |
| SLA-010 | Saat dilimi farklılığı | UTC / yerel saat tutarlı olmalı | `SlaEdgeCasesTest::test_utc_input_gives_same_moment_as_local_input` (bu senaryo hata buldu; düzeltildi (aşağıda)) | ✅ |
| SLA-011 | Bozuk JSON takvimi | Varsayılan takvime geçilmeli | `SlaCalculatorServiceTest::test_corrupt_calendar_uses_default_hours`; `JsonCalendarRepositoryTest::test_corrupt_file_falls_back_to_default_and_logs` | ✅ |
| SLA-012 | 5 yıllık tatil verisi | Sonuç doğru ve 500 ms altında olmalı | `SlaCalculatorServiceTest::test_five_years_of_holidays_is_fast` | ✅ |
| SLA-013 | SLA %0 | Başlangıç durumu doğru olmalı | `SlaStatusServiceTest::test_normal_below_80_percent`, `test_just_below_80_percent_is_normal` | ✅ |
| SLA-014 | SLA %79 | Normal durum | `SlaStatusServiceTest::test_normal_below_80_percent`, `test_just_below_80_percent_is_normal` | ✅ |
| SLA-015 | SLA %80 | Sarı uyarı | `SlaStatusServiceTest::test_warning_at_exactly_80_percent` | ✅ |
| SLA-016 | SLA %99 | Sarı uyarı | `SlaStatusServiceTest::test_warning_at_exactly_80_percent` | ✅ |
| SLA-017 | SLA %100 | Kırmızı ihlal | `SlaStatusServiceTest::test_breached_exactly_at_deadline` | ✅ |
| SLA-018 | SLA %110 | İhlal durumu korunmalı | `SlaStatusServiceTest::test_breached_after_deadline` | ✅ |
| SLA-019 | SLA başlatma | Başlangıç tarihi kaydedilmeli | `TicketServiceTest::test_create_computes_deadline_with_policy` | ✅ |
| SLA-020 | SLA bitişi | Bitiş tarihi kaydedilmeli | `TicketServiceTest::test_create_computes_deadline_with_policy` | ✅ |
| SLA-021 | Kategoriye özel SLA | Doğru kural uygulanmalı | `CatalogServiceTest::test_set_sla_overrides_default_for_new_tickets_only` | ✅ |
| SLA-022 | Olmayan kategori ayarı | Tanımlı varsayılan davranış | `CatalogServiceTest::test_clearing_sla_falls_back_to_priority_default`; `TicketServiceTest::test_create_without_policy_uses_priority_default` | ✅ |
| SLA-023 | Süresi dolmayan talep | Eskalasyon yapılmamalı | `ScenarioCoverageTest::test_monitor_does_nothing_before_deadline` | ✅ |
| SLA-024 | Süresi dolan talep | Eskalasyon kontrolü yapılmalı | `EscalationTest::test_breach_puts_ticket_on_hold_in_pool_and_asks_customer` (onaylı kural: önce müşteriye sorulur, varsayılan 1 iş günü beklenir) | ✅ |
| SLA-025 | Otomatik eskalasyon | Üst yetkiliye devredilmeli | `EscalationTest::test_no_response_escalates_to_next_level_with_extra_sla` | ✅ |
| SLA-026 | Aynı talebin tekrar eskalasyonu | Tekrarlı kayıt oluşmamalı | `EscalationTest::test_same_breach_is_handled_once` | ✅ |
| SLA-027 | Manuel eskalasyon | Yetkili yönetici yapabilmeli | `ResolutionTest::test_agent_escalates_manually_with_reason` | ✅ |
| SLA-028 | Yetkisiz manuel eskalasyon | Engellenmeli | `ScenarioCoverageTest::test_customer_cannot_escalate_and_only_admin_closes_as_unresolvable` | ✅ |
| SLA-029 | Üst personele devir | Doğru personel atanmalı | `EscalationTest::test_no_response_escalates_to_next_level_with_extra_sla` | ✅ |
| SLA-030 | Ek SLA süresi | Belirlenen süre eklenmeli | `ResolutionTest::test_did_not_work_applies_extra_sla`; `EscalationTest::test_customer_continue_returns_to_same_agent_with_extra_sla` | ✅ |
| SLA-031 | Birden fazla ek süre | Birikim doğru hesaplanmalı | `ScenarioCoverageTest::test_extra_sla_accumulates_and_first_deadline_is_kept` | ✅ |
| SLA-032 | İlk SLA kaydı | Geçmiş saklanmalı | `ScenarioCoverageTest::test_extra_sla_accumulates_and_first_deadline_is_kept` | ✅ |
| SLA-033 | Ek SLA kaydı | Önceki kayıt korunmalı | `ScenarioCoverageTest::test_extra_sla_accumulates_and_first_deadline_is_kept` | ✅ |
| SLA-034 | Bekleyen eskalasyon | Yönetici bildirimi oluşmalı | `EscalationTest::test_no_upper_level_leaves_ticket_for_admin` | ✅ |
| SLA-035 | Normal talebe yanıt | İzin verilmeli | `TicketServiceTest::test_reply_rules` | ✅ |
| SLA-036 | SLA'sı dolan talebe yanıt | Tanımlı kurala göre engellenmeli | `TicketServiceTest::test_agent_cannot_reply_to_breached_or_resolved`; `ScenarioCoverageTest::test_agent_sees_why_breached_ticket_cannot_be_answered` (FR-60: personel yazamaz, müşteri ve yönetici yazabilir) | ✅ |
| SLA-037 | Çözüldü talebe yanıt | Yetki ve durum kontrol edilmeli | `TicketDetailTest::test_agent_cannot_reply_to_resolved_but_customer_can` | ✅ |

## 6. Çözüm, müşteri onayı ve kapanış testleri

| No | Senaryo | Beklenen | Test / denetim | Durum |
|---|---|---|---|---|
| RES-001 | Talebi çözüldü yapma | Durum güncellenmeli | `TicketServiceTest::test_full_flow_resolve_sets_resolved_at_and_approve_closes` | ✅ |
| RES-002 | Yetkisiz çözüm işlemi | Engellenmeli | `TicketServiceTest::test_customer_cannot_resolve`, `test_other_agent_cannot_start_review` | ✅ |
| RES-003 | Müşterinin çözümü onaylaması | Onay kaydedilmeli | `TicketDetailTest::test_agent_works_and_customer_rejects_then_approves` | ✅ |
| RES-004 | Müşterinin çözümü reddetmesi | Ret kaydedilmeli | `TicketServiceTest::test_reject_increments_attempt_and_returns_to_review` | ✅ |
| RES-005 | Başka müşterinin onayı | Engellenmeli | `ScenarioCoverageTest::test_other_customer_cannot_approve_and_double_approval_is_refused` | ✅ |
| RES-006 | İşe yaradı seçeneği | Olumlu geri bildirim | `ResolutionTest::test_customer_sees_worked_and_did_not_work_buttons` | ✅ |
| RES-007 | İşe yaramadı seçeneği | Olumsuz geri bildirim | `ResolutionTest::test_did_not_work_applies_extra_sla` | ✅ |
| RES-008 | Aynı geri bildirimi tekrar gönderme | Çift işlem engellenmeli | `ScenarioCoverageTest::test_other_customer_cannot_approve_and_double_approval_is_refused` | ✅ |
| RES-009 | Aynı personelle devam | Atama korunmalı | `ResolutionTest::test_did_not_work_offers_same_or_another_agent`, `test_did_not_work_applies_extra_sla`; `EscalationTest::test_customer_continue_returns_to_same_agent_with_extra_sla` | ✅ |
| RES-010 | Pasif personelle devam | Engellenmeli | `EscalationTest::test_continue_with_unavailable_agent_goes_to_upper_level` (pasif ya da izinli personelle devam edilmez, talep üst seviyeye gider) | ✅ |
| RES-011 | Başka personel isteme | Eskalasyon başlatılmalı | `ResolutionTest::test_asking_for_another_agent_escalates_to_next_level`, `test_another_agent_request_needs_owner_and_resolved_status`, `test_third_rejection_escalates_to_next_level` | ✅ |
| RES-012 | Üst personel bulunamaması | Kontrollü hata | `EscalationTest::test_no_upper_level_leaves_ticket_for_admin` | ✅ |
| RES-013 | Müşteri yanıt süresinin dolması | Zaman aşımı işlenmeli | `EscalationTest::test_no_response_escalates_to_next_level_with_extra_sla`; `QuestionTest::test_unanswered_question_resumes_sla_when_reply_time_ends` | ✅ |
| RES-014 | Müşteri zamanında yanıt vermiş | Otomatik işlem yapılmamalı | `EscalationTest::test_customer_continue_returns_to_same_agent_with_extra_sla` | ✅ |
| RES-015 | 3 iş günü yanıtsızlık | Talep kapanmalı | `ResolutionTest::test_unanswered_resolution_is_auto_completed` | ✅ |
| RES-016 | 2 iş günü geçmiş | Kapanmamalı | `ResolutionTest::test_resolution_is_not_auto_completed_early` | ✅ |
| RES-017 | 3 gün dolmadan müşteri itirazı | Kapanış durmalı | `ScenarioCoverageTest::test_objection_stops_auto_close` | ✅ |
| RES-018 | Job iki kez çalıştırma | Çift kapanış olmamalı | `ScenarioCoverageTest::test_auto_complete_runs_once` | ✅ |
| RES-019 | Otomatik kapanış süresini değiştirme | Yeni süre uygulanmalı | `EscalationTest::test_admin_changes_sla_rules_with_validation` | ✅ |
| RES-020 | Geçersiz kapanış süresi | Reddedilmeli | `EscalationTest::test_admin_changes_sla_rules_with_validation` | ✅ |
| RES-021 | Çözülemez kapatma | Yetkili işlem yapabilmeli | `ResolutionTest::test_admin_closes_as_unresolvable_with_reason` | ✅ |
| RES-022 | Yetkisiz çözülemez kapatma | Engellenmeli | `ScenarioCoverageTest::test_customer_cannot_escalate_and_only_admin_closes_as_unresolvable` | ✅ |
| RES-023 | Gerekçe boş | Reddedilmeli | `ScenarioCoverageTest::test_unresolvable_close_needs_reason_of_at_least_five_characters` | ✅ |
| RES-024 | Çok kısa gerekçe | Kurala göre reddedilmeli | `ScenarioCoverageTest::test_unresolvable_close_needs_reason_of_at_least_five_characters` | ✅ |
| RES-025 | Kapanış türü kaydetme | Doğru tür saklanmalı | `EscalationTest::test_customer_says_resolved_closes_with_reason`; `ResolutionTest::test_admin_closes_as_unresolvable_with_reason` | ✅ |

## 7. Dosya ve ek yönetimi testleri

| No | Senaryo | Beklenen | Test / denetim | Durum |
|---|---|---|---|---|
| ATT-001 | JPG yükleme | Kabul edilmeli | `AttachmentServiceTest::test_accepts_jpg_png_pdf` | ✅ |
| ATT-002 | PNG yükleme | Kabul edilmeli | `AttachmentServiceTest::test_accepts_jpg_png_pdf` | ✅ |
| ATT-003 | MP4 yükleme | Kabul edilmeli | `AttachmentServiceTest::test_accepts_mp4_and_webm_video` | ✅ |
| ATT-004 | WebM yükleme | Kabul edilmeli | `AttachmentServiceTest::test_accepts_mp4_and_webm_video` | ✅ |
| ATT-005 | EXE yükleme | Reddedilmeli | `AttachmentServiceTest::test_rejects_exe` | ✅ |
| ATT-006 | PHP dosyası yükleme | Reddedilmeli | `AttachmentServiceTest::test_rejects_php_disguised_as_jpg`; `BrandingServiceTest::test_rejects_fake_png_and_large_file_and_keeps_old_logo` (tür, uzantıdan değil dosya içeriğinden belirlenir) | ✅ |
| ATT-007 | Boyut sınırını aşma | Reddedilmeli | `AttachmentServiceTest::test_rejects_oversized_image`, `test_video_over_video_limit_rejected`, `test_php_ini_size_error_says_file_is_too_big` | ✅ |
| ATT-008 | Dosya sayısı sınırını aşma | Reddedilmeli | `AttachmentServiceTest::test_validate_many_checks_count_and_skips_empty_slots` | ✅ |
| ATT-009 | Dosya adında özel karakter | Güvenli isim üretilmeli | `AttachmentServiceTest::test_stored_name_is_random_and_has_correct_extension` | ✅ |
| ATT-010 | Aynı isimli iki dosya | Üzerine yazılmamalı | `AttachmentServiceTest::test_stored_name_is_random_and_has_correct_extension` | ✅ |
| ATT-011 | Görsel boyutlandırma | Maksimum 1920x1080 | `AttachmentServiceTest::test_large_png_is_downscaled_to_1920x1080` | ✅ |
| ATT-012 | Yetkili dosya indirme | İndirme başarılı | `AttachmentAccessTest::test_owner_downloads_with_safe_headers` | ✅ |
| ATT-013 | Başka müşterinin dosyasını indirme | 403 / 404 | `AttachmentAccessTest::test_other_customers_attachment_403` | ✅ |
| ATT-014 | Büyük harf uzantı | Tanımlı kurala göre kabul | `AttachmentServiceTest::test_uppercase_extension_is_accepted_by_content` | ✅ |
| ATT-015 | Çift uzantı .jpg.php | Reddedilmeli | `AttachmentServiceTest::test_double_extension_is_stored_safely_or_rejected` | ✅ |
| ATT-016 | Gerçek MIME kontrolü | İçerikle uyumlu olmalı | `AttachmentServiceTest::test_rejects_php_disguised_as_jpg`; `BrandingServiceTest::test_rejects_fake_png_and_large_file_and_keeps_old_logo` (tür, uzantıdan değil dosya içeriğinden belirlenir) | ✅ |
| ATT-017 | MIME sahteciliği | Reddedilmeli | `AttachmentServiceTest::test_rejects_php_disguised_as_jpg`; `BrandingServiceTest::test_rejects_fake_png_and_large_file_and_keeps_old_logo` (tür, uzantıdan değil dosya içeriğinden belirlenir) | ✅ |
| ATT-018 | Bozuk görsel | Reddedilmeli | `AttachmentServiceTest::test_corrupt_image_is_rejected` | ✅ |
| ATT-019 | Sahte MP4 dosyası | Reddedilmeli | `AttachmentServiceTest::test_fake_mp4_is_rejected` | ✅ |
| ATT-020 | Yönetici limit değişikliği | Yeni limit uygulanmalı | `SecurityAndLimitsTest::test_admin_changes_file_limits_and_ticket_form_follows` | ✅ |
| ATT-021 | Dosya yetkisi | Yetki doğru kontrol edilmeli | `AttachmentAccessTest::test_other_customers_attachment_403` | ✅ |
| ATT-022 | URL değiştirerek indirme | Engellenmeli | `AttachmentAccessTest::test_other_customers_attachment_403`, `test_unknown_attachment_404_and_guest_redirect` | ✅ |

## 8. Bildirim sistemi testleri

| No | Senaryo | Beklenen | Test / denetim | Durum |
|---|---|---|---|---|
| NOT-001 | Bildirim oluşturma | Bildirim kaydedilmeli | `NotificationTest::test_assignment_notifies_agent_and_bell_shows_count`; `PaginationTest::test_notifications_show_fifty_per_page_and_older_ones_are_reachable` | ✅ |
| NOT-002 | Olmayan kullanıcıya bildirim | Kontrollü hata | `ScenarioGapsTest::test_notification_to_unknown_user_is_ignored` | ✅ |
| NOT-003 | Bildirimleri listeleme | Kullanıcı bildirimleri dönmeli | `NotificationTest::test_assignment_notifies_agent_and_bell_shows_count`; `PaginationTest::test_notifications_show_fifty_per_page_and_older_ones_are_reachable` | ✅ |
| NOT-004 | Başka kullanıcının bildirimi | Erişim engellenmeli | `NotificationTest::test_opening_notification_marks_read_and_others_cannot_open_it`, `test_mark_all_read` | ✅ |
| NOT-005 | Bildirimi okundu yapma | Durum güncellenmeli | `NotificationTest::test_opening_notification_marks_read_and_others_cannot_open_it`, `test_mark_all_read` | ✅ |
| NOT-006 | Bildirimi okunmadı yapma | Durum güncellenmeli | `ScenarioGapsTest::test_notification_can_be_marked_unread_and_reading_twice_is_consistent` | ✅ |
| NOT-007 | Aynı bildirimi iki kez okuma | Veri tutarlı kalmalı | `ScenarioGapsTest::test_notification_can_be_marked_unread_and_reading_twice_is_consistent` | ✅ |
| NOT-008 | Yeni görev atama | Personele bildirim | `NotificationTest::test_assignment_notifies_agent_and_bell_shows_count`; `PaginationTest::test_notifications_show_fifty_per_page_and_older_ones_are_reachable` | ✅ |
| NOT-009 | Eskalasyon | İlgili personele bildirim | `EscalationTest::test_no_response_escalates_to_next_level_with_extra_sla` | ✅ |
| NOT-010 | SLA uyarısı | Uyarı bildirimi | `ScenarioGapsTest::test_sla_warning_notification_is_sent_once`; `EscalationTest::test_breach_puts_ticket_on_hold_in_pool_and_asks_customer` | ✅ |
| NOT-011 | Çözüm onayı | Müşteriye bildirim | `NotificationTest::test_replies_and_resolution_notify_the_other_side` | ✅ |
| NOT-012 | Bekleyen işlem | Yöneticiye bildirim | `EscalationTest::test_no_upper_level_leaves_ticket_for_admin` | ✅ |
| NOT-013 | Aynı olayın tekrar oluşması | Gereksiz çift bildirim oluşmamalı | `ScenarioGapsTest::test_sla_warning_notification_is_sent_once`; `EscalationTest::test_same_breach_is_handled_once` | ✅ |
| NOT-014 | AJAX bildirim sorgusu | Sayfa yenilenmeden veri dönmeli | `NotificationTest::test_notification_poll_does_not_keep_session_alive` | ✅ |

## 9. Yönetici paneli ve sistem ayarları testleri

| No | Senaryo | Beklenen | Test / denetim | Durum |
|---|---|---|---|---|
| ADM-001 | Yönetici talep listesi | Tüm yetkili kayıtlar | `AdminPagesTest::test_admin_list_filters` | ✅ |
| ADM-002 | Yönetici özet ekranı | Sayısal değerler doğru | `ReportServiceTest::test_summary_counts_and_average`; `AdminPagesTest::test_admin_pages_render` | ✅ |
| ADM-003 | Sistem ayarı oluşturma | Kaydedilmeli | `SettingsServiceTest::test_update_persists_and_returns_typed_values` | ✅ |
| ADM-004 | Sistem ayarı güncelleme | Güncellenmeli | `SettingsServiceTest::test_update_persists_and_returns_typed_values` | ✅ |
| ADM-005 | Geçersiz ayar | Reddedilmeli | `SettingsServiceTest::test_invalid_value_writes_nothing`, `test_unknown_key_is_rejected` | ✅ |
| ADM-006 | Site adı değiştirme | Yeni ad gösterilmeli | `SystemConfigTest::test_site_title_change_appears_in_header` | ✅ |
| ADM-007 | Logo yükleme | Logo doğru görüntülenmeli | `BrandingServiceTest::test_new_logo_replaces_old_file`; `ConfigCompletionTest::test_home_image_upload_shows_on_home_page` | ✅ |
| ADM-008 | Ana sayfa metni değiştirme | Yeni metin görünmeli | `UiTest::test_guest_sees_public_home_with_admin_text` | ✅ |
| ADM-009 | XSS içeren metin | Zararsız gösterilmeli | `ScenarioCoverageTest::test_script_and_event_attributes_are_shown_harmless` | ✅ |
| ADM-010 | Öncelik oluşturma | Kaydedilmeli | `PriorityServiceTest::test_create_validates_and_records_audit`; `SystemConfigTest::test_priority_create_appears_in_ticket_form` | ✅ |
| ADM-011 | Öncelik silme | Bağımlılık kontrolü yapılmalı | `PriorityServiceTest::test_cannot_deactivate_last_active_priority` (öncelik silinmez, pasifleştirilir; eski talepler önceliğini korur) | ✅ |
| ADM-012 | Kategori oluşturma | Kaydedilmeli | `SystemConfigTest::test_category_create_and_deactivate_hides_from_form` | ✅ |
| ADM-013 | Kategori güncelleme | Güncellenmeli | `CatalogServiceTest::test_category_name_is_required_and_unique`; `SubcategoryAndFieldTypesTest::test_subcategory_is_created_under_parent_and_listed_with_path` | ✅ |
| ADM-014 | Kullanılan kategoriyi silme | Veri bütünlüğü korunmalı | `CatalogServiceTest::test_cannot_deactivate_last_active_category`, `test_inactive_category_hidden_from_form_and_rejected_on_create` (kategori silinmez, pasifleştirilir; eski talepler kategorisini korur) | ✅ |
| ADM-015 | SLA kuralı ekleme | Kural kaydedilmeli | `SystemConfigTest::test_sla_grid_saves_and_clears` | ✅ |
| ADM-016 | SLA kuralı düzenleme | Yeni kural uygulanmalı | `SystemConfigTest::test_priority_default_sla_change_applies_to_new_ticket_in_seeded_category`; `CatalogServiceTest::test_set_sla_overrides_default_for_new_tickets_only` | ✅ |
| ADM-017 | Personel ekleme | Personel oluşturulmalı | `SystemConfigTest::test_staff_create_shows_P_number` | ✅ |
| ADM-018 | Personel pasifleştirme | Durum değişmeli | `StaffServiceTest::test_deactivate_and_activate_agent` | ✅ |
| ADM-019 | Kullanıcı düzenleme | Bilgiler güncellenmeli | `ConfigCompletionTest::test_admin_edits_staff_name` | ✅ |
| ADM-020 | Kullanıcı rolü değiştirme | Yetkili işlem yapabilmeli | Rol değiştirme yok: rol, kullanıcı numarasının önekiyle (M/P/Y, FR-26) birlikte verilir; rol değişseydi numara da değişmek zorunda kalırdı. Yönetici yeni rolde hesap açar, eskisini pasifleştirir | ✖ |
| ADM-021 | Otomatik kapanış süresi | Ayar kaydedilmeli | `EscalationTest::test_admin_changes_sla_rules_with_validation` | ✅ |
| ADM-022 | Dosya limiti değiştirme | Yeni limit uygulanmalı | `SecurityAndLimitsTest::test_admin_changes_file_limits_and_ticket_form_follows` | ✅ |
| ADM-023 | Güvenlik sorusu yönetimi | Soru değiştirilebilmeli | `SecurityAndLimitsTest::test_admin_edits_security_questions_and_register_lists_them` | ✅ |
| ADM-024 | Geçerli yapılandırma | Kabul edilmeli | `SettingsServiceTest::test_update_persists_and_returns_typed_values` | ✅ |
| ADM-025 | Eksik yapılandırma | Hata üretilmeli | `SettingsServiceTest::test_unknown_key_falls_back_to_default`; `CalendarServiceTest::test_save_rejects_calendar_without_working_days_and_keeps_old` | ✅ |
| ADM-026 | Bozuk JSON | Kontrollü varsayılan davranış | `JsonCalendarRepositoryTest::test_corrupt_file_falls_back_to_default_and_logs`; `KernelTest::test_logged_in_user_with_corrupt_data_sees_friendly_500_page` | ✅ |

## 10. Raporlama ve canlı güncelleme testleri

| No | Senaryo | Beklenen | Test / denetim | Durum |
|---|---|---|---|---|
| RPT-001 | Talep durumu dağılımı | Sayılar doğru | `ReportServiceTest::test_summary_counts_and_average`, `test_summary_with_no_resolved_has_null_average` | ✅ |
| RPT-002 | Öncelik dağılımı | Sayılar doğru | `ReportServiceTest::test_summary_counts_and_average`, `test_summary_with_no_resolved_has_null_average` | ✅ |
| RPT-003 | SLA uyarıları | Sayılar doğru | `ReportServiceTest::test_summary_counts_and_average`, `test_summary_with_no_resolved_has_null_average` | ✅ |
| RPT-004 | SLA ihlalleri | Sayılar doğru | `ReportServiceTest::test_summary_counts_and_average`, `test_summary_with_no_resolved_has_null_average` | ✅ |
| RPT-005 | Personel iş yükü | Sayılar doğru | `AdvancedAdminTest::test_report_with_filters_and_per_agent_stats` | ✅ |
| RPT-006 | Ortalama çözüm süresi | Doğru hesaplanmalı | `ReportServiceTest::test_summary_counts_and_average`, `test_summary_with_no_resolved_has_null_average` | ✅ |
| RPT-007 | SLA uyum oranı | Doğru hesaplanmalı | `DetailedReportServiceTest::test_sla_compliance_uses_first_deadline` | ✅ |
| RPT-008 | Ayrıntılı talep raporu | Kayıtlar doğru | `AdvancedAdminTest::test_report_with_filters_and_per_agent_stats` | ✅ |
| RPT-009 | Personel performansı | Puan ve süre doğru | `ScoreAndRestoreTest::test_score_depends_on_attempts_and_escalation_bonus` | ✅ |
| RPT-010 | SLA raporu | SLA geçmişi doğru | `DetailedReportServiceTest::test_sla_compliance_uses_first_deadline` | ✅ |
| RPT-011 | Eskalasyon raporu | Devir kayıtları doğru | `ScenarioGapsTest::test_report_lists_escalations` | ✅ |
| RPT-012 | Tarih filtresi | Doğru kayıtlar | `ScenarioCoverageTest::test_report_date_and_combined_filters` | ✅ |
| RPT-013 | Personel filtresi | Doğru kayıtlar | `AdvancedAdminTest::test_report_with_filters_and_per_agent_stats` | ✅ |
| RPT-014 | Kategori filtresi | Doğru kayıtlar | `ScenarioCoverageTest::test_report_date_and_combined_filters` | ✅ |
| RPT-015 | Birden fazla filtre | Doğru kesişim | `ScenarioCoverageTest::test_report_date_and_combined_filters` | ✅ |
| RPT-016 | CSV dışa aktarma | Dosya oluşturulmalı | `AdvancedAdminTest::test_csv_export_uses_same_filters` | ✅ |
| RPT-017 | Türkçe karakterler | Karakterler korunmalı | `AdvancedAdminTest::test_csv_export_uses_same_filters` | ✅ |
| RPT-018 | =CMD(...) hücresi | Formül etkisizleştirilmeli | `AdvancedAdminTest::test_csv_cells_are_protected_against_formula_injection` | ✅ |
| RPT-019 | +, -, @ ile başlayan veri | Güvenli hale getirilmeli | `AdvancedAdminTest::test_csv_cells_are_protected_against_formula_injection` | ✅ |
| RPT-020 | Canlı rapor bağlantısı | Veri aktarılmalı | FR-83 SSE yerine kısa aralıklı sorgu (yoklama) kullanılıyor: yerleşik PHP sunucusu tek iş parçacıklıdır, açık kalan SSE bağlantısı diğer istekleri bekletir. SLA durumu ve bildirimler sayfa yenilenmeden güncellenir (KanbanApiTest, NotificationTest) | ✖ |
| RPT-021 | Yetkisiz SSE bağlantısı | Engellenmeli | `KanbanApiTest::test_api_without_session_returns_401_json`, `test_sla_endpoint_returns_levels_for_visible_tickets_only` (canlı veri ucu oturumsuz isteğe 401 verir, yalnız görülebilen talepleri döner) | ✅ |
| RPT-022 | Bağlantı açma | Bağlantı kurulmalı | FR-83 SSE yerine kısa aralıklı sorgu (yoklama) kullanılıyor: yerleşik PHP sunucusu tek iş parçacıklıdır, açık kalan SSE bağlantısı diğer istekleri bekletir. SLA durumu ve bildirimler sayfa yenilenmeden güncellenir (KanbanApiTest, NotificationTest) | ✖ |
| RPT-023 | Bağlantı kapatma | Kaynak serbest bırakılmalı | FR-83 SSE yerine kısa aralıklı sorgu (yoklama) kullanılıyor: yerleşik PHP sunucusu tek iş parçacıklıdır, açık kalan SSE bağlantısı diğer istekleri bekletir. SLA durumu ve bildirimler sayfa yenilenmeden güncellenir (KanbanApiTest, NotificationTest) | ✖ |
| RPT-024 | Çok sayıda bağlantı | Kaynak yönetimi korunmalı | FR-83 SSE yerine kısa aralıklı sorgu (yoklama) kullanılıyor: yerleşik PHP sunucusu tek iş parçacıklıdır, açık kalan SSE bağlantısı diğer istekleri bekletir. SLA durumu ve bildirimler sayfa yenilenmeden güncellenir (KanbanApiTest, NotificationTest) | ✖ |

## 11. Güvenlik, kayıt ve altyapı testleri

| No | Senaryo | Beklenen | Test / denetim | Durum |
|---|---|---|---|---|
| SEC-001 | Yönetici erişimi | İzin verilmeli | `AdminPagesTest::test_admin_pages_render` | ✅ |
| SEC-002 | Personel yönetici işlemi | Engellenmeli | `ScenarioCoverageTest::test_non_admin_cannot_post_admin_actions`; `AdminPagesTest::test_agent_and_customer_cannot_open_admin_pages` | ✅ |
| SEC-003 | Müşteri personel işlemi | Engellenmeli | `PoolTest::test_customer_cannot_see_pool_or_take`; `ScenarioCoverageTest::test_non_admin_cannot_post_admin_actions` | ✅ |
| SEC-004 | Geçerli CSRF token | Kabul edilmeli | `CsrfTest::test_token_is_stable_within_session_and_validates` | ✅ |
| SEC-005 | Token olmadan POST | 403 | `KernelTest::test_post_without_csrf_returns_403` | ✅ |
| SEC-006 | Geçersiz token | 403 | `CsrfTest::test_rejects_null_and_wrong_token` | ✅ |
| SEC-007 | Süresi geçmiş token | Reddedilmeli | `CsrfTest::test_new_session_gets_different_token`; `AuthFlowTest::test_csrf_token_is_rotated_on_login` (anahtar oturuma bağlıdır; oturum bitince ya da yenilenince eskisi geçmez) | ✅ |
| SEC-008 | HTML script girdisi | Zararsız hale getirilmeli | `TicketDetailTest::test_reply_is_escaped`; `ScenarioCoverageTest::test_script_and_event_attributes_are_shown_harmless` | ✅ |
| SEC-009 | JavaScript event attribute | Temizlenmeli | `ScenarioCoverageTest::test_script_and_event_attributes_are_shown_harmless` | ✅ |
| SEC-010 | SQL Injection denemesi | Engellenmeli | MySQL, PDO ve migration Aşama 3'te; Aşama 2 verisi JSON dosyalarında, SQL sorgusu yok | ⏭ |
| SEC-011 | Parametreli sorgu | Doğru çalışmalı | MySQL, PDO ve migration Aşama 3'te; Aşama 2 verisi JSON dosyalarında, SQL sorgusu yok | ⏭ |
| SEC-012 | 30 dakika hareketsizlik | Oturum kapatılmalı | `AuthFlowTest::test_idle_30_minutes_logs_out` | ✅ |
| SEC-013 | Oturum ID değiştirme | Güvenli yenilenmeli | `AuthFlowTest::test_session_id_regenerated_on_login` | ✅ |
| SEC-014 | Kritik işlem kaydı | Log oluşturulmalı | `AuditLoggerTest::test_record_stores_actor_time_and_values` | ✅ |
| SEC-015 | Log kaydı değişikliği | Yetkisiz değişiklik engellenmeli | `AuditLoggerTest::test_repository_has_no_update_or_delete` | ✅ |
| SEC-016 | Yönetici ayar değişikliği | Eski/yeni değer kaydedilmeli | `SettingsServiceTest::test_update_records_old_and_new_value`; `SystemConfigTest::test_audit_page_lists_changes` | ✅ |
| SEC-017 | Geçerli DB bağlantısı | Bağlantı kurulmalı | MySQL, PDO ve migration Aşama 3'te; Aşama 2 verisi JSON dosyalarında, SQL sorgusu yok | ⏭ |
| SEC-018 | Hatalı DB bilgileri | Kontrollü hata | MySQL, PDO ve migration Aşama 3'te; Aşama 2 verisi JSON dosyalarında, SQL sorgusu yok | ⏭ |
| SEC-019 | Migration çalıştırma | Şema güncellenmeli | MySQL, PDO ve migration Aşama 3'te; Aşama 2 verisi JSON dosyalarında, SQL sorgusu yok | ⏭ |
| SEC-020 | Migration ikinci kez çalıştırma | Tekrarlı değişiklik olmamalı | MySQL, PDO ve migration Aşama 3'te; Aşama 2 verisi JSON dosyalarında, SQL sorgusu yok | ⏭ |
| SEC-021 | JSON okuma | Veri doğru okunmalı | `JsonStoreTest::test_missing_or_empty_file_reads_as_empty_list`, `test_unicode_is_stored_readably` | ✅ |
| SEC-022 | JSON yazma | Veri doğru yazılmalı | `JsonStoreTest::test_insert_assigns_incrementing_ids` | ✅ |
| SEC-023 | JSON güncelleme | Veri doğru güncellenmeli | `JsonStoreTest::test_update_merges_fields` | ✅ |
| SEC-024 | Bozuk JSON | Kontrollü hata | `JsonStoreTest::test_corrupt_file_throws_and_is_not_overwritten` | ✅ |
| SEC-025 | Eş zamanlı JSON yazma | Veri kaybı olmamalı | `JsonStoreTest::test_transaction_conditional_update_only_first_wins`; `JsonTicketRepositoryTest::test_concurrent_assign_cannot_exceed_capacity` | ✅ |
| SEC-026 | JSON'dan MySQL'e aktarım | Kayıtlar korunmalı | MySQL, PDO ve migration Aşama 3'te; Aşama 2 verisi JSON dosyalarında, SQL sorgusu yok | ⏭ |
| SEC-027 | Aynı veriyi tekrar aktarma | Çift kayıt oluşmamalı | MySQL, PDO ve migration Aşama 3'te; Aşama 2 verisi JSON dosyalarında, SQL sorgusu yok | ⏭ |
| SEC-028 | Yedek oluşturma | Yedek oluşmalı | `AdvancedAdminTest::test_backup_download_is_zip_and_admin_only` | ✅ |
| SEC-029 | Yedek dosyası bütünlüğü | Veri doğrulanmalı | `ScoreAndRestoreTest::test_invalid_backup_is_rejected_and_data_untouched` | ✅ |
| SEC-030 | Yedekten geri yükleme | Veri geri gelmeli | `ScoreAndRestoreTest::test_restore_brings_back_data_and_keeps_automatic_backup` | ✅ |
| SEC-031 | Bozuk yedek yükleme | İşlem güvenli biçimde durmalı | `ScoreAndRestoreTest::test_invalid_backup_is_rejected_and_data_untouched` | ✅ |
| SEC-032 | Zamanlanmış görev çalıştırma | Görev tetiklenmeli | `ConfigCompletionTest::test_inactive_staff_is_deactivated_after_configured_days`; `ScenarioGapsTest::test_sla_warning_notification_is_sent_once` (cron yok: SLA izleme her istekte en fazla dakikada bir çalışır, `php bin/sla-monitor.php` ile elle de çalıştırılır) | ✅ |
| SEC-033 | Aynı job iki kez çalıştırma | Çakışma önlenmeli | `SlaEdgeCasesTest::test_job_lock_allows_one_run_per_interval_and_expires` | ✅ |
| SEC-034 | Job yarıda kalma | Kilit kontrollü serbest bırakılmalı | `SlaEdgeCasesTest::test_job_lock_allows_one_run_per_interval_and_expires` | ✅ |
| SEC-035 | Beklenmeyen hata | Kontrollü hata yanıtı | `KernelTest::test_wrong_shaped_data_gives_friendly_500_without_leaking_input`, `test_logged_in_user_with_corrupt_data_sees_friendly_500_page` | ✅ |
| SEC-036 | Üretim ortamında hata | Hassas bilgiler gizlenmeli | `KernelTest::test_wrong_shaped_data_gives_friendly_500_without_leaking_input`, `test_logged_in_user_with_corrupt_data_sees_friendly_500_page` | ✅ |

## 12. Performans, yük ve arayüz testleri

| No | Senaryo | Beklenen | Test / denetim | Durum |
|---|---|---|---|---|
| PERF-001 | SLA hesaplama: 5 yıllık tatil verisi | ≤ 500 ms | `SlaCalculatorServiceTest::test_five_years_of_holidays_is_fast` | ✅ |
| PERF-002 | Kanban yükleme: 100 aktif talep | < 1 saniye | `KanbanPerformanceTest::test_kanban_with_100_active_tickets_renders_under_one_second` | ✅ |
| PERF-003 | Kanban AJAX: Sürükle bırak | < 200 ms | `PerformanceScenarioTest::test_kanban_move_is_fast` | ✅ |
| PERF-004 | Dosya erişimi: Yetkili dosya kontrolü | < 1 saniye | `AttachmentAccessTest::test_authorized_download_is_under_one_second` | ✅ |
| PERF-005 | Eş zamanlı görev alma: İki personel İkinci işlem | ≤ 100 ms içinde reddedilmeli | `PerformanceScenarioTest::test_second_take_is_rejected_quickly` | ✅ |
| PERF-006 | CSRF kontrolü: Geçersiz token | ≤ 50 ms | `PerformanceScenarioTest::test_invalid_csrf_is_rejected_quickly` | ✅ |
| PERF-007 | Bozuk JSON: Varsayılan takvime geçiş | ≤ 100 ms | `PerformanceScenarioTest::test_corrupt_calendar_falls_back_quickly` | ✅ |
| PERF-008 | Görsel boyutlandırma: Bir dosya | ≤ 2 saniye | `AttachmentServiceTest::test_downscale_finishes_within_two_seconds` | ✅ |
| PERF-009 | Video yükleme sonrası işleme: 50 MB video | ≤ 1600 ms | `AttachmentServiceTest::test_large_video_is_stored_quickly` | ✅ |
| PERF-010 | Oturum zaman aşımı: 30 dakika hareketsizlik | Oturum sonlandırılmalı | `AuthFlowTest::test_idle_30_minutes_logs_out` | ✅ |
| PERF-011 | Responsive tasarım: 375–1920 px | Yatay kaydırma olmamalı | Tarayıcıda 375, 768 ve 1920 piksel genişlikte bütün ekranlar açıldı; yatay kaydırma çubuğu çıkmadı | 👁 |
| PERF-012 | Raporlama: 10.000 talep | Performans ölçülmeli | `PerformanceScenarioTest::test_report_with_ten_thousand_tickets` (10.000 talepte süzme ve personel istatistiği 5 saniyenin altında) | ✅ |
| PERF-013 | Eş zamanlı kullanıcı: 100 kullanıcı | Hata oranı ölçülmeli | `PerformanceScenarioTest::test_hundred_requests_without_errors` (ayrıca k6 ile 100 eşzamanlı kullanıcı: 35.464 istek, hata %0, p95 110 ms (`docs/test-raporu.md`)) | ✅ |
| PERF-014 | Eş zamanlı kullanıcı: 500 kullanıcı | Sistem davranışı ölçülmeli | k6 ile 500 eşzamanlı kullanıcı (`tests/Load/k6-yuk-testi.js`): 36.032 istek, hata %0, p95 2,63 sn; işlem hızı yaklaşık 500 istek/sn'de doyuma ulaştı (`docs/test-raporu.md`) | 👁 |
| PERF-015 | Eş zamanlı kullanıcı: 1.000 kullanıcı | Darboğazlar belirlenmeli | k6 ile 1000 eşzamanlı kullanıcı: 35.968 istek, hata %0, p95 5,62 sn; darboğaz giriş (şifre doğrulama) ve JSON tablolarının tamamının okunması (`docs/test-raporu.md`) | 👁 |
