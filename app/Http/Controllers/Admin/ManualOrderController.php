<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManualOrderRequest;
use App\Models\Product;
use App\Models\Table;
use App\OrderCreation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ManualOrderController extends Controller
{
    public function create(Request $request): View
    {
        $tokens = $request->session()->get('manual_order_tokens', []);
        $token = $request->old('checkout_token');
        if (! is_string($token) || ($tokens[$token] ?? null) !== $request->user()->id) {
            $token = (string) Str::uuid();
            $tokens[$token] = $request->user()->id;
            $request->session()->put('manual_order_tokens', array_slice($tokens, -50, null, true));
        }
        $tables = Table::where('is_available', true)->orderBy('table_no')->get(['id', 'table_no']);
        $products = Product::with(['resto:id,resto_name', 'category:id,category_name'])
            ->where('is_available', true)
            ->whereHas('category', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->where('status', 'active')->orWhereNull('status')))
            ->orderBy('product_name')->orderBy('id')->get(['id', 'category_id', 'resto_id', 'product_name', 'price', 'disc']);
        $categories = $products->pluck('category')->unique('id')->sortBy('category_name');

        return view('admin.orders.create', compact('tables', 'products', 'categories', 'token'));
    }

    public function store(ManualOrderRequest $request, OrderCreation $creation): RedirectResponse
    {
        $token = $request->validated('checkout_token');
        if ($request->session()->get('manual_order_tokens.'.$token) !== $request->user()->id) {
            throw ValidationException::withMessages(['checkout_token' => 'Sesi pesanan tidak tersedia. Buka kembali form Buat Pesanan.']);
        }
        try {
            $creation->create((int) $request->validated('table_id'), $token, $request->validated('items'), $request->validated('customer_name'));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['order' => 'Pesanan belum berhasil disimpan. Silakan coba lagi.']);
        }

        return to_route('admin.orders.index')->with('success', 'Pesanan berhasil dibuat. Silakan konfirmasi pesanan.');
    }
}
