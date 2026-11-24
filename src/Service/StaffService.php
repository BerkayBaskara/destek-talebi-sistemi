<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\UserRepositoryInterface;

/**
 * Yöneticinin personel yönetimi: personel hesabı oluşturma, aktif/pasif yapma, görev kapasitesi.
 * Pasif personel giriş yapamaz ve otomatik atamaya girmez. Yönetici hesabı burada oluşturulmaz
 * (yalnız sunucuda `php bin/create-admin.php`).
 */
final class StaffService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuthService $auth,
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @throws ValidationException */
    public function create(User $admin, string $name, string $surname, #[\SensitiveParameter] string $password): User
    {
        $agent = $this->auth->createStaff(Role::Agent, $name, $surname, $password);
        $this->audit->record($admin, 'create', 'staff', $agent->id, null, [
            'customer_no' => $agent->customerNo, 'name' => $agent->fullName(),
        ]);

        return $agent;
    }

    /** @throws ValidationException */
    public function setActive(User $admin, int $userId, bool $active): User
    {
        $user = $this->agent($userId);
        if ($user->active === $active) {
            return $user;
        }
        $user->active = $active;
        $this->users->save($user);
        $this->audit->record($admin, $active ? 'activate' : 'deactivate', 'staff', $userId, ['active' => !$active], ['active' => $active]);

        return $user;
    }

    /** @throws ValidationException */
    public function setCapacity(User $admin, int $userId, ?int $capacity): User
    {
        if ($capacity !== null && ($capacity < 1 || $capacity > 100)) {
            throw new ValidationException(['capacity' => 'Kapasite 1 ile 100 arasında bir tam sayı olmalı.']);
        }
        $user = $this->agent($userId);
        if ($user->capacity === $capacity) {
            return $user;
        }
        $old = $user->capacity;
        $user->capacity = $capacity;
        $this->users->save($user);
        $this->audit->record($admin, 'update', 'staff', $userId, ['capacity' => $old], ['capacity' => $capacity]);

        return $user;
    }

    /** Personelin aynı anda üstlenebileceği aktif talep sayısı (boşsa genel varsayılan). */
    public function capacityOf(User $agent): int
    {
        return $agent->capacity ?? $this->settings->int('staff.default_capacity');
    }

    /**
     * Formdaki kapasite alanı: boş = varsayılan (null), aksi hâlde pozitif tam sayı.
     *
     * @throws ValidationException
     */
    public static function parseCapacity(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $value) !== 1) {
            throw new ValidationException(['capacity' => 'Kapasite 1 ile 100 arasında bir tam sayı olmalı.']);
        }

        return (int) $value;
    }

    /** Personelin adını ve soyadını düzenler (US-26). @throws ValidationException */
    public function rename(User $admin, int $userId, string $name, string $surname): User
    {
        $name = trim($name);
        $surname = trim($surname);
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors['name'] = 'Ad en az 2 karakter olmalı.';
        }
        if (mb_strlen($surname) < 2 || mb_strlen($surname) > 100) {
            $errors['surname'] = 'Soyad en az 2 karakter olmalı.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $user = $this->agent($userId);
        if ($user->name === $name && $user->surname === $surname) {
            return $user;
        }
        $old = ['name' => $user->name, 'surname' => $user->surname];
        $user->name = $name;
        $user->surname = $surname;
        $this->users->save($user);
        $this->audit->record($admin, 'update', 'staff', $userId, $old, ['name' => $name, 'surname' => $surname]);

        return $user;
    }

    /**
     * FR-49: ayardaki gün sayısı boyunca giriş yapmayan personeli pasif yapar (0 = kapalı).
     * Hiç giriş yapmamış personelde sayım ilk denetimde başlar. Pasif yapılanların listesini döner.
     *
     * @return list<User>
     */
    public function deactivateInactive(\DateTimeImmutable $now): array
    {
        $days = $this->settings->int('staff.inactivity_days');
        if ($days <= 0) {
            return [];
        }
        $deactivated = [];
        foreach ($this->users->findByRole(Role::Agent) as $agent) {
            if (!$agent->active) {
                continue;
            }
            if ($agent->lastLoginAt === null) {
                $agent->lastLoginAt = $now; // sayım bugünden başlar
                $this->users->save($agent);
                continue;
            }
            if ($agent->lastLoginAt->modify('+' . $days . ' days') < $now) {
                $agent->active = false;
                $this->users->save($agent);
                $deactivated[] = $agent;
            }
        }

        return $deactivated;
    }

    /** Yalnız personel hesapları yönetilir; yönetici kendi hesabını pasif yapamaz. */
    /** FR-33: müşteri hesabını aktif ya da pasif yapar; pasif müşteri giriş yapamaz, açık oturumu sonraki istekte kapanır. */
    public function setCustomerActive(User $admin, int $userId, bool $active): User
    {
        $user = $this->users->findById($userId);
        if ($user === null || $user->role !== Role::Customer) {
            throw new NotFoundException('Müşteri bulunamadı.');
        }
        if ($user->active === $active) {
            return $user;
        }
        $user->active = $active;
        $this->users->save($user);
        $this->audit->record($admin, $active ? 'activate' : 'deactivate', 'customer', $userId, ['active' => !$active], ['active' => $active]);

        return $user;
    }

    private function agent(int $userId): User
    {
        $user = $this->users->findById($userId) ?? throw new NotFoundException('Kullanıcı bulunamadı.');
        if ($user->role !== Role::Agent) {
            throw new ValidationException(['active' => 'Yalnız personel hesapları değiştirilebilir.']);
        }

        return $user;
    }
}
