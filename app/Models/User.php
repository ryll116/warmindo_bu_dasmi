<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'shift_type'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ATTENDANCE_EXEMPT_ROLES = ['admin', 'superAdmin'];

    public function requiresAttendance(): bool
    {
        return ! in_array($this->role, self::ATTENDANCE_EXEMPT_ROLES, true);
    }

    public function scopeAttendanceRequired(Builder $query): Builder
    {
        return $query->whereNotIn('role', self::ATTENDANCE_EXEMPT_ROLES);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isKasir(): bool
    {
        return $this->role === 'kasir';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superAdmin';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
