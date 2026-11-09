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
use App\Exception\RedirectException;
use App\Model\Enum\Role;
use App\Model\User;
use App\Repository\Json\JsonUserRepository;
use App\Repository\UserRepositoryInterface;
use App\Service\AuthService;
use Throwable;

/**
 * Uygulamanın kalbi: nesneleri bir kez kurar (elle yazılmış basit bağımlılık kurulumu),
 * rotaları bağlar ve her isteği bir yanıta çevirir. Hataları kullanıcı dostu sayfalara dönüştürür.
 */
final class Kernel
{
    /** NFR-10: 30 dakika hareketsiz kalan oturum kapatılır. */
    public const IDLE_TIMEOUT_SECONDS = 1800;

    private readonly ClockInterface $clock;
    private readonly SessionInterface $session;
    private readonly JsonStore $store;
    private readonly Csrf $csrf;
    private readonly View $view;
    private readonly Router $router;
    private readonly UserRepositoryInterface $userRepository;
    private readonly AuthService $authService;

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

        $this->userRepository = new JsonUserRepository($this->store);
        $this->authService = new AuthService($this->userRepository);

        $registerRoutes = require __DIR__ . '/routes.php';
        $registerRoutes($this->router, $this);
    }

    public function handle(Request $request): Response
    {
        try {
            $this->enforceIdleTimeout();

            if (in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $token = $request->input('_csrf') ?? $request->header('X-CSRF-Token');
                if (!$this->csrf->isValid(is_string($token) ? $token : null)) {
                    throw new HttpException(403, 'Güvenlik anahtarı (CSRF) eksik veya geçersiz. Sayfayı yenileyip tekrar deneyin.');
                }
            }

            $response = $this->router->dispatch($request);
        } catch (RedirectException $e) {
            $response = Response::redirect($e->url);
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

    /** Oturumdaki kullanıcı; giriş yapılmamışsa null. */
    public function currentUser(): ?User
    {
        $id = $this->session->get('user_id');

        return is_int($id) ? $this->userRepository->findById($id) : null;
    }

    /**
     * Giriş yapmış ve verilen rollerden birine sahip kullanıcıyı döner.
     * Giriş yoksa /giris'e yönlendirir, rol uymazsa 403 verir. Her korumalı işlem bununla başlar.
     */
    public function requireRole(Role ...$roles): User
    {
        $user = $this->currentUser();
        if ($user === null) {
            throw new RedirectException('/giris');
        }
        if ($roles !== [] && !in_array($user->role, $roles, true)) {
            throw new AuthorizationException('Bu sayfaya erişim yetkiniz yok.');
        }

        return $user;
    }

    /** Girişte çağrılır: oturum kimliği yenilenir (session fixation önlemi). */
    public function logIn(User $user): void
    {
        $this->session->regenerate();
        $this->session->set('user_id', $user->id);
        $this->session->set('last_activity', $this->clock->now()->getTimestamp());
    }

    public function logOut(): void
    {
        $this->session->destroy();
    }

    /**
     * Bir şablonu ortak verilerle (CSRF alanı, flash mesajlar, oturumdaki kullanıcı) HTML yanıtına çevirir.
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
        $this->view->share('currentUser', $this->currentUser());

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

    public function userRepository(): UserRepositoryInterface
    {
        return $this->userRepository;
    }

    public function authService(): AuthService
    {
        return $this->authService;
    }

    /** NFR-10: son işlemden bu yana 30 dakika geçtiyse oturumu kapatır. */
    private function enforceIdleTimeout(): void
    {
        if ($this->session->get('user_id') === null) {
            return;
        }

        $now = $this->clock->now()->getTimestamp();
        $last = $this->session->get('last_activity');
        if (is_int($last) && $now - $last > self::IDLE_TIMEOUT_SECONDS) {
            $this->session->destroy();
            $this->session->flash('error', 'Oturumunuz 30 dakika işlem yapılmadığı için sonlandırıldı. Lütfen tekrar giriş yapın.');

            return;
        }

        $this->session->set('last_activity', $now);
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
