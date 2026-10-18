## Yapay Zeka Günlüğü

# srs.md
srs.md dosyasında, fonksiyonel/kalite gereksinimlerinin çoğu ve kullanıcı hikayeleri için de 3-4 örnek yapay zekaya kullanılarak hazırlanmıştır.
* **Neden:** fonksiyonel ve kalite gereksinimlerinin genel olarak tespit edilmesi ve kağıda dökülmesi zordur bu aşamayı yapay zekaya yaptırıp şahsen incelemin, "kullanıcı hikayeleri" ne örnek teşkil etmesi için 3-4 örnek almanın daha verimli olacağına karar verilmiştir.
* **Kullanılan prompt:** "Bana sistemin genelini ele alan 12 adet FR ve 6 adet NFR örneği ayrıca 3-4 tane de kullanıcı hikayesi verebilir misin?"
* **Müdahale:** verilen 4-5 gereksinim eksik veya saçmaydı bunları şahsen ele alındı geri kalanlar doğrudan eklendi.


# tasarim.md
tasarim.md dosyasında katman şeması, klasör yapısı, tasarım desenleri, risk listesi ve plan ve backlog da ağrılıklı olarak yapay zeka kullanılmıştır. Diyagramlar, arayüz sayfa haritası ve ekran taslakları için ise yapay zekadan draw.io da kullanabilmek için plantuml formatında hazır şablonlar alınarak bunlar üzerinde değişikler yapılmıştır.
* **Neden:** Yapay zekanın sistemin tamamını daha iyi bir şekilde ele alması gözünden kaçan detayları yakalaması ve ayrıca iş yükünü azalttığından dolayı tercih edilmiştir.
* **Kullanılan prompt 1:** "Bana Destek Talebi Sistemi + SLA projesi için katman şeması, klasör yapısı, tasarım desenleri, risk listesi ve plan ve backlog'un detaylıca ele alındığı bir markdown kodu verebilir misin?"
* **Müdahele 1:** müdahale olmadı
* **Kullanılan prompt 2:** "Bana sınıf, sıralama ve ER diyagramları, arayüz sayfa haritası ve ekran taslaklarının her biri için draw.io da kullanabileceğim kopyala yapıştır formatında alabileceğim plantuml kodları verebilir misin?"
* **Müdahele 2:** çoğu kez müdahale etmek zorunda kalındı en son tüm kodları teker teker vermesi için yönlendirildi