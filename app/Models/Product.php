<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['product_code', 'category_id', 'resto_id', 'product_name', 'price', 'disc', 'is_available'])]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'disc' => 'decimal:2', 'is_available' => 'boolean'];
    }

    public function hasDiscount(): bool
    {
        return $this->disc > 0 && $this->disc <= 100;
    }

    /** Calculate the selling price in cents, rounded half up once per unit. */
    public function effectivePrice(): string
    {
        $discount = $this->disc ?? '0.00';
        if (! preg_match('/^\d+\.\d{2}$/', $this->price ?? '') || ! preg_match('/^\d+\.\d{2}$/', $discount) || $discount > 100) {
            throw ValidationException::withMessages(['items' => 'Harga atau diskon menu tidak valid. Silakan hubungi admin.']);
        }
        $cents = (int) str_replace('.', '', $this->price);
        $basisPoints = (int) str_replace('.', '', $discount);
        $effectiveCents = intdiv($cents * (10000 - $basisPoints) + 5000, 10000);

        return intdiv($effectiveCents, 100).'.'.str_pad((string) ($effectiveCents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function resto(): BelongsTo
    {
        return $this->belongsTo(Resto::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
