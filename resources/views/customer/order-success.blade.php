@extends('layouts.customer')

@section('title', 'Pesanan Berhasil')

@section('content')
    <main id="checkout-success" class="menu-container py-5" style="max-width: 640px" data-token="{{ $table->qr_token }}" data-clear-cart="{{ $clearCart ? 'true' : 'false' }}">
        <h1 class="h4">Pesanan Berhasil</h1>
        <p>Pesanan berhasil dibuat.</p>
        @if ($order->payment_status === 'unpaid' && $order->payment_type !== 'qris_manual')
            <p class="text-secondary">Silakan melakukan pembayaran di kasir.</p>
        @endif
        <dl class="mt-4">
            <dt>Referensi pesanan</dt><dd class="text-break">{{ $order->id }}</dd>
            <dt>Meja</dt><dd>{{ $table->table_no }}</dd>
            <dt>Nama Pemesan</dt><dd>{{ $order->customer_name }}</dd>
            <dt>Metode pembayaran</dt><dd>{{ App\Models\Order::CUSTOMER_PAYMENT_TYPES[$order->payment_type] ?? 'Bayar di Kasir' }}</dd>
            <dt>Total</dt><dd>Rp{{ number_format((float) $order->total, str_ends_with($order->total, '.00') ? 0 : 2, ',', '.') }}</dd>
            <dt>Status pesanan</dt><dd>{{ $order->order_status }}</dd>
            <dt>Status pembayaran</dt><dd>{{ $order->payment_status }}</dd>
        </dl>
        @if ($order->payment_type === 'qris_manual' && $order->payment_status === 'unpaid')
            @include('customer._qris-demo')
        @endif
        <p id="checkout-storage-message" class="alert alert-warning" hidden>Pesanan berhasil, tetapi penyimpanan browser tidak dapat dibersihkan. Kosongkan keranjang sebelum membuat pesanan baru.</p>
        <a href="{{ route('customer.menu', $table->qr_token) }}" class="btn btn-dark w-100 mt-3">Kembali ke Menu</a>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset('js/customer-checkout.js') }}" defer></script>
@endpush
