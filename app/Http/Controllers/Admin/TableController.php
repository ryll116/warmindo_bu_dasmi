<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListRequest;
use App\Http\Requests\Admin\TableRequest;
use App\Models\Table;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TableController extends Controller
{
    public function index(ListRequest $request): View
    {
        $search = trim($request->validated('search') ?? '');
        $status = $request->validated('status');
        $query = Table::query();

        if ($search !== '') {
            $query->whereLike('table_no', '%'.$search.'%');
        }

        if ($status !== null) {
            $query->where('is_available', $status === 'active');
        }

        return view('admin.tables.index', [
            'tables' => $query->orderBy('table_no')->orderBy('id')->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.tables.create', ['table' => new Table(['is_available' => true])]);
    }

    public function store(TableRequest $request): RedirectResponse
    {
        Table::create($request->validated());

        return to_route('admin.tables.index')->with('success', 'Meja berhasil ditambahkan.');
    }

    public function edit(Table $table): View
    {
        return view('admin.tables.edit', compact('table'));
    }

    public function update(TableRequest $request, Table $table): RedirectResponse
    {
        $table->update($request->validated());

        return to_route('admin.tables.index')->with('success', 'Meja berhasil diperbarui.');
    }

    public function destroy(Table $table): RedirectResponse
    {
        if ($table->orders()->exists()) {
            return $this->cannotDelete();
        }

        try {
            $table->delete();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1451) {
                throw $exception;
            }

            return $this->cannotDelete();
        }

        return to_route('admin.tables.index')->with('success', 'Meja berhasil dihapus.');
    }

    private function cannotDelete(): RedirectResponse
    {
        return to_route('admin.tables.index')->with('error', 'Meja sudah digunakan dalam transaksi dan tidak dapat dihapus. Ubah status menjadi Inactive.');
    }
}
