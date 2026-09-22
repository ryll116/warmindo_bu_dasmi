<p class="small text-secondary">{{ $orders->total() }} pesanan</p>
<div class="row g-3">
    @forelse ($orders as $order)
        <div class="col-12 col-md-6 col-xl-4">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Meja {{ str_pad((string) $order->table?->table_no, 2, '0', STR_PAD_LEFT) }}</h2>
                    <p class="fw-semibold text-break mb-1">{{ $order->customer_name ?: 'Nama belum tersedia' }}</p>
                    <p class="small text-secondary">{{ $order->created_at?->copy()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
                    <div class="d-flex justify-content-between gap-2 mb-3"><span>{{ $order->item_quantity ?? 0 }} item</span><strong>Rp{{ number_format((float) $order->total, str_ends_with($order->total, '.00') ? 0 : 2, ',', '.') }}</strong></div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge {{ ['pending' => 'text-bg-warning', 'confirmed' => 'text-bg-info', 'processing' => 'text-bg-primary', 'completed' => 'text-bg-secondary'][$order->order_status] ?? 'text-bg-secondary' }}">{{ ucfirst($order->order_status) }}</span>
                        <span class="badge {{ $order->payment_status === 'paid' ? 'text-bg-success' : 'text-bg-danger' }}">{{ strtoupper($order->payment_status) }}</span>
                    </div>
                    <p class="small text-secondary">{{ ($order->payment_status === 'unpaid' ? App\Models\Order::CUSTOMER_PAYMENT_TYPES : App\Models\Order::PAYMENT_TYPES)[$order->payment_type] ?? $order->payment_type ?? 'Belum ada pembayaran' }}</p>
                    <a class="btn btn-outline-primary w-100" href="{{ route('admin.orders.show', $order) }}">Lihat Detail</a>
                    @if($order->payment_status == 'paid')
                        <a class="btn btn-outline-secondary w-100 mt-2" href="{{ route('admin.orders.receipt', $order) }}">Lihat Struk</a>
                    @endif
                </div>
            </article>
        </div>
    @empty
        <div class="col-12"><p class="card card-body text-center">Tidak ada pesanan yang sesuai filter.</p></div>
    @endforelse
</div>
@if ($orders->hasPages())<div class="mt-4">{{ $orders->links('pagination::bootstrap-5') }}</div>@endif
