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

    public const CUSTOMER_PAYMENT_TYPES = ['cash' => 'Bayar di Kasir', 'qris_manual' => 'QRIS'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'queue_number' => 'integer'];
    }

    public function getQueueLabelAttribute(): string
    {
        return $this->queue_number === null ? '-' : 'A-'.str_pad((string) $this->queue_number, 3, '0', STR_PAD_LEFT);
    }

    protected static function booted(): void
    {
        static::updating(function (Order $order): void {
            if ($order->isDirty(['queue_date', 'queue_number'])) {
                throw new \LogicException('Nomor antrian tidak dapat diubah setelah pesanan dibuat.');
            }
        });
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
