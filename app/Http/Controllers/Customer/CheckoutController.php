<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CheckoutRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function review(CheckoutRequest $request, string $qr_token): RedirectResponse
    {
        $table = $this->availableTable($qr_token);
        $items = array_map(fn (array $item): array => [
            'product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity'],
        ], $request->validated('items'));
        $this->lines($items);
        $token = (string) Str::uuid();
        $request->session()->put('checkout_drafts.'.$token, ['table_id' => $table->id, 'items' => $items]);

        return to_route('customer.checkout.show', ['qr_token' => $qr_token, 'checkout_token' => $token]);
    }

    public function show(Request $request, string $qr_token, string $checkout_token): View|RedirectResponse
    {
        try {
            $table = $this->availableTable($qr_token);
            $draft = $this->draft($request, $checkout_token, $table);
            $lines = $this->lines($draft['items']);
        } catch (ValidationException $exception) {
            return to_route('customer.menu', $qr_token)->withErrors($exception->errors());
        }
        $total = $this->decimal(array_sum(array_column($lines, 'subtotal_cents')));

        return view('customer.checkout', compact('table', 'lines', 'total', 'checkout_token'));
    }

    public function store(CheckoutRequest $request, string $qr_token): RedirectResponse
    {
        try {
            $order = DB::transaction(function () use ($request, $qr_token): Order {
                $table = $this->availableTable($qr_token, true);
                $token = $request->validated('checkout_token');
                $draft = $this->draft($request, $token, $table);
                $existing = Order::whereKey($token)->where('table_id', $table->id)->first();
                if ($existing) {
                    return $existing;
                }

                $lines = $this->lines($draft['items'], true);
                $order = new Order;
                $order->customer_name = $request->validated('customer_name');
                $order->payment_type = $request->validated('payment_type');
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
                    $item->price = $line['price'];
                    $item->qty = $line['quantity'];
                    $item->subtotal = $line['subtotal'];
                    $item->notes = $request->validated('notes');
                    $order->items()->save($item);
                    $total += $line['subtotal_cents'];
                }
                $order->total = $this->decimal($total);
                $order->save();

                return $order;
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['checkout' => 'Pesanan belum berhasil disimpan. Keranjang tetap tersimpan. Silakan coba lagi.']);
        }

        return redirect(URL::temporarySignedRoute('customer.checkout.success', now()->addDay(), [
            'qr_token' => $qr_token, 'order' => $order->id,
        ]))->with('checkout_completed', $order->id);
    }

    public function success(Request $request, string $qr_token, Order $order): View
    {
        $table = $order->table;
        abort_unless($table && hash_equals($table->qr_token, $qr_token), 404);
        $clearCart = $request->session()->get('checkout_completed') === $order->id
            && ! $request->session()->get('checkout_seen.'.$order->id, false);
        if ($clearCart) {
            $request->session()->put('checkout_seen.'.$order->id, true);
        }

        return view('customer.order-success', compact('order', 'table', 'clearCart'));
    }

    private function availableTable(string $token, bool $lock = false): Table
    {
        $table = Table::where('qr_token', $token)->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->first();
        if (! $table || ! $table->is_available) {
            throw ValidationException::withMessages(['checkout' => 'Meja tidak tersedia. Silakan scan ulang QR meja atau hubungi kasir.']);
        }

        return $table;
    }

    /** @return array{table_id: int, items: array<int, array{product_id: int, quantity: int}>} */
    private function draft(Request $request, string $token, Table $table): array
    {
        $draft = Str::isUuid($token) ? $request->session()->get('checkout_drafts.'.$token) : null;
        if (! $draft || $draft['table_id'] !== $table->id) {
            throw ValidationException::withMessages(['checkout' => 'Sesi checkout tidak tersedia. Silakan review ulang dari keranjang.']);
        }

        return $draft;
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @return array<int, array{product_id: int, product_name: string, price: string, quantity: int, subtotal: string, subtotal_cents: int}>
     */
    private function lines(array $items, bool $lock = false): array
    {
        $products = Product::whereIn('id', array_column($items, 'product_id'))
            ->where('is_available', true)
            ->whereHas('category', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->where('status', 'active')->orWhereNull('status')))
            ->orderBy('id')->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->get()->keyBy('id');
        $lines = [];
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if (! $product || ! preg_match('/^\d+\.\d{2}$/', $product->price)) {
                throw ValidationException::withMessages(['items' => 'Ada menu yang sudah tidak tersedia atau harganya tidak valid. Silakan periksa kembali keranjang.']);
            }
            $subtotal = (int) str_replace('.', '', $product->price) * $item['quantity'];
            $lines[] = [
                'product_id' => $product->id, 'product_name' => $product->product_name,
                'price' => $product->price, 'quantity' => $item['quantity'],
                'subtotal' => $this->decimal($subtotal), 'subtotal_cents' => $subtotal,
            ];
        }

        return $lines;
    }

    private function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
