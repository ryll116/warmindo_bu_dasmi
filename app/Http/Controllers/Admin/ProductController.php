<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductIndexRequest;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Requests\Admin\UpdateProductAvailabilityRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Resto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request): View
    {
        $search = trim($request->validated('search') ?? '');
        $category = $request->validated('category');
        $status = $request->validated('status');
        $query = Product::with(['category', 'resto']);

        if ($request->validated('resto') !== null) {
            $query->where('resto_id', $request->validated('resto'));
        }

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('product_code', '%'.$search.'%')
                    ->orWhereLike('product_name', '%'.$search.'%')
                    ->orWhereHas('category', function (Builder $query) use ($search): void {
                        $query->whereLike('category_name', '%'.$search.'%');
                    });
            });
        }

        if ($category !== null) {
            $query->where('category_id', $category);
        }

        if ($status !== null) {
            $query->where('is_available', $status === 'active');
        }

        return view('admin.products.index', [
            'products' => $query->latest('id')->paginate(15)->withQueryString(),
            'categories' => Category::orderBy('category_name')->get(),
            'restos' => Resto::orderBy('resto_name')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product(['is_available' => true]),
            'categories' => Category::orderBy('category_name')->get(),
            'restos' => Resto::orderBy('resto_name')->orderBy('id')->get(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return to_route('admin.products.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product,
            'categories' => Category::orderBy('category_name')->get(),
            'restos' => Resto::orderBy('resto_name')->orderBy('id')->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return to_route('admin.products.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function updateAvailability(UpdateProductAvailabilityRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return to_route('admin.products.index')->with('success', 'Ketersediaan produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->orderItems()->exists()) {
            return $this->cannotDelete();
        }

        try {
            $product->delete();
        } catch (QueryException $exception) {
            // A transaction may reference the product after the existence check.
            if (($exception->errorInfo[1] ?? null) !== 1451) {
                throw $exception;
            }

            return $this->cannotDelete();
        }

        return to_route('admin.products.index')->with('success', 'Produk berhasil dihapus.');
    }

    private function cannotDelete(): RedirectResponse
    {
        return to_route('admin.products.index')->with('error', 'Produk sudah digunakan dalam transaksi dan tidak dapat dihapus. Ubah status menjadi Unavailable.');
    }
}
