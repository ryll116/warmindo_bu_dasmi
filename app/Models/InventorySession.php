<?php

namespace App\Models;

use Database\Factories\InventorySessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class InventorySession extends Model
{
    /** @use HasFactory<InventorySessionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (self $session): void {
            if ($session->movements()->exists()) {
                throw ValidationException::withMessages(['session' => 'Session dengan ledger tidak dapat dihapus.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['session_date' => 'immutable_date', 'opened_at' => 'immutable_datetime', 'closed_at' => 'immutable_datetime'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
