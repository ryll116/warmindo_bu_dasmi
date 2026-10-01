<?php

namespace App\Providers;

use App\AttendanceService;
use App\Models\Attendance;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('admin.attendance.banner', function (View $view): void {
            $user = auth()->user();
            if (! $user?->requiresAttendance()) {
                return;
            }
            $service = app(AttendanceService::class);
            $view->with('attendanceToday', Attendance::where('user_id', $user->id)->whereDate('attendance_date', $service->today())->first());
            $view->with('attendanceShift', $service->shift($user));
        });
    }
}
