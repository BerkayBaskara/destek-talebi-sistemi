# 1. Mimari

Projemiz, modern PHP standartlarına (PSR-4, PSR-12) uygun olarak Controller, Service, Repository ve Model katmanlarından oluşan MVC (Model-View-Controller) türevi katmanlı bir mimari üzerine inşa edilmiştir. Yenilikçi özelliklerimiz (Kanban, Akıllı Dağıtım, SLA Isı Haritası) iş mantığını yormamak adına özel servis sınıflarına (Service) ayrılmıştır.

## 1.1. Katman Şeması

| Katman | Görevi | Asla Yapmaz | Destek Talebi Projesi Örneği |
| :--- | :--- | :--- | :--- |
| **Router** | URL adresine göre hangi controller metodunun çalışacağını seçer. | İş kuralı veya algoritma içermez. | `POST /ticket/create` → `TicketController::store` |
| **Controller** | İstekten (`$_POST`/`$_GET`) veriyi alır, ilgili servisi çağırır ve uygun görünümü (View) döndürür. | Veritabanı (SQL) sorgusu yazmaz, SLA hesabı yapmaz. | `TicketController`, `KanbanController` |
| **Service** | Projenin iş kurallarını ve çekirdek algoritmalarını barındırır. | `$_POST` okumaz, HTML üretmez veya doğrudan ekrana basmaz. | `SlaCalculatorService::calculateTime`, `SmartDispatcherService::assignTicket` |
| **Repository** | Veritabanı (veya JSON) okuma/yazma işlemlerini soyutlar. | İş kuralı veya yetki kontrolü içermez. | `TicketRepositoryInterface`, `JsonTicketRepository` |
| **Model** | Veritabanındaki bir kaydı temsil eden, veriyi taşıyan sade (DTO benzeri) sınıftır. | Veritabanı bağlantısı açmaz, SQL çalıştırmaz. | `Ticket`, `User`, `SlaPolicy` |
| **View** | Kullanıcıya HTML arayüzünü gösterir ve XSS önlemi için çıktıyı kaçışlar. | Veritabanından veri çekmez, karmaşık hesaplama yapmaz. | `views/kanban/board.php`, `views/ticket/form.php` |

## 1.2. Klasör Yapısı

Sistemdeki her bir dosya, projenin tek giriş noktası olan `public/index.php` üzerinden yönlendirilir. Dışarıdan yalnızca `public/` dizinine erişilebilir.

```text
destek-talebi-sistemi/
├── public/            # index.php (tek giriş noktası), css, js (Kanban AJAX), görseller
├── src/
│   ├── Controller/    # HTTP isteklerini karşılayan sınıflar (TicketController, AuthController)
│   ├── Service/       # SLA hesabı, kelime bazlı önceliklendirme ve akıllı atama algoritmaları
│   ├── Repository/    # Veri erişim arayüzleri ve JSON/MySQL implementasyonları
│   └── Model/         # Varlık sınıfları (User, Ticket, Category, Escalation)
├── views/             # Şablon dosyaları (Kanban panosu, ısı haritası görünümleri)
├── database/          # MySQL migration ve örnek veri (seed) betikleri (Aşama 3 için)
├── tests/             # Çekirdek SLA hesaplamaları için PHPUnit testleri
├── docs/              # srs.md, tasarim.md, ai-gunlugu.md ve telif/taslak görselleri
├── storage/           # Müşterilerin yüklediği ek dosyalar ve Aşama 2 JSON verileri
├── composer.json      # PSR-4 otomatik yükleme (autoload) ve bağımlılık (PHPUnit vb.) ayarları
└── README.md          # Kurulum ve çalıştırma talimatları
```

## 2. Sınıf Diyagramı

Sınıf diyagramımız, hocanın gereksinimlerinde belirttiği gibi en az 12 sınıftan oluşmaktadır[cite: 5]. Yenilikçi özelliklerimiz olan kelime bazlı önceliklendirme ve akıllı iş yükü ataması `SmartDispatcherService` içinde, çekirdek problemimiz olan mesai saatlerine duyarlı hesaplamalar ise `SlaCalculatorService` içinde konumlandırılmıştır[cite: 3]. Controller katmanı kesinlikle doğrudan Repository katmanına erişmez; tüm işlemler Service katmanı üzerinden geçer[cite: 5].

```mermaid
classDiagram
    %% Controller Katmanı
    class TicketController {
        +store() void
        +show() void
    }
    class KanbanController {
        +board() void
        +updateStatus() void
    }

    %% Service Katmanı (İş Kuralları ve Çekirdek Algoritmalar)
    class TicketService {
        -TicketRepositoryInterface repo
        -SlaCalculatorService slaService
        -SmartDispatcherService dispatcher
        +createTicket(int customerId, array data) Ticket
        +resolveTicket(int ticketId) void
    }
    class SlaCalculatorService {
        +calculateDeadline(DateTime createdAt, SlaPolicy policy) DateTime
        +checkEscalation(Ticket ticket) bool
        +calculateHeatmapColor(Ticket ticket) string
    }
    class SmartDispatcherService {
        -UserRepositoryInterface userRepo
        +analyzePriorityFromText(string message, int defaultPriority) int
        +assignOptimalAgent(Ticket ticket) User
    }

    %% Repository Katmanı (Veri Erişimi)
    class TicketRepositoryInterface {
        <<interface>>
        +save(Ticket t) void
        +findById(int id) Ticket
        +getTicketsByAgent(int agentId) array
    }
    class JsonTicketRepository {
        -string file
    }
    class MySqlTicketRepository {
        -PDO pdo
    }
    class UserRepositoryInterface {
        <<interface>>
        +findAvailableAgents() array
    }

    %% Model Katmanı (Varlıklar)
    class Ticket {
        +int id
        +int customerId
        +int agentId
        +int categoryId
        +string status
        +int priority
        +DateTime deadline
    }
    class User {
        +int id
        +string name
        +string role
    }
    class SlaPolicy {
        +int id
        +int categoryId
        +int durationHours
    }
    class Reply {
        +int id
        +int ticketId
        +int userId
        +string message
        +DateTime createdAt
    }

    %% İlişkiler
    TicketController ..> TicketService : kullanır
    KanbanController ..> TicketService : kullanır
    
    TicketService --> TicketRepositoryInterface : bağımlıdır
    TicketService --> SlaCalculatorService : bağımlıdır
    TicketService --> SmartDispatcherService : bağımlıdır
    
    SmartDispatcherService --> UserRepositoryInterface : bağımlıdır
    
    JsonTicketRepository ..|> TicketRepositoryInterface : uygular
    MySqlTicketRepository ..|> TicketRepositoryInterface : uygular
    
    TicketRepositoryInterface ..> Ticket : üretir
    
    Ticket *-- Reply : içerir
    Ticket --> User : müşteri ve personel
    Ticket --> SlaPolicy : kuralı
```

## 3. Sıralama Diyagramı

Sıralama diyagramı, projenin çekirdek problemi olan "yeni bir destek talebinin açılması, kelime bazlı önceliklendirilmesi, SLA süresinin hesaplanması ve uygun personele atanması" akışını göstermektedir. Mimari katman kurallarına uygun olarak Tarayıcı, Controller, Service ve Repository arasındaki iletişim resmedilmiştir. Hata durumu (geçersiz veri) `alt` bloğu ile ayrılmıştır[cite: 5].

```mermaid
sequenceDiagram
    participant T as Tarayıcı
    participant C as TicketController
    participant TS as TicketService
    participant Disp as SmartDispatcherService
    participant Sla as SlaCalculatorService
    participant R as TicketRepository

    T->>C: POST /ticket/create (kategori, öncelik, mesaj, ek)
    C->>TS: createTicket(customerId, data)
    TS->>TS: veri geçerli mi? (kategori var mı, mesaj boş mu?)
    
    alt veri geçersiz
        TS-->>C: ValidationException
        C-->>T: formu hata mesajıyla tekrar göster
    else veri geçerli
        TS->>Disp: analyzePriorityFromText(mesaj, öncelik)
        Disp-->>TS: int (hesaplanmış nihai öncelik)
        
        TS->>Disp: assignOptimalAgent()
        Disp->>R: findAvailableAgents()
        R-->>Disp: agentList (iş yükü listesi)
        Disp-->>TS: User (seçilen personel)
        
        TS->>Sla: calculateDeadline(now, policy)
        Note right of Sla: Mesai saatleri (09:00-18:00) <br/> ve tatil günleri hesaba katılır.
        Sla-->>TS: DateTime (SLA Bitiş Süresi)
        
        TS->>R: save(ticket)
        R-->>TS: kaydedildi
        TS-->>C: Ticket (Oluşturulan Talep)
        C-->>T: 302 /tickets + "Talebiniz Alındı" mesajı
    end
```

## 4. ER Diyagramı ve Normalizasyon

Aşağıdaki ER diyagramı, Aşama 3'te MySQL/MariaDB üzerinde kurulacak olan veritabanımızın yapısını göstermektedir. Sistemdeki tablolar, destek talebi ve SLA hesaplama gereksinimlerini karşılayacak şekilde tasarlanmıştır[cite: 3].

```mermaid
erDiagram
    USERS ||--o{ TICKETS : "müşteri olarak açar"
    USERS ||--o{ TICKETS : "personel olarak atanır"
    CATEGORIES ||--o{ TICKETS : "sınıflandırır"
    CATEGORIES ||--o{ SLA_POLICIES : "kurallara sahiptir"
    TICKETS ||--o{ REPLIES : "yanıtlar içerir"
    USERS ||--o{ REPLIES : "yanıt yazar"
    TICKETS ||--o{ ESCALATIONS : "eskalasyon kaydı"

    USERS {
        int id PK
        string name
        string email UK
        string password_hash
        string role "musteri, personel, yonetici"
    }
    CATEGORIES {
        int id PK
        string name UK
    }
    SLA_POLICIES {
        int id PK
        int category_id FK
        int priority "1:Düşük, 2:Orta, 3:Yüksek vb."
        int duration_hours
    }
    TICKETS {
        int id PK
        int customer_id FK
        int agent_id FK
        int category_id FK
        string status "Açık, İşlemde, Çözüldü, Kapalı"
        int priority
        datetime created_at
        datetime deadline "SLA Bitiş Süresi"
    }
    REPLIES {
        int id PK
        int ticket_id FK
        int user_id FK
        text message
        datetime created_at
    }
    HOLIDAYS {
        int id PK
        date holiday_date UK
        string description
    }
    ESCALATIONS {
        int id PK
        int ticket_id FK
        int escalated_from_user_id FK
        int escalated_to_user_id FK
        datetime escalated_at
    }
```

## 5. Tasarım Desenleri

Projede mimari zorunluluk olan **Repository** desenine ek olarak, destek talebi akışını ve SLA uyarı sistemini yönetmek için iki farklı tasarım deseni daha kullanılmıştır. Bu desenler ve seçim gerekçeleri aşağıdadır[cite: 5]:

### 5.1. State Deseni (Durum Yönetimi)
-   **Problem:** Destek taleplerinin belirli bir durum akışı (Açık → İşlemde → Çözüldü → Kapalı) izlemesi ve geçersiz durum geçişlerinin (Örn: Kapalı bir biletin İşlemde'ye alınması) engellenmesi gerekiyordu.
-   **Desen:** State deseni; `TicketState` arayüzü ile onu uygulayan `OpenState`, `InProgressState`, `ResolvedState` ve `ClosedState` sınıfları üzerinden uygulanmaktadır.
-   **Alternatif:** Desensiz çözüm, Controller veya Service içinde devasa `if/elseif` veya `switch/case` blokları yazarak durum kontrolü yapmaktı.
-   **Neden:** State deseni sayesinde her durum kendi kurallarını ve geçiş izinlerini yönetir, koda dokunmadan yeni bir durum eklemek kolaylaşır ve karmaşık `if` yığınlarının önüne geçilir.

### 5.2. Observer Deseni (SLA Eskalasyon ve Uyarı)
-   **Problem:** SLA süresinin dolması veya %80'e ulaşması gibi durumlarda, sistemin uyarı üretmesi ve otomatik olarak bir üst yetkiliye eskalasyon işlemini tetiklemesi gerekiyordu.
-   **Desen:** Observer deseni; olayları takip eden `TicketSubject` sınıfı ile tetiklendiğinde çalışan `SlaWarningObserver` ve `EscalationObserver` sınıfları aracılığıyla uygulanmaktadır.
-   **Alternatif:** Desensiz çözüm, SLA zamanını kontrol eden döngünün veya servisin içine eskalasyon kodlarını ve uyarı mesajlarını doğrudan (hard-coded) yazmaktı.
-   **Neden:** Observer deseni sayesinde olayı tespit eden kısım (SLA kontrolcüsü) ile olaya tepki veren kısım (eskalasyon/uyarı) birbirinden ayrılır, böylece sınıfların bağımlılıkları azalır ve esneklik artar.

## 6. Arayüz ve Sayfa Haritası

### 6.1. Sayfa Haritası

Sistemimizdeki sayfalar ve roller arası geçişler aşağıdaki haritada gösterilmiştir. Sistemde üç ana rol bulunmaktadır: Müşteri, Personel ve Yönetici.

```mermaid
flowchart LR
    G[Giriş / Kayıt] --> P[Ana Panel]
    
    %% Müşteri Akışı
    P --> M1[Talep Açma Formu - Müşteri]
    P --> M2[Taleplerim Listesi - Müşteri]
    M2 --> M3[Talep Detay ve Yanıt - Müşteri]

    %% Personel Akışı
    P --> A1[Kanban Panosu - Personel]
    A1 --> A2[Talep Detay ve SLA Isı Haritası - Personel]
    
    %% Yönetici Akışı
    P --> Y1[SLA Raporları - Yönetici]
    P --> Y2[Kategori ve Kural Yönetimi - Yönetici]
    P --> Y3[Kullanıcı Yönetimi - Yönetici]
```

## 7. Risk Listesi

Projenin zamanında ve eksiksiz tamamlanmasını engelleyebilecek potansiyel riskler, olasılık/etki dereceleri ve bunlara karşı alınacak önlemler aşağıda listelenmiştir. Çekirdek problemimiz olan SLA ve atama algoritmalarına özel önem verilmiştir[cite: 5].

| Risk | Olasılık | Etki | Önlem |
| :--- | :--- | :--- | :--- |
| **SLA tatil/mesai saati hesaplamalarının yanlış çıkması (Çekirdek Problem)** | Orta | Yüksek | `SlaCalculatorService` için özellikle hafta sonu, resmi tatil ve mesai bitimine denk gelen senaryoları kapsayan kapsamlı PHPUnit testleri yazılacak. |
| **Akıllı atama algoritmasının yükü yanlış dağıtması (Çekirdek Problem)** | Orta | Yüksek | Dağıtım servisine bir üst limit (threshold) eklenecek; uygun personel bulunamazsa biletler havuzda (genel kuyruk) bekletilecek. |
| **Eşzamanlılık (Race Condition) hatası** | Düşük | Orta | İki personelin aynı bileti aynı anda üzerine almasını veya kapatmasını önlemek için veritabanında *transaction* ve *satır kilidi* (`SELECT ... FOR UPDATE`) kullanılacak. |
| **Yenilikçi arayüzlerin (Kanban vb.) kapsamı şişirmesi (Scope Creep)** | Yüksek | Orta | Öncelikle temel CRUD ve arka plan SLA algoritması bitirilecek. Kanban ve AJAX özellikleri ikinci öncelik olarak ele alınacak; backlog'a sadık kalınacak. |
| **Yapay zekânın (AI) katman kurallarını bozması** | Yüksek | Yüksek | AI'a verilen her istemin (prompt) başına 1. Bölüm'deki mimari katman tablosu kural olarak verilecek. Kod depoya (commit) eklenmeden önce mutlaka ekipçe okunacak. |
| **Aynı dosyada çakışan değişiklikler (Git Conflict)** | Yüksek | Düşük | Kesinlikle dal (branch) ve pull request (PR) mantığıyla çalışılacak. Üyelerin görevleri dosya bazında ayrıştırılarak çakışma ihtimali en aza indirilecek. |