<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Response;
use App\Kernel;
use App\Model\Enum\Role;

/** Ana sayfa: misafire yöneticinin yazdığı tanıtım sayfası; giriş yapmış kullanıcı kendi paneline yönlendirilir. */
final class HomeController
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function index(): Response
    {
        $user = $this->kernel->currentUser();
        if ($user === null) {
            $settings = $this->kernel->settingsService();

            return $this->kernel->render('home', [
                'title' => 'Ana Sayfa',
                'heading' => $settings->string('site.home_heading'),
                'text' => $settings->string('site.home_text'),
            ]);
        }

        return Response::redirect(match ($user->role) {
            Role::Customer => '/talepler',
            Role::Agent => '/kanban',
            Role::Admin => '/yonetici',
        });
    }
}
