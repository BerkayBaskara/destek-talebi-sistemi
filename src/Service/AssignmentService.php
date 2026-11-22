<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\TicketRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/**
 * FR-04: iş yüküne göre otomatik atama. Aktif iş yükü = durumu "Alındı" veya "İnceleniyor" olan
 * talep sayısı. Aday: aktif ve kapasitesi dolmamış personel. En az iş yükü olan seçilir; eşitlikte en küçük
 * numaralı (öngörülebilir, test edilebilir). Kimse uygun değilse talep havuzda bekler. (İzinli personel FR-20, Aşama 3.)
 */
final class AssignmentService
{
    /** @param StaffService|null $staff kapasite kuralı; verilmezse kapasite sınırsız sayılır */
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TicketRepositoryInterface $tickets,
        private readonly ?StaffService $staff = null,
    ) {
    }

    public function pickAgent(): ?User
    {
        $load = $this->tickets->countActiveByAgent();
        $best = null;
        $bestLoad = PHP_INT_MAX;

        foreach ($this->users->findByRole(Role::Agent) as $agent) {
            $current = $load[(int) $agent->id] ?? 0;
            if (!$agent->active || $current >= $this->capacityOf($agent)) {
                continue; // pasif ya da kapasitesi dolu personel atamaya girmez
            }
            if ($current < $bestLoad || ($current === $bestLoad && $best !== null && (int) $agent->id < (int) $best->id)) {
                $best = $agent;
                $bestLoad = $current;
            }
        }

        return $best;
    }

    /** Personelin aynı anda üstlenebileceği aktif talep sayısı. */
    public function capacityOf(User $agent): int
    {
        return $this->staff?->capacityOf($agent) ?? PHP_INT_MAX;
    }
}
