<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(OrderRequest $request): View|JsonResponse
    {
        $monitor = $request->header('X-Order-Monitor');
        $baselines = $request->session()->get('cashier_monitors', []);
        $baseline = is_string($monitor) ? ($baselines[$monitor] ?? null) : null;
        if ($baseline === null) {
            $monitor = (string) Str::uuid();
            $baseline = Order::pluck('id')->all();
            $baselines[$monitor] = $baseline;
            $request->session()->put('cashier_monitors', array_slice($baselines, -10, null, true));
        }
        $filters = $request->validated();
        $tab = $filters['tab'] ?? 'active';
        $search = trim($filters['search'] ?? '');
        $query = Order::with('table')->withSum('items as item_quantity', 'qty');
        if ($tab === 'active') {
            $query->where('order_status', '!=', 'completed');
        } elseif ($tab === 'completed') {
            $query->where('order_status', 'completed');
        }
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('id', '%'.$search.'%')->orWhereLike('customer_name', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $query->orWhereHas('table', fn (Builder $query): Builder => $query->where('table_no', (int) $search));
                }
            });
        }
        foreach (['order_status', 'payment_status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        $orders = $query->orderByRaw("CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END")
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString();

        if ($request->header('X-Orders-Partial') === '1' && $request->expectsJson()) {
            $notifications = Order::whereNotIn('id', $baseline)->with('table')->withSum('items as item_quantity', 'qty')
                ->orderBy('created_at')->orderBy('id')->get()
                ->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'customer_name' => $order->customer_name ?: 'Nama belum tersedia',
                    'table' => str_pad((string) $order->table?->table_no, 2, '0', STR_PAD_LEFT),
                    'quantity' => (int) $order->item_quantity,
                    'total' => $order->total,
                    'url' => route('admin.orders.show', $order),
                ]);

            return response()->json(['html' => view('admin.orders._list', compact('orders'))->render(), 'monitor' => $monitor, 'notifications' => $notifications]);
        }

        return view($request->header('X-Orders-Partial') === '1' ? 'admin.orders._list' : 'admin.orders.index', compact('orders', 'filters', 'tab', 'monitor'));
    }

    public function show(Order $order): View
    {
        $order->load('table', 'items');

        $editable = $order->order_status === 'pending' && $order->payment_status === 'unpaid';
        $products = $editable ? Product::where('is_available', true)
            ->whereHas('category', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->where('status', 'active')->orWhereNull('status')))
            ->orderBy('product_name')->get(['id', 'product_name', 'price', 'disc']) : collect();

        return view('admin.orders.show', compact('order', 'products', 'editable'));
    }

    public function status(OrderRequest $request, Order $order): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($request, $order): void {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $next = Order::STATUS_FLOW[$locked->order_status] ?? null;
            if ($next === null || $next !== $request->validated('order_status')) {
                throw ValidationException::withMessages(['order_status' => 'Status sudah berubah atau transisi tidak diizinkan. Muat ulang detail pesanan.']);
            }
            $locked->order_status = $next;
            $locked->save();
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Status pesanan berhasil diperbarui.']);
        }

        return to_route('admin.orders.show', $order)->with('success', 'Status pesanan berhasil diperbarui.');
    }

    public function payment(OrderRequest $request, Order $order): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($request, $order): void {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->payment_status !== 'unpaid') {
                throw ValidationException::withMessages(['payment_type' => 'Pembayaran sudah dicatat atau status pembayaran tidak valid.']);
            }
            $locked->payment_status = 'paid';
            $locked->payment_type = $request->validated('payment_type');
            $locked->payment_time = now();
            $locked->save();
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Pembayaran berhasil dicatat.']);
        }

        return to_route('admin.orders.show', $order)->with('success', 'Pembayaran berhasil dicatat.');
    }
}
