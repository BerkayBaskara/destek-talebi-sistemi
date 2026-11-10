<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\HomeController;
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

    // Geçici paneller (sonraki dallarda gerçek ekranlarla değişecek)
    $router->add('GET', '/talepler', static fn (Request $r, array $p): Response => $home->placeholder('Taleplerim', Role::Customer));
    $router->add('GET', '/kanban', static fn (Request $r, array $p): Response => $home->placeholder('Kanban', Role::Agent, Role::Admin));
    $router->add('GET', '/yonetici', static fn (Request $r, array $p): Response => $home->placeholder('Yönetici paneli', Role::Admin));
};
