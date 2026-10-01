<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function toleranceMinutes(): int
    {
        return (int) config('attendance.tolerance_minutes');
    }

    public function isLate(): bool
    {
        if (! $this->clock_in) {
            return false;
        }

        return $this->scheduled_start_at
            ? $this->clock_in->gt($this->scheduled_start_at->addMinutes(self::toleranceMinutes()))
            : $this->status === 'late';
    }

    public function scopeLate(Builder $query, bool $late = true): Builder
    {
        $cutoff = $query->getConnection()->getDriverName() === 'sqlite'
            ? "datetime(scheduled_start_at, '+' || ? || ' minutes')"
            : 'DATE_ADD(scheduled_start_at, INTERVAL ? MINUTE)';

        return $query->whereNotNull('clock_in')->whereRaw(
            "(CASE WHEN scheduled_start_at IS NULL THEN CASE WHEN status = 'late' THEN 1 ELSE 0 END WHEN clock_in > {$cutoff} THEN 1 ELSE 0 END) = ?",
            [self::toleranceMinutes(), $late ? 1 : 0],
        );
    }

    public function scopeMissingClockOut(Builder $query): Builder
    {
        return $query->whereNotNull('clock_in')->whereNull('clock_out')
            ->whereNotNull('scheduled_end_at')->where('scheduled_end_at', '<', now());
    }

    protected function casts(): array
    {
        return [
            'attendance_date' => 'immutable_date',
            'clock_in' => 'immutable_datetime',
            'clock_out' => 'immutable_datetime',
            'scheduled_start_at' => 'immutable_datetime',
            'scheduled_end_at' => 'immutable_datetime',
            'clock_in_latitude' => 'decimal:7',
            'clock_in_longitude' => 'decimal:7',
            'clock_out_latitude' => 'decimal:7',
            'clock_out_longitude' => 'decimal:7',
        ];
    }

    public function isMissingClockOut(): bool
    {
        return $this->clock_in !== null && $this->clock_out === null
            && $this->scheduled_end_at !== null && now()->gt($this->scheduled_end_at);
    }

    public function durationLabel(): ?string
    {
        if (! $this->clock_in || ! $this->clock_out || $this->clock_out->lt($this->clock_in)) {
            return null;
        }

        $minutes = (int) $this->clock_in->diffInMinutes($this->clock_out);

        return sprintf('%dj %02dm', intdiv($minutes, 60), $minutes % 60);
    }
}
