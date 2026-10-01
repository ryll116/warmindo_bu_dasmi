@extends('layouts.admin')
@section('title', 'Absensi Saya')
@push('styles')
    <link href="{{ asset('css/attendance.css') }}" rel="stylesheet">
@endpush
@section('content')
<div class="attendance-page">
    <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <p class="attendance-eyebrow mb-2">KEHADIRAN KARYAWAN</p>
            <h1 class="h3 fw-bold mb-2">Absensi Saya</h1>
            <p class="text-secondary mb-0">Catat kehadiran dan pantau riwayat kerja Anda.</p>
        </div>
        <span class="attendance-date">{{ now(config('attendance.timezone'))->format('d/m/Y') }}</span>
    </header>
    @include('admin.attendance.banner')
    <div class="card attendance-card">
        <div class="card-body">
            <h2 class="h5">Riwayat Absensi</h2>
            <p class="small text-secondary">Waktu ditampilkan dalam {{ config('attendance.timezone') }}. Toleransi masuk {{ config('attendance.tolerance_minutes') }} menit. Status diperbarui saat halaman dimuat.</p>
            <div class="table-responsive">
                <table class="table align-middle attendance-history">
                    <thead><tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th>Durasi</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($history as $attendance)
                        <tr>
                            <td class="text-nowrap">{{ $attendance->attendance_date->format('d/m/Y') }}</td>
                            <td>{{ $attendance->clock_in?->setTimezone(config('attendance.timezone'))->format('H:i') ?? '—' }}</td>
                            <td>{{ $attendance->clock_out?->setTimezone(config('attendance.timezone'))->format('H:i') ?? '—' }}</td>
                            <td class="text-nowrap">{{ $attendance->durationLabel() ?? '—' }}</td>
                            <td>@include('admin.attendance.status')</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">Belum ada riwayat absensi.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $history->links() }}
        </div>
    </div>
</div>
@endsection
