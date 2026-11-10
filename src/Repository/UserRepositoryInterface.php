<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Enum\Role;
use App\Model\User;

/** Kullanıcı verisine erişim. Aşama 2: JsonUserRepository, Aşama 3: MySqlUserRepository. */
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByCustomerNo(string $customerNo): ?User;

    /** @return list<User> id sırasıyla */
    public function findByRole(Role $role): array;

    /** id yoksa ekler, varsa günceller; id'si dolu kullanıcıyı döner. */
    public function save(User $user): User;
}
