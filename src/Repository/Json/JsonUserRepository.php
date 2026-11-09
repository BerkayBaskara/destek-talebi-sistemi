<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\UserRepositoryInterface;

/** users.json üzerinde kullanıcı deposu. */
final class JsonUserRepository implements UserRepositoryInterface
{
    private const TABLE = 'users';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function findById(int $id): ?User
    {
        $row = $this->store->find(self::TABLE, $id);

        return $row === null ? null : User::fromArray($row);
    }

    public function findByCustomerNo(string $customerNo): ?User
    {
        foreach ($this->store->all(self::TABLE) as $row) {
            if (($row['customer_no'] ?? null) === $customerNo) {
                return User::fromArray($row);
            }
        }

        return null;
    }

    public function findByRole(Role $role): array
    {
        $users = [];
        foreach ($this->store->all(self::TABLE) as $row) {
            if (($row['role'] ?? null) === $role->value) {
                $users[] = User::fromArray($row);
            }
        }

        return $users;
    }

    public function save(User $user): User
    {
        $data = $user->toArray();
        unset($data['id']);

        if ($user->id === null) {
            $user->id = $this->store->insert(self::TABLE, $data);
        } else {
            $this->store->update(self::TABLE, $user->id, $data);
        }

        return $user;
    }
}
