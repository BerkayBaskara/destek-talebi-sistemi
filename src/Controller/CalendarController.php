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

    private const MONTHS = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];

    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function index(Request $request): Response
    {
        $this->kernel->requireRole(Role::Admin);

        return $this->view($this->kernel->workCalendarRepository()->workHours(), [], ['date' => '', 'description' => ''], 200, $request->string('ay'));
    }

    public function addSpecialDay(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        try {
            $this->kernel->calendarService()->addSpecialDay($admin, $request->string('date'), $request->string('start'), $request->string('end'), $request->string('description'));
        } catch (ValidationException $e) {
            return $this->view($this->kernel->workCalendarRepository()->workHours(), $e->errors(), ['date' => '', 'description' => ''], 422);
        }

        return $this->saved();
    }

    public function removeSpecialDay(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $this->kernel->calendarService()->removeSpecialDay($admin, $request->string('date'));

        return $this->saved();
    }

    public function import(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $file = $request->files['takvim'] ?? [];
        try {
            $this->kernel->calendarService()->importFile($admin, is_array($file) ? $file : []);
        } catch (ValidationException $e) {
            return $this->view($this->kernel->workCalendarRepository()->workHours(), $e->errors(), ['date' => '', 'description' => ''], 422);
        }

        return $this->saved();
    }

    /** FR-13.4: takvim değişikliği açık taleplere uygulansın mı. */
    public function saveRule(Request $request): Response
    {
        $admin = $this->kernel->requireRole(Role::Admin);
        $this->kernel->settingsService()->update($admin, ['calendar.recalculate_open' => $request->string('recalculate_open') === '1' ? '1' : '0']);

        return $this->saved();
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
    private function view(array $days, array $errors, array $holiday, int $status = 200, string $month = ''): Response
    {
        $repo = $this->kernel->workCalendarRepository();
        $first = \DateTimeImmutable::createFromFormat('!Y-m', $month, new \DateTimeZone('Europe/Istanbul'));
        if ($first === false || $first->format('Y-m') !== $month) {
            $first = $this->kernel->clock()->now()->modify('first day of this month')->setTime(0, 0);
        }
        $holidays = array_column($repo->holidays(), 'description', 'date');
        $specials = [];
        foreach ($repo->specialDays() as $special) {
            $specials[$special['date']] = $special;
        }
        $config = $repo->getConfig();
        $grid = [];
        for ($d = $first; $d->format('m') === $first->format('m'); $d = $d->modify('+1 day')) {
            $date = $d->format('Y-m-d');
            $type = isset($holidays[$date]) ? 'holiday' : (isset($specials[$date]) ? 'special' : ($config->intervalsFor($d) !== [] ? 'work' : 'off'));
            $grid[] = ['date' => $d, 'type' => $type, 'note' => $holidays[$date] ?? ($specials[$date]['description'] ?? '')];
        }

        return $this->kernel->render('admin/config/calendar', [
            'monthTitle' => self::MONTHS[(int) $first->format('n')] . ' ' . $first->format('Y'),
            'prevMonth' => $first->modify('-1 month')->format('Y-m'),
            'nextMonth' => $first->modify('+1 month')->format('Y-m'),
            'grid' => $grid,
            'specialDays' => $repo->specialDays(),
            'recalculateOpen' => $this->kernel->settingsService()->string('calendar.recalculate_open') === '1',
            'title' => 'Çalışma Takvimi',
            'days' => $days,
            'dayNames' => self::DAY_NAMES,
            'holidays' => $this->kernel->workCalendarRepository()->holidays(),
            'holiday' => $holiday,
            'errors' => $errors,
        ], $status);
    }
}
