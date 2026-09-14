<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\MenuRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function __invoke(MenuRequest $request, string $qr_token): View|Response
    {
        $table = Table::where('qr_token', $qr_token)->where('is_available', true)->first();

        if (! $table) {
            return response()->view('customer.unavailable', [], 404);
        }

        $search = trim($request->validated('search') ?? '');
        $categoryId = $request->validated('category');
        $availableProducts = Product::where('is_available', true)
            ->whereHas('category', fn (Builder $query) => $query->where(fn (Builder $query): Builder => $query->where('status', 'active')->orWhereNull('status')));

        $products = (clone $availableProducts)
            ->when($search !== '', fn (Builder $query) => $query->whereLike('product_name', '%'.$search.'%'))
            ->when($categoryId !== null, fn (Builder $query) => $query->where('category_id', $categoryId))
            ->orderBy('product_name')->orderBy('id')
            ->get(['id', 'category_id', 'product_name', 'price']);

        $categories = Category::where(fn (Builder $query): Builder => $query->where('status', 'active')->orWhereNull('status'))
            ->whereHas('products', fn (Builder $query) => $query->where('is_available', true))
            ->orderBy('category_name')->get(['id', 'category_name']);

        $catalog = (clone $availableProducts)->get(['id', 'product_name', 'price'])
            ->mapWithKeys(fn (Product $product) => [
                $product->id => [
                    'name' => $product->product_name,
                    'price' => $product->price,
                ],
            ]);

        return view('customer.menu', compact('table', 'products', 'categories', 'catalog', 'search', 'categoryId'));
    }
}
