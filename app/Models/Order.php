<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasUuids;

    public const STATUS_FLOW = ['pending' => 'confirmed', 'confirmed' => 'processing', 'processing' => 'completed'];

    public const STATUSES = ['pending', 'confirmed', 'processing', 'completed'];

    public const PAYMENT_TYPES = ['cash' => 'Cash', 'qris_manual' => 'QRIS Manual'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2'];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
