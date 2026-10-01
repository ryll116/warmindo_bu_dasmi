@php($attendanceTimezone = config('attendance.timezone'))
<section class="card attendance-card attendance-today mb-4 {{ $attendanceToday?->clock_in ? 'attendance-recorded' : 'attendance-pending' }}" aria-label="Absensi hari ini">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="attendance-summary">
            <p class="attendance-eyebrow mb-2">ABSENSI HARI INI</p>
            <h2 class="h5 fw-semibold mb-3">{{ $attendanceToday?->clock_out && $attendanceToday?->clock_in ? 'Absensi Hari Ini Selesai' : ($attendanceToday?->clock_in ? 'Sudah Absen Masuk' : 'Anda belum absen hari ini') }}</h2>
            <div class="small text-secondary">
                @if ($attendanceToday?->scheduled_start_at && $attendanceToday?->scheduled_end_at)
                    Jadwal: {{ $attendanceToday->scheduled_start_at->setTimezone($attendanceTimezone)->format('H:i') }} – {{ $attendanceToday->scheduled_end_at->setTimezone($attendanceTimezone)->format('H:i') }}
                @elseif ($attendanceShift)
                    Jadwal: {{ $attendanceShift['start'] }} – {{ $attendanceShift['end'] }}
                @else
                    Jadwal kerja belum diatur. Hubungi admin.
                @endif
                <span class="ms-1">({{ $attendanceTimezone }})</span>
            </div>
            @if ($attendanceToday?->clock_in)
                <div class="small mt-2">Masuk: <strong>{{ $attendanceToday->clock_in->setTimezone($attendanceTimezone)->format('H:i') }}</strong>
                    @if ($attendanceToday->clock_out)
                        · Pulang: <strong>{{ $attendanceToday->clock_out->setTimezone($attendanceTimezone)->format('H:i') }}</strong>
                        · Durasi: {{ $attendanceToday->durationLabel() ?? 'Tidak valid' }}
                    @endif
                </div>
                @include('admin.attendance.status', ['attendance' => $attendanceToday])
                @if ($attendanceToday->clock_in_latitude !== null && $attendanceToday->clock_in_longitude !== null)
                    <p class="small text-secondary mt-2 mb-0">Lokasi masuk: Terverifikasi</p>
                @endif
                @if ($attendanceToday->clock_out_latitude !== null && $attendanceToday->clock_out_longitude !== null)
                    <p class="small text-secondary mt-1 mb-0">Lokasi pulang: Terverifikasi</p>
                @endif
            @elseif ($attendanceToday?->clock_out)
                <p class="small text-danger mb-0">Data absensi tidak lengkap. Hubungi admin.</p>
            @endif
        </div>
        <div class="attendance-actions">
            @if (! $attendanceToday?->clock_in && ! $attendanceToday?->clock_out && $attendanceShift)
                <form method="POST" action="{{ route('attendance.clock-in') }}" data-attendance-form>
                    @csrf
                    <button class="btn btn-primary py-2" type="submit">Absen Masuk</button>
                    <p class="small text-secondary mt-2 mb-0">Izinkan akses lokasi untuk memverifikasi area absensi.</p>
                    <p class="small text-danger mt-2 mb-0" role="alert" data-location-error hidden></p>
                </form>
            @elseif ($attendanceToday?->clock_in && ! $attendanceToday->clock_out)
                @php($clockOutLocked = ! $attendanceToday->scheduled_end_at || now()->lt($attendanceToday->scheduled_end_at))
                <form method="POST" action="{{ route('attendance.clock-out') }}" data-attendance-form data-confirm="Catat absen pulang sekarang? Pastikan pekerjaan Anda sudah selesai. Absen pulang hanya dapat dicatat sekali.">
                    @csrf
                    <button class="btn btn-outline-primary py-2" type="submit" data-shift-locked="{{ $clockOutLocked ? 'true' : 'false' }}" @disabled($clockOutLocked)>Absen Pulang</button>
                    <p class="small text-danger mt-2 mb-0" role="alert" data-location-error hidden></p>
                    @if ($clockOutLocked)
                        <p class="small text-secondary mt-2 mb-0">
                            @if ($attendanceToday->scheduled_end_at)
                                Tersedia mulai {{ $attendanceToday->scheduled_end_at->setTimezone($attendanceTimezone)->format('H:i') }}. Muat ulang halaman setelah shift berakhir.
                            @else
                                Jadwal selesai shift belum tersedia. Hubungi admin.
                            @endif
                        </p>
                    @endif
                </form>
            @endif
            @unless (request()->routeIs('attendance.index'))
                <a href="{{ route('attendance.index') }}" class="btn btn-link">Absensi Saya</a>
            @endunless
        </div>
    </div>
</section>
@push('scripts')
    <script src="{{ asset('js/attendance.js') }}" defer></script>
@endpush
