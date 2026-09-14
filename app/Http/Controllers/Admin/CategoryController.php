<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Requests\Admin\ListRequest;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(ListRequest $request): View
    {
        $search = trim($request->validated('search') ?? '');
        $status = $request->validated('status');
        $query = Category::query();

        if ($search !== '') {
            $query->whereLike('category_name', '%'.$search.'%');
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return view('admin.categories.index', [
            'categories' => $query->latest('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', ['category' => new Category(['status' => 'active'])]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return to_route('admin.categories.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return to_route('admin.categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return $this->cannotDelete();
        }

        try {
            $category->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1451) {
                throw $exception;
            }

            return $this->cannotDelete();
        }

        return to_route('admin.categories.index')->with('success', 'Kategori berhasil dihapus.');
    }

    private function cannotDelete(): RedirectResponse
    {
        return to_route('admin.categories.index')->with('error', 'Kategori masih digunakan oleh produk dan tidak dapat dihapus.');
    }
}
