<p class="small text-secondary">{{ $orders->total() }} pesanan</p>
<div class="row g-3">
    @forelse ($orders as $order)
        <div class="col-12 col-md-6 col-xxl-4">
            <article class="card cashier-order-card h-100">
                <div class="card-body d-flex flex-column">
                    <header class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div class="cashier-order-heading">
                            <h2 class="h4 fw-bold mb-1">Meja {{ str_pad((string) $order->table?->table_no, 2, '0', STR_PAD_LEFT) }}</h2>
                            <p class="fw-semibold text-break mb-1">{{ $order->customer_name ?: 'Nama belum tersedia' }}</p>
                            <p class="small text-secondary mb-0">{{ $order->created_at?->copy()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
                        </div>
                        <span class="cashier-item-count small text-nowrap">{{ $order->item_quantity ?? 0 }} item</span>
                    </header>
                    <div class="cashier-order-total d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><span class="small text-secondary">Total</span><strong>Rp{{ number_format((float) $order->total, str_ends_with($order->total, '.00') ? 0 : 2, ',', '.') }}</strong></div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge {{ ['pending' => 'text-bg-warning', 'confirmed' => 'text-bg-primary', 'processing' => 'text-bg-info', 'completed' => 'text-bg-success'][$order->order_status] ?? 'text-bg-secondary' }}">{{ ucfirst($order->order_status) }}</span>
                        <span class="badge {{ $order->payment_status === 'paid' ? 'text-bg-success' : 'text-bg-danger' }}">{{ strtoupper($order->payment_status) }}</span>
                    </div>
                    <p class="small text-secondary">{{ ($order->payment_status === 'unpaid' ? App\Models\Order::CUSTOMER_PAYMENT_TYPES : App\Models\Order::PAYMENT_TYPES)[$order->payment_type] ?? $order->payment_type ?? 'Belum ada pembayaran' }}</p>
                    <div class="cashier-order-actions mt-auto pt-2">
                        @if ($next = App\Models\Order::STATUS_FLOW[$order->order_status] ?? null)
                            <form method="POST" action="{{ route('admin.orders.status', $order) }}" data-dashboard-action class="mb-2">
                                @csrf @method('PATCH')
                                <input type="hidden" name="order_status" value="{{ $next }}">
                                <button type="submit" class="btn btn-primary w-100 py-2">{{ ['confirmed' => 'Konfirmasi Pesanan', 'processing' => 'Mulai Proses', 'completed' => 'Selesaikan Pesanan'][$next] }}</button>
                            </form>
                        @endif
                        @if ($order->payment_status === 'unpaid')
                            <button type="button" class="btn btn-success w-100 py-2 mb-2" data-bs-toggle="modal" data-bs-target="#dashboard-payment" data-payment-url="{{ route('admin.orders.payment', $order) }}" data-customer="{{ $order->customer_name ?: 'Nama belum tersedia' }}" data-table="{{ str_pad((string) $order->table?->table_no, 2, '0', STR_PAD_LEFT) }}" data-total="{{ number_format((float) $order->total, 2, ',', '.') }}">Konfirmasi Pembayaran</button>
                        @endif
                        <a class="btn btn-outline-primary w-100" href="{{ route('admin.orders.show', $order) }}">Lihat Detail</a>
                        @if($order->payment_status == 'paid')
                            <a class="btn btn-outline-secondary w-100 mt-2" href="{{ route('admin.orders.receipt', $order) }}">Lihat Struk</a>
                        @endif
                    </div>
                </div>
            </article>
        </div>
    @empty
        <div class="col-12"><p class="card card-body text-center">Tidak ada pesanan yang sesuai filter.</p></div>
    @endforelse
</div>
@if ($orders->hasPages())<div class="mt-4">{{ $orders->links('pagination::bootstrap-5') }}</div>@endif
