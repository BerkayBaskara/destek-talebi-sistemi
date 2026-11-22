<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\ValidationException;
use App\Kernel;
use App\Model\Enum\Role;

/**
 * Personel hesapları (yalnız yönetici): oluşturma, aktif/pasif yapma ve kapasite. Aktif yapma ya da kapasite
 * artışı personelde yer açar; o anda havuzda bekleyen talepler uygun personele atanır.
 */
final class StaffController
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function index(): Response
    {
        $this->kernel->requireRole(Role::Admin);

        return $this->view([], [], 0, [], []);
    }

    public function create(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $old = ['name' => $request->string('name'), 'surname' => $request->string('surname')];
        try {
            $agent = $this->kernel->staffService()->create($admin, $old['name'], $old['surname'], $request->string('password'));
        } catch (ValidationException $e) {
            return $this->view($old, $e->errors(), 0, [], [], 422);
        }

        $this->kernel->session()->flash('success', 'Personel hesabı oluşturuldu. Personel numarası: ' . $agent->customerNo);

        return Response::redirect('/yonetici/personel');
    }

    public function update(Request $request, int $id): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $staff = $this->kernel->staffService();
        $active = $request->string('active') === '1';
        $capacityInput = $request->string('capacity');

        $before = $this->kernel->userRepository()->findById($id);
        try {
            if ($request->input('name') !== null) {
                $staff->rename($admin, $id, $request->string('name'), $request->string('surname'));
            }
            $capacity = $staff->parseCapacity($capacityInput);
            $agent = $staff->setCapacity($admin, $id, $capacity);
            $agent = $staff->setActive($admin, $id, $active);
        } catch (ValidationException $e) {
            return $this->view([], [], $id, ['capacity' => $capacityInput, 'active' => $active ? '1' : '', 'name' => $request->string('name'), 'surname' => $request->string('surname')], $e->errors(), 422);
        }

        $message = SettingsController::SAVED;
        $freed = $before !== null && $agent->active
            && (!$before->active || $staff->capacityOf($agent) > $staff->capacityOf($before));
        if ($freed) {
            $message .= SettingsController::poolMessage($this->kernel->ticketService()->fillFromPool());
        }
        $this->kernel->session()->flash('success', $message);

        return Response::redirect('/yonetici/personel');
    }

    /**
     * @param array<string, string> $old yeni personel formu
     * @param array<string, string> $errors yeni personel formu hataları
     * @param int $rowId hatalı güncellenen personel satırı (0: yok)
     * @param array<string, string> $rowOld
     * @param array<string, string> $rowErrors
     */
    private function view(array $old, array $errors, int $rowId, array $rowOld, array $rowErrors, int $status = 200): Response
    {
        $staff = $this->kernel->staffService();
        $load = $this->kernel->ticketRepository()->countActiveByAgent();
        $rows = [];
        foreach ($this->kernel->userRepository()->findByRole(Role::Agent) as $agent) {
            $rows[] = [
                'agent' => $agent,
                'active' => $load[(int) $agent->id] ?? 0,
                'capacity' => $staff->capacityOf($agent),
            ];
        }

        return $this->kernel->render('admin/config/staff', [
            'title' => 'Personel',
            'rows' => $rows,
            'defaultCapacity' => $this->kernel->settingsService()->int('staff.default_capacity'),
            'old' => $old,
            'errors' => $errors,
            'rowId' => $rowId,
            'rowOld' => $rowOld,
            'rowErrors' => $rowErrors,
        ], $status);
    }
}
