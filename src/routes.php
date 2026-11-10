<?php

declare(strict_types=1);

use App\Controller\AttachmentController;
use App\Controller\AuthController;
use App\Controller\HomeController;
use App\Controller\TicketController;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Router;
use App\Kernel;
use App\Model\Enum\Role;

/**
 * Rota tablosu: hangi adres hangi controller metoduna gider.
 */
return static function (Router $router, Kernel $kernel): void {
    $home = new HomeController($kernel);
    $auth = new AuthController($kernel, $kernel->authService());

    $router->add('GET', '/', static fn (Request $r, array $p): Response => $home->index());

    // Kimlik doğrulama
    $router->add('GET', '/giris', static fn (Request $r, array $p): Response => $auth->loginForm());
    $router->add('POST', '/giris', static fn (Request $r, array $p): Response => $auth->login($r));
    $router->add('POST', '/cikis', static fn (Request $r, array $p): Response => $auth->logout());
    $router->add('GET', '/kayit', static fn (Request $r, array $p): Response => $auth->registerForm());
    $router->add('POST', '/kayit', static fn (Request $r, array $p): Response => $auth->register($r));
    $router->add('GET', '/kayit/tamam', static fn (Request $r, array $p): Response => $auth->registerDone());
    $router->add('GET', '/sifre-sifirla', static fn (Request $r, array $p): Response => $auth->forgotForm());
    $router->add('POST', '/sifre-sifirla', static fn (Request $r, array $p): Response => $auth->forgot($r));
    $router->add('POST', '/sifre-sifirla/yeni', static fn (Request $r, array $p): Response => $auth->reset($r));

    // Talepler
    $tickets = new TicketController($kernel, $kernel->ticketService());
    $router->add('GET', '/talepler', static fn (Request $r, array $p): Response => $tickets->index($r));
    $router->add('GET', '/talepler/yeni', static fn (Request $r, array $p): Response => $tickets->createForm());
    $router->add('POST', '/talepler', static fn (Request $r, array $p): Response => $tickets->store($r));
    $router->add('GET', '/talepler/{id}', static fn (Request $r, array $p): Response => $tickets->show((int) $p['id']));
    $router->add('POST', '/talepler/{id}/yanit', static fn (Request $r, array $p): Response => $tickets->reply($r, (int) $p['id']));
    $router->add('POST', '/talepler/{id}/islem', static fn (Request $r, array $p): Response => $tickets->action($r, (int) $p['id']));

    // Ek dosyalar (yalnız yetkili kullanıcıya)
    $attachments = new AttachmentController($kernel, $kernel->attachmentService());
    $router->add('GET', '/ekler/{id}', static fn (Request $r, array $p): Response => $attachments->download((int) $p['id']));

    // Geçici paneller (sonraki dallarda gerçek ekranlarla değişecek)
    $router->add('GET', '/kanban', static fn (Request $r, array $p): Response => $home->placeholder('Kanban', Role::Agent, Role::Admin));
    $router->add('GET', '/yonetici', static fn (Request $r, array $p): Response => $home->placeholder('Yönetici paneli', Role::Admin));
};
