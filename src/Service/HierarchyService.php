<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\HierarchyRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/** Yöneticinin kategori bazlı personel hiyerarşisini düzenlemesi (FR-43). Tümü doğrulanmadan hiçbir şey yazılmaz. */
final class HierarchyService
{
    public const MAX_LEVEL = 9;

    public function __construct(
        private readonly HierarchyRepositoryInterface $hierarchy,
        private readonly CategoryRepositoryInterface $categories,
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param array<int|string, string> $raw personel id → seviye metni ("" = hiyerarşide yok)
     * @throws ValidationException
     */
    public function saveLevels(User $admin, int $categoryId, array $raw): void
    {
        if ($this->categories->findById($categoryId) === null) {
            throw new NotFoundException('Kategori bulunamadı.');
        }

        $levels = [];
        $errors = [];
        foreach ($raw as $userId => $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            $user = $this->users->findById((int) $userId);
            if ($user === null || $user->role !== Role::Agent) {
                continue; // personel olmayan kimlik yok sayılır
            }
            if (preg_match('/^\d+$/', $value) !== 1 || (int) $value < 1 || (int) $value > self::MAX_LEVEL) {
                $errors['level_' . (int) $userId] = 'Seviye 1 ile ' . self::MAX_LEVEL . ' arasında olmalı.';
                continue;
            }
            $levels[(int) $userId] = (int) $value;
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $old = $this->hierarchy->levelsFor($categoryId);
        ksort($levels);
        if ($old === $levels) {
            return;
        }
        $this->hierarchy->replaceLevels($categoryId, $levels);
        $this->audit->record($admin, 'update', 'hierarchy', $categoryId, ['levels' => $old], ['levels' => $levels]);
    }
}
