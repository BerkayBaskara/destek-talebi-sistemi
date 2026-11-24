<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\ValidationException;
use App\Kernel;
use App\Model\Enum\Role;

/** Sistem Yapılandırması giriş sayfası, site ayarları, logo ve denetim kaydı (yalnız yönetici). */
final class SettingsController
{
    public const SAVED = 'Değişiklikler kaydedildi.';

    /** Form alanı → ayar anahtarı */
    private const SITE_FIELDS = [
        'site_title' => 'site.title',
        'home_heading' => 'site.home_heading',
        'home_text' => 'site.home_text',
        'default_capacity' => 'staff.default_capacity',
    ];

    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function index(): Response
    {
        $this->kernel->requireRole(Role::Admin);

        return $this->kernel->render('admin/config/index', ['title' => 'Sistem Yapılandırması']);
    }

    public function siteForm(): Response
    {
        $this->kernel->requireRole(Role::Admin);
        $settings = $this->kernel->settingsService();
        $values = [];
        foreach (self::SITE_FIELDS as $field => $key) {
            $values[$field] = $settings->string($key);
        }

        return $this->siteView($values, []);
    }

    public function saveSite(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $settings = $this->kernel->settingsService();
        $old = [];
        $values = [];
        foreach (self::SITE_FIELDS as $field => $key) {
            $old[$field] = $request->string($field);
            $values[$key] = $field === 'home_text' ? $old[$field] : trim($old[$field]);
        }

        $capacityBefore = $settings->int('staff.default_capacity');
        try {
            $settings->update($admin, $values);
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $key => $message) {
                $errors[(string) array_search($key, self::SITE_FIELDS, true)] = $message;
            }

            return $this->siteView($old, $errors, 422);
        }

        $message = self::SAVED;
        if ($settings->int('staff.default_capacity') > $capacityBefore) {
            $message .= self::poolMessage($this->kernel->ticketService()->fillFromPool());
        }
        $this->kernel->session()->flash('success', $message);

        return Response::redirect('/yonetici/ayarlar/site');
    }

    public function uploadLogo(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $file = $request->files['logo'] ?? [];

        try {
            $this->kernel->brandingService()->storeLogo($admin, is_array($file) ? $file : []);
        } catch (ValidationException $e) {
            $settings = $this->kernel->settingsService();
            $values = [];
            foreach (self::SITE_FIELDS as $field => $key) {
                $values[$field] = $settings->string($key);
            }

            return $this->siteView($values, $e->errors(), 422);
        }
        $this->kernel->session()->flash('success', self::SAVED);

        return Response::redirect('/yonetici/ayarlar/site');
    }

    /** Giriş/sıfırlama deneme sınırı ve oturum süresi (FR-31, NFR-19, NFR-10); hareketsizlik süresi (FR-49). */
    public function saveSecurity(Request $request): Response
    {
        return $this->saveGroup($request, [
            'max_attempts' => 'auth.max_attempts',
            'lock_minutes' => 'auth.lock_minutes',
            'idle_minutes' => 'auth.idle_minutes',
            'inactivity_days' => 'staff.inactivity_days',
        ], true);
    }

    /** Açık olan bildirim türleri (US-45); işaretlenmeyenler kapatılır. */
    public function saveNotifications(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $enabled = $request->input('enabled', []);
        $enabled = is_array($enabled) ? array_map('strval', $enabled) : [];
        $disabled = array_values(array_diff(array_keys(\App\Service\NotificationService::TYPES), $enabled));
        $this->kernel->settingsService()->update($admin, ['notify.disabled' => implode(',', $disabled)]);
        $this->kernel->session()->flash('success', self::SAVED);

        return Response::redirect('/yonetici/ayarlar/site');
    }

    public function uploadHomeImage(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $file = $request->files['home_image'] ?? [];
        try {
            $this->kernel->brandingService()->storeImage($admin, is_array($file) ? $file : [], 'site.home_image');
        } catch (ValidationException $e) {
            $settings = $this->kernel->settingsService();
            $values = [];
            foreach (self::SITE_FIELDS as $field => $key) {
                $values[$field] = $settings->string($key);
            }

            return $this->siteView($values, $e->errors(), 422);
        }
        $this->kernel->session()->flash('success', self::SAVED);

        return Response::redirect('/yonetici/ayarlar/site');
    }

    public function removeHomeImage(): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $this->kernel->brandingService()->removeImage($admin, 'site.home_image');
        $this->kernel->session()->flash('success', self::SAVED);

        return Response::redirect('/yonetici/ayarlar/site');
    }

    /** Dosya sınırları (görsel/PDF MB, video MB, dosya sayısı). */
    public function saveFiles(Request $request): Response
    {
        return $this->saveGroup($request, [
            'max_image_mb' => 'files.max_image_mb',
            'max_video_mb' => 'files.max_video_mb',
            'max_count' => 'files.max_count',
        ]);
    }

    /** Kayıtta seçilebilen güvenlik soruları (satır başına bir soru). */
    public function saveQuestions(Request $request): Response
    {
        return $this->saveGroup($request, ['questions' => 'auth.security_questions']);
    }

    /**
     * Site Ayarları sayfasındaki ayrı bir formun ayarlarını kaydeder; hata varsa sayfayı girilen değerlerle gösterir.
     *
     * @param array<string, string> $fields form alanı → ayar anahtarı
     */
    private function saveGroup(Request $request, array $fields, bool $security = false): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $values = [];
        $old = [];
        foreach ($fields as $field => $key) {
            if ($request->input($field) === null) {
                continue; // formda olmayan alan değiştirilmez
            }
            $old[$field] = $request->string($field);
            $values[$key] = $field === 'questions' ? $old[$field] : trim($old[$field]);
        }
        try {
            $this->kernel->settingsService()->update($admin, $values);
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $key => $message) {
                $errors[(string) array_search($key, $fields, true)] = $message;
            }
            if ($security) {
                return $this->securityView($old, $errors, 422);
            }
            $settings = $this->kernel->settingsService();
            $siteValues = [];
            foreach (self::SITE_FIELDS as $field => $key) {
                $siteValues[$field] = $settings->string($key);
            }

            return $this->siteView($siteValues, $errors, 422, $old);
        }
        $this->kernel->session()->flash('success', self::SAVED);

        return Response::redirect($security ? '/yonetici/ayarlar/guvenlik' : '/yonetici/ayarlar/site');
    }

    /** Güvenlik ve oturum ayarları sayfası. */
    public function securityPage(): Response
    {
        $this->kernel->requireRole(Role::Admin);

        return $this->securityView([], []);
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function securityView(array $old, array $errors, int $status = 200): Response
    {
        $settings = $this->kernel->settingsService();
        $values = [
            'max_attempts' => $settings->string('auth.max_attempts'),
            'lock_minutes' => $settings->string('auth.lock_minutes'),
            'idle_minutes' => $settings->string('auth.idle_minutes'),
            'inactivity_days' => $settings->string('staff.inactivity_days'),
        ];

        return $this->kernel->render('admin/config/security', [
            'title' => 'Güvenlik ve Oturum',
            'values' => $old + $values,
            'errors' => $errors,
        ], $status);
    }

    public function backupPage(): Response
    {
        $this->kernel->requireRole(Role::Admin);

        return $this->kernel->render('admin/config/backup', ['title' => 'Veri Yedeği', 'errors' => []]);
    }

    public function restore(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $file = $request->files['yedek'] ?? [];
        try {
            $auto = $this->kernel->backupService()->restore($admin, is_array($file) ? $file : []);
        } catch (ValidationException $e) {
            return $this->kernel->render('admin/config/backup', ['title' => 'Veri Yedeği', 'errors' => $e->errors()], 422);
        }
        $same = $this->kernel->userRepository()->findById((int) $admin->id);
        if ($same === null || $same->customerNo !== $admin->customerNo || $same->role !== Role::Admin || !$same->active) {
            // Yedekteki kullanıcı tablosunda bu oturumun hesabı yok ya da başka biri: oturum kapatılır.
            $this->kernel->session()->destroy();

            return Response::redirect('/giris');
        }
        $this->kernel->session()->flash('success', 'Yedek geri yüklendi. Önceki veri storage/backups/' . $auto . ' dosyasına yedeklendi.');

        return Response::redirect('/yonetici/yedek');
    }

    public function backupDownload(): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $file = 'yedek-' . $this->kernel->clock()->now()->format('Ymd-Hi') . '.zip';

        return new Response($this->kernel->backupService()->createZip($admin), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
        ]);
    }

    /** Son 100 yönetici değişikliği. */
    public function audit(): Response
    {
        $this->kernel->requireRole(Role::Admin);
        $logs = $this->kernel->auditLogRepository()->latest(100);
        $names = [];
        foreach ($logs as $log) {
            $names[$log->userId] ??= $this->kernel->userRepository()->findById($log->userId)?->fullName() ?? 'Silinmiş kullanıcı';
        }

        return $this->kernel->render('admin/config/audit', [
            'title' => 'Denetim Kaydı',
            'logs' => $logs,
            'names' => $names,
        ]);
    }

    /** Havuzdan atanan talep sayısını bildirim cümlesine çevirir (0 ise boş). */
    public static function poolMessage(int $assigned): string
    {
        return $assigned > 0 ? ' Havuzda bekleyen ' . $assigned . ' talep atandı.' : '';
    }

    /**
     * @param array<string, string> $values
     * @param array<string, string> $errors
     * @param array<string, string> $groupOld dosya sınırları / sorular formunda girilen değerler
     */
    private function siteView(array $values, array $errors, int $status = 200, array $groupOld = []): Response
    {
        $settings = $this->kernel->settingsService();
        $groups = [
            'max_image_mb' => $settings->string('files.max_image_mb'),
            'max_video_mb' => $settings->string('files.max_video_mb'),
            'max_count' => $settings->string('files.max_count'),
            'questions' => $settings->string('auth.security_questions'),
        ];

        $disabled = array_filter(explode(',', $settings->string('notify.disabled')));

        return $this->kernel->render('admin/config/site', [
            'groups' => $groupOld + $groups,
            'homeImage' => $settings->string('site.home_image'),
            'notificationTypes' => \App\Service\NotificationService::TYPES,
            'disabledNotifications' => $disabled,
            'title' => 'Site Ayarları',
            'values' => $values,
            'errors' => $errors,
            'logoPath' => $this->kernel->settingsService()->string('site.logo_path'),
            'maxLogoBytes' => \App\Service\BrandingService::MAX_BYTES,
        ], $status);
    }
}
