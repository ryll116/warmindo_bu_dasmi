<?php

namespace App;

use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private AttendanceLocationService $location) {}

    public function today(): string
    {
        return CarbonImmutable::now(config('attendance.timezone'))->toDateString();
    }

    /** @return array{label: string, start: string, end: string}|null */
    public function shift(User $user): ?array
    {
        return config('attendance.shifts')[$user->shift_type ?? ''] ?? null;
    }

    /** @param array<string, mixed> $coordinates */
    public function clockIn(User $user, array $coordinates): void
    {
        abort_unless($user->requiresAttendance(), 403);
        $location = $this->location->validate($coordinates);
        DB::transaction(function () use ($user, $location): void {
            $employee = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($employee->requiresAttendance(), 403);
            $shift = $this->shift($employee);
            if (! $shift) {
                throw ValidationException::withMessages(['attendance' => 'Jadwal kerja belum diatur. Hubungi admin.']);
            }
            $now = CarbonImmutable::now(config('attendance.timezone'));
            $attendance = Attendance::where('user_id', $employee->id)->whereDate('attendance_date', $now->toDateString())->lockForUpdate()->first();
            if ($attendance && ($attendance->clock_in || $attendance->clock_out)) {
                throw ValidationException::withMessages(['attendance' => 'Absensi hari ini sudah tercatat. Muat ulang halaman untuk melihat status terbaru.']);
            }
            $start = $now->setTimeFromTimeString($shift['start']);
            $end = $now->setTimeFromTimeString($shift['end']);
            $attendance ??= new Attendance;
            $attendance->user_id = $employee->id;
            $attendance->attendance_date = $now->toDateString();
            $attendance->scheduled_start_at = $start->setTimezone(config('app.timezone'));
            $attendance->scheduled_end_at = $end->setTimezone(config('app.timezone'));
            $attendance->clock_in = $now->setTimezone(config('app.timezone'));
            $attendance->clock_in_latitude = $location['latitude'];
            $attendance->clock_in_longitude = $location['longitude'];
            $attendance->status = $attendance->isLate() ? 'late' : 'present';
            $attendance->save();
        }, 3);
    }

    /** @param array<string, mixed> $coordinates */
    public function clockOut(User $user, array $coordinates): void
    {
        abort_unless($user->requiresAttendance(), 403);
        $location = $this->location->validate($coordinates);
        DB::transaction(function () use ($user, $location): void {
            $employee = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($employee->requiresAttendance(), 403);
            $attendance = Attendance::where('user_id', $employee->id)->whereDate('attendance_date', $this->today())->lockForUpdate()->first();
            if (! $attendance?->clock_in) {
                throw ValidationException::withMessages(['attendance' => 'Anda belum absen masuk hari ini.']);
            }
            if ($attendance->clock_out) {
                throw ValidationException::withMessages(['attendance' => 'Absen pulang sudah tercatat.']);
            }
            $now = CarbonImmutable::now();
            if (! $attendance->scheduled_end_at) {
                throw ValidationException::withMessages(['attendance' => 'Jadwal selesai shift belum tersedia. Hubungi admin.']);
            }
            if ($now->lt($attendance->scheduled_end_at)) {
                throw ValidationException::withMessages(['attendance' => 'Absen pulang hanya dapat dilakukan setelah shift berakhir.']);
            }
            if ($now->lt($attendance->clock_in)) {
                throw ValidationException::withMessages(['attendance' => 'Waktu absensi tidak valid. Hubungi admin.']);
            }
            $attendance->clock_out = $now;
            $attendance->clock_out_latitude = $location['latitude'];
            $attendance->clock_out_longitude = $location['longitude'];
            $attendance->save();
        }, 3);
    }
}
