<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderItemRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderItemController extends Controller
{
    public function update(OrderItemRequest $request, Order $order, string $item): RedirectResponse
    {
        DB::transaction(function () use ($request, $order, $item): void {
            $locked = $this->editableOrder($order);
            $line = $locked->items()->whereKey($item)->lockForUpdate()->firstOrFail();
            $line->qty = (int) $request->validated('quantity');
            $this->saveItem($line);
            $this->recalculate($locked);
        });

        return to_route('admin.orders.show', $order)->with('success', 'Jumlah menu diperbarui.');
    }

    public function store(OrderItemRequest $request, Order $order): RedirectResponse
    {
        DB::transaction(function () use ($request, $order): void {
            $locked = $this->editableOrder($order);
            $product = Product::whereKey($request->validated('product_id'))->where('is_available', true)
                ->whereHas('category', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->where('status', 'active')->orWhereNull('status')))
                ->lockForUpdate()->first();
            if (! $product) {
                throw ValidationException::withMessages(['product_id' => 'Menu sudah tidak tersedia.']);
            }
            $line = $locked->items()->where('product_id', $product->id)->lockForUpdate()->first();
            if (! $line) {
                $line = new OrderItem;
                $line->order()->associate($locked);
                $line->product_id = $product->id;
                $line->product_name = $product->product_name;
                $line->price = $product->price;
                $line->qty = 0;
            }
            $line->qty += (int) $request->validated('quantity');
            if ($line->qty > 99) {
                throw ValidationException::withMessages(['quantity' => 'Maksimal 99 porsi untuk satu menu.']);
            }
            $this->saveItem($line);
            $this->recalculate($locked);
        });

        return to_route('admin.orders.show', $order)->with('success', 'Menu ditambahkan ke pesanan.');
    }

    public function destroy(OrderItemRequest $request, Order $order, string $item): RedirectResponse
    {
        DB::transaction(function () use ($order, $item): void {
            $locked = $this->editableOrder($order);
            $line = $locked->items()->whereKey($item)->lockForUpdate()->firstOrFail();
            if ($locked->items()->count() <= 1) {
                throw ValidationException::withMessages(['items' => 'Item terakhir tidak boleh dihapus. Pesanan tidak boleh kosong.']);
            }
            $line->delete();
            $this->recalculate($locked);
        });

        return to_route('admin.orders.show', $order)->with('success', 'Menu dihapus dari pesanan.');
    }

    private function editableOrder(Order $order): Order
    {
        $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        if ($locked->order_status !== 'pending' || $locked->payment_status !== 'unpaid') {
            throw ValidationException::withMessages(['items' => 'Item hanya dapat diedit saat pesanan pending dan belum dibayar.']);
        }

        return $locked;
    }

    private function saveItem(OrderItem $item): void
    {
        $item->subtotal = $this->decimal($this->cents($item->price) * $item->qty);
        $item->save();
    }

    private function recalculate(Order $order): void
    {
        $total = 0;
        foreach ($order->items()->get() as $item) {
            $total += $this->cents($item->subtotal);
        }
        $order->total = $this->decimal($total);
        $order->save();
    }

    private function cents(string $amount): int
    {
        if (! preg_match('/^\d+\.\d{2}$/', $amount)) {
            throw ValidationException::withMessages(['items' => 'Harga menu tidak valid.']);
        }

        return (int) str_replace('.', '', $amount);
    }

    private function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
