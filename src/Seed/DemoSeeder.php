<?php

declare(strict_types=1);

namespace App\Seed;

use App\Core\Clock\FixedClock;
use App\Kernel;
use App\Model\Category;
use App\Service\PriorityService;
use App\Model\Enum\Role;
use App\Model\SlaPolicy;
use App\Model\Ticket;
use App\Model\User;
use App\Service\AuthService;
use DateTimeImmutable;

/**
 * Sunum için örnek veri üretir (hoca: "boş veritabanıyla demo yapılmaz"). Tüm şifreler: Destek2026!
 *
 * Talepler gerçek akıştan geçer (TicketService): saat geçmişe alınır, talep açılır, ileri sarılarak
 * personel eylemleri yapılır. Böylece otomatik atama, SLA bitişi ve durum geçmişi gerçek kurallarla oluşur.
 * Sarı/kırmızı SLA durumları seed'in çalıştığı ana göre ayarlanır; sunumdan hemen önce yeniden çalıştırın.
 */
final class DemoSeeder
{
    /** Varsayılan öncelik kimlikleri (PriorityService::defaults). */
    private const LOW = 1;
    private const NORMAL = 2;
    private const HIGH = 3;
    private const TOP = 4;

    public const PASSWORD = 'Destek2026!';
    public const SECRET_ANSWER = 'pamuk';

    /** @var array<string, User> numara => kullanıcı */
    private array $users = [];

    public function __construct(private readonly Kernel $kernel, private readonly FixedClock $clock)
    {
    }

    /** Örnek talepleri geçmiş tarihlerde açabilmek için ileri/geri alınabilen saat. */
    public function clock(): FixedClock
    {
        return $this->clock;
    }

    /** @return list<array{rol: string, numara: string, ad: string}> oluşturulan giriş bilgileri */
    public function run(): array
    {
        $now = $this->clock->now();

        $accounts = $this->seedUsers([
            [Role::Admin, 'Y000001', 'Selin', 'Arslan'],
            [Role::Customer, 'M100001', 'Ahmet', 'Yılmaz'],
            [Role::Customer, 'M100002', 'Ayşe', 'Çelik'],
            [Role::Customer, 'M100003', 'Mehmet', 'Öztürk'],
            [Role::Customer, 'M100004', 'Fatma', 'Aydın'],
            [Role::Customer, 'M100005', 'Can', 'Koç'],
        ]);
        $this->seedCatalog();

        // Personel yokken açılan talepler ortak havuzda kalır (ve bir kısmı müşterice iptal edilir).
        $this->seedPoolAndCancelled($now);

        $accounts = array_merge($accounts, $this->seedUsers([
            [Role::Agent, 'P000001', 'Zeynep', 'Kaya'],
            [Role::Agent, 'P000002', 'Murat', 'Demir'],
            [Role::Agent, 'P000003', 'Elif', 'Şahin'],
        ]));
        $this->seedWorkedTickets($now);

        $this->clock->set($now);

        return $accounts;
    }

    /**
     * Öncelikler, kategoriler ve kategori × öncelik SLA kuralları. Varsayılan süre her önceliğin kendi süresi;
     * Ağ / Erişim kesintisi işi durdurduğu için Çok Yüksek kuralı 2 saat.
     */
    public function seedCatalog(): void
    {
        $priorities = $this->kernel->priorityRepository();
        foreach (PriorityService::defaults() as $priority) {
            $priorities->save($priority);
        }

        $categories = $this->kernel->categoryRepository();
        $policies = $this->kernel->slaPolicyRepository();
        foreach (['Donanım', 'Yazılım', 'Ağ / Erişim', 'Hesap'] as $name) {
            $category = $categories->save(new Category(null, $name));
            foreach ($priorities->findAll() as $priority) {
                $hours = $name === 'Ağ / Erişim' && $priority->id === self::TOP ? 2 : $priority->defaultSlaHours;
                $policies->save(new SlaPolicy(null, (int) $category->id, (int) $priority->id, $hours));
            }
        }
    }

    /**
     * @param list<array{0: Role, 1: string, 2: string, 3: string}> $people
     * @return list<array{rol: string, numara: string, ad: string}>
     */
    public function seedUsers(array $people = []): array
    {
        if ($people === []) {
            $people = [
                [Role::Admin, 'Y000001', 'Selin', 'Arslan'],
                [Role::Agent, 'P000001', 'Zeynep', 'Kaya'],
                [Role::Agent, 'P000002', 'Murat', 'Demir'],
                [Role::Agent, 'P000003', 'Elif', 'Şahin'],
                [Role::Customer, 'M100001', 'Ahmet', 'Yılmaz'],
            ];
        }

        $repo = $this->kernel->userRepository();
        $passwordHash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $answerHash = password_hash(self::SECRET_ANSWER, PASSWORD_DEFAULT);
        $created = [];

        foreach ($people as [$role, $number, $name, $surname]) {
            $user = $repo->save(new User(null, $number, $passwordHash, $role, $name, $surname, AuthService::SECRET_QUESTIONS[0], $answerHash));
            $this->users[$number] = $user;
            $created[] = ['rol' => $role->label(), 'numara' => $number, 'ad' => $name . ' ' . $surname];
        }

        return $created;
    }

    private function seedPoolAndCancelled(DateTimeImmutable $now): void
    {
        $pool = [
            ['M100003', 1, self::LOW, 'Ek monitör talebi', 'Çift ekranla çalışmak için ikinci bir monitör istiyoruz.', '-2 hours'],
            ['M100004', 4, self::NORMAL, 'Ortak klasöre erişim yetkisi', 'Muhasebe ortak klasörüne yazma yetkim yok.', '-90 minutes'],
            ['M100005', 2, self::NORMAL, 'Ofis programı lisansı', 'Yeni gelen stajyer için ofis programı lisansı gerekiyor.', '-40 minutes'],
            ['M100001', 1, self::LOW, 'Kulaklık değişimi', 'Kulaklığın mikrofonu ses almıyor, yenisi lazım.', '-20 minutes'],
            ['M100002', 3, self::NORMAL, 'Misafir Wi-Fi şifresi', 'Toplantıya gelecek misafirler için Wi-Fi şifresi lazım.', '-3 hours'],
        ];
        foreach ($pool as [$customer, $category, $priority, $title, $description, $ago]) {
            $this->open($customer, $category, $priority, $title, $description, $now->modify($ago));
        }

        $cancelled = [
            ['M100001', 2, self::NORMAL, 'Excel açılmıyor', 'Excel her açılışta donuyor. (Sonradan kendim çözdüm.)', '-2 days'],
            ['M100003', 1, self::LOW, 'Fare tekerleği', 'Fare tekerleği takılıyor.', '-3 days'],
            ['M100004', 4, self::NORMAL, 'Şifre sıfırlama', 'Şifremi unuttum, sıfırlanmasını istiyorum.', '-26 hours'],
        ];
        foreach ($cancelled as [$customer, $category, $priority, $title, $description, $ago]) {
            $ticket = $this->open($customer, $category, $priority, $title, $description, $now->modify($ago));
            $this->clock->advance('+15 minutes');
            $this->kernel->ticketService()->perform((int) $ticket->id, $this->users[$customer], 'cancel');
        }
    }

    private function seedWorkedTickets(DateTimeImmutable $now): void
    {
        $service = $this->kernel->ticketService();

        // Kapalı (müşteri onayladı); biri önce reddedildi, biri SLA'yı aşarak çözüldü.
        $closed = [
            ['M100001', 1, self::NORMAL, 'Yazıcıdan çıktı alamıyorum', 'Muhasebe yazıcısı sürekli kağıt sıkıştırıyor.', '-6 days', '+2 hours', false],
            ['M100002', 2, self::HIGH, 'Muhasebe programı açılmıyor', 'Program açılırken hata veriyor, fatura kesemiyoruz.', '-5 days', '+3 hours', true],
            ['M100003', 3, self::TOP, 'VPN bağlantısı yok', 'Uzaktan çalışanlar VPN\'e bağlanamıyor.', '-5 days', '+30 hours', false],
            ['M100004', 4, self::NORMAL, 'E-posta şifresi değişikliği', 'E-posta şifremi değiştirmek istiyorum.', '-4 days', '+1 hour', false],
            ['M100005', 1, self::LOW, 'Klavye tuşları basmıyor', 'Klavyede bazı tuşlar çalışmıyor.', '-7 days', '+5 hours', false],
            ['M100001', 2, self::NORMAL, 'Antivirüs uyarısı', 'Antivirüs sürekli güncelleme uyarısı veriyor.', '-3 days', '+4 hours', false],
        ];
        foreach ($closed as [$customer, $category, $priority, $title, $description, $ago, $workTime, $rejectOnce]) {
            $ticket = $this->open($customer, $category, $priority, $title, $description, $now->modify($ago));
            $agent = $this->agentOf($ticket);
            $this->work($ticket, $agent, 'Talebinize bakıyorum.', $workTime);
            if ($rejectOnce) {
                $this->clock->advance('+1 hour');
                $service->addReply((int) $ticket->id, $this->users[$customer], 'Sorun hâlâ devam ediyor.');
                $service->perform((int) $ticket->id, $this->users[$customer], 'reject');
                $this->clock->advance('+2 hours');
                $service->addReply((int) $ticket->id, $agent, 'Programı yeniden kurdum, tekrar dener misiniz?');
                $service->perform((int) $ticket->id, $agent, 'resolve');
            }
            $this->clock->advance('+3 hours');
            $service->perform((int) $ticket->id, $this->users[$customer], 'approve');
        }

        // Çözüldü (müşteri onayı bekleniyor)
        $resolved = [
            ['M100002', 4, self::NORMAL, 'Kullanıcı hesabı kilitlendi', 'Üç kez yanlış şifre girince hesabım kilitlendi.', '-2 days', '+2 hours'],
            ['M100003', 2, self::HIGH, 'Rapor ekranı hata veriyor', 'Aylık rapor ekranı hata mesajı gösteriyor.', '-1 day', '+3 hours'],
            ['M100005', 3, self::NORMAL, 'İnternet çok yavaş', 'Öğleden sonra internet çok yavaşlıyor.', '-30 hours', '+6 hours'],
            ['M100004', 1, self::LOW, 'Toplantı odası projektörü', 'Projektör görüntü vermiyor.', '-3 days', '+4 hours'],
        ];
        foreach ($resolved as [$customer, $category, $priority, $title, $description, $ago, $workTime]) {
            $ticket = $this->open($customer, $category, $priority, $title, $description, $now->modify($ago));
            $this->work($ticket, $this->agentOf($ticket), 'İnceleme tamam, çözüm uygulandı.', $workTime);
        }

        // Kırmızı: SLA'yı çoktan aşmış aktif talepler (alındı / inceleniyor)
        $breached = [
            // 6-8 gün önce: hafta sonu ve bayram tatili araya girse de SLA kesin aşılmış olur.
            ['M100001', 3, self::TOP, 'Sunucu erişimi koptu', 'Sabahtan beri sunucuya bağlanamıyoruz.', '-6 days', true],
            ['M100004', 2, self::HIGH, 'Fatura programı hata veriyor', 'Fatura kesmeye çalışınca program kapanıyor.', '-8 days', false],
            ['M100005', 1, self::TOP, 'Kasa bilgisayarı açılmıyor', 'Kasa bilgisayarı hiç açılmıyor, satış yapamıyoruz.', '-7 days', true],
        ];
        foreach ($breached as [$customer, $category, $priority, $title, $description, $ago, $startReview]) {
            $ticket = $this->open($customer, $category, $priority, $title, $description, $now->modify($ago));
            if ($startReview) {
                $this->clock->advance('+1 hour');
                $service->perform((int) $ticket->id, $this->agentOf($ticket), 'start_review');
            }
        }

        // Sarı: SLA'nın ~%82'si dolmuş aktif talepler. Uzun SLA'lı (16-24 iş saati) öncelikler seçildi ki
        // sarı durum sunum boyunca birkaç iş saati sürsün (kısa SLA'lar dakikalar içinde kırmızıya döner).
        // Açıklamalarda anahtar kelime yok; öncelik yükselip SLA kısalmasın.
        $warning = [
            ['M100002', 3, self::NORMAL, 'Kablosuz ağ kopuyor', 'Ofiste kablosuz ağ sık sık kopuyor.', true],
            ['M100003', 4, self::LOW, 'Yeni personel hesabı', 'Yeni başlayan personel için hesap açılmalı.', false],
            ['M100001', 1, self::NORMAL, 'Sunucu odası kliması', 'Sunucu odasının kliması soğutmuyor, bakım gerekiyor.', true],
        ];
        foreach ($warning as [$customer, $category, $priority, $title, $description, $startReview]) {
            $ticket = $this->open($customer, $category, $priority, $title, $description, $this->warningStart($now, $category, $priority));
            if ($startReview) {
                $this->clock->advance('+10 minutes');
                $service->perform((int) $ticket->id, $this->agentOf($ticket), 'start_review');
                $service->addReply((int) $ticket->id, $this->agentOf($ticket), 'İnceliyorum, yakında dönüş yapacağım.');
            }
        }

        // Normal: yeni açılmış aktif talepler (biri anahtar kelimeyle önceliği yükselmiş)
        $fresh = [
            ['M100004', 2, self::LOW, 'Yazılım güncellemesi', 'Muhasebe yazılımı güncelleme istiyor.', '-30 minutes', false],
            ['M100005', 1, self::NORMAL, 'Telefon santrali çöktü', 'Dahili telefonlar çalışmıyor.', '-15 minutes', false],
            ['M100002', 4, self::LOW, 'Yetki talebi', 'Raporlama ekranına erişim yetkisi istiyorum.', '-45 minutes', true],
            ['M100003', 1, self::LOW, 'Yeni fare talebi', 'Farenin tekerleği bozuldu.', '-10 minutes', false],
        ];
        foreach ($fresh as [$customer, $category, $priority, $title, $description, $ago, $startReview]) {
            $ticket = $this->open($customer, $category, $priority, $title, $description, $now->modify($ago));
            if ($startReview) {
                $this->clock->advance('+5 minutes');
                $service->perform((int) $ticket->id, $this->agentOf($ticket), 'start_review');
            }
        }
    }

    /** Saati verilen ana alıp talebi müşteri adına açar. */
    private function open(string $customer, int $category, int $priority, string $title, string $description, DateTimeImmutable $at): Ticket
    {
        $this->clock->set($at);

        return $this->kernel->ticketService()->createTicket($this->users[$customer], [
            'category_id' => $category,
            'priority' => $priority,
            'title' => $title,
            'description' => $description,
        ]);
    }

    /** Personel incelemeye alır, yanıt yazar ve $duration sonra çözer. */
    private function work(Ticket $ticket, User $agent, string $message, string $duration): void
    {
        $service = $this->kernel->ticketService();
        $this->clock->advance('+20 minutes');
        $service->perform((int) $ticket->id, $agent, 'start_review');
        $service->addReply((int) $ticket->id, $agent, $message);
        $this->clock->advance($duration);
        $service->perform((int) $ticket->id, $agent, 'resolve');
    }

    private function agentOf(Ticket $ticket): User
    {
        $fresh = $this->kernel->ticketRepository()->findById((int) $ticket->id);
        $agent = $fresh?->agentId !== null ? $this->kernel->userRepository()->findById($fresh->agentId) : null;
        if ($agent === null) {
            throw new \RuntimeException('Örnek talep personele atanmadı: #' . $ticket->id);
        }

        return $agent;
    }

    /**
     * SLA'sının ~%82'si dolmuş olacak açılış anı: şimdiden geriye, iş dakikasıyla, SLA süresinin %82'si kadar git.
     * (Hafta sonu veya mesai dışında çalıştırılsa da iş saatine göre doğru sonuç verir.)
     */
    private function warningStart(DateTimeImmutable $now, int $category, int $priority): DateTimeImmutable
    {
        $hours = $this->kernel->slaPolicyRepository()->findFor($category, $priority)->durationHours
            ?? $this->kernel->priorityRepository()->findById($priority)->defaultSlaHours ?? 16;
        $target = (int) ceil($hours * 60 * 0.82);
        $calculator = $this->kernel->slaCalculator();

        $start = $now;
        for ($i = 0; $i < 2000 && $calculator->businessMinutesBetween($start, $now) < $target; $i++) {
            $start = $start->modify('-10 minutes');
        }

        return $start;
    }
}
