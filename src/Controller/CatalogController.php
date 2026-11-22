<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\ValidationException;
use App\Kernel;
use App\Model\Enum\Role;

/**
 * Kategori, öncelik ve SLA tablosu yönetimi (yalnız yönetici). Kayıt başarılıysa yönlendirilir (PRG);
 * hatalı formda girilen değerler korunur ve hata ilgili alanın altında gösterilir.
 */
final class CatalogController
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function categories(): Response
    {
        $this->kernel->requireRole(Role::Admin);

        return $this->categoriesView([], []);
    }

    public function createCategory(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $name = $request->string('name');
        try {
            $this->kernel->catalogService()->createCategory($admin, $name);
        } catch (ValidationException $e) {
            return $this->categoriesView(['new' => ['name' => $name]], ['new' => $e->errors()], 422);
        }

        return $this->saved('/yonetici/kategoriler');
    }

    public function updateCategory(Request $request, int $id): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $name = $request->string('name');
        $active = $request->string('active') === '1';
        try {
            $this->kernel->catalogService()->updateCategory($admin, $id, $name, $active);
        } catch (ValidationException $e) {
            return $this->categoriesView([(string) $id => ['name' => $name, 'active' => $active ? '1' : '']], [(string) $id => $e->errors()], 422);
        }

        return $this->saved('/yonetici/kategoriler');
    }

    public function priorities(): Response
    {
        $this->kernel->requireRole(Role::Admin);

        return $this->prioritiesView([], []);
    }

    public function createPriority(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $data = $this->priorityInput($request);
        try {
            $this->kernel->priorityService()->create($admin, $data);
        } catch (ValidationException $e) {
            return $this->prioritiesView(['new' => $data], ['new' => $e->errors()], 422);
        }

        return $this->saved('/yonetici/oncelikler');
    }

    public function updatePriority(Request $request, int $id): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $data = $this->priorityInput($request);
        try {
            $this->kernel->priorityService()->update($admin, $id, $data);
        } catch (ValidationException $e) {
            return $this->prioritiesView([(string) $id => $data], [(string) $id => $e->errors()], 422);
        }

        return $this->saved('/yonetici/oncelikler');
    }

    public function sla(): Response
    {
        $this->kernel->requireRole(Role::Admin);

        return $this->slaView([], []);
    }

    /** Tüm tablo önce doğrulanır; tek hücre bile hatalıysa hiçbir değer yazılmaz. */
    public function saveSla(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $input = $request->input('sla', []);
        $input = is_array($input) ? $input : [];

        $values = [];
        $old = [];
        $errors = [];
        foreach ($input as $categoryId => $row) {
            foreach (is_array($row) ? $row : [] as $priorityId => $raw) {
                $raw = trim(is_string($raw) ? $raw : '');
                $old[(int) $categoryId][(int) $priorityId] = $raw;
                if ($raw === '') {
                    $values[(int) $categoryId][(int) $priorityId] = null;
                } elseif (preg_match('/^\d+$/', $raw) === 1 && (int) $raw >= 1 && (int) $raw <= 720) {
                    $values[(int) $categoryId][(int) $priorityId] = (int) $raw;
                } else {
                    $errors[$categoryId . '-' . $priorityId] = 'SLA süresi 1 ile 720 iş saati arasında olmalı.';
                }
            }
        }
        if ($errors !== []) {
            return $this->slaView($old, $errors, 422);
        }

        $catalog = $this->kernel->catalogService();
        foreach ($values as $categoryId => $row) {
            foreach ($row as $priorityId => $hours) {
                $catalog->setSla($admin, $categoryId, $priorityId, $hours);
            }
        }

        return $this->saved('/yonetici/sla');
    }

    private function saved(string $url): Response
    {
        $this->kernel->session()->flash('success', SettingsController::SAVED);

        return Response::redirect($url);
    }

    /** @return array<string, string> */
    private function priorityInput(Request $request): array
    {
        return [
            'name' => $request->string('name'),
            'rank' => $request->string('rank'),
            'default_sla_hours' => $request->string('default_sla_hours'),
            'active' => $request->string('active') === '1' ? '1' : '0',
            'keywords' => $request->string('keywords'),
        ];
    }

    /**
     * @param array<int|string, array<string, string>> $old satır (id ya da "new") → alan → değer
     * @param array<int|string, array<string, string>> $errors satır → alan → hata
     */
    private function categoriesView(array $old, array $errors, int $status = 200): Response
    {
        return $this->kernel->render('admin/config/categories', [
            'title' => 'Kategoriler',
            'categories' => $this->kernel->categoryRepository()->findAll(),
            'old' => $old,
            'errors' => $errors,
        ], $status);
    }

    /**
     * @param array<int|string, array<string, string>> $old
     * @param array<int|string, array<string, string>> $errors
     */
    private function prioritiesView(array $old, array $errors, int $status = 200): Response
    {
        return $this->kernel->render('admin/config/priorities', [
            'title' => 'Öncelikler',
            'priorities' => $this->kernel->priorityRepository()->findAll(),
            'old' => $old,
            'errors' => $errors,
        ], $status);
    }

    /**
     * @param array<int, array<int, string>> $old
     * @param array<string, string> $errors "kategori-öncelik" → hata
     */
    private function slaView(array $old, array $errors, int $status = 200): Response
    {
        return $this->kernel->render('admin/config/sla', [
            'title' => 'SLA Süreleri',
            'categories' => $this->kernel->categoryRepository()->findAll(),
            'priorities' => $this->kernel->priorityRepository()->findAll(),
            'grid' => $this->kernel->catalogService()->slaGrid(),
            'old' => $old,
            'errors' => $errors,
        ], $status);
    }
}
