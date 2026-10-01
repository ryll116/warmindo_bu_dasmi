@if ($attendance)
    @if ($attendance->scheduled_start_at && $attendance->scheduled_end_at)
        {{ $attendance->scheduled_start_at->setTimezone(config('attendance.timezone'))->format('H:i') }}–{{ $attendance->scheduled_end_at->setTimezone(config('attendance.timezone'))->format('H:i') }}
    @else
        Snapshot jadwal belum tersedia
    @endif
@elseif ($shift ?? null)
    {{ $shift['start'] }}–{{ $shift['end'] }}
@else
    Jadwal belum diatur
@endif
