<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\LeaveRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use DateTimeImmutable;

/** Yöneticinin personel izinlerini girmesi (FR-20). İzinli personel o günlerde otomatik atamaya girmez. */
final class LeaveService
{
    public function __construct(
        private readonly LeaveRepositoryInterface $leaves,
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @throws ValidationException */
    public function add(User $admin, int $userId, string $start, string $end, string $note): int
    {
        $errors = [];
        $user = $this->users->findById($userId);
        if ($user === null || $user->role !== Role::Agent) {
            $errors['user_id'] = 'Bir personel seçin.';
        }
        $start = trim($start);
        $end = trim($end);
        if (!self::isDate($start)) {
            $errors['start'] = 'Başlangıç tarihi geçerli bir gün olmalı.';
        }
        if (!self::isDate($end)) {
            $errors['end'] = 'Bitiş tarihi geçerli bir gün olmalı.';
        } elseif (self::isDate($start) && $end < $start) {
            $errors['end'] = 'Bitiş tarihi başlangıçtan önce olamaz.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $note = mb_substr(trim($note), 0, 100);
        $id = $this->leaves->add($userId, $start, $end, $note);
        $this->audit->record($admin, 'create', 'leave', $id, null, ['user_id' => $userId, 'start' => $start, 'end' => $end, 'note' => $note]);

        return $id;
    }

    public function remove(User $admin, int $id): void
    {
        foreach ($this->leaves->all() as $leave) {
            if ($leave['id'] === $id) {
                $this->leaves->remove($id);
                $this->audit->record($admin, 'delete', 'leave', $id, $leave, null);

                return;
            }
        }
    }

    private static function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
