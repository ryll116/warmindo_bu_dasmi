@extends('layouts.customer')

@section('title', 'Menu · Meja '.str_pad($table->table_no, 2, '0', STR_PAD_LEFT))

@section('content')
    <a href="#product-list" class="visually-hidden-focusable skip-menu">Langsung ke menu</a>
    <header class="menu-header">
        <div class="menu-container">
            <div class="menu-brand-row">
                <div>
                    <div class="brand">warmindo<span>.</span></div>
                    <p class="brand-note">Makan enak, santai sejenak.</p>
                </div>
                <span class="table-label">Meja {{ str_pad($table->table_no, 2, '0', STR_PAD_LEFT) }}</span>
            </div>
            <form method="GET" action="{{ route('customer.menu', $table->qr_token) }}" class="menu-search" role="search">
                @if ($categoryId !== null)
                    <input type="hidden" name="category" value="{{ $categoryId }}">
                @endif
                <label for="menu-search" class="visually-hidden">Cari makanan atau minuman</label>
                <input type="search" id="menu-search" name="search" value="{{ $search }}" maxlength="255" placeholder="Cari makanan atau minuman..." autocomplete="off" class="form-control" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="menu-suggestions">
                <button type="submit" class="btn search-button" aria-label="Cari produk">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>
                </button>
                <div id="menu-suggestions" class="menu-suggestions" role="listbox" aria-label="Saran menu" data-placeholder="{{ asset('images/product-placeholder.svg') }}" hidden></div>
            </form>
        </div>
    </header>
    <main class="menu-container menu-main">
        @if ($errors->any())
            <div class="alert alert-warning small" role="alert">{{ $errors->first() }}</div>
        @endif
        <noscript><p class="alert alert-warning small">Aktifkan JavaScript untuk menggunakan keranjang. Anda tetap dapat melihat dan mencari menu.</p></noscript>
        <div class="menu-columns">
            <aside class="category-sidebar" aria-label="Kategori menu">
                <p class="category-label">KATEGORI</p>
                <nav class="category-links">
                    <a class="category-link {{ $categoryId === null ? 'is-active' : '' }}" href="{{ route('customer.menu', array_filter(['qr_token' => $table->qr_token, 'search' => $search], fn ($value) => $value !== '')) }}" @if ($categoryId === null) aria-current="page" @endif>Semua</a>
                    @foreach ($categories as $category)
                        <a class="category-link {{ (string) $categoryId === (string) $category->id ? 'is-active' : '' }}" href="{{ route('customer.menu', array_filter(['qr_token' => $table->qr_token, 'category' => $category->id, 'search' => $search], fn ($value) => $value !== '')) }}" @if ((string) $categoryId === (string) $category->id) aria-current="page" @endif>{{ $category->category_name }}</a>
                    @endforeach
                </nav>
            </aside>
            <section id="product-list" class="menu-products" aria-labelledby="menu-title">
                <div class="menu-section-heading">
                    <div>
                        <span class="menu-eyebrow">DIBUAT UNTUK SELERA ANDA</span>
                        <h1 id="menu-title">{{ $categoryId === null ? 'Mau makan apa?' : ($categories->firstWhere('id', $categoryId)?->category_name ?? 'Pilihan menu') }}</h1>
                    </div>
                    <span class="menu-result-count">{{ $products->count() }} menu</span>
                </div>
                @if ($search !== '')
                    <p class="search-summary">Hasil untuk “{{ $search }}” <a href="{{ route('customer.menu', array_filter(['qr_token' => $table->qr_token, 'category' => $categoryId], fn ($value) => $value !== null)) }}">Hapus pencarian</a></p>
                @endif
                <div class="product-grid">
                    @forelse ($products as $product)
                        <article class="menu-product" data-product-id="{{ $product->id }}">
                            <div class="product-image">
                                <img src="{{ asset('images/product-placeholder.svg') }}" alt="Ilustrasi menu, foto belum tersedia" width="240" height="240" loading="lazy" decoding="async">
                            </div>
                            <div class="product-content">
                                <h2 class="product-title">{{ $product->product_name }}</h2>
                                <p class="product-price">Rp {{ number_format((float) $product->price, str_ends_with($product->price, '.00') ? 0 : 2, ',', '.') }}</p>
                                <p class="portion-label">per porsi</p>
                                <div class="product-quantity" data-quantity-control="{{ $product->id }}">
                                    <button type="button" class="quantity-button decrease" data-cart-action="decrease" data-id="{{ $product->id }}" aria-label="Kurangi {{ $product->product_name }}" hidden disabled>−</button>
                                    <output class="quantity-value" aria-label="Jumlah {{ $product->product_name }}" hidden>0</output>
                                    <button type="button" class="quantity-button increase" data-cart-action="increase" data-id="{{ $product->id }}" aria-label="Tambah {{ $product->product_name }}" disabled>+</button>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="menu-empty">
                            <h2 class="h6">Belum ada menu yang cocok</h2>
                            <p>Coba kata lain atau pilih kategori Semua.</p>
                            <a href="{{ route('customer.menu', $table->qr_token) }}" class="btn btn-outline-dark btn-sm">Lihat semua menu</a>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
        <p id="cart-feedback" class="cart-feedback" role="status" aria-live="polite" aria-atomic="true"></p>
    </main>

    <div id="cart-bar" class="cart-bar" hidden>
        <div class="cart-summary"><span id="cart-count">0 item</span><strong id="cart-total">Rp 0</strong></div>
        <button type="button" id="open-cart" class="btn cart-open" data-bs-toggle="offcanvas" data-bs-target="#cart-drawer" aria-controls="cart-drawer">Lihat Keranjang <span aria-hidden="true">→</span></button>
    </div>

    <section class="offcanvas offcanvas-bottom menu-cart" tabindex="-1" id="cart-drawer" aria-labelledby="cart-title">
        <div class="offcanvas-header">
            <div>
                <h2 id="cart-title">Keranjang Anda</h2>
                <p>Meja {{ str_pad($table->table_no, 2, '0', STR_PAD_LEFT) }} · Pilihan untuk dinikmati</p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup keranjang"></button>
        </div>
        <div class="offcanvas-body">
            <p id="cart-empty" class="text-secondary">Keranjang masih kosong. Yuk, pilih menu favorit Anda.</p>
            <div id="cart-items"></div>
        </div>
        <div class="cart-footer" id="cart-footer" hidden>
            <div class="cart-grand-total"><span>Total</span><strong id="drawer-total">Rp 0</strong></div>
            <form id="checkout-start" method="POST" action="{{ route('customer.checkout.review', $table->qr_token) }}" class="mt-3">
                @csrf
                <div id="checkout-items" hidden></div>
                <button type="submit" class="btn btn-dark w-100">Lanjutkan Pesanan</button>
            </form>
            <button type="button" class="btn btn-outline-dark w-100 mt-3" data-bs-dismiss="offcanvas">Tambah menu lainnya</button>
        </div>
    </section>
@endsection

@push('scripts')
    <script>window.warmindoMenu = {{ Illuminate\Support\Js::from(['token' => $table->qr_token, 'catalog' => $catalog]) }};</script>
    <script src="{{ asset('js/customer-menu.js') }}" defer></script>
    <script src="{{ asset('js/customer-search.js') }}" defer></script>
    <script src="{{ asset('js/customer-checkout.js') }}" defer></script>
@endpush
