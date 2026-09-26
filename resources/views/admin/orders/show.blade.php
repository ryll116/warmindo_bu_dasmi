@extends('layouts.admin')
@section('title', 'Detail Pesanan')
@section('content')
    <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary mb-3">Kembali ke Kasir</a>
    <a href="{{ route('admin.orders.receipt', $order) }}" class="btn btn-outline-primary mb-3">Lihat Struk</a>
    <h1 class="h3">Meja {{ str_pad((string) $order->table?->table_no, 2, '0', STR_PAD_LEFT) }}</h1>
    <p class="fw-semibold text-break">Nama Pemesan: {{ $order->customer_name ?: 'Nama belum tersedia' }}</p>
    <div class="card card-body border-0 shadow-sm mb-3">
        <dl class="row mb-0">
            <dt class="col-sm-4">Waktu order</dt><dd class="col-sm-8">{{ $order->created_at?->copy()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</dd>
            <dt class="col-sm-4">Order status</dt><dd class="col-sm-8"><span class="badge text-bg-primary">{{ ucfirst($order->order_status) }}</span></dd>
            <dt class="col-sm-4">Payment status</dt><dd class="col-sm-8"><span class="badge {{ $order->payment_status === 'paid' ? 'text-bg-success' : 'text-bg-danger' }}">{{ strtoupper($order->payment_status) }}</span></dd>
            <dt class="col-sm-4">Metode pembayaran</dt><dd class="col-sm-8">{{ ($order->payment_status === 'unpaid' ? App\Models\Order::CUSTOMER_PAYMENT_TYPES : App\Models\Order::PAYMENT_TYPES)[$order->payment_type] ?? $order->payment_type ?? '—' }}</dd>
            @if ($order->payment_time)<dt class="col-sm-4">Waktu pembayaran</dt><dd class="col-sm-8">{{ Carbon\CarbonImmutable::parse($order->payment_time, config('app.timezone'))->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</dd>@endif
        </dl>
    </div>
    <div class="card card-body border-0 shadow-sm mb-3">
        @foreach ($order->items as $item)
            <article class="border-bottom py-3">
                <h2 class="h6">{{ $item->product_name }}</h2>
                <div class="d-flex flex-wrap justify-content-between gap-2"><span>Rp{{ number_format((float) $item->price, str_ends_with($item->price, '.00') ? 0 : 2, ',', '.') }} × {{ $item->qty }}</span><strong>Rp{{ number_format((float) $item->subtotal, str_ends_with($item->subtotal, '.00') ? 0 : 2, ',', '.') }}</strong></div>
                @if ($item->notes)<p class="small text-secondary text-break mt-2 mb-0">Catatan: {{ $item->notes }}</p>@endif
                @if ($editable)
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        <form method="POST" action="{{ route($item->qty === 1 ? 'admin.orders.items.destroy' : 'admin.orders.items.update', [$order, $item]) }}" data-cashier-action @if ($item->qty === 1) data-confirm="Hapus {{ $item->product_name }} dari pesanan?" @endif>
                            @csrf @method($item->qty === 1 ? 'DELETE' : 'PATCH')
                            <input type="hidden" name="quantity" value="{{ $item->qty - 1 }}">
                            <button type="submit" class="btn btn-outline-secondary" aria-label="Kurangi {{ $item->product_name }}" @disabled($item->qty === 1 && $order->items->count() === 1)>−</button>
                        </form>
                        <span>{{ $item->qty }}</span>
                        <form method="POST" action="{{ route('admin.orders.items.update', [$order, $item]) }}" data-cashier-action>
                            @csrf @method('PATCH')
                            <input type="hidden" name="quantity" value="{{ $item->qty + 1 }}">
                            <button type="submit" class="btn btn-outline-secondary" aria-label="Tambah {{ $item->product_name }}" @disabled($item->qty >= 99)>+</button>
                        </form>
                        @if ($order->items->count() > 1)
                            <form method="POST" action="{{ route('admin.orders.items.destroy', [$order, $item]) }}" data-cashier-action data-confirm="Hapus {{ $item->product_name }} dari pesanan?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger">Hapus</button>
                            </form>
                        @endif
                    </div>
                @endif
            </article>
        @endforeach
        <div class="d-flex flex-wrap justify-content-between gap-2 pt-3"><span>{{ $order->items->sum('qty') }} item</span><strong>Total Rp{{ number_format((float) $order->total, str_ends_with($order->total, '.00') ? 0 : 2, ',', '.') }}</strong></div>
    </div>
    <div class="d-flex flex-wrap gap-3">
        @if ($editable)<button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#add-order-menu">+ Tambah Menu</button>@endif
        @if ($next = App\Models\Order::STATUS_FLOW[$order->order_status] ?? null)
            <form method="POST" action="{{ route('admin.orders.status', $order) }}" data-cashier-action>
                @csrf @method('PATCH')
                <input type="hidden" name="order_status" value="{{ $next }}">
                <button type="submit" class="btn btn-primary py-2">{{ ['confirmed' => 'Konfirmasi Pesanan', 'processing' => 'Mulai Proses', 'completed' => 'Selesaikan Pesanan'][$next] }}</button>
            </form>
        @endif
        @if ($order->payment_status === 'unpaid')<button type="button" class="btn btn-success py-2" data-bs-toggle="modal" data-bs-target="#payment-confirmation">Konfirmasi Pembayaran</button>@endif
    </div>
    @if ($editable)
        <div class="modal fade" id="add-order-menu" tabindex="-1" aria-labelledby="add-menu-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('admin.orders.items.store', $order) }}" data-cashier-action>
                @csrf
                <div class="modal-header"><h2 id="add-menu-title" class="modal-title fs-5">Tambah Menu</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
                <div class="modal-body">
                    <label for="add-menu-search" class="form-label">Cari nama menu</label><input type="search" id="add-menu-search" class="form-control mb-3">
                    <label for="add-menu-product" class="form-label">Menu dan harga</label>
                    <select id="add-menu-product" name="product_id" class="form-select mb-3" required>
                        <option value="">Pilih menu</option>
                        @foreach ($products as $product)<option value="{{ $product->id }}" data-name="{{ $product->product_name }}">{{ $product->product_name }} — Rp{{ number_format((float) $product->effectivePrice(), 2, ',', '.') }}</option>@endforeach
                    </select>
                    <label for="add-menu-quantity" class="form-label">Quantity</label><input id="add-menu-quantity" name="quantity" type="number" min="1" max="99" value="1" required class="form-control">
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Tambahkan</button></div>
            </form></div>
        </div>
    @endif
    @if ($order->payment_status === 'unpaid')
        <div class="modal fade" id="payment-confirmation" tabindex="-1" aria-labelledby="payment-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('admin.orders.payment', $order) }}" class="modal-content" data-cashier-action>
                @csrf @method('PATCH')
                <div class="modal-header"><h2 class="modal-title fs-5" id="payment-title">Konfirmasi Pembayaran</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
                <div class="modal-body">
                    <p>Pastikan pembayaran sebesar <strong>Rp{{ number_format((float) $order->total, 2, ',', '.') }}</strong> sudah diterima.</p>
                    <label for="payment-type" class="form-label">Metode pembayaran</label>
                    <select id="payment-type" name="payment_type" class="form-select" required><option value="">Pilih metode</option>@foreach (App\Models\Order::PAYMENT_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('payment_type') === $value)>{{ $label }}</option>@endforeach</select>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success">Pembayaran Diterima</button></div>
            </form></div>
        </div>
    @endif
@endsection
@push('scripts')
    <script src="{{ asset('js/cashier-orders.js') }}" defer></script>
@endpush
