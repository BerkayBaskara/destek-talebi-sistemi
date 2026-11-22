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
use App\Repository\AuditLogRepositoryInterface;
use App\Repository\Json\JsonAuditLogRepository;
use App\Repository\Json\JsonSettingsRepository;
use App\Repository\Json\JsonPriorityRepository;
use App\Repository\Json\JsonWorkCalendarRepository;
use App\Repository\WorkCalendarRepositoryInterface;
use App\Service\CalendarService;
use App\Service\CatalogService;
use App\Repository\PriorityRepositoryInterface;
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
use App\Repository\TicketActivityRepositoryInterface;
use App\Repository\Json\JsonTicketActivityRepository;
use App\Service\Observer\TicketActivityObserver;
use App\Repository\TicketRepositoryInterface;
use App\Repository\UserRepositoryInterface;
use App\Service\AssignmentService;
use App\Service\AuditLogger;
use App\Service\AuthService;
use App\Service\BrandingService;
use App\Service\HierarchyService;
use App\Service\BackupService;
use App\Service\DetailedReportService;
use App\Repository\CategoryFieldRepositoryInterface;
use App\Repository\Json\JsonCategoryFieldRepository;
use App\Service\NotificationService;
use App\Service\EscalationService;
use App\Service\SlaMonitorService;
use App\Service\Observer\EscalationObserver;
use App\Repository\EscalationRepositoryInterface;
use App\Repository\Json\JsonEscalationRepository;
use App\Repository\Json\JsonRuntimeStateRepository;
use App\Service\Observer\NotificationObserver;
use App\Repository\NotificationRepositoryInterface;
use App\Repository\Json\JsonNotificationRepository;
use App\Service\LeaveService;
use App\Repository\HierarchyRepositoryInterface;
use App\Repository\LeaveRepositoryInterface;
use App\Repository\Json\JsonHierarchyRepository;
use App\Repository\Json\JsonLeaveRepository;
use App\Service\SettingsService;
use App\Service\StaffService;
use App\Service\Observer\TicketLogObserver;
use App\Service\PriorityService;
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

    /** Giriş gerektirmeyen formlar: oturum süresi dolmuş olsa da normal işlenir. */
    private const PUBLIC_FORMS = ['/giris', '/kayit', '/sifre-sifirla', '/sifre-sifirla/yeni'];

    private readonly ClockInterface $clock;
    private readonly SessionInterface $session;
    private readonly JsonStore $store;
    private readonly Csrf $csrf;
    private readonly View $view;
    private readonly Router $router;
    private readonly AuditLogRepositoryInterface $auditLogRepository;
    private readonly AuditLogger $auditLogger;
    private readonly SettingsService $settingsService;
    private readonly TicketActivityRepositoryInterface $ticketActivityRepository;
    private readonly BrandingService $brandingService;
    private readonly HierarchyRepositoryInterface $hierarchyRepository;
    private readonly LeaveRepositoryInterface $leaveRepository;
    private readonly HierarchyService $hierarchyService;
    private readonly CategoryFieldRepositoryInterface $categoryFieldRepository;
    private readonly NotificationRepositoryInterface $notificationRepository;
    private readonly NotificationService $notificationService;
    private readonly AssignmentService $assignmentService;
    private readonly EscalationRepositoryInterface $escalationRepository;
    private readonly EscalationService $escalationService;
    private readonly SlaMonitorService $slaMonitor;
    private readonly LeaveService $leaveService;
    private readonly PriorityRepositoryInterface $priorityRepository;
    private readonly PriorityService $priorityService;
    private readonly WorkCalendarRepositoryInterface $workCalendarRepository;
    private readonly CalendarService $calendarService;
    private readonly CatalogService $catalogService;
    private readonly StaffService $staffService;
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

        $this->auditLogRepository = new JsonAuditLogRepository($this->store);
        $this->auditLogger = new AuditLogger($this->auditLogRepository, $this->clock);
        $this->settingsService = new SettingsService(new JsonSettingsRepository($this->store), $this->auditLogger);
        $this->brandingService = new BrandingService($this->rootDir . '/public/branding', $this->settingsService, $this->auditLogger);

        $this->userRepository = new JsonUserRepository($this->store);
        $this->authService = new AuthService($this->userRepository, $this->settingsService, new \App\Repository\Json\JsonAttemptRepository($this->store), $this->clock);
        $this->staffService = new StaffService($this->userRepository, $this->authService, $this->settingsService, $this->auditLogger);

        // Aşama 2: tüm depolar JSON. Aşama 3'te yalnız bu satırlar MySql* sınıflarıyla değişir.
        $this->ticketRepository = new JsonTicketRepository($this->store);
        $this->categoryRepository = new JsonCategoryRepository($this->store);
        $this->slaPolicyRepository = new JsonSlaPolicyRepository($this->store);
        $this->priorityRepository = new JsonPriorityRepository($this->store);
        $this->priorityService = new PriorityService($this->priorityRepository, $this->auditLogger);
        $this->replyRepository = new JsonReplyRepository($this->store);
        $this->ticketLogRepository = new JsonTicketLogRepository($this->store);

        $config = $this->rootDir . '/storage/config';
        $this->workCalendarRepository = new JsonWorkCalendarRepository($this->store, null, new JsonCalendarRepository($config . '/calendar.json'));
        $this->calendarService = new CalendarService($this->workCalendarRepository, $this->auditLogger, new \App\Service\OpenTicketRecalculator($this->ticketRepository, new \App\Service\SlaCalculatorService($this->workCalendarRepository), $this->settingsService, $this->clock));
        $this->hierarchyRepository = new JsonHierarchyRepository($this->store);
        $this->leaveRepository = new JsonLeaveRepository($this->store);
        $this->hierarchyService = new HierarchyService($this->hierarchyRepository, $this->categoryRepository, $this->userRepository, $this->auditLogger);
        $this->leaveService = new LeaveService($this->leaveRepository, $this->userRepository, $this->auditLogger);
        $this->categoryFieldRepository = new JsonCategoryFieldRepository($this->store);
        $this->catalogService = new CatalogService($this->categoryRepository, $this->priorityRepository, $this->slaPolicyRepository, $this->auditLogger, $this->categoryFieldRepository);
        $this->slaCalculator = new SlaCalculatorService($this->workCalendarRepository);
        $this->slaStatusService = new SlaStatusService($this->slaCalculator, $this->clock);
        $this->ticketService = new TicketService(
            $this->ticketRepository,
            $this->replyRepository,
            $this->categoryRepository,
            $this->slaPolicyRepository,
            $this->slaCalculator,
            $this->priorityRepository,
            $this->assignmentService = new AssignmentService($this->userRepository, $this->ticketRepository, $this->staffService, $this->hierarchyRepository, $this->leaveRepository, $this->clock),
            $this->clock,
            $this->categoryFieldRepository,
            new \App\Service\ExtraSlaPolicy($this->slaCalculator, $this->settingsService),
            new \App\Service\QuestionPolicy($this->settingsService, $this->slaCalculator),
        );
        $this->ticketService->attachObserver(new TicketLogObserver($this->ticketLogRepository, $this->clock));
        $this->ticketActivityRepository = new JsonTicketActivityRepository($this->store);
        $this->ticketService->attachObserver(new TicketActivityObserver($this->ticketActivityRepository, $this->clock));
        $this->notificationRepository = new JsonNotificationRepository($this->store);
        $this->notificationService = new NotificationService($this->notificationRepository, $this->userRepository, $this->clock, $this->settingsService);
        $this->ticketService->attachObserver(new NotificationObserver($this->notificationService));
        $this->escalationRepository = new JsonEscalationRepository($this->store);
        $this->escalationService = new EscalationService(
            $this->ticketRepository,
            $this->ticketService,
            $this->assignmentService,
            $this->slaCalculator,
            $this->settingsService,
            $this->escalationRepository,
            $this->notificationService,
            $this->clock,
        );
        $this->ticketService->attachObserver(new EscalationObserver($this->escalationService));
        $this->slaMonitor = new SlaMonitorService($this->ticketRepository, $this->escalationService, $this->ticketService, $this->staffService, $this->notificationService, $this->slaStatusService, new JsonRuntimeStateRepository($this->store), $this->clock);

        $this->attachmentRepository = new JsonAttachmentRepository($this->store);

        $registerRoutes = require __DIR__ . '/routes.php';
        $registerRoutes($this->router, $this);
    }

    public function handle(Request $request): Response
    {
        try {
            $isWrite = in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
            $publicForm = in_array($request->path, self::PUBLIC_FORMS, true);
            if ($this->enforceIdleTimeout($request) && $isWrite && !$publicForm) {
                // Süresi dolmuş oturumla gönderilen form "CSRF geçersiz" yerine girişe yönlendirilir.
                throw new RedirectException('/giris');
            }

            if ($request->isApi() && $this->session->get('user_id') === null) {
                // Tüm API uçları giriş ister; oturum yoksa CSRF hatası yerine net 401 dön.
                throw new RedirectException('/giris');
            }

            if ($isWrite && $request->post === [] && $request->files === [] && (int) $request->header('Content-Length') > self::postMaxBytes()) {
                // post_max_size aşılınca PHP tüm formu atar; CSRF anahtarı da gelmez. Doğru nedeni söyle.
                throw new HttpException(413, 'Gönderilen dosyalar toplamda çok büyük. Daha küçük dosyalarla tekrar deneyin.');
            }

            if ($isWrite) {
                $token = $request->input('_csrf') ?? $request->header('X-CSRF-Token');
                if (!$this->csrf->isValid(is_string($token) ? $token : null)) {
                    throw new HttpException(403, 'Güvenlik anahtarı (CSRF) eksik veya geçersiz. Sayfayı yenileyip tekrar deneyin.');
                }
            }

            $this->runSlaMonitor();
            $response = $this->router->dispatch($request);
        } catch (RedirectException $e) {
            $response = $request->isApi()
                ? Response::json(['ok' => false, 'error' => 'Oturumunuz yok veya sona erdi. Lütfen tekrar giriş yapın.'], 401)
                : Response::redirect($e->url);
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
        $user = is_int($id) ? $this->userRepository->findById($id) : null;
        if ($user !== null && !$user->active) {
            // Yönetici hesabı pasif yaptıysa açık oturum bir sonraki istekte kapanır.
            $this->session->destroy();

            return null;
        }

        return $user;
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
        $this->session->remove('_csrf'); // girişten önce bilinen anahtar girişten sonra geçmesin
        $this->session->set('user_id', $user->id);
        $this->session->set('last_activity', $this->clock->now()->getTimestamp());
    }

    public function logOut(): void
    {
        $this->session->destroy();
    }

    /**
     * Ekranlarda öncelik adını göstermek için id → ad listesi (pasifler dahil; eski talepler bozulmasın).
     * Veri okunamazsa boş liste: hata sayfası yine de çizilebilsin.
     *
     * @return array<int, string>
     */
    private function priorityNames(): array
    {
        try {
            $names = [];
            foreach ($this->priorityRepository->findAll() as $priority) {
                $names[(int) $priority->id] = $priority->name;
            }

            return $names;
        } catch (StorageException) {
            return [];
        }
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
        $this->view->share('priorityNames', $this->priorityNames());
        try {
            $this->view->share('siteTitle', $this->settingsService->string('site.title'));
            $this->view->share('siteLogo', $this->settingsService->string('site.logo_path'));
        } catch (StorageException) {
            // veri dosyası okunamazsa varsayılan başlıkla devam edilir (hata sayfası yine çizilsin)
            $this->view->share('siteTitle', SettingsService::DEFAULTS['site.title']);
            $this->view->share('siteLogo', '');
        }
        $this->view->share('csrfField', static fn (): string => '<input type="hidden" name="_csrf" value="' . e($token) . '">');
        $this->view->share('flashSuccess', $this->session->pullFlash('success'));
        $this->view->share('flashError', $this->session->pullFlash('error'));
        try {
            $currentUser = $this->currentUser();
        } catch (StorageException) {
            $currentUser = null; // veri dosyası bozuksa hata sayfası yine de sayfa düzeniyle gösterilsin
        }
        $this->view->share('currentUser', $currentUser);
        try {
            $this->view->share('unreadNotifications', $currentUser !== null ? $this->notificationRepository->unreadCount((int) $currentUser->id) : 0);
        } catch (StorageException) {
            $this->view->share('unreadNotifications', 0);
        }

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

    public function auditLogRepository(): AuditLogRepositoryInterface
    {
        return $this->auditLogRepository;
    }

    public function auditLogger(): AuditLogger
    {
        return $this->auditLogger;
    }

    public function priorityRepository(): PriorityRepositoryInterface
    {
        return $this->priorityRepository;
    }

    public function priorityService(): PriorityService
    {
        return $this->priorityService;
    }

    public function workCalendarRepository(): WorkCalendarRepositoryInterface
    {
        return $this->workCalendarRepository;
    }

    public function calendarService(): CalendarService
    {
        return $this->calendarService;
    }

    public function catalogService(): CatalogService
    {
        return $this->catalogService;
    }

    public function staffService(): StaffService
    {
        return $this->staffService;
    }

    public function settingsService(): SettingsService
    {
        return $this->settingsService;
    }

    public function escalationRepository(): EscalationRepositoryInterface
    {
        return $this->escalationRepository;
    }

    public function escalationService(): EscalationService
    {
        return $this->escalationService;
    }

    public function slaMonitor(): SlaMonitorService
    {
        return $this->slaMonitor;
    }

    public function notificationRepository(): NotificationRepositoryInterface
    {
        return $this->notificationRepository;
    }

    public function notificationService(): NotificationService
    {
        return $this->notificationService;
    }

    public function categoryFieldRepository(): CategoryFieldRepositoryInterface
    {
        return $this->categoryFieldRepository;
    }

    public function detailedReportService(): DetailedReportService
    {
        return new DetailedReportService($this->ticketRepository, $this->userRepository, $this->slaCalculator, $this->escalationRepository, $this->settingsService);
    }

    public function backupService(): BackupService
    {
        return new BackupService($this->rootDir . '/storage/data', $this->auditLogger);
    }

    public function hierarchyRepository(): HierarchyRepositoryInterface
    {
        return $this->hierarchyRepository;
    }

    public function leaveRepository(): LeaveRepositoryInterface
    {
        return $this->leaveRepository;
    }

    public function hierarchyService(): HierarchyService
    {
        return $this->hierarchyService;
    }

    public function leaveService(): LeaveService
    {
        return $this->leaveService;
    }

    public function brandingService(): BrandingService
    {
        return $this->brandingService;
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

    public function ticketActivityRepository(): TicketActivityRepositoryInterface
    {
        return $this->ticketActivityRepository;
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

    /** Dosya sınırları her kullanımda güncel yönetici ayarından okunur (Sistem Yapılandırması → Site Ayarları). */
    public function attachmentService(): AttachmentService
    {
        return new AttachmentService(
            $this->attachmentRepository,
            $this->rootDir . '/storage/uploads',
            [
                'max_upload_bytes' => $this->settingsService->int('files.max_image_mb') * 1024 * 1024,
                'max_video_bytes' => $this->settingsService->int('files.max_video_mb') * 1024 * 1024,
                'max_files_per_ticket' => $this->settingsService->int('files.max_count'),
            ],
            $this->clock,
        );
    }

    /**
     * NFR-10: son işlemden bu yana 30 dakika geçtiyse oturumu kapatır ve true döner.
     * Sayfanın kendi kendine yaptığı SLA yoklaması (/api/sla) kullanıcı etkinliği sayılmaz.
     */
    /** Oturum zaman aşımı yönetici ayarından (NFR-10); ayar okunamazsa 30 dakika. */
    private function idleTimeoutSeconds(): int
    {
        try {
            return $this->settingsService->int('auth.idle_minutes') * 60;
        } catch (StorageException) {
            return self::IDLE_TIMEOUT_SECONDS;
        }
    }

    /** Zamana bağlı SLA kuralları (cron yok): her istekte en fazla dakikada bir. Hata isteği bozmaz, günlüğe yazılır. */
    private function runSlaMonitor(): void
    {
        try {
            $this->slaMonitor->runIfDue();
        } catch (Throwable $e) {
            error_log('SLA denetimi çalışmadı: ' . $e->getMessage());
        }
    }

    /** Sayfanın kendi kendine yaptığı yoklamalar; kullanıcı etkinliği sayılmaz. */
    private const PASSIVE_POLLS = ['/api/sla', '/api/bildirimler/sayi'];

    private function enforceIdleTimeout(Request $request): bool
    {
        if ($this->session->get('user_id') === null) {
            return false;
        }

        $now = $this->clock->now()->getTimestamp();
        $last = $this->session->get('last_activity');
        if (is_int($last) && $now - $last > $this->idleTimeoutSeconds()) {
            $token = $this->csrf->token();
            $this->session->destroy();
            $this->session->set('_csrf', $token); // açık sayfadaki giriş formu çalışmaya devam etsin
            $this->session->flash('error', 'Oturumunuz 30 dakika işlem yapılmadığı için sonlandırıldı. Lütfen tekrar giriş yapın.');

            return true;
        }

        if (!in_array($request->path, self::PASSIVE_POLLS, true)) {
            $this->session->set('last_activity', $now);
        }

        return false;
    }

    private static function postMaxBytes(): int
    {
        $limit = AttachmentService::iniBytes((string) ini_get('post_max_size'));

        return $limit > 0 ? $limit : PHP_INT_MAX;
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
