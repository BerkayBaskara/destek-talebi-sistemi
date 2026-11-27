<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Text;
use App\Kernel;
use App\Model\Enum\Role;
use App\Model\User;
use App\Service\TicketService;

/** Müşteri hesapları (yalnız yönetici, FR-33): listeleme, arama ve aktif/pasif yapma. Sayfa başına 50 kayıt (NFR-33). */
final class CustomerController
{
    public const PER_PAGE = 50;

    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function index(Request $request): Response
    {
        $this->kernel->requireRole(Role::Admin);
        $query = trim($request->string('q'));
        $needle = Text::fold($query);
        $customers = array_values(array_filter(
            $this->kernel->userRepository()->findByRole(Role::Customer),
            static fn (User $u): bool => $needle === ''
                || str_contains(Text::fold($u->name . ' ' . $u->surname), $needle)
                || str_contains(Text::fold($u->customerNo), $needle),
        ));
        usort($customers, static fn (User $a, User $b): int => $a->customerNo <=> $b->customerNo);
        $result = TicketService::paginate($customers, max(1, (int) $request->string('sayfa')), self::PER_PAGE);

        return $this->kernel->render('admin/config/customers', [
            'title' => 'Müşteriler',
            'result' => $result,
            'query' => $query,
        ]);
    }

    public function setActive(Request $request, int $id): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $active = $request->string('active') === '1';
        $user = $this->kernel->staffService()->setCustomerActive($admin, $id, $active);
        $this->kernel->session()->flash('success', $user->customerNo . ' numaralı müşteri ' . ($active ? 'aktif' : 'pasif') . ' yapıldı.');

        return Response::redirect('/yonetici/musteriler');
    }
}
