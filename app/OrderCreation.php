<?php

namespace App;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCreation
{
    /** @param array<int, array{product_id: int, quantity: int}> $items */
    public function create(int $tableId, string $token, array $items, string $customerName, ?string $paymentType = null, ?string $notes = null): Order
    {
        return DB::transaction(function () use ($tableId, $token, $items, $customerName, $paymentType, $notes): Order {
            $table = Table::whereKey($tableId)->lockForUpdate()->first();
            if (! $table || ! $table->is_available) {
                throw ValidationException::withMessages(['table_id' => 'Meja tidak tersedia. Pilih meja aktif.']);
            }
            $existing = Order::whereKey($token)->first();
            if ($existing) {
                if ($existing->table_id !== $table->id) {
                    throw ValidationException::withMessages(['checkout_token' => 'Pesanan sudah dibuat untuk meja lain. Buka form pesanan baru.']);
                }

                return $existing;
            }
            $lines = $this->lines($items, true);
            $order = new Order;
            $order->customer_name = $customerName;
            $order->payment_type = $paymentType;
            $order->order_status = 'pending';
            $order->payment_status = 'unpaid';
            $order->payment_time = null;
            $order->id = $token;
            $order->table()->associate($table);
            $order->save();
            $total = 0;
            foreach ($lines as $line) {
                $item = new OrderItem;
                $item->product_id = $line['product_id'];
                $item->product_name = $line['product_name'];
                $item->resto_id = $line['resto_id'];
                $item->resto_name = $line['resto_name'];
                $item->price = $line['price'];
                $item->qty = $line['quantity'];
                $item->subtotal = $line['subtotal'];
                $item->notes = $notes;
                $order->items()->save($item);
                $total += $line['subtotal_cents'];
            }
            $order->total = $this->decimal($total);
            $order->save();

            return $order;
        }, 3);
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @return array<int, array{product_id: int, product_name: string, resto_id: ?int, resto_name: ?string, price: string, quantity: int, subtotal: string, subtotal_cents: int}>
     */
    public function lines(array $items, bool $lock = false): array
    {
        $products = Product::with('resto')->whereIn('id', array_column($items, 'product_id'))
            ->where('is_available', true)
            ->whereHas('category', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->where('status', 'active')->orWhereNull('status')))
            ->orderBy('id')->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->get()->keyBy('id');
        $lines = [];
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if (! $product || ! preg_match('/^\d+\.\d{2}$/', $product->price)) {
                throw ValidationException::withMessages(['items' => 'Ada menu yang sudah tidak tersedia atau harganya tidak valid. Silakan periksa kembali keranjang.']);
            }
            $price = $product->effectivePrice();
            $subtotal = (int) str_replace('.', '', $price) * $item['quantity'];
            $lines[] = [
                'product_id' => $product->id, 'product_name' => $product->product_name,
                'resto_id' => $product->resto_id, 'resto_name' => $product->resto?->resto_name,
                'price' => $price, 'quantity' => $item['quantity'],
                'subtotal' => $this->decimal($subtotal), 'subtotal_cents' => $subtotal,
            ];
        }

        return $lines;
    }

    public function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
