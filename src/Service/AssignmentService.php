<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\TicketRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/**
 * FR-04: iş yüküne göre otomatik atama. Aktif iş yükü = durumu "Alındı" veya "İnceleniyor" olan
 * talep sayısı. En az iş yükü olan personel seçilir; eşitlikte en küçük numaralı (öngörülebilir, test edilebilir).
 * (İzinli personeli hariç tutmak FR-20, Aşama 3.)
 */
final class AssignmentService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TicketRepositoryInterface $tickets,
    ) {
    }

    public function pickAgent(): ?User
    {
        $load = $this->tickets->countActiveByAgent();
        $best = null;
        $bestLoad = PHP_INT_MAX;

        foreach ($this->users->findByRole(Role::Agent) as $agent) {
            $current = $load[(int) $agent->id] ?? 0;
            if ($current < $bestLoad || ($current === $bestLoad && $best !== null && (int) $agent->id < (int) $best->id)) {
                $best = $agent;
                $bestLoad = $current;
            }
        }

        return $best;
    }
}
