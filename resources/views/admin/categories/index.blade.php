@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Categories <span class="badge text-bg-light border fs-6 align-middle">{{ $categories->total() }}</span></h1>
            <p class="text-secondary mb-0">Kelola kategori produk Warmindo.</p>
        </div>
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">+ Tambah Category</a>
    </div>
    @include('admin.partials.filters', [
        'indexRoute' => 'admin.categories.index',
        'searchPlaceholder' => 'Cari nama kategori',
        'showStatus' => true,
    ])
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Daftar Category Warmindo</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="ps-4">No</th>
                        <th scope="col">Category Name</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td class="ps-4">{{ $categories->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold text-break">{{ $category->category_name }}</td>
                            <td>
                                @if ($category->status === 'active')
                                    <span class="badge rounded-pill text-bg-success">Active</span>
                                @elseif ($category->status === 'inactive')
                                    <span class="badge rounded-pill text-bg-secondary">Inactive</span>
                                @else
                                    <span class="badge rounded-pill text-bg-light border">Belum diatur</span>
                                @endif
                            </td>
                            <td class="pe-4">
                                <div class="d-flex justify-content-end flex-wrap gap-2">
                                    <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-primary" aria-label="Edit {{ $category->category_name }}">Edit</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-record" data-delete-url="{{ route('admin.categories.destroy', $category) }}" data-record-name="{{ $category->category_name }}" aria-label="Hapus {{ $category->category_name }}">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-5">
                            @if (request()->filled('search') || request()->filled('status'))
                                <h2 class="h5">Tidak ada hasil yang sesuai</h2>
                                <p class="text-secondary mb-0">Ubah pencarian atau tekan Reset untuk melihat semua data.</p>
                            @else
                                <h2 class="h5">Belum ada kategori</h2>
                                <a href="{{ route('admin.categories.create') }}" class="btn btn-primary mt-2">Tambah Category</a>
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($categories->hasPages())
            <div class="card-body border-top pb-0">{{ $categories->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
    @include('admin.partials.delete-modal', ['recordLabel' => 'Category'])
@endsection
