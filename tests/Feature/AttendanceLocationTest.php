<?php

namespace Tests\Feature;

use App\AttendanceLocationService;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class AttendanceLocationTest extends AdminDatabaseTestCase
{
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC', 'attendance.timezone' => 'Asia/Jakarta', 'attendance.location' => ['latitude' => 0, 'longitude' => 0, 'radius_meters' => 100]]);
        $this->travelTo(CarbonImmutable::parse('2026-09-30 08:35:00', 'Asia/Jakarta'));
        $this->employee = User::factory()->create(['shift_type' => 'morning']);
        $this->actingAs($this->employee);
    }

    /** @return array{latitude: float, longitude: float} */
    private function point(float $meters): array
    {
        return ['latitude' => rad2deg($meters / 6371000), 'longitude' => 0.0];
    }

    public function test_inside_clock_in_and_fresh_clock_out_coordinates_are_saved_on_authenticated_user(): void
    {
        $other = User::factory()->create();
        $this->post(route('attendance.clock-in'), $this->point(42) + ['user_id' => $other->id, 'accuracy' => 500, 'clock_in' => '2000-01-01', 'clock_in_latitude' => 80])->assertRedirect()->assertSessionHasNoErrors();
        $record = Attendance::sole();
        $this->assertSame($this->employee->id, $record->user_id);
        $this->assertEqualsWithDelta($this->point(42)['latitude'], (float) $record->clock_in_latitude, 0.0000001);
        $this->assertSame('0.0000000', $record->clock_in_longitude);
        $this->assertNull($record->clock_out_latitude);
        $this->assertSame('present', $record->status);
        $this->assertSame('01:35:00', $record->clock_in->format('H:i:s'));
        $this->travelTo(CarbonImmutable::parse('2026-09-30 16:30:00', 'Asia/Jakarta'));
        $this->post(route('attendance.clock-out'), $this->point(75) + ['user_id' => $other->id, 'clock_out_latitude' => 80])->assertRedirect()->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertEqualsWithDelta($this->point(75)['latitude'], (float) $record->clock_out_latitude, 0.0000001);
        $this->assertNotSame($record->clock_in_latitude, $record->clock_out_latitude);
        $this->assertSame('09:30:00', $record->clock_out->format('H:i:s'));
        $this->get(route('attendance.index'))->assertOk()->assertSee('Lokasi masuk: Terverifikasi')->assertSee('Lokasi pulang: Terverifikasi');
    }

    public function test_outside_clock_in_and_boundary_rules(): void
    {
        foreach ([100.1, 235] as $distance) {
            $this->postJson(route('attendance.clock-in'), $this->point($distance))->assertUnprocessable()->assertJsonValidationErrors('location');
            $this->assertDatabaseCount('attendances', 0);
        }
        foreach ([99.9, 100.0] as $distance) {
            $this->actingAs(User::factory()->create(['shift_type' => 'morning']));
            $this->post(route('attendance.clock-in'), $this->point($distance))->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_missing_and_invalid_coordinates_are_rejected_for_both_actions(): void
    {
        $invalid = [[], ['latitude' => 0], ['longitude' => 0], ['latitude' => 90.1, 'longitude' => 0], ['latitude' => -90.1, 'longitude' => 0], ['latitude' => 0, 'longitude' => 180.1], ['latitude' => 0, 'longitude' => -180.1], ['latitude' => 'invalid', 'longitude' => 0], ['latitude' => [], 'longitude' => 0], ['latitude' => '1e999', 'longitude' => 0], ['latitude' => 0, 'longitude' => 0, 'accuracy' => -1]];
        foreach ($invalid as $coordinates) {
            $this->postJson(route('attendance.clock-in'), $coordinates)->assertUnprocessable();
        }
        $this->assertDatabaseCount('attendances', 0);
        $this->post(route('attendance.clock-in'), $this->point(0))->assertSessionHasNoErrors();
        $original = Attendance::sole()->getAttributes();
        $this->travelTo(CarbonImmutable::parse('2026-09-30 17:00:00', 'Asia/Jakarta'));
        foreach ($invalid as $coordinates) {
            $this->postJson(route('attendance.clock-out'), $coordinates)->assertUnprocessable();
            $this->assertSame($original, Attendance::sole()->getAttributes());
        }
    }

    public function test_outside_clock_out_does_not_change_any_attendance_field(): void
    {
        $this->post(route('attendance.clock-in'), $this->point(0))->assertSessionHasNoErrors();
        $original = Attendance::sole()->getAttributes();
        $this->travelTo(CarbonImmutable::parse('2026-09-30 17:00:00', 'Asia/Jakarta'));
        $this->postJson(route('attendance.clock-out'), $this->point(235))->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->assertSame($original, Attendance::sole()->getAttributes());
    }

    public function test_missing_or_invalid_configuration_fails_safely_on_both_actions(): void
    {
        $this->post(route('attendance.clock-in'), $this->point(0))->assertSessionHasNoErrors();
        $original = Attendance::sole()->getAttributes();
        $this->travelTo(CarbonImmutable::parse('2026-09-30 17:00:00', 'Asia/Jakarta'));
        foreach ([[], ['latitude' => null], ['longitude' => ''], ['radius_meters' => null], ['radius_meters' => 0], ['radius_meters' => -1], ['radius_meters' => 'bad'], ['radius_meters' => '1e999'], ['latitude' => 91], ['longitude' => -181]] as $invalid) {
            $location = $invalid === [] ? [] : array_merge(['latitude' => 0, 'longitude' => 0, 'radius_meters' => 100], $invalid);
            config(['attendance.location' => $location]);
            $this->actingAs($this->employee)->postJson(route('attendance.clock-out'), $this->point(0))->assertUnprocessable()->assertJsonValidationErrors('location');
            $this->assertSame($original, Attendance::sole()->getAttributes());
            $this->actingAs(User::factory()->create(['shift_type' => 'morning']))->postJson(route('attendance.clock-in'), $this->point(0))->assertUnprocessable()->assertJsonValidationErrors('location');
            $this->assertDatabaseCount('attendances', 1);
        }
    }

    public function test_privileged_roles_cannot_attend_even_with_valid_coordinates(): void
    {
        foreach (['admin', 'superAdmin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->postJson(route('attendance.clock-in'), $this->point(0))->assertForbidden();
            $this->postJson(route('attendance.clock-out'), $this->point(0))->assertForbidden();
        }
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_browser_supplied_outlet_coordinates_are_accepted_as_mvp_spoofing_limitation(): void
    {
        $this->post(route('attendance.clock-in'), ['latitude' => 0, 'longitude' => 0])->assertSessionHasNoErrors();
        $original = Attendance::sole()->getAttributes();
        $this->postJson(route('attendance.clock-in'), $this->point(10))->assertUnprocessable();
        $this->assertSame($original, Attendance::sole()->getAttributes());
    }

    public function test_reports_remain_available_for_historical_rows_without_coordinates(): void
    {
        $record = new Attendance;
        $record->user_id = $this->employee->id;
        $record->attendance_date = '2026-09-29';
        $record->clock_in = CarbonImmutable::parse('2026-09-29 08:30:00', 'Asia/Jakarta')->setTimezone('UTC');
        $record->save();
        $this->get(route('attendance.index'))->assertOk()->assertDontSee('Lokasi masuk: Terverifikasi');
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.reports.attendance'))->assertOk();
        $this->assertNull($record->fresh()->clock_in_latitude);
    }

    public function test_location_service_accepts_zero_and_negative_outlet_coordinates(): void
    {
        config(['attendance.location' => ['latitude' => -6.2, 'longitude' => 106.8, 'radius_meters' => 100]]);
        $this->assertSame(['latitude' => -6.2, 'longitude' => 106.8], app(AttendanceLocationService::class)->validate(['latitude' => '-6.2', 'longitude' => '106.8']));
    }

    public function test_location_migration_keeps_historical_attendance_with_null_coordinates(): void
    {
        $migration = require database_path('migrations/2026_09_30_091337_add_location_coordinates_to_attendances_table.php');
        $migration->down();
        $record = new Attendance;
        $record->user_id = $this->employee->id;
        $record->attendance_date = '2026-09-29';
        $record->clock_in = now();
        $record->save();
        $originalTime = $record->clock_in;
        $migration->up();
        $record->refresh();
        $this->assertTrue($record->clock_in->eq($originalTime));
        foreach (['clock_in_latitude', 'clock_in_longitude', 'clock_out_latitude', 'clock_out_longitude'] as $column) {
            $this->assertNull($record->{$column});
        }
    }
}
