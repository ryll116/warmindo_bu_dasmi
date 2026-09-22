@extends('layouts.admin')
@section('title', 'Struk Transaksi')
@push('styles')
    <link href="{{ asset('css/receipt.css') }}" rel="stylesheet">
@endpush
@section('content')
    <div class="receipt-actions d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline-secondary">Kembali ke Detail</a>
        <button type="button" class="btn btn-primary" id="print-receipt">Print Struk</button>
    </div>
    <article class="receipt" aria-label="Struk transaksi">
        <header class="receipt-heading">
            <h1>Warmindo Bu Dasmi</h1>
            <p>Struk Transaksi</p>
        </header>
        <dl class="receipt-details">
            <dt>Waktu order</dt><dd>{{ $order->created_at?->copy()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</dd>
            <dt>Customer</dt><dd>{{ $order->customer_name ?: 'Nama belum tersedia' }}</dd>
            <dt>Meja</dt><dd>{{ $order->table ? str_pad((string) $order->table->table_no, 2, '0', STR_PAD_LEFT) : 'Tidak tersedia' }}</dd>
        </dl>
        <div class="receipt-items">
            @foreach ($order->items as $item)
                <section class="receipt-item">
                    <h2>{{ $item->product_name }}</h2>
                    <div class="receipt-line">
                        <span>{{ $item->qty }} × Rp{{ number_format((float) $item->price, str_ends_with($item->price, '.00') ? 0 : 2, ',', '.') }}</span>
                        <strong>Rp{{ number_format((float) $item->subtotal, str_ends_with($item->subtotal, '.00') ? 0 : 2, ',', '.') }}</strong>
                    </div>
                    @if ($item->notes)<p class="receipt-note">Catatan: {{ $item->notes }}</p>@endif
                </section>
            @endforeach
        </div>
        <div class="receipt-line receipt-total"><strong>Total</strong><strong>Rp{{ number_format((float) $order->total, str_ends_with($order->total, '.00') ? 0 : 2, ',', '.') }}</strong></div>
        <dl class="receipt-details">
            <dt>Metode</dt><dd>{{ App\Models\Order::PAYMENT_TYPES[$order->payment_type] ?? $order->payment_type ?? 'Belum tercatat' }}</dd>
            <dt>Status</dt><dd><strong>{{ ['paid' => 'LUNAS', 'unpaid' => 'BELUM DIBAYAR'][$order->payment_status] ?? strtoupper($order->payment_status) }}</strong></dd>
            @if ($order->payment_time)
                <dt>Dibayar</dt><dd>{{ Carbon\CarbonImmutable::parse($order->payment_time, config('app.timezone'))->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</dd>
            @endif
        </dl>
    </article>
@endsection
@push('scripts')
    <script src="{{ asset('js/receipt.js') }}" defer></script>
@endpush
