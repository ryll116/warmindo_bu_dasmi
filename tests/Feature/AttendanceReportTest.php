<?php

namespace Tests\Feature;

use App\AttendanceService;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class AttendanceReportTest extends AdminDatabaseTestCase
{
    private const LOCATION = ['latitude' => 0, 'longitude' => 0];

    protected function setUp(): void
    {
        parent::setUp();
        config(['attendance.location' => ['latitude' => 0, 'longitude' => 0, 'radius_meters' => 100]]);
        config(['app.timezone' => 'UTC', 'attendance.timezone' => 'Asia/Jakarta']);
        $this->at('2026-09-30 17:00:00');
    }

    private function at(string $time): void
    {
        $this->travelTo(CarbonImmutable::parse($time, 'Asia/Jakarta'));
    }

    private function employee(string $name = 'Pegawai', ?string $shift = 'morning'): User
    {
        return User::factory()->create(['name' => $name, 'shift_type' => $shift]);
    }

    private function enter(User $employee, string $time): Attendance
    {
        $this->at($time);
        app(AttendanceService::class)->clockIn($employee, self::LOCATION);

        return Attendance::where('user_id', $employee->id)->orderByDesc('id')->firstOrFail();
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_only_admin_and_super_admin_can_read_report(): void
    {
        $this->get(route('admin.reports.attendance'))->assertRedirect(route('login'));
        foreach (['kasir', 'pegawai', 'admin', 'superAdmin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $response = $this->get(route('admin.reports.attendance'));
            if (in_array($role, ['admin', 'superAdmin'], true)) {
                $response->assertOk()->assertSee('Laporan Absensi')->assertDontSee('data-attendance-form');
                $this->post(route('admin.reports.attendance'))->assertStatus(405);
            } else {
                $response->assertForbidden();
            }
        }
    }

    public function test_monitoring_includes_unclocked_and_unassigned_employees_but_not_admins(): void
    {
        $this->admin();
        User::factory()->create(['role' => 'superAdmin']);
        $this->employee('Belum Masuk');
        $this->employee('Tanpa Jadwal', null);
        $entered = $this->employee('Sudah Masuk');
        $this->enter($entered, '2026-09-30 08:35:00');
        $this->at('2026-09-30 09:00:00');
        $this->get(route('admin.reports.attendance'))->assertOk()
            ->assertSee('Belum Masuk')->assertSee('Tanpa Jadwal')->assertSee('Jadwal belum diatur')
            ->assertSee('Belum Absen')->assertSee('08:35')
            ->assertViewHas('todaySummary', ['employees' => 3, 'entered' => 1, 'late' => 0, 'absent' => 2])
            ->assertViewHas('history', fn ($history): bool => $history->total() === 1);
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_tolerance_and_combined_missing_status_match_employee_helpers(): void
    {
        $this->admin();
        $onTime = $this->enter($this->employee('Tepat'), '2026-09-30 08:35:00');
        $late = $this->enter($this->employee('Telat'), '2026-09-30 08:36:00');
        $running = $this->enter($this->employee('Siang', 'afternoon'), '2026-09-30 14:05:00');
        $this->at('2026-09-30 17:00:00');
        $this->assertFalse($onTime->isLate());
        $this->assertTrue($late->isLate());
        $this->assertTrue($late->isMissingClockOut());
        $this->assertFalse($running->isMissingClockOut());
        $this->get(route('admin.reports.attendance'))->assertOk()
            ->assertViewHas('todaySummary', ['employees' => 3, 'entered' => 3, 'late' => 1, 'absent' => 0])
            ->assertViewHas('summary', ['total' => 3, 'late' => 1, 'missing' => 2]);
        $this->assertStringContainsString('Terlambat', view('admin.attendance.status', ['attendance' => $late])->render());
        $this->assertStringContainsString('Tidak Absen Pulang', view('admin.attendance.status', ['attendance' => $late])->render());
    }

    public function test_history_date_and_employee_filters_do_not_filter_today_monitoring(): void
    {
        $this->admin();
        $first = $this->employee('Pertama');
        $second = $this->employee('Kedua');
        $this->enter($first, '2026-08-31 08:30:00');
        $kept = $this->enter($first, '2026-09-01 08:30:00');
        $this->enter($second, '2026-09-01 08:30:00');
        $this->enter($first, '2026-09-30 08:30:00');
        $this->at('2026-09-30 09:00:00');
        $this->get(route('admin.reports.attendance'))->assertViewHas('start', '2026-09-01')->assertViewHas('end', '2026-09-30')
            ->assertViewHas('history', fn ($history): bool => $history->total() === 3);
        $this->get(route('admin.reports.attendance', ['start' => '2026-09-01', 'end' => '2026-09-01', 'employee' => $first->id]))
            ->assertViewHas('history', fn ($history): bool => $history->total() === 1 && $history->first()->id === $kept->id)
            ->assertViewHas('summary', ['total' => 1, 'late' => 0, 'missing' => 1])
            ->assertViewHas('todaySummary', ['employees' => 2, 'entered' => 1, 'late' => 0, 'absent' => 1]);
    }

    public function test_each_condition_filters_history_and_summary(): void
    {
        $this->admin();
        $this->enter($this->employee(), '2026-09-30 08:35:00');
        $late = $this->enter($this->employee(), '2026-09-30 08:36:00');
        $this->enter($this->employee('Siang', 'afternoon'), '2026-09-30 14:06:00');
        $this->at('2026-09-30 17:00:00');
        foreach ([['present', 1, 0, 1], ['late', 2, 2, 1], ['missing_clock_out', 2, 1, 2]] as [$condition, $total, $lateCount, $missing]) {
            $this->get(route('admin.reports.attendance', ['condition' => $condition]))->assertOk()
                ->assertViewHas('summary', ['total' => $total, 'late' => $lateCount, 'missing' => $missing])
                ->assertViewHas('history', fn ($history): bool => $history->total() === $total);
        }
    }

    public function test_snapshot_is_used_after_assignment_changes_and_late_uses_config(): void
    {
        $this->admin();
        config(['attendance.tolerance_minutes' => 10]);
        $employee = $this->employee();
        $onTime = $this->enter($employee, '2026-09-30 08:40:00');
        $late = $this->enter($this->employee(), '2026-09-30 08:40:01');
        $employee->update(['shift_type' => 'afternoon']);
        $this->at('2026-09-30 17:00:00');
        $this->get(route('admin.reports.attendance'))->assertViewHas('summary', ['total' => 2, 'late' => 1, 'missing' => 2]);
        $this->assertStringContainsString('08:30', view('admin.attendance.shift', ['attendance' => $onTime])->render());
        $this->assertSame([$late->id], Attendance::late()->pluck('id')->all());
    }

    public function test_missing_ends_at_exact_boundary_and_clears_on_clock_out(): void
    {
        $this->admin();
        $employee = $this->employee();
        $record = $this->enter($employee, '2026-09-30 08:30:00');
        $this->at('2026-09-30 16:30:00');
        $this->assertFalse($record->isMissingClockOut());
        $this->assertSame(0, Attendance::missingClockOut()->count());
        $this->at('2026-09-30 16:30:01');
        $this->assertTrue($record->isMissingClockOut());
        $this->assertSame(1, Attendance::missingClockOut()->count());
        app(AttendanceService::class)->clockOut($employee, self::LOCATION);
        $this->assertSame(0, Attendance::missingClockOut()->count());
        $this->get(route('admin.reports.attendance'))->assertViewHas('summary', ['total' => 1, 'late' => 0, 'missing' => 0]);
    }

    public function test_history_pagination_preserves_filters_and_summary_covers_all_pages(): void
    {
        $this->admin();
        $employee = $this->employee();
        for ($day = 1; $day <= 16; $day++) {
            $this->enter($employee, sprintf('2026-09-%02d 08:30:00', $day));
        }
        $this->at('2026-09-30 09:00:00');
        $filters = ['employee' => $employee->id, 'condition' => 'present'];
        $this->get(route('admin.reports.attendance', $filters))->assertViewHas('history', fn ($history): bool => $history->count() === 15 && $history->total() === 16 && str_contains($history->nextPageUrl(), 'condition=present'))
            ->assertViewHas('summary', ['total' => 16, 'late' => 0, 'missing' => 16]);
        $this->get(route('admin.reports.attendance', $filters + ['page' => 2]))->assertViewHas('history', fn ($history): bool => $history->count() === 1 && $history->currentPage() === 2);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->admin();
        foreach ([['start' => 'bad', 'end' => '2026-09-30'], ['start' => '2026-09-30', 'end' => '2026-09-01'], ['start' => '2026-09-01'], ['employee' => 99999], ['employee' => auth()->id()], ['condition' => 'absent'], ['page' => -1]] as $filters) {
            $this->getJson(route('admin.reports.attendance', $filters))->assertUnprocessable();
        }
    }

    public function test_missing_snapshot_falls_back_to_saved_late_status_without_inventing_missing(): void
    {
        $this->admin();
        $record = $this->enter($this->employee(), '2026-09-29 08:40:00');
        $record->scheduled_start_at = null;
        $record->scheduled_end_at = null;
        $record->save();
        $this->at('2026-09-30 17:00:00');
        $this->assertTrue($record->isLate());
        $this->assertFalse($record->isMissingClockOut());
        $this->get(route('admin.reports.attendance'))->assertViewHas('summary', ['total' => 1, 'late' => 1, 'missing' => 0]);
    }

    public function test_query_count_does_not_grow_per_employee(): void
    {
        $this->admin();
        $this->enter($this->employee(), '2026-09-30 08:30:00');
        DB::enableQueryLog();
        $this->get(route('admin.reports.attendance'))->assertOk();
        $initial = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (range(1, 5) as $index) {
            $this->enter($this->employee('Pegawai '.$index), '2026-09-30 08:30:00');
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.reports.attendance'))->assertOk();
        $this->assertSame($initial, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_report_uses_snapshot_times_without_rewriting_saved_status(): void
    {
        $this->admin();
        $record = $this->enter($this->employee(), '2026-09-30 08:36:00');
        $record->status = 'present';
        $record->save();
        $original = $record->getAttributes();
        $this->at('2026-09-30 17:00:00');
        $this->get(route('admin.reports.attendance', ['condition' => 'late']))->assertOk()
            ->assertViewHas('summary', ['total' => 1, 'late' => 1, 'missing' => 1]);
        $this->assertSame($original, $record->fresh()->getAttributes());
    }

    public function test_today_and_default_month_follow_attendance_timezone(): void
    {
        $this->admin();
        $employee = $this->employee();
        $this->enter($employee, '2026-10-01 00:30:00');
        $this->get(route('admin.reports.attendance'))->assertOk()
            ->assertViewHas('start', '2026-10-01')->assertViewHas('end', '2026-10-31')
            ->assertViewHas('todaySummary', ['employees' => 1, 'entered' => 1, 'late' => 0, 'absent' => 0])
            ->assertViewHas('history', fn ($history): bool => $history->total() === 1);
    }
}
