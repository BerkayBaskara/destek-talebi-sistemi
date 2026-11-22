<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Clock\ClockInterface;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\HierarchyRepositoryInterface;
use App\Repository\LeaveRepositoryInterface;
use App\Repository\TicketRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/**
 * FR-04, FR-48: otomatik atama. Aktif iş yükü = durumu "Atandı" veya "İnceleniyor" olan talep sayısı.
 * Uygun personel: aktif, bugün izinde değil, kapasitesi dolmamış. Kategoride hiyerarşi tanımlıysa yalnız o
 * kategorinin personeli aday olur ve en alt uygun seviyeden seçilir; tanımlı değilse tüm personel adaydır.
 * Aynı seviyede en az iş yükü olan, eşitlikte en küçük numaralı seçilir. Kimse uygun değilse talep havuzda bekler.
 */
final class AssignmentService
{
    /**
     * @param StaffService|null $staff kapasite kuralı; verilmezse kapasite sınırsız sayılır
     * @param HierarchyRepositoryInterface|null $hierarchy verilmezse hiyerarşi yok sayılır
     * @param LeaveRepositoryInterface|null $leaves verilmezse izin denetlenmez
     */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TicketRepositoryInterface $tickets,
        private readonly ?StaffService $staff = null,
        private readonly ?HierarchyRepositoryInterface $hierarchy = null,
        private readonly ?LeaveRepositoryInterface $leaves = null,
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    /**
     * @param int|null $categoryId talebin kategorisi (null: hiyerarşiye bakılmaz)
     * @param int $aboveLevel eskalasyonda çözemeyen personelin seviyesi; yalnız daha üst seviyeler aday olur
     */
    public function pickAgent(?int $categoryId = null, int $aboveLevel = 0): ?User
    {
        $levels = $categoryId !== null ? $this->levelsFor($categoryId) : [];
        if ($levels === [] && $aboveLevel > 0) {
            return null; // hiyerarşi yoksa "üst seviye" tanımsız
        }

        $load = $this->tickets->countActiveByAgent();
        $today = $this->clock?->now()->format('Y-m-d');
        $best = null;
        $bestKey = null;

        foreach ($this->users->findByRole(Role::Agent) as $agent) {
            $id = (int) $agent->id;
            $level = $levels === [] ? 0 : ($levels[$id] ?? null);
            if ($level === null || ($levels !== [] && $level <= $aboveLevel)) {
                continue; // bu kategorinin hiyerarşisinde yok ya da yeterince üst seviyede değil
            }
            $current = $load[$id] ?? 0;
            if (!$agent->active || $current >= $this->capacityOf($agent)) {
                continue; // pasif ya da kapasitesi dolu
            }
            if ($today !== null && $this->leaves?->isOnLeave($id, $today) === true) {
                continue; // bugün izinli
            }
            $key = [$level, $current, $id]; // önce en alt seviye, sonra en az iş yükü, sonra küçük numara
            if ($bestKey === null || $key < $bestKey) {
                $best = $agent;
                $bestKey = $key;
            }
        }

        return $best;
    }

    /** Personelin kategorideki seviyesi; hiyerarşide yoksa null. */
    public function levelOf(int $categoryId, int $userId): ?int
    {
        return $this->levelsFor($categoryId)[$userId] ?? null;
    }

    /** Kategoride hiyerarşi tanımlıysa personel o kategoride yetkili mi; tanımsızsa herkes yetkili. */
    public function canWork(int $categoryId, int $userId): bool
    {
        $levels = $this->levelsFor($categoryId);

        return $levels === [] || isset($levels[$userId]);
    }

    /** Personelin aynı anda üstlenebileceği aktif talep sayısı. */
    public function capacityOf(User $agent): int
    {
        return $this->staff?->capacityOf($agent) ?? PHP_INT_MAX;
    }

    /** @return array<int, int> */
    private function levelsFor(int $categoryId): array
    {
        return $this->hierarchy?->levelsFor($categoryId) ?? [];
    }
}
