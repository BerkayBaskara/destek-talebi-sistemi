<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\ValidationException;
use App\Kernel;
use App\Model\Enum\Role;
use App\Service\CalendarService;

/** Çalışma saatleri ve resmî tatiller (yalnız yönetici). SLA bitiş zamanları bu takvime göre hesaplanır. */
final class CalendarController
{
    public const DAY_NAMES = CalendarService::DAY_NAMES;

    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function index(): Response
    {
        $this->kernel->requireRole(Role::Admin);

        return $this->view($this->kernel->workCalendarRepository()->workHours(), [], ['date' => '', 'description' => '']);
    }

    public function saveHours(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $input = $request->input('days', []);
        $input = is_array($input) ? $input : [];

        $days = [];
        foreach (array_keys(self::DAY_NAMES) as $day) {
            $row = is_array($input[(string) $day] ?? null) ? $input[(string) $day] : [];
            $text = static fn (string $key): string => trim(is_string($row[$key] ?? null) ? $row[$key] : '');
            $days[$day] = [
                'works' => $text('works') === '1',
                'start' => $text('start'),
                'end' => $text('end'),
                'break_start' => $text('break_start') !== '' ? $text('break_start') : null,
                'break_end' => $text('break_end') !== '' ? $text('break_end') : null,
            ];
        }

        try {
            $this->kernel->calendarService()->saveWorkHours($admin, $days);
        } catch (ValidationException $e) {
            return $this->view($days, $e->errors(), ['date' => '', 'description' => ''], 422);
        }

        return $this->saved();
    }

    public function addHoliday(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $holiday = ['date' => $request->string('date'), 'description' => $request->string('description')];
        try {
            $this->kernel->calendarService()->addHoliday($admin, $holiday['date'], $holiday['description']);
        } catch (ValidationException $e) {
            return $this->view($this->kernel->workCalendarRepository()->workHours(), $e->errors(), $holiday, 422);
        }

        return $this->saved();
    }

    public function removeHoliday(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $this->kernel->calendarService()->removeHoliday($admin, $request->string('date'));

        return $this->saved();
    }

    private function saved(): Response
    {
        $this->kernel->session()->flash('success', SettingsController::SAVED);

        return Response::redirect('/yonetici/takvim');
    }

    /**
     * @param array<int, array{works: bool, start: string, end: string, break_start: ?string, break_end: ?string}> $days
     * @param array<string, string> $errors
     * @param array{date: string, description: string} $holiday
     */
    private function view(array $days, array $errors, array $holiday, int $status = 200): Response
    {
        return $this->kernel->render('admin/config/calendar', [
            'title' => 'Çalışma Takvimi',
            'days' => $days,
            'dayNames' => self::DAY_NAMES,
            'holidays' => $this->kernel->workCalendarRepository()->holidays(),
            'holiday' => $holiday,
            'errors' => $errors,
        ], $status);
    }
}
