<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\Json\JsonUserRepository;
use PHPUnit\Framework\TestCase;
use Tests\Unit\TempStoreTrait;

final class JsonUserRepositoryTest extends TestCase
{
    use TempStoreTrait;

    private static function user(string $no, Role $role): User
    {
        return new User(null, $no, password_hash('x', PASSWORD_DEFAULT), $role, 'Ad', 'Soyad', 'Soru?', password_hash('cevap', PASSWORD_DEFAULT));
    }

    public function test_save_then_find_by_customer_no(): void
    {
        $repo = new JsonUserRepository($this->makeStore());

        $saved = $repo->save(self::user('M123456', Role::Customer));

        self::assertNotNull($saved->id);
        $found = $repo->findByCustomerNo('M123456');
        self::assertNotNull($found);
        self::assertSame($saved->id, $found->id);
        self::assertSame(Role::Customer, $found->role);
        self::assertSame('Ad Soyad', $found->fullName());
        self::assertNull($repo->findByCustomerNo('M000000'));
    }

    public function test_save_existing_updates(): void
    {
        $repo = new JsonUserRepository($this->makeStore());
        $user = $repo->save(self::user('M123456', Role::Customer));

        $user->name = 'Yeni';
        $repo->save($user);

        self::assertSame('Yeni', $repo->findById((int) $user->id)?->name);
        self::assertCount(1, $repo->findByRole(Role::Customer));
    }

    public function test_find_by_role(): void
    {
        $repo = new JsonUserRepository($this->makeStore());
        $repo->save(self::user('M100001', Role::Customer));
        $repo->save(self::user('P000001', Role::Agent));
        $repo->save(self::user('P000002', Role::Agent));

        $agents = $repo->findByRole(Role::Agent);

        self::assertSame(['P000001', 'P000002'], array_map(static fn (User $u): string => $u->customerNo, $agents));
    }
}
