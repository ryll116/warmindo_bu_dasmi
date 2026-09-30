<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductCodeRequest;
use App\Http\Requests\Admin\ProductIndexRequest;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Requests\Admin\UpdateProductAvailabilityRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Resto;
use App\ProductCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

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

    public function nextCode(ProductCodeRequest $request, ProductCodeGenerator $generator): JsonResponse
    {
        $category = Category::findOrFail($request->validated('category_id'));

        return response()->json(['product_code' => $generator->next($category)])->header('Cache-Control', 'no-store');
    }

    public function store(ProductRequest $request, ProductCodeGenerator $generator): RedirectResponse
    {
        $image = $this->storeImage($request);
        try {
            DB::transaction(function () use ($request, $generator, $image): void {
                $attributes = $request->safe()->except(['image', 'remove_image', 'img']);
                $category = Category::whereKey($attributes['category_id'])->lockForUpdate()->first();
                if (! $category) {
                    throw ValidationException::withMessages(['category_id' => 'Kategori tidak tersedia. Pilih kategori lain.']);
                }
                $attributes['product_code'] = $generator->next($category, true);
                $product = new Product($attributes);
                $product->img = $image;
                $product->save();
            }, 3);
        } catch (Throwable $exception) {
            $this->deleteImage($image);
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            report($exception);

            return back()->withInput()->withErrors(['image' => 'Produk belum berhasil disimpan. Silakan coba lagi dan pilih ulang foto.']);
        }

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
        $image = $this->storeImage($request);
        try {
            $oldImage = DB::transaction(function () use ($request, $product, $image): ?string {
                $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                $oldImage = null;
                $locked->fill($request->safe()->except(['image', 'remove_image', 'img']));
                if ($image !== null || $request->boolean('remove_image')) {
                    $oldImage = $locked->img;
                    $locked->img = $image;
                }
                $locked->save();

                return $oldImage;
            }, 3);
        } catch (Throwable $exception) {
            $this->deleteImage($image);
            report($exception);

            return back()->withInput()->withErrors(['image' => 'Perubahan belum berhasil disimpan. Foto lama tetap tersimpan. Silakan coba lagi dan pilih ulang foto.']);
        }
        $this->deleteImage($oldImage);

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
            if ($product->delete()) {
                $this->deleteImage($product->img);
            }
        } catch (QueryException $exception) {
            // A transaction may reference the product after the existence check.
            if (($exception->errorInfo[1] ?? null) !== 1451) {
                throw $exception;
            }

            return $this->cannotDelete();
        }

        return to_route('admin.products.index')->with('success', 'Produk berhasil dihapus.');
    }

    private function storeImage(ProductRequest $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }
        try {
            $path = $request->file('image')->store('products', 'public');
            if ($path !== false) {
                return $path;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        throw ValidationException::withMessages(['image' => 'Foto belum berhasil disimpan. Silakan coba lagi.']);
    }

    private function deleteImage(?string $path): void
    {
        if ($path === null || ! preg_match('/\Aproducts\/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)\z/', $path)) {
            return;
        }
        try {
            if (! Storage::disk('public')->delete($path)) {
                Log::warning('Foto produk belum dapat dihapus.', ['path' => $path]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function cannotDelete(): RedirectResponse
    {
        return to_route('admin.products.index')->with('error', 'Produk sudah digunakan dalam transaksi dan tidak dapat dihapus. Ubah status menjadi Unavailable.');
    }
}
