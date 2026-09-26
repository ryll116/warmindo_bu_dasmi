<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CheckoutRequest;
use App\Models\Order;
use App\Models\Table;
use App\OrderCreation;
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
    public function __construct(private OrderCreation $creation) {}

    public function review(CheckoutRequest $request, string $qr_token): RedirectResponse
    {
        $table = $this->availableTable($qr_token);
        $items = array_map(fn (array $item): array => [
            'product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity'],
        ], $request->validated('items'));
        $this->creation->lines($items);
        $token = (string) Str::uuid();
        $request->session()->put('checkout_drafts.'.$token, ['table_id' => $table->id, 'items' => $items]);

        return to_route('customer.checkout.show', ['qr_token' => $qr_token, 'checkout_token' => $token]);
    }

    public function show(Request $request, string $qr_token, string $checkout_token): View|RedirectResponse
    {
        try {
            $table = $this->availableTable($qr_token);
            $draft = $this->draft($request, $checkout_token, $table);
            $lines = $this->creation->lines($draft['items']);
        } catch (ValidationException $exception) {
            return to_route('customer.menu', $qr_token)->withErrors($exception->errors());
        }
        $total = $this->creation->decimal(array_sum(array_column($lines, 'subtotal_cents')));

        return view('customer.checkout', compact('table', 'lines', 'total', 'checkout_token'));
    }

    public function store(CheckoutRequest $request, string $qr_token): RedirectResponse
    {
        try {
            $order = DB::transaction(function () use ($request, $qr_token): Order {
                $table = $this->availableTable($qr_token, true);
                $token = $request->validated('checkout_token');
                $draft = $this->draft($request, $token, $table);

                return $this->creation->create($table->id, $token, $draft['items'], $request->validated('customer_name'), $request->validated('payment_type'), $request->validated('notes'));
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
}
