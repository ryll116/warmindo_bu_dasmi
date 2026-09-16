@extends('layouts.admin')
@section('title', 'Kasir')
@section('content')
    <h1 class="h3 fw-bold">Kasir</h1>
    <p class="text-secondary">Pesanan masuk dan pembayaran manual.</p>
    <nav class="nav nav-pills gap-2 mb-3" aria-label="Kelompok pesanan">
        @foreach (['active' => 'Aktif', 'completed' => 'Selesai', 'all' => 'Semua'] as $value => $label)
            <a class="nav-link {{ $tab === $value ? 'active' : '' }}" href="{{ route('admin.orders.index', array_merge(request()->only('search', 'payment_status'), ['tab' => $value])) }}">{{ $label }}</a>
        @endforeach
    </nav>
    <form method="GET" action="{{ route('admin.orders.index') }}" class="card card-body border-0 shadow-sm mb-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-4"><label for="order-search" class="form-label">Cari referensi / nomor meja</label><input id="order-search" name="search" class="form-control" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Contoh: 05"></div>
            <div class="col-6 col-lg-2"><label for="order-status" class="form-label">Order</label><select id="order-status" name="order_status" class="form-select"><option value="">Semua status</option>@foreach (App\Models\Order::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['order_status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="col-6 col-lg-2"><label for="payment-status" class="form-label">Payment</label><select id="payment-status" name="payment_status" class="form-select"><option value="">Semua status</option>@foreach (['unpaid', 'paid'] as $status)<option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>{{ strtoupper($status) }}</option>@endforeach</select></div>
            <div class="col-12 col-lg-4 d-flex gap-2"><button class="btn btn-primary" type="submit">Terapkan</button><a class="btn btn-outline-secondary" href="{{ route('admin.orders.index') }}">Reset Filter</a></div>
        </div>
    </form>
    <p id="orders-refresh-status" class="small text-secondary" role="status">Diperbarui otomatis setiap 8 detik.</p>
    <div id="orders-list" data-monitor="{{ $monitor }}">@include('admin.orders._list')</div>
    <div id="order-notifications" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090; max-width: 100vw" aria-live="polite" aria-atomic="false"></div>
@endsection
@push('scripts')
    <script src="{{ asset('js/cashier-orders.js') }}" defer></script>
@endpush
