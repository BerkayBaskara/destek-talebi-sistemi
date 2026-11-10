# Yapay Zeka Günlüğü

## srs.md
srs.md dosyasında fonksiyonel/kalite gereksinimlerinin çoğu ve kullanıcı hikayeleri için de 3-4 örnek yapay zeka kullanılarak hazırlanmıştır.
* **Neden:** Fonksiyonel ve kalite gereksinimlerinin genel olarak tespit edilmesi ve kâğıda dökülmesi zordur. Bu aşamayı yapay zekaya yaptırıp inceledim; "kullanıcı hikayeleri"ne örnek teşkil etmesi için 3-4 örnek almanın daha verimli olacağına karar verdim.
* **Kullanılan prompt:** "Bana sistemin genelini ele alan 12 adet FR ve 6 adet NFR örneği ayrıca 3-4 tane de kullanıcı hikayesi verebilir misin?"
* **Müdahale:** Verilen 4-5 gereksinim eksik veya hatalıydı; bunlar ele alınıp düzeltildi, geri kalanlar doğrudan eklendi.

## tasarim.md
tasarim.md dosyasında katman şeması, klasör yapısı, tasarım desenleri, risk listesi ile plan ve backlog bölümlerinde ağırlıklı olarak yapay zeka kullanılmıştır. Diyagramlar, arayüz sayfa haritası ve ekran taslakları için ise yapay zekadan draw.io'da kullanılabilecek PlantUML formatında hazır şablonlar alınmış ve bunlar üzerinde değişiklikler yapılmıştır.
* **Neden:** Yapay zekanın sistemin tamamını daha iyi ele alması, gözden kaçan detayları yakalaması ve iş yükünü azaltması nedeniyle tercih edilmiştir.
* **Kullanılan prompt 1:** "Bana Destek Talebi Sistemi + SLA projesi için katman şeması, klasör yapısı, tasarım desenleri, risk listesi ve plan ve backlog'un detaylıca ele alındığı bir markdown kodu verebilir misin?"
* **Müdahale 1:** Müdahale olmadı.
* **Kullanılan prompt 2:** "Bana sınıf, sıralama ve ER diyagramları, arayüz sayfa haritası ve ekran taslaklarının her biri için draw.io'da kullanabileceğim, kopyala-yapıştır formatında alabileceğim PlantUML kodları verebilir misin?"
* **Müdahale 2:** Çoğu kez müdahale etmek zorunda kalındı; en son tüm kodları teker teker vermesi için yönlendirildi.
