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

    /** Yalnız personel hesapları yönetilir; yönetici kendi hesabını pasif yapamaz. */
    private function agent(int $userId): User
    {
        $user = $this->users->findById($userId) ?? throw new NotFoundException('Kullanıcı bulunamadı.');
        if ($user->role !== Role::Agent) {
            throw new ValidationException(['active' => 'Yalnız personel hesapları değiştirilebilir.']);
        }

        return $user;
    }
}
