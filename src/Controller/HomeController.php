<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Response;
use App\Kernel;
use App\Model\Enum\Role;

/** Ana sayfa: kullanıcıyı rolüne göre kendi paneline yönlendirir. */
final class HomeController
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function index(): Response
    {
        $user = $this->kernel->currentUser();
        if ($user === null) {
            return Response::redirect('/giris');
        }

        return Response::redirect(match ($user->role) {
            Role::Customer => '/talepler',
            Role::Agent => '/kanban',
            Role::Admin => '/yonetici',
        });
    }
}
