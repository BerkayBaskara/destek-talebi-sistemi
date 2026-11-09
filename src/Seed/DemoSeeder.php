<?php

declare(strict_types=1);

namespace App\Seed;

use App\Core\Clock\FixedClock;
use App\Kernel;
use App\Model\Category;
use App\Model\Enum\Priority;
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
     * Kategoriler ve kategori × öncelik SLA kuralları. Varsayılan süreler Priority::defaultSlaHours();
     * Ağ / Erişim kesintisi işi durdurduğu için Acil kuralı 2 saat.
     */
    public function seedCatalog(): void
    {
        $categories = $this->kernel->categoryRepository();
        $policies = $this->kernel->slaPolicyRepository();

        foreach (['Donanım', 'Yazılım', 'Ağ / Erişim', 'Hesap'] as $name) {
            $category = $categories->save(new Category(null, $name));
            foreach (Priority::cases() as $priority) {
                $hours = $name === 'Ağ / Erişim' && $priority === Priority::Urgent ? 2 : $priority->defaultSlaHours();
                $policies->save(new SlaPolicy(null, (int) $category->id, $priority, $hours));
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
            ['M100003', 1, Priority::Low, 'Ek monitör talebi', 'Çift ekranla çalışmak için ikinci bir monitör istiyoruz.', '-2 hours'],
            ['M100004', 4, Priority::Normal, 'Ortak klasöre erişim yetkisi', 'Muhasebe ortak klasörüne yazma yetkim yok.', '-90 minutes'],
            ['M100005', 2, Priority::Normal, 'Ofis programı lisansı', 'Yeni gelen stajyer için ofis programı lisansı gerekiyor.', '-40 minutes'],
            ['M100001', 1, Priority::Low, 'Kulaklık değişimi', 'Kulaklığın mikrofonu ses almıyor, yenisi lazım.', '-20 minutes'],
            ['M100002', 3, Priority::Normal, 'Misafir Wi-Fi şifresi', 'Toplantıya gelecek misafirler için Wi-Fi şifresi lazım.', '-3 hours'],
        ];
        foreach ($pool as [$customer, $category, $priority, $title, $description, $ago]) {
            $this->open($customer, $category, $priority, $title, $description, $now->modify($ago));
        }

        $cancelled = [
            ['M100001', 2, Priority::Normal, 'Excel açılmıyor', 'Excel her açılışta donuyor. (Sonradan kendim çözdüm.)', '-2 days'],
            ['M100003', 1, Priority::Low, 'Fare tekerleği', 'Fare tekerleği takılıyor.', '-3 days'],
            ['M100004', 4, Priority::Normal, 'Şifre sıfırlama', 'Şifremi unuttum, sıfırlanmasını istiyorum.', '-26 hours'],
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
            ['M100001', 1, Priority::Normal, 'Yazıcıdan çıktı alamıyorum', 'Muhasebe yazıcısı sürekli kağıt sıkıştırıyor.', '-6 days', '+2 hours', false],
            ['M100002', 2, Priority::High, 'Muhasebe programı açılmıyor', 'Program açılırken hata veriyor, fatura kesemiyoruz.', '-5 days', '+3 hours', true],
            ['M100003', 3, Priority::Urgent, 'VPN bağlantısı yok', 'Uzaktan çalışanlar VPN\'e bağlanamıyor.', '-5 days', '+30 hours', false],
            ['M100004', 4, Priority::Normal, 'E-posta şifresi değişikliği', 'E-posta şifremi değiştirmek istiyorum.', '-4 days', '+1 hour', false],
            ['M100005', 1, Priority::Low, 'Klavye tuşları basmıyor', 'Klavyede bazı tuşlar çalışmıyor.', '-7 days', '+5 hours', false],
            ['M100001', 2, Priority::Normal, 'Antivirüs uyarısı', 'Antivirüs sürekli güncelleme uyarısı veriyor.', '-3 days', '+4 hours', false],
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
            ['M100002', 4, Priority::Normal, 'Kullanıcı hesabı kilitlendi', 'Üç kez yanlış şifre girince hesabım kilitlendi.', '-2 days', '+2 hours'],
            ['M100003', 2, Priority::High, 'Rapor ekranı hata veriyor', 'Aylık rapor ekranı hata mesajı gösteriyor.', '-1 day', '+3 hours'],
            ['M100005', 3, Priority::Normal, 'İnternet çok yavaş', 'Öğleden sonra internet çok yavaşlıyor.', '-30 hours', '+6 hours'],
            ['M100004', 1, Priority::Low, 'Toplantı odası projektörü', 'Projektör görüntü vermiyor.', '-3 days', '+4 hours'],
        ];
        foreach ($resolved as [$customer, $category, $priority, $title, $description, $ago, $workTime]) {
            $ticket = $this->open($customer, $category, $priority, $title, $description, $now->modify($ago));
            $this->work($ticket, $this->agentOf($ticket), 'İnceleme tamam, çözüm uygulandı.', $workTime);
        }

        // Kırmızı: SLA'yı çoktan aşmış aktif talepler (alındı / inceleniyor)
        $breached = [
            // 6-8 gün önce: hafta sonu ve bayram tatili araya girse de SLA kesin aşılmış olur.
            ['M100001', 3, Priority::Urgent, 'Sunucu erişimi koptu', 'Sabahtan beri sunucuya bağlanamıyoruz.', '-6 days', true],
            ['M100004', 2, Priority::High, 'Fatura programı hata veriyor', 'Fatura kesmeye çalışınca program kapanıyor.', '-8 days', false],
            ['M100005', 1, Priority::Urgent, 'Kasa bilgisayarı açılmıyor', 'Kasa bilgisayarı hiç açılmıyor, satış yapamıyoruz.', '-7 days', true],
        ];
        foreach ($breached as [$customer, $category, $priority, $title, $description, $ago, $startReview]) {
            $ticket = $this->open($customer, $category, $priority, $title, $description, $now->modify($ago));
            if ($startReview) {
                $this->clock->advance('+1 hour');
                $service->perform((int) $ticket->id, $this->agentOf($ticket), 'start_review');
            }
        }

        // Sarı: SLA'nın %80'inden fazlası dolmuş, henüz aşılmamış aktif talepler
        $warning = [
            ['M100002', 3, Priority::Urgent, 'Kablosuz ağ kopuyor', 'Ofiste kablosuz ağ sık sık kopuyor.', true],
            ['M100003', 4, Priority::High, 'Yeni personel hesabı', 'Yeni başlayan personel için hesap açılmalı.', false],
            ['M100001', 1, Priority::Urgent, 'Sunucu odası klima arızası', 'Sunucu odasının kliması çalışmıyor, acil!', true],
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
            ['M100004', 2, Priority::Low, 'Yazılım güncellemesi', 'Muhasebe yazılımı güncelleme istiyor.', '-30 minutes', false],
            ['M100005', 1, Priority::Normal, 'Telefon santrali çöktü', 'Dahili telefonlar çalışmıyor.', '-15 minutes', false],
            ['M100002', 4, Priority::Low, 'Yetki talebi', 'Raporlama ekranına erişim yetkisi istiyorum.', '-45 minutes', true],
            ['M100003', 1, Priority::Low, 'Yeni fare talebi', 'Farenin tekerleği bozuldu.', '-10 minutes', false],
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
    private function open(string $customer, int $category, Priority $priority, string $title, string $description, DateTimeImmutable $at): Ticket
    {
        $this->clock->set($at);

        return $this->kernel->ticketService()->createTicket($this->users[$customer], [
            'category_id' => $category,
            'priority' => $priority->value,
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
     * SLA'sının ~%85-95'i dolmuş olacak açılış anı: şimdiden geriye, iş dakikasıyla, SLA süresinin %85'i kadar git.
     * (Hafta sonu veya mesai dışında çalıştırılsa da iş saatine göre doğru sonuç verir.)
     */
    private function warningStart(DateTimeImmutable $now, int $category, Priority $priority): DateTimeImmutable
    {
        $hours = $this->kernel->slaPolicyRepository()->findFor($category, $priority)->durationHours ?? $priority->defaultSlaHours();
        $target = (int) ceil($hours * 60 * 0.85);
        $calculator = $this->kernel->slaCalculator();

        $start = $now;
        for ($i = 0; $i < 2000 && $calculator->businessMinutesBetween($start, $now) < $target; $i++) {
            $start = $start->modify('-10 minutes');
        }

        return $start;
    }
}
