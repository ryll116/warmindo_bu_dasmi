<?php

namespace App\Http\Controllers;

use App\AttendanceService;
use App\Models\Attendance;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.attendance.index', [
            'history' => Attendance::where('user_id', $request->user()->id)->orderByDesc('attendance_date')->paginate(15),
        ]);
    }

    public function clockIn(Request $request, AttendanceService $service): RedirectResponse
    {
        try {
            $service->clockIn($request->user(), $request->only(['latitude', 'longitude', 'accuracy']));
        } catch (QueryException $exception) {
            report($exception);

            return back()->with('error', 'Absensi belum dapat disimpan. Muat ulang halaman untuk memeriksa status sebelum mencoba kembali.');
        }

        return back()->with('success', 'Absen masuk berhasil dicatat.');
    }

    public function clockOut(Request $request, AttendanceService $service): RedirectResponse
    {
        try {
            $service->clockOut($request->user(), $request->only(['latitude', 'longitude', 'accuracy']));
        } catch (QueryException $exception) {
            report($exception);

            return back()->with('error', 'Absensi belum dapat disimpan. Muat ulang halaman untuk memeriksa status sebelum mencoba kembali.');
        }

        return back()->with('success', 'Absen pulang berhasil dicatat.');
    }
}
