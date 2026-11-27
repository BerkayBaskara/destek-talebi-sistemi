<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\ValidationException;
use App\Kernel;
use App\Model\Enum\Role;

/** Kategori bazlı personel hiyerarşisi ve personel izinleri ekranları (yalnız yönetici). */
final class HierarchyController
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function index(): Response
    {
        $this->kernel->requireRole(Role::Admin);

        return $this->hierarchyView(0, [], []);
    }

    public function save(Request $request, int $categoryId): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $raw = $request->input('level', []);
        $levels = [];
        foreach (is_array($raw) ? $raw : [] as $userId => $value) {
            $levels[(int) $userId] = is_string($value) ? $value : '';
        }

        try {
            $this->kernel->hierarchyService()->saveLevels($admin, $categoryId, $levels);
        } catch (ValidationException $e) {
            return $this->hierarchyView($categoryId, $levels, $e->errors(), 422);
        }
        $this->kernel->session()->flash('success', SettingsController::SAVED);

        return Response::redirect('/yonetici/hiyerarsi');
    }

    public function leaves(): Response
    {
        $this->kernel->requireRole(Role::Admin);

        return $this->leavesView([], []);
    }

    public function addLeave(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $old = [
            'user_id' => $request->string('user_id'),
            'start' => $request->string('start'),
            'end' => $request->string('end'),
            'note' => $request->string('note'),
        ];
        try {
            $this->kernel->leaveService()->add($admin, (int) $old['user_id'], $old['start'], $old['end'], $old['note']);
        } catch (ValidationException $e) {
            return $this->leavesView($old, $e->errors(), 422);
        }
        $this->kernel->session()->flash('success', SettingsController::SAVED);

        return Response::redirect('/yonetici/izinler');
    }

    public function removeLeave(int $id): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $this->kernel->leaveService()->remove($admin, $id);
        $this->kernel->session()->flash('success', SettingsController::SAVED);

        return Response::redirect('/yonetici/izinler');
    }

    /**
     * @param array<int, string> $old hatalı gönderilen kategorinin seviyeleri
     * @param array<string, string> $errors
     */
    private function hierarchyView(int $categoryId, array $old, array $errors, int $status = 200): Response
    {
        $repo = $this->kernel->hierarchyRepository();
        $levels = [];
        foreach ($this->kernel->categoryRepository()->findAll() as $category) {
            $levels[(int) $category->id] = $repo->levelsFor((int) $category->id);
        }

        return $this->kernel->render('admin/config/hierarchy', [
            'title' => 'Personel Hiyerarşisi',
            'categories' => $this->kernel->categoryRepository()->findAll(),
            'agents' => $this->kernel->userRepository()->findByRole(Role::Agent),
            'levels' => $levels,
            'errorCategory' => $categoryId,
            'old' => $old,
            'errors' => $errors,
            'maxLevel' => \App\Service\HierarchyService::MAX_LEVEL,
        ], $status);
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function leavesView(array $old, array $errors, int $status = 200): Response
    {
        $names = [];
        $agents = $this->kernel->userRepository()->findByRole(Role::Agent);
        foreach ($agents as $agent) {
            $names[(int) $agent->id] = $agent->fullName() . ' (' . $agent->customerNo . ')';
        }

        return $this->kernel->render('admin/config/leaves', [
            'title' => 'Personel İzinleri',
            'leaves' => $this->kernel->leaveRepository()->all(),
            'names' => $names,
            'old' => $old,
            'errors' => $errors,
        ], $status);
    }
}
