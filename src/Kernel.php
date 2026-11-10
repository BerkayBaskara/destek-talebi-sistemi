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
use App\Repository\AttachmentRepositoryInterface;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\Json\JsonAttachmentRepository;
use App\Service\AttachmentService;
use App\Repository\Json\JsonCalendarRepository;
use App\Repository\Json\JsonCategoryRepository;
use App\Repository\Json\JsonReplyRepository;
use App\Repository\Json\JsonSlaPolicyRepository;
use App\Repository\Json\JsonTicketLogRepository;
use App\Repository\Json\JsonTicketRepository;
use App\Repository\Json\JsonUserRepository;
use App\Repository\ReplyRepositoryInterface;
use App\Repository\SlaPolicyRepositoryInterface;
use App\Repository\TicketLogRepositoryInterface;
use App\Repository\TicketRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Service\AssignmentService;
use App\Service\AuthService;
use App\Service\Observer\TicketLogObserver;
use App\Service\PriorityDetector;
use App\Service\SlaCalculatorService;
use App\Service\SlaStatusService;
use App\Service\TicketService;
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
    private readonly TicketRepositoryInterface $ticketRepository;
    private readonly CategoryRepositoryInterface $categoryRepository;
    private readonly SlaPolicyRepositoryInterface $slaPolicyRepository;
    private readonly ReplyRepositoryInterface $replyRepository;
    private readonly TicketLogRepositoryInterface $ticketLogRepository;
    private readonly SlaCalculatorService $slaCalculator;
    private readonly SlaStatusService $slaStatusService;
    private readonly TicketService $ticketService;
    private readonly AttachmentRepositoryInterface $attachmentRepository;
    private readonly AttachmentService $attachmentService;

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

        // Aşama 2: tüm depolar JSON. Aşama 3'te yalnız bu satırlar MySql* sınıflarıyla değişir.
        $this->ticketRepository = new JsonTicketRepository($this->store);
        $this->categoryRepository = new JsonCategoryRepository($this->store);
        $this->slaPolicyRepository = new JsonSlaPolicyRepository($this->store);
        $this->replyRepository = new JsonReplyRepository($this->store);
        $this->ticketLogRepository = new JsonTicketLogRepository($this->store);

        $config = $this->rootDir . '/storage/config';
        $this->slaCalculator = new SlaCalculatorService(new JsonCalendarRepository($config . '/calendar.json'));
        $this->slaStatusService = new SlaStatusService($this->slaCalculator, $this->clock);
        $this->ticketService = new TicketService(
            $this->ticketRepository,
            $this->replyRepository,
            $this->categoryRepository,
            $this->slaPolicyRepository,
            $this->slaCalculator,
            PriorityDetector::fromFile($config . '/priority_keywords.json'),
            new AssignmentService($this->userRepository, $this->ticketRepository),
            $this->clock,
        );
        $this->ticketService->attachObserver(new TicketLogObserver($this->ticketLogRepository, $this->clock));

        $this->attachmentRepository = new JsonAttachmentRepository($this->store);
        $this->attachmentService = AttachmentService::fromSettingsFile(
            $this->attachmentRepository,
            $this->rootDir . '/storage/uploads',
            $config . '/settings.json',
            $this->clock,
        );

        $registerRoutes = require __DIR__ . '/routes.php';
        $registerRoutes($this->router, $this);
    }

    public function handle(Request $request): Response
    {
        try {
            $isWrite = in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
            if ($this->enforceIdleTimeout() && $isWrite) {
                // Süresi dolmuş oturumla gönderilen form "CSRF geçersiz" yerine girişe yönlendirilir.
                return $request->isApi()
                    ? Response::json(['ok' => false, 'error' => 'Oturumunuz sona erdi. Lütfen tekrar giriş yapın.'], 401)
                    : Response::redirect('/giris');
            }

            if ($isWrite) {
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
        } catch (Throwable $e) {
            // Beklenmeyen her hata: ayrıntı yalnız sunucu günlüğüne; kullanıcıya genel mesaj
            // (hata ayrıntısı ekrana basılırsa girilen şifre gibi bilgiler sızabilir).
            error_log((string) $e);
            $response = $this->error($request, 500, 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.');
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

    public function ticketRepository(): TicketRepositoryInterface
    {
        return $this->ticketRepository;
    }

    public function categoryRepository(): CategoryRepositoryInterface
    {
        return $this->categoryRepository;
    }

    public function slaPolicyRepository(): SlaPolicyRepositoryInterface
    {
        return $this->slaPolicyRepository;
    }

    public function replyRepository(): ReplyRepositoryInterface
    {
        return $this->replyRepository;
    }

    public function ticketLogRepository(): TicketLogRepositoryInterface
    {
        return $this->ticketLogRepository;
    }

    public function slaCalculator(): SlaCalculatorService
    {
        return $this->slaCalculator;
    }

    public function slaStatusService(): SlaStatusService
    {
        return $this->slaStatusService;
    }

    public function ticketService(): TicketService
    {
        return $this->ticketService;
    }

    public function attachmentRepository(): AttachmentRepositoryInterface
    {
        return $this->attachmentRepository;
    }

    public function attachmentService(): AttachmentService
    {
        return $this->attachmentService;
    }

    /** NFR-10: son işlemden bu yana 30 dakika geçtiyse oturumu kapatır ve true döner. */
    private function enforceIdleTimeout(): bool
    {
        if ($this->session->get('user_id') === null) {
            return false;
        }

        $now = $this->clock->now()->getTimestamp();
        $last = $this->session->get('last_activity');
        if (is_int($last) && $now - $last > self::IDLE_TIMEOUT_SECONDS) {
            $this->session->destroy();
            $this->session->flash('error', 'Oturumunuz 30 dakika işlem yapılmadığı için sonlandırıldı. Lütfen tekrar giriş yapın.');

            return true;
        }

        $this->session->set('last_activity', $now);

        return false;
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
