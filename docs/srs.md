# Destek Talebi Sistemi ve SLA Yönetimi - Yazılım Gereksinimi Belgesi (SRS)

## 1. Çekirdek Problem ve Proje Kapsamı
Bu projenin çözdüğü **çekirdek mühendislik problemi**, mesai saatlerine ve tatil günlerine duyarlı SLA (Hizmet Seviyesi Anlaşması) hesaplama algoritmasıdır. 
* Sistem; hafta içi 09:00 - 18:00 saatlerini mesai olarak kabul eder, öğle aralarını ve sisteme tanımlı resmi tatilleri süre hesabından düşer. 
* Örneğin, Cuma günü 17:30'da açılan ve 4 saatlik SLA süresi olan bir biletin çözüm tarihi Pazartesi öğleden sonraya sarkacak şekilde hesaplanır.
* İş yüküne göre biletler otomatik dağıtılır ve SLA süresi %80'i aştığında uyarılır, %100'ü aştığında otomatik eskalasyon yapılır.

## 2. Kapsam Dışı (Out of Scope)
Projenin boyutunu kontrol altında tutmak adına aşağıdaki özellikler kesinlikle **kapsam dışı** bırakılmıştır:
* E-posta veya SMS ile bildirim gönderimi (Bildirimler sadece sistem içi arayüzde gösterilir).
* Canlı sohbet (Live chat) entegrasyonu.
* Gerçek ödeme sistemi veya faturalandırma (Sadece destek süreci yönetilecektir).
* Biletleri PDF olarak indirme işlemi.

## 3. Use-Case (Kullanım Durumu) Diyagramı


## 4. Kullanıcı Hikayeleri (User Stories)

**Müşteri Hikayeleri:**
*   **US-01:** Müşteri olarak, sistem üzerinden yeni bir destek talebi açabilmeliyim ki sorunumu yetkililere bildirebileyim.
*   **US-02:** Müşteri olarak, talebime ekran görüntüsü (dosya) ekleyebilmeliyim ki sorunumu daha net anlatabileyim.
*   **US-03:** Müşteri olarak, açtığım taleplerin durumunu (Açık, İşlemde, Çözüldü) listeyebilmeliyim ki sürecin ne aşamada olduğunu bileyim.
*   **US-04:** Müşteri olarak, talebime personel tarafından verilen yanıtları okuyup tekrar cevap yazabilmeliyim.
*   **US-05:** Müşteri olarak, talebimi açarken önem derecesini (önceliğini) seçebilmeliyim ki acil durumları sisteme iletebileyim.

**Personel Hikayeleri:**
*   **US-06:** Personel olarak, üzerime atanan biletleri bir Kanban panosunda görebilmeliyim ki iş takibimi kolay yapabileyim.
*   **US-07:** Personel olarak, talebin durumunu sürükle-bırak yöntemiyle güncelleyebilmeliyim ki işlemlerimi hızlıca kaydedebileyim.
*   **US-08:** Personel olarak, bir talebe metin olarak yanıt yazıp müşteriye iletebilmeliyim.
*   **US-09:** Personel olarak, atanan biletler üzerindeki SLA geri sayımını dakika/saat cinsinden görebilmeliyim ki gecikmeleri önleyebileyim.
*   **US-10:** Personel olarak, SLA süresi yaklaşan biletlerde renkli (ısı haritası) uyarılar alabilmeliyim ki önceliğimi buna göre belirleyebileyim.

**Yönetici Hikayeleri:**
*   **US-11:** Yönetici olarak, sisteme yeni personel ve kategoriler ekleyebilmeliyim.
*   **US-12:** Yönetici olarak, personel SLA ihlal oranlarını ve ortalama çözüm sürelerini detaylı bir rapor olarak görebilmeliyim.
*   **US-13:** Yönetici olarak, destek kategorilerini ve bu kategorilerin varsayılan SLA kurallarını (kaç saatte çözülmeli) belirleyebilmeliyim.
*   **US-14:** Yönetici olarak, resmi tatilleri tarih bazlı olarak sisteme girebilmeliyim ki SLA hesaplamaları doğru yapılsın.
*   **US-15:** Yönetici olarak, geciken biletlerin (eskalasyon) hangi üst personele otomatik devredileceğini seçebilmeliyim.

## 5. İşlevsel Gereksinimler (Functional Requirements - FR)

*   **FR-01:** Sistem, müşteri girişi yapıldığında kategori, öncelik, başlık ve mesaj alanlarından oluşan bir form sunmalıdır. (Bağlantılı Hikayeler: US-01, US-05)
*   **FR-02:** Sistem, bilet oluşturma esnasında JPG/PNG/PDF formatlarında, tür ve boyut kontrolü yapılarak ek dosya yüklenmesine izin vermelidir. (US-02)
*   **FR-03:** Sistem, biletin içeriğini (metnini) analiz ederek anahtar kelime eşleşmesi ile akıllı/gölge öncelik belirleyebilmelidir.
*   **FR-04:** Sistem, açılan yeni bileti, havuzdaki mevcut aktif iş yükü (açık bilet sayısı) en az olan personele otomatik olarak atamalıdır.
*   **FR-05 (Çekirdek Algoritma):** Sistem; biletin açıldığı anı, sisteme girilen resmi tatil günlerini ve mesai saatlerini (09:00-18:00) hesaplayarak kesin bir SLA bitiş tarihi (Deadline) oluşturmalıdır.
*   **FR-06:** Sistem, durum geçişlerini State deseni ile kısıtlamalıdır. Kapatılmış bir bilet yeniden "İşlemde" durumuna alınamaz. (US-07)
*   **FR-07:** Sistem, SLA süresinin %80'i dolduğunda bileti arayüzde sarı uyarı moduna, %100'ü dolduğunda ise kırmızı ihlal moduna sokmalıdır. (US-09, US-10)
*   **FR-08:** Sistem, SLA süresi tamamen dolduğunda bileti otomatik olarak eskalasyon yöneticisine (bir üst yetkiliye) devretmeli ve veritabanına eskalasyon kaydı atmalıdır.
*   **FR-09:** Sistem, personelin biletleri yönetmesi için AJAX tabanlı (sayfa yenilemeden çalışan) bir Kanban panosu sağlamalıdır. (US-06)
*   **FR-10:** Sistem, yöneticilerin SLA kurallarını (X kategorisi için Y saat SLA kuralı) CRUD mantığı ile yönetmesine imkan tanımalıdır. (US-13)
*   **FR-11:** Sistem, yöneticilerin sadece tarih seçerek resmi tatilleri ekleyebileceği bir takvim arayüzü sunmalıdır. (US-14)
*   **FR-12:** Sistem, bilet çözüm işlemlerini kayıt altına almalı ve her personel için "SLA'ya uyum oranı" ve "Ortalama çözüm süresi" değerlerini hesaplayıp raporlamalıdır. (US-12)

## 6. Ölçülebilir İşlevsel Olmayan Gereksinimler (Non-Functional Requirements - NFR)

*   **NFR-01 Performans:** SLA hesaplama algoritması, 5 yıllık tatil verisi ve on binlerce bilet kaydı veritabanında bulunsa dahi, yeni bir bilet açıldığında süre hesabını en fazla **500 milisaniye (0.5 saniye)** içerisinde tamamlamalıdır.
*   **NFR-02 Kullanılabilirlik:** Personel Kanban panosu, ilgili personelin üzerinde 100 aktif bilet bulunduğunda bile sayfayı **1 saniyenin altında** render etmeli ve AJAX sürükle-bırak gecikmesi **200 ms altında** gerçekleşmelidir.
*   **NFR-03 Güvenlik:** Sisteme yüklenen ek dosyalar genel URL ile indirilememeli, "storage" dizininde korunmalı ve sadece biletin yetkili kullanıcısına **1 saniye içerisinde** doğrulanarak gösterilmelidir.
*   **NFR-04 Güvenilirlik / Eşzamanlılık:** İki personelin havuzdaki aynı bileti aynı anda üzerine almak istemesi durumunda, sistem ilk işlemi veritabanında satır kilidi (`SELECT ... FOR UPDATE`) ile bağlamalı ve ikinci personelin isteğini maksimum **100 ms içinde** reddederek hata mesajı göstermelidir.
*   **NFR-05 Uyumluluk:** Sistem arayüzü (özellikle Kanban panosu ve formlar), 375 px (mobil) genişlik ile 1920 px (masaüstü) genişlik arasındaki tüm ekranlarda, **%100 oranında yatay kaydırma çubuğu çıkmayacak şekilde** fonksiyonelliğini korumalıdır.