<div class="product-grid">
    @forelse ($products as $product)
        <article class="menu-product" data-product-id="{{ $product->id }}">
            <div class="product-image">
                <img src="{{ asset('images/product-placeholder.svg') }}" alt="Ilustrasi menu, foto belum tersedia" width="240" height="240" loading="lazy" decoding="async">
            </div>
            <div class="product-content">
                <h2 class="product-title">{{ $product->product_name }}</h2>
                @if ($product->hasDiscount())
                    <span class="badge text-bg-warning mb-2">PROMO {{ rtrim(rtrim($product->disc, '0'), '.') }}%</span>
                    <div class="small text-secondary"><del>Rp {{ number_format((float) $product->price, str_ends_with($product->price, '.00') ? 0 : 2, ',', '.') }}</del></div>
                @endif
                @php($sellingPrice = $product->effectivePrice())
                <p class="product-price">Rp {{ number_format((float) $sellingPrice, str_ends_with($sellingPrice, '.00') ? 0 : 2, ',', '.') }}</p>
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
