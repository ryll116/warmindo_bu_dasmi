<?php

namespace Tests\Feature;

use App\AttendanceService;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class AttendanceTest extends AdminDatabaseTestCase
{
    private const LOCATION = ['latitude' => 0, 'longitude' => 0];

    protected function setUp(): void
    {
        parent::setUp();
        config(['attendance.location' => ['latitude' => 0, 'longitude' => 0, 'radius_meters' => 100]]);
        config(['app.timezone' => 'UTC', 'attendance.timezone' => 'Asia/Jakarta']);
        $this->at('08:30:00');
    }

    private function at(string $time, string $date = '2026-09-30'): void
    {
        $this->travelTo(CarbonImmutable::parse($date.' '.$time, 'Asia/Jakarta'));
    }

    private function employee(?string $shift = 'morning'): User
    {
        $user = User::factory()->create(['shift_type' => $shift]);
        $this->actingAs($user);

        return $user;
    }

    public function test_guest_and_admin_roles_cannot_access_personal_attendance(): void
    {
        $this->get(route('attendance.index'))->assertRedirect(route('login'));
        foreach (['clock-in', 'clock-out'] as $action) {
            $this->postJson(route('attendance.'.$action), self::LOCATION)->assertUnauthorized();
        }
        foreach (['admin', 'superAdmin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('attendance.index'))->assertForbidden();
            $this->postJson(route('attendance.clock-in'), self::LOCATION)->assertForbidden();
            $this->postJson(route('attendance.clock-out'), self::LOCATION)->assertForbidden();
        }
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_warning_clock_in_and_completed_ui_and_duration(): void
    {
        $this->employee();
        $this->get(route('attendance.index'))->assertOk()->assertSee('Anda belum absen hari ini')->assertSee('08:30')->assertSee('Absen Masuk');
        $this->at('08:32:00');
        $this->from(route('attendance.index'))->post(route('attendance.clock-in'), self::LOCATION)->assertRedirect(route('attendance.index'));
        $this->get(route('attendance.index'))->assertSee('Sudah Absen Masuk')->assertSee('Absen Pulang')->assertSee('08:32');
        $this->at('16:35:00');
        $this->post(route('attendance.clock-out'), self::LOCATION)->assertRedirect();
        $this->get(route('attendance.index'))->assertSee('Absensi Hari Ini Selesai')->assertSee('8j 03m');
    }

    public function test_request_identity_timestamps_and_status_are_ignored(): void
    {
        $user = $this->employee();
        $other = User::factory()->create();
        $this->at('08:40:12');
        $this->post(route('attendance.clock-in'), self::LOCATION + ['user_id' => $other->id, 'clock_in' => '2000-01-01', 'status' => 'present', 'attendance_date' => '2000-01-01'])->assertRedirect();
        $record = Attendance::sole();
        $this->assertSame($user->id, $record->user_id);
        $this->assertSame('2026-09-30 01:40:12', $record->clock_in->format('Y-m-d H:i:s'));
        $this->assertSame('late', $record->status);
        $this->assertSame('2026-09-30', $record->attendance_date->toDateString());
        $this->at('17:00:00');
        $this->post(route('attendance.clock-out'), self::LOCATION + ['user_id' => $other->id, 'clock_out' => '2000-01-01', 'attendance_id' => 999])->assertRedirect();
        $this->assertSame('10:00:00', $record->fresh()->clock_out->format('H:i:s'));
    }

    public function test_exact_server_tolerance_for_both_shifts(): void
    {
        foreach ([['morning', '08:25:00', 'present'], ['morning', '08:35:00', 'present'], ['morning', '08:35:01', 'late'], ['morning', '08:36:00', 'late'], ['afternoon', '14:05:00', 'present'], ['afternoon', '14:06:00', 'late']] as [$shift, $time, $status]) {
            $user = $this->employee($shift);
            $this->at($time);
            $this->post(route('attendance.clock-in'), self::LOCATION)->assertRedirect();
            $this->assertDatabaseHas('attendances', ['user_id' => $user->id, 'status' => $status]);
        }
    }

    public function test_double_requests_preserve_first_timestamps(): void
    {
        $this->employee();
        $this->post(route('attendance.clock-in'), self::LOCATION)->assertRedirect();
        $first = Attendance::sole()->clock_in;
        $this->at('09:00:00');
        $this->postJson(route('attendance.clock-in'), self::LOCATION)->assertUnprocessable();
        $this->assertDatabaseCount('attendances', 1);
        $this->assertTrue(Attendance::sole()->clock_in->eq($first));
        $this->at('17:00:00');
        $this->post(route('attendance.clock-out'), self::LOCATION)->assertRedirect();
        $last = Attendance::sole()->clock_out;
        $this->at('18:00:00');
        $this->postJson(route('attendance.clock-out'), self::LOCATION)->assertUnprocessable();
        $this->assertTrue(Attendance::sole()->clock_out->eq($last));
    }

    public function test_missing_clock_out_preserves_late_and_uses_schedule_snapshot(): void
    {
        $user = $this->employee();
        $this->at('08:40:00');
        $this->post(route('attendance.clock-in'), self::LOCATION);
        $user->update(['shift_type' => 'afternoon']);
        $this->at('16:30:00');
        $this->assertFalse(Attendance::sole()->isMissingClockOut());
        $this->at('16:31:00');
        $this->get(route('attendance.index'))->assertSee('Tidak Absen Pulang')->assertSee('Terlambat')->assertSee('08:30');
        $this->assertSame('late', Attendance::sole()->status);
        $this->at('08:00:00', '2026-10-01');
        $this->get(route('attendance.index'))->assertSee('Tidak Absen Pulang')->assertSee('Terlambat');
        $this->assertTrue(Attendance::sole()->isMissingClockOut());
    }

    public function test_clock_out_is_locked_until_exact_shift_end_for_both_shifts(): void
    {
        foreach ([['morning', '16:29:59', '16:30:00'], ['afternoon', '21:59:59', '22:00:00']] as [$shift, $before, $end]) {
            $this->at('08:00:00');
            $user = $this->employee($shift);
            $this->post(route('attendance.clock-in'), self::LOCATION)->assertRedirect();
            $user->update(['shift_type' => $shift === 'morning' ? 'afternoon' : 'morning']);
            $this->at($before);
            $this->get(route('attendance.index'))->assertSee('data-shift-locked="true" disabled', false);
            $this->postJson(route('attendance.clock-out'), self::LOCATION + ['scheduled_end_at' => '2000-01-01'])->assertUnprocessable();
            $record = Attendance::where('user_id', $user->id)->sole();
            $this->assertNull($record->clock_out);
            $this->at($end);
            $this->get(route('attendance.index'))->assertSee('data-shift-locked="false"', false)->assertDontSee('data-shift-locked="true"', false);
            $this->post(route('attendance.clock-out'), self::LOCATION)->assertRedirect();
            $this->assertNotNull($record->fresh()->clock_out);
            $this->assertSame('present', $record->fresh()->status);
        }
    }

    public function test_missing_shift_and_invalid_clock_out_are_rejected(): void
    {
        $this->employee(null);
        $this->get(route('attendance.index'))->assertSee('Jadwal kerja belum diatur');
        $this->postJson(route('attendance.clock-in'), self::LOCATION)->assertUnprocessable();
        $this->postJson(route('attendance.clock-out'), self::LOCATION)->assertUnprocessable();
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_null_clock_in_record_can_be_completed_but_invalid_record_is_rejected(): void
    {
        $user = $this->employee();
        $record = new Attendance;
        $record->user_id = $user->id;
        $record->attendance_date = '2026-09-30';
        $record->save();
        $this->postJson(route('attendance.clock-out'), self::LOCATION)->assertUnprocessable();
        $this->post(route('attendance.clock-in'), self::LOCATION)->assertRedirect();
        $this->assertDatabaseCount('attendances', 1);
        $record->refresh();
        $record->clock_in = null;
        $record->clock_out = now();
        $record->save();
        $this->postJson(route('attendance.clock-in'), self::LOCATION)->assertUnprocessable();
        $this->get(route('attendance.index'))->assertOk()->assertSee('Data absensi tidak lengkap');
    }

    public function test_history_is_private_and_date_uses_local_day(): void
    {
        $other = $this->employee();
        $this->at('08:00:00', '2026-09-29');
        $this->post(route('attendance.clock-in'), self::LOCATION);
        $user = $this->employee();
        $this->at('00:30:00');
        $this->post(route('attendance.clock-in'), self::LOCATION)->assertRedirect();
        $this->assertSame('2026-09-30', Attendance::where('user_id', $user->id)->sole()->attendance_date->toDateString());
        $this->get(route('attendance.index'))->assertDontSee('29/09/2026')->assertViewHas('history', fn ($history) => $history->count() === 1 && $history->first()->user_id === $user->id);
    }

    public function test_super_admin_can_assign_valid_shift_and_invalid_shift_is_rejected(): void
    {
        $user = $this->employee();
        $this->actingAs(User::factory()->create(['role' => 'superAdmin']));
        $payload = ['name' => $user->name, 'email' => $user->email, 'role' => 'kasir', 'shift_type' => 'afternoon'];
        $this->put(route('admin.users.update', $user), $payload)->assertRedirect();
        $this->assertSame('afternoon', $user->fresh()->shift_type);
        $this->putJson(route('admin.users.update', $user), array_merge($payload, ['shift_type' => 'invalid']))->assertUnprocessable();
        $this->actingAs($user);
        $this->putJson(route('admin.users.update', $user), $payload)->assertForbidden();
    }

    public function test_employee_role_beyond_cashier_can_use_attendance(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'pegawai', 'shift_type' => 'morning']));
        $this->get(route('attendance.index'))->assertOk();
        $this->post(route('attendance.clock-in'), self::LOCATION)->assertRedirect();
    }

    public function test_old_record_without_snapshot_does_not_invent_missing_clock_out(): void
    {
        $this->employee();
        $this->post(route('attendance.clock-in'), self::LOCATION);
        $record = Attendance::sole();
        $record->scheduled_end_at = null;
        $record->save();
        $this->at('23:00:00');
        $this->assertFalse($record->isMissingClockOut());
        $this->get(route('attendance.index'))->assertSee('Snapshot jadwal belum tersedia');
        $this->postJson(route('attendance.clock-out'), self::LOCATION)->assertUnprocessable();
        $this->assertNull($record->fresh()->clock_out);
    }

    public function test_clock_out_cannot_precede_clock_in(): void
    {
        $this->employee();
        $this->post(route('attendance.clock-in'), self::LOCATION);
        $this->at('08:00:00');
        $this->postJson(route('attendance.clock-out'), self::LOCATION)->assertUnprocessable();
        $this->assertNull(Attendance::sole()->clock_out);
    }

    public function test_banner_queries_today_once_and_is_absent_for_admin(): void
    {
        $this->employee();
        DB::enableQueryLog();
        $this->get(route('attendance.index'))->assertOk();
        $todayQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'attendances') && in_array('2026-09-30', $query['bindings'], true));
        $this->assertCount(1, $todayQueries);
        DB::disableQueryLog();
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.products.index'))->assertOk()->assertDontSee('Anda belum absen hari ini')->assertDontSee('Absensi Saya');
    }

    public function test_database_failure_returns_friendly_error(): void
    {
        $this->employee();
        $this->mock(AttendanceService::class, function ($mock): void {
            $mock->shouldReceive('clockIn')->once()->andThrow(new QueryException('sqlite', 'private SQL', [], new \Exception('private failure')));
        });
        $this->from(route('attendance.index'))->post(route('attendance.clock-in'), self::LOCATION)->assertRedirect(route('attendance.index'))->assertSessionHas('error', 'Absensi belum dapat disimpan. Muat ulang halaman untuk memeriksa status sebelum mencoba kembali.');
        $this->assertDatabaseCount('attendances', 0);
    }
}
