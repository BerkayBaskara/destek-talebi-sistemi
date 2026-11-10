<?php

declare(strict_types=1);

namespace App\Seed;

use App\Core\Clock\FixedClock;
use App\Kernel;
use App\Model\Category;
use App\Model\Enum\Priority;
use App\Model\Enum\Role;
use App\Model\SlaPolicy;
use App\Model\User;
use App\Service\AuthService;

/**
 * Sunum için örnek veri üretir (hoca: "boş veritabanıyla demo yapılmaz").
 * Tüm hesapların şifresi: Destek2026!
 */
final class DemoSeeder
{
    public const PASSWORD = 'Destek2026!';
    public const SECRET_ANSWER = 'pamuk';

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
        $accounts = $this->seedUsers();
        $this->seedCatalog();

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

    /** @return list<array{rol: string, numara: string, ad: string}> */
    public function seedUsers(): array
    {
        $people = [
            [Role::Admin, 'Y000001', 'Selin', 'Arslan'],
            [Role::Agent, 'P000001', 'Zeynep', 'Kaya'],
            [Role::Agent, 'P000002', 'Murat', 'Demir'],
            [Role::Agent, 'P000003', 'Elif', 'Şahin'],
            [Role::Customer, 'M100001', 'Ahmet', 'Yılmaz'],
            [Role::Customer, 'M100002', 'Ayşe', 'Çelik'],
            [Role::Customer, 'M100003', 'Mehmet', 'Öztürk'],
            [Role::Customer, 'M100004', 'Fatma', 'Aydın'],
            [Role::Customer, 'M100005', 'Can', 'Koç'],
        ];

        $users = $this->kernel->userRepository();
        $passwordHash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $answerHash = password_hash(self::SECRET_ANSWER, PASSWORD_DEFAULT);
        $created = [];

        foreach ($people as [$role, $number, $name, $surname]) {
            $users->save(new User(null, $number, $passwordHash, $role, $name, $surname, AuthService::SECRET_QUESTIONS[0], $answerHash));
            $created[] = ['rol' => $role->label(), 'numara' => $number, 'ad' => $name . ' ' . $surname];
        }

        return $created;
    }
}
