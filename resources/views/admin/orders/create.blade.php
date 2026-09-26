@extends('layouts.admin')
@section('title', 'Buat Pesanan')
@section('content')
    <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary mb-3">Kembali ke Kasir</a>
    <h1 class="h3 fw-bold">Buat Pesanan</h1>
    <p class="text-secondary">Pilih meja dan menu. Pembayaran dicatat setelah pesanan dibuat.</p>
    <noscript><div class="alert alert-warning">Aktifkan JavaScript untuk memilih menu dan membuat pesanan.</div></noscript>
    <form method="POST" action="{{ route('admin.orders.store') }}" id="manual-order-form" class="manual-order-form" data-cashier-action>
        @csrf
        <input type="hidden" name="checkout_token" value="{{ $token }}">
        <div class="card card-body cashier-filters mb-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="manual-table" class="form-label">Meja</label>
                    <select id="manual-table" name="table_id" class="form-select" required>
                        <option value="">Pilih meja</option>
                        @foreach ($tables as $table)<option value="{{ $table->id }}" @selected(is_scalar(old('table_id')) && (string) old('table_id') === (string) $table->id)>Meja {{ str_pad((string) $table->table_no, 2, '0', STR_PAD_LEFT) }}</option>@endforeach
                    </select>
                    @if ($tables->isEmpty())<p class="small text-danger mt-2 mb-0">Belum ada meja yang tersedia.</p>@endif
                </div>
                <div class="col-md-6"><label for="manual-customer" class="form-label">Nama Customer</label><input id="manual-customer" name="customer_name" value="{{ is_string(old('customer_name')) ? old('customer_name') : '' }}" class="form-control" required maxlength="100" autocomplete="off"></div>
            </div>
        </div>
        <div id="manual-order-feedback" class="alert alert-warning d-none" role="alert"></div>
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card card-body cashier-filters mb-3">
                    <label for="manual-search" class="form-label">Cari Menu</label><input id="manual-search" type="search" class="form-control mb-3" placeholder="Nama menu atau resto">
                    <label for="manual-category" class="form-label">Kategori</label>
                    <select id="manual-category" class="form-select"><option value="">Semua kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->category_name }}</option>@endforeach</select>
                </div>
                <div class="d-grid gap-2" id="manual-products">
                    @foreach ($products as $product)
                        <article class="card card-body cashier-order-card" data-manual-product data-product-id="{{ $product->id }}" data-name="{{ $product->product_name }}" data-resto="{{ $product->resto?->resto_name }}" data-price="{{ $product->effectivePrice() }}" data-category="{{ $product->category_id }}">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div class="manual-product-name">
                                    <h2 class="h6 fw-semibold mb-1">{{ $product->product_name }}</h2>
                                    <p class="small text-secondary mb-1">{{ $product->resto?->resto_name ?: 'Resto belum ditentukan' }}</p>
                                    @if ($product->hasDiscount())
                                        <span class="badge text-bg-warning">PROMO {{ rtrim(rtrim($product->disc, '0'), '.') }}%</span>
                                        <del class="d-block small text-secondary">Rp{{ number_format((float) $product->price, 2, ',', '.') }}</del>
                                    @endif
                                    <strong>Rp{{ number_format((float) $product->effectivePrice(), 2, ',', '.') }}</strong>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <button type="button" class="btn btn-outline-secondary" data-quantity-change="-1" aria-label="Kurangi {{ $product->product_name }}">−</button>
                                    <label for="manual-qty-{{ $product->id }}" class="visually-hidden">Jumlah {{ $product->product_name }} {{ $product->resto?->resto_name }}</label>
                                    <input id="manual-qty-{{ $product->id }}" type="number" min="0" max="99" step="1" value="0" inputmode="numeric" class="form-control manual-quantity">
                                    <button type="button" class="btn btn-outline-primary" data-quantity-change="1" aria-label="Tambah {{ $product->product_name }}">+</button>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                <p id="manual-empty" class="text-secondary mt-3" @if ($products->isNotEmpty()) hidden @endif>Tidak ada menu yang tersedia sesuai pencarian.</p>
            </div>
            <div class="col-lg-5">
                <section class="card card-body cashier-order-card manual-summary" aria-labelledby="manual-summary-title">
                    <h2 class="h5 fw-bold" id="manual-summary-title">Pesanan</h2>
                    <div id="manual-summary-items"></div>
                    <p class="small text-secondary mb-0 mt-3">Harga dan ketersediaan menu diperiksa kembali saat pesanan dibuat.</p>
                </section>
            </div>
        </div>
        <div id="manual-item-inputs"></div>
        <div class="manual-submit-bar d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><span class="small text-secondary d-block">Total <span id="manual-item-count">0 item</span></span><strong id="manual-total" class="fs-5" aria-live="polite">Rp0</strong></div>
            <button type="submit" class="btn btn-primary px-4 py-2" disabled>Buat Pesanan</button>
        </div>
    </form>
    <script type="application/json" id="manual-old-items">@json(old('items', []))</script>
@endsection
@push('scripts')
    <script src="{{ asset('js/manual-order.js') }}" defer></script>
    <script src="{{ asset('js/cashier-orders.js') }}" defer></script>
@endpush
