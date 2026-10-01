<?php

namespace App\Http\Controllers\Admin;

use App\AttendanceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttendanceReportRequest;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(AttendanceReportRequest $request, AttendanceService $service): View
    {
        $filters = $request->validated();
        $today = CarbonImmutable::parse($service->today(), config('attendance.timezone'));
        $start = $filters['start'] ?? $today->startOfMonth()->toDateString();
        $end = $filters['end'] ?? $today->endOfMonth()->toDateString();
        $employeeId = $filters['employee'] ?? null;
        $condition = $filters['condition'] ?? null;

        $employees = User::attendanceRequired()->select('id', 'name', 'shift_type')->orderBy('name')->orderBy('id')
            ->with(['attendances' => fn (HasMany $query): HasMany => $query->whereDate('attendance_date', $today->toDateString())])->get();
        $monitoring = $employees->map(function (User $employee) use ($service): array {
            return ['employee' => $employee, 'attendance' => $employee->attendances->first(), 'shift' => $service->shift($employee)];
        });
        $entered = $monitoring->filter(fn (array $row): bool => $row['attendance']?->clock_in !== null)->count();
        $todaySummary = [
            'employees' => $employees->count(),
            'entered' => $entered,
            'late' => $monitoring->filter(fn (array $row): bool => $row['attendance']?->isLate() ?? false)->count(),
            'absent' => $employees->count() - $entered,
        ];

        $query = Attendance::whereHas('user', fn (Builder $query): Builder => $query->attendanceRequired())
            ->where('attendance_date', '>=', $start)
            ->where('attendance_date', '<', CarbonImmutable::parse($end)->addDay()->toDateString())
            ->when($employeeId, fn (Builder $query): Builder => $query->where('user_id', $employeeId));
        match ($condition) {
            'present' => $query->late(false),
            'late' => $query->late(),
            'missing_clock_out' => $query->missingClockOut(),
            default => null,
        };
        $summary = [
            'total' => (clone $query)->whereNotNull('clock_in')->count(),
            'late' => (clone $query)->late()->count(),
            'missing' => (clone $query)->missingClockOut()->count(),
        ];
        $history = $query->with('user:id,name')->orderByDesc('attendance_date')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.reports.attendance', compact('today', 'start', 'end', 'employeeId', 'condition', 'employees', 'monitoring', 'todaySummary', 'summary', 'history'));
    }
}
