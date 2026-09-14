@extends('layouts.admin')

@section('title', 'Products')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Products <span class="badge text-bg-light border fs-6 align-middle">{{ $products->total() }}</span></h1>
            <p class="text-secondary mb-0">Kelola menu, harga, dan ketersediaan produk Warmindo.</p>
        </div>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">+ Tambah Product</a>
    </div>
    @include('admin.partials.filters', [
        'indexRoute' => 'admin.products.index',
        'searchPlaceholder' => 'Kode, nama produk, atau nama kategori',
        'categoryOptions' => $categories,
        'statusLabel' => 'Availability',
        'activeLabel' => 'Available',
        'inactiveLabel' => 'Unavailable',
    ])
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Daftar produk Warmindo</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="ps-4">Product</th>
                        <th scope="col">Category</th>
                        <th scope="col" class="text-end">Price</th>
                        <th scope="col">Availability</th>
                        <th scope="col" class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td class="ps-4 product-name">
                                <div class="fw-semibold">{{ $product->product_name }}</div>
                                <div class="small text-secondary">{{ $product->product_code }}</div>
                            </td>
                            <td>{{ $product->category?->category_name ?? '—' }}</td>
                            <td class="text-end text-nowrap">Rp {{ number_format((float) $product->price, 2, ',', '.') }}</td>
                            <td><span class="badge rounded-pill {{ $product->is_available ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $product->is_available ? 'Available' : 'Unavailable' }}</span></td>
                            <td class="pe-4">
                                <div class="d-flex justify-content-end flex-wrap gap-2">
                                    <form method="POST" action="{{ route('admin.products.availability', $product) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="is_available" value="{{ $product->is_available ? 0 : 1 }}">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit" aria-label="{{ $product->is_available ? 'Nonaktifkan' : 'Aktifkan' }} {{ $product->product_name }}">{{ $product->is_available ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary" aria-label="Edit {{ $product->product_name }}">Edit</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-product" data-delete-url="{{ route('admin.products.destroy', $product) }}" data-product-name="{{ $product->product_name }}" aria-label="Hapus {{ $product->product_name }}">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5">
                            @if (request()->filled('search') || request()->filled('category') || request()->filled('status'))
                                <h2 class="h5">Tidak ada hasil yang sesuai</h2>
                                <p class="text-secondary mb-0">Ubah pencarian atau tekan Reset untuk melihat semua data.</p>
                            @else
                                <h2 class="h5">Belum ada produk</h2>
                                <p class="text-secondary">Tambahkan produk pertama untuk mulai mengelola menu.</p>
                                <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Tambah Product</a>
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="card-body border-top pb-0">{{ $products->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
    <div class="modal fade" id="delete-product" tabindex="-1" aria-labelledby="delete-product-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="delete-product-title">Hapus product?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">Produk <strong id="delete-product-name"></strong> akan dihapus. Tindakan ini tidak dapat dibatalkan.</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" id="delete-product-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" disabled>Ya, hapus product</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('delete-product').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            const form = document.getElementById('delete-product-form');
            form.action = button.dataset.deleteUrl;
            form.querySelector('button').disabled = false;
            document.getElementById('delete-product-name').textContent = button.dataset.productName;
        });
    </script>
@endpush
