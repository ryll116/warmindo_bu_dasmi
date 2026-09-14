<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['table_no', 'is_available'])]
class Table extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['table_no' => 'integer', 'is_available' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Table $table): void {
            do {
                $token = Str::random(64);
            } while (static::where('qr_token', $token)->exists());

            $table->qr_token = $token;
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
