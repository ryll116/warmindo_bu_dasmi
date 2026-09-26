@extends('layouts.customer')

@section('title', 'Menu · Meja '.str_pad($table->table_no, 2, '0', STR_PAD_LEFT))

@section('content')
    <a href="#product-list" class="visually-hidden-focusable skip-menu">Langsung ke menu</a>
    <header class="menu-header">
        <div class="menu-container">
            <div class="menu-brand-row">
                <div>
                    <div class="brand">Baji Minasa<span>.</span></div>
                    <p class="brand-note">Coto Makassar dan Sop Konro.</p>
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
                        <h1 id="menu-title">{{ $categoryId === null ? 'Pesan Apa Hari Ini?' : ($categories->firstWhere('id', $categoryId)?->category_name ?? 'Pilihan menu') }}</h1>
                    </div>
                    <span class="menu-result-count">{{ $products->count() }} menu</span>
                </div>
                @if ($search !== '')
                    <p class="search-summary">Hasil untuk “{{ $search }}” <a href="{{ route('customer.menu', array_filter(['qr_token' => $table->qr_token, 'category' => $categoryId], fn ($value) => $value !== null)) }}">Hapus pencarian</a></p>
                @endif
                @if ($categoryId === null && $products->isNotEmpty())
                    @foreach ($categories as $category)
                        @if ($productsByCategory->has($category->id))
                            <section class="menu-category-section" aria-labelledby="menu-category-{{ $category->id }}">
                                <h2 id="menu-category-{{ $category->id }}" class="menu-category-heading">{{ $category->category_name }}</h2>
                                @include('customer._product-grid', ['products' => $productsByCategory->get($category->id)])
                            </section>
                        @endif
                    @endforeach
                @else
                    @include('customer._product-grid')
                @endif
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
