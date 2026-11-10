<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Router;
use App\Kernel;

/**
 * Rota tablosu: hangi adres hangi controller metoduna gider.
 * Her rota bir sonraki görevlerde kendi controller'ıyla eklenir.
 */
return static function (Router $router, Kernel $kernel): void {
    $router->add('GET', '/', static fn (Request $r, array $p): Response => $kernel->render('home'));
};
