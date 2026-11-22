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
     */
    private function siteView(array $values, array $errors, int $status = 200): Response
    {
        return $this->kernel->render('admin/config/site', [
            'title' => 'Site Ayarları',
            'values' => $values,
            'errors' => $errors,
            'logoPath' => $this->kernel->settingsService()->string('site.logo_path'),
            'maxLogoBytes' => \App\Service\BrandingService::MAX_BYTES,
        ], $status);
    }
}
