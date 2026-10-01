@extends('layouts.admin')
@section('title', 'Laporan Absensi')
@push('styles')
    <link href="{{ asset('css/attendance.css') }}" rel="stylesheet">
@endpush
@section('content')
<div class="attendance-page">
    <header class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <p class="attendance-eyebrow mb-2">MONITORING KARYAWAN</p>
            <h1 class="h3 fw-bold mb-2">Laporan Absensi</h1>
            <p class="text-secondary mb-0">Pantau kehadiran hari ini dan telusuri riwayat pegawai.</p>
        </div>
        <span class="attendance-date">{{ $today->format('d/m/Y') }}</span>
    </header>
    <section aria-labelledby="monitoring-title" class="mb-4">
        <h2 id="monitoring-title" class="h5 mb-3">Monitoring Hari Ini</h2>
        <div class="row g-3 mb-3">
            @foreach (['employees' => ['Total Karyawan', 'blue'], 'entered' => ['Sudah Masuk', 'green'], 'late' => ['Terlambat', 'amber'], 'absent' => ['Belum Absen', 'red']] as $key => [$label, $color])
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card attendance-card attendance-kpi attendance-kpi-{{ $color }} h-100">
                        <div class="card-body"><p class="small text-secondary fw-semibold mb-2">{{ $label }}</p><strong class="attendance-kpi-value">{{ $todaySummary[$key] }}</strong></div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="card attendance-card">
            <div class="card-body">
                <p class="small text-secondary mb-3">Seluruh pegawai yang wajib absensi, termasuk yang belum memiliki jadwal. Monitoring hari ini tidak mengikuti filter riwayat.</p>
                <div class="table-responsive">
                    <table class="table attendance-history align-middle mb-0">
                        <thead><tr><th scope="col">Karyawan</th><th scope="col">Shift</th><th scope="col">Masuk</th><th scope="col">Pulang</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                        @forelse ($monitoring as $row)
                            @php($attendance = $row['attendance'])
                            <tr>
                                <td class="fw-semibold">{{ $row['employee']->name }}</td>
                                <td class="text-secondary">@include('admin.attendance.shift', ['shift' => $row['shift']])</td>
                                <td class="text-nowrap">{{ $attendance?->clock_in?->setTimezone(config('attendance.timezone'))->format('H:i') ?? '—' }}</td>
                                <td class="text-nowrap">{{ $attendance?->clock_out?->setTimezone(config('attendance.timezone'))->format('H:i') ?? '—' }}</td>
                                <td>
                                    @if (! $attendance?->clock_in)
                                        <span class="badge text-bg-danger">Belum Absen</span>
                                    @else
                                        @include('admin.attendance.status')
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-4">Belum ada pegawai yang wajib absensi.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
    <section aria-labelledby="history-title">
        <h2 id="history-title" class="h5 mb-3">Riwayat Absensi</h2>
        <form method="GET" action="{{ route('admin.reports.attendance') }}" class="card attendance-card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end attendance-report-filters">
                    <div class="col-12 col-sm-6 col-xl-2"><label for="start" class="form-label">Tanggal mulai</label><input id="start" name="start" type="date" class="form-control" value="{{ $start }}" required></div>
                    <div class="col-12 col-sm-6 col-xl-2"><label for="end" class="form-label">Tanggal akhir</label><input id="end" name="end" type="date" class="form-control" value="{{ $end }}" required></div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <label for="employee" class="form-label">Karyawan</label>
                        <select id="employee" name="employee" class="form-select">
                            <option value="">Semua Karyawan</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) $employeeId === (string) $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <label for="condition" class="form-label">Kondisi</label>
                        <select id="condition" name="condition" class="form-select">
                            <option value="">Semua</option>
                            @foreach (['present' => 'Hadir (tepat waktu)', 'late' => 'Terlambat', 'missing_clock_out' => 'Tidak Absen Pulang'] as $value => $label)
                                <option value="{{ $value }}" @selected($condition === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-xl-2 d-flex gap-2"><button type="submit" class="btn btn-primary flex-fill">Terapkan</button><a href="{{ route('admin.reports.attendance') }}" class="btn btn-outline-secondary">Reset</a></div>
                </div>
            </div>
        </form>
        <div class="card attendance-card mb-3">
            <div class="card-body">
                <h3 class="h6 fw-semibold mb-3">Rekap Periode <span class="text-secondary fw-normal">{{ $start }} – {{ $end }}</span></h3>
                <div class="row g-3">
                    @foreach (['total' => 'Total Kehadiran', 'late' => 'Terlambat', 'missing' => 'Tidak Absen Pulang'] as $key => $label)
                        <div class="col-12 col-sm-4"><p class="small text-secondary mb-1">{{ $label }}</p><strong class="fs-4">{{ $summary[$key] }}</strong></div>
                    @endforeach
                </div>
                <p class="small text-secondary mt-3 mb-0">Rekap mengikuti seluruh filter aktif. Terlambat dan Tidak Absen Pulang dapat terjadi pada kehadiran yang sama.</p>
            </div>
        </div>
        <div class="card attendance-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table attendance-history align-middle">
                        <thead><tr><th scope="col">Tanggal</th><th scope="col">Karyawan</th><th scope="col">Shift</th><th scope="col">Masuk</th><th scope="col">Pulang</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                        @forelse ($history as $attendance)
                            <tr>
                                <td class="text-nowrap">{{ $attendance->attendance_date->format('d/m/Y') }}</td>
                                <td class="fw-semibold">{{ $attendance->user->name }}</td>
                                <td class="text-secondary">@include('admin.attendance.shift')</td>
                                <td>{{ $attendance->clock_in?->setTimezone(config('attendance.timezone'))->format('H:i') ?? '—' }}</td>
                                <td>{{ $attendance->clock_out?->setTimezone(config('attendance.timezone'))->format('H:i') ?? '—' }}</td>
                                <td>@include('admin.attendance.status')</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">Tidak ada riwayat absensi sesuai filter.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $history->links() }}
                <p class="small text-secondary mb-0 mt-3">Waktu: {{ config('attendance.timezone') }} · Diperbarui saat halaman dimuat. Riwayat hanya memuat absensi yang tercatat.</p>
            </div>
        </div>
    </section>
</div>
@endsection
