@extends('layouts.admin')
@section('title', 'Kasir')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div><h1 class="h3 fw-bold">Kasir</h1><p class="text-secondary mb-0">Pesanan masuk dan pembayaran manual.</p></div>
        <a href="{{ route('admin.orders.create') }}" class="btn btn-primary py-2">+ Buat Pesanan</a>
    </div>
    <nav class="nav nav-pills cashier-tabs gap-1 mb-3" aria-label="Kelompok pesanan">
        @foreach (['active' => 'Aktif', 'completed' => 'Selesai', 'all' => 'Semua'] as $value => $label)
            <a class="nav-link {{ $tab === $value ? 'active' : '' }}" href="{{ route('admin.orders.index', array_merge(request()->only('search', 'payment_status'), ['tab' => $value])) }}">{{ $label }}</a>
        @endforeach
    </nav>
    <form method="GET" action="{{ route('admin.orders.index') }}" class="card card-body cashier-filters mb-3">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-xl-4"><label for="order-search" class="form-label">Cari pesanan</label><input id="order-search" name="search" class="form-control" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="Nama customer, meja, atau referensi"></div>
            <div class="col-12 col-sm-6 col-xl-2"><label for="order-status" class="form-label">Status Order</label><select id="order-status" name="order_status" class="form-select"><option value="">Semua status</option>@foreach (App\Models\Order::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['order_status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
            <div class="col-12 col-sm-6 col-xl-2"><label for="payment-status" class="form-label">Status Pembayaran</label><select id="payment-status" name="payment_status" class="form-select"><option value="">Semua status</option>@foreach (['unpaid', 'paid'] as $status)<option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>{{ strtoupper($status) }}</option>@endforeach</select></div>
            <div class="col-12 col-xl-4 d-flex gap-2"><button class="btn btn-primary" type="submit">Terapkan</button><a class="btn btn-outline-secondary" href="{{ route('admin.orders.index') }}">Reset</a></div>
        </div>
    </form>
    <p id="orders-refresh-status" class="small text-secondary" role="status">Diperbarui otomatis setiap 8 detik.</p>
    <div id="orders-action-feedback" class="alert d-none" role="status" aria-live="polite"></div>
    <div id="orders-list" data-monitor="{{ $monitor }}">@include('admin.orders._list')</div>
    <div class="modal fade" id="dashboard-payment" tabindex="-1" aria-labelledby="dashboard-payment-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content" data-dashboard-action>
            @csrf @method('PATCH')
            <div class="modal-header"><h2 class="modal-title fs-5" id="dashboard-payment-title">Konfirmasi Pembayaran</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <p id="dashboard-payment-order" class="fw-semibold text-break"></p>
                <p>Pastikan pembayaran sebesar <strong id="dashboard-payment-total"></strong> sudah diterima.</p>
                <label for="dashboard-payment-type" class="form-label">Metode pembayaran</label>
                <select id="dashboard-payment-type" name="payment_type" class="form-select" required><option value="">Pilih metode</option>@foreach (App\Models\Order::PAYMENT_TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success">Pembayaran Diterima</button></div>
        </form></div>
    </div>
    <div id="order-notifications" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090; max-width: 100vw" aria-live="polite" aria-atomic="false"></div>
@endsection
@push('scripts')
    <script src="{{ asset('js/cashier-orders.js') }}" defer></script>
@endpush
