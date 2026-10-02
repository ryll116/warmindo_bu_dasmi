<?php

namespace App\Models;

use App\InventoryQuantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockMovement extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Ledger inventory tidak dapat diubah. Buat movement koreksi terpisah.');
        });
        static::deleting(function (): never {
            throw new LogicException('Ledger inventory tidak dapat dihapus.');
        });
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'stock_before' => 'decimal:3', 'stock_after' => 'decimal:3', 'physical_stock' => 'decimal:3'];
    }

    public function variance(): ?string
    {
        return $this->physical_stock === null ? null : InventoryQuantity::format(
            InventoryQuantity::parse($this->physical_stock) - InventoryQuantity::parse($this->stock_before)
        );
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class, 'inventory_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
