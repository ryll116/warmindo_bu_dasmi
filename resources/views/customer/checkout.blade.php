@extends('layouts.customer')

@section('title', 'Review Pesanan')

@section('content')
    <main class="menu-container py-4" style="max-width: 640px">
        <a href="{{ route('customer.menu', $table->qr_token) }}" class="text-secondary">Kembali ke Menu</a>
        <h1 class="h4 mt-4">Review Pesanan</h1>
        <p class="text-secondary">Meja {{ $table->table_no }}</p>
        @if ($errors->any())
            <div class="alert alert-warning" role="alert">{{ $errors->first() }}</div>
        @endif
        @foreach ($lines as $line)
            <article class="cart-item">
                <div>
                    <h3>{{ $line['product_name'] }}</h3>
                    <p>Rp{{ number_format((float) $line['price'], str_ends_with($line['price'], '.00') ? 0 : 2, ',', '.') }} × {{ $line['quantity'] }}</p>
                </div>
                <strong class="cart-line-total">Rp{{ number_format((float) $line['subtotal'], str_ends_with($line['subtotal'], '.00') ? 0 : 2, ',', '.') }}</strong>
            </article>
        @endforeach
        <div class="cart-grand-total my-4"><span>Total</span><strong>Rp{{ number_format((float) $total, str_ends_with($total, '.00') ? 0 : 2, ',', '.') }}</strong></div>
        <form id="checkout-submit" method="POST" action="{{ route('customer.checkout.store', $table->qr_token) }}">
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $checkout_token }}">
            <label for="customer-name" class="form-label">Nama Pemesan</label>
            <input id="customer-name" name="customer_name" class="form-control mb-3" required maxlength="100" autocomplete="name" value="{{ old('customer_name') }}">
            <label for="order-notes" class="form-label">Catatan pesanan (opsional)</label>
            <textarea id="order-notes" name="notes" class="form-control" rows="3" maxlength="1000" placeholder="Contoh: Indomie jangan pedas">{{ old('notes') }}</textarea>
            <fieldset class="my-4">
                <legend class="h6">Metode Pembayaran</legend>
                <label class="d-flex gap-3 border rounded p-3 mb-2" for="payment-cash">
                    <input id="payment-cash" class="form-check-input flex-shrink-0" type="radio" name="payment_type" value="cash" required @checked(old('payment_type') === 'cash')>
                    <span><strong class="d-block">Bayar di Kasir</strong><span class="small text-secondary">Bayar langsung di kasir setelah membuat pesanan.</span></span>
                </label>
                <label class="d-flex gap-3 border rounded p-3" for="payment-qris">
                    <input id="payment-qris" class="form-check-input flex-shrink-0" type="radio" name="payment_type" value="qris_manual" required @checked(old('payment_type') === 'qris_manual')>
                    <span><strong class="d-block">Bayar dari HP (QRIS)</strong><span class="small text-secondary">Bayar menggunakan QRIS dari HP Anda.</span><span class="d-block small text-secondary">Demo: QRIS belum terhubung.</span></span>
                </label>
                @error('payment_type')<p class="text-danger small mt-2" role="alert">{{ $message }}</p>@enderror
            </fieldset>
            <button type="submit" class="btn btn-dark w-100 py-3">Pesan Sekarang</button>
        </form>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset('js/customer-checkout.js') }}" defer></script>
@endpush
