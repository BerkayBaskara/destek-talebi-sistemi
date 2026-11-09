<?php

declare(strict_types=1);

namespace App;

use App\Core\Clock\ClockInterface;
use App\Core\Clock\SystemClock;
use App\Core\Csrf;
use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Router;
use App\Core\Session\NativeSession;
use App\Core\Session\SessionInterface;
use App\Core\Storage\JsonStore;
use App\Core\Storage\StorageException;
use App\Core\View;
use App\Exception\AuthorizationException;
use App\Exception\NotFoundException;
use Throwable;

/**
 * Uygulamanın kalbi: nesneleri bir kez kurar (elle yazılmış basit bağımlılık kurulumu),
 * rotaları bağlar ve her isteği bir yanıta çevirir. Hataları kullanıcı dostu sayfalara dönüştürür.
 */
final class Kernel
{
    private readonly ClockInterface $clock;
    private readonly SessionInterface $session;
    private readonly JsonStore $store;
    private readonly Csrf $csrf;
    private readonly View $view;
    private readonly Router $router;

    public function __construct(
        private readonly string $rootDir,
        ?ClockInterface $clock = null,
        ?SessionInterface $session = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
        $this->session = $session ?? new NativeSession();
        $this->store = new JsonStore($this->rootDir . '/storage/data');
        $this->csrf = new Csrf($this->session);
        $this->view = new View($this->rootDir . '/views');
        $this->router = new Router();

        $registerRoutes = require __DIR__ . '/routes.php';
        $registerRoutes($this->router, $this);
    }

    public function handle(Request $request): Response
    {
        try {
            if (in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $token = $request->input('_csrf') ?? $request->header('X-CSRF-Token');
                if (!$this->csrf->isValid(is_string($token) ? $token : null)) {
                    throw new HttpException(403, 'Güvenlik anahtarı (CSRF) eksik veya geçersiz. Sayfayı yenileyip tekrar deneyin.');
                }
            }

            $response = $this->router->dispatch($request);
        } catch (HttpException $e) {
            $response = $this->error($request, $e->status, $e->getMessage());
        } catch (AuthorizationException $e) {
            $response = $this->error($request, 403, $e->getMessage());
        } catch (NotFoundException $e) {
            $response = $this->error($request, 404, $e->getMessage());
        } catch (StorageException $e) {
            error_log($e->getMessage());
            $response = $this->error($request, 500, 'Veri okunamadı. Lütfen yöneticiye haber verin.');
        }

        return $response->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
        ]);
    }

    /**
     * Bir şablonu ortak verilerle (CSRF alanı, flash mesajlar) birlikte HTML yanıtına çevirir.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], int $status = 200): Response
    {
        $token = $this->csrf->token();
        $this->view->share('csrfToken', $token);
        $this->view->share('csrfField', static fn (): string => '<input type="hidden" name="_csrf" value="' . e($token) . '">');
        $this->view->share('flashSuccess', $this->session->pullFlash('success'));
        $this->view->share('flashError', $this->session->pullFlash('error'));

        return new Response($this->view->render($template, $data), $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function clock(): ClockInterface
    {
        return $this->clock;
    }

    public function session(): SessionInterface
    {
        return $this->session;
    }

    public function store(): JsonStore
    {
        return $this->store;
    }

    public function csrf(): Csrf
    {
        return $this->csrf;
    }

    public function rootDir(): string
    {
        return $this->rootDir;
    }

    private function error(Request $request, int $status, string $message): Response
    {
        if ($request->isApi()) {
            return Response::json(['ok' => false, 'error' => $message], $status);
        }

        $template = in_array($status, [403, 404, 500], true) ? 'errors/' . $status : 'errors/500';
        try {
            return $this->render($template, ['message' => $message, 'status' => $status], $status);
        } catch (Throwable) {
            return new Response(e($message), $status, ['Content-Type' => 'text/html; charset=utf-8']);
        }
    }
}
