<div class="d-flex flex-wrap gap-1 mt-2">
    @if ($attendance->clock_in)
        <span class="badge {{ $attendance->isLate() ? 'text-bg-warning' : 'text-bg-success' }}">{{ $attendance->isLate() ? 'Terlambat' : 'Hadir' }}</span>
        @if ($attendance->isMissingClockOut())
            <span class="badge text-bg-danger">Tidak Absen Pulang</span>
        @elseif ($attendance->clock_out)
            <span class="badge text-bg-secondary">Selesai</span>
        @else
            <span class="badge text-bg-light">Belum absen pulang</span>
        @endif
        @if (! $attendance->scheduled_end_at)
            <span class="badge text-bg-light">Snapshot jadwal belum tersedia</span>
        @endif
    @else
        <span class="badge text-bg-light">{{ $attendance->clock_out ? 'Data absensi tidak lengkap' : 'Belum absen masuk' }}</span>
    @endif
</div>
