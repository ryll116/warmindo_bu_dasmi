@extends('layouts.admin')
@section('title', 'Laporan Penjualan')
@section('content')
    <h1 class="h3 fw-bold">Laporan Penjualan</h1>
    <p class="text-secondary">Transaksi lunas berdasarkan waktu pembayaran (WIB), {{ $start->format('d/m/Y') }} – {{ $end->format('d/m/Y') }}.</p>
    <form method="GET" action="{{ route('admin.reports.sales') }}" class="card card-body border-0 shadow-sm mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4 col-xl-2"><label for="report-period" class="form-label">Periode</label><select id="report-period" name="period" class="form-select">
                @foreach (['today' => 'Hari Ini', 'yesterday' => 'Kemarin', 'last7' => '7 Hari Terakhir', 'month' => 'Bulan Ini', 'custom' => 'Custom Date Range'] as $value => $label)<option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>@endforeach
            </select></div>
            <div class="col-6 col-md-4 col-xl-2"><label for="report-start" class="form-label">Tanggal awal</label><input id="report-start" type="date" name="start" value="{{ $start->format('Y-m-d') }}" class="form-control" disabled></div>
            <div class="col-6 col-md-4 col-xl-2"><label for="report-end" class="form-label">Tanggal akhir</label><input id="report-end" type="date" name="end" value="{{ $end->format('Y-m-d') }}" class="form-control" disabled></div>
            <div class="col-12 col-md-8 col-xl-4"><label for="report-search" class="form-label">Nama customer / nomor meja</label><input id="report-search" name="search" value="{{ $search }}" maxlength="100" class="form-control" placeholder="Contoh: Evan atau 05"></div>
            <div class="col-12 col-md-4 col-xl-2 d-flex gap-2"><button type="submit" class="btn btn-primary">Terapkan</button><a href="{{ route('admin.reports.sales') }}" class="btn btn-outline-secondary">Reset</a></div>
        </div>
        <p class="small text-secondary mt-2 mb-0">Tanggal manual digunakan untuk Custom Date Range. Pencarian berlaku untuk seluruh laporan.</p>
    </form>
    <div class="row g-3 mb-4">
        @foreach (['Total Omzet' => 'Rp'.number_format((float) $summary->revenue, 2, ',', '.'), 'Jumlah Transaksi' => number_format($summary->transactions, 0, ',', '.'), 'Total Item Terjual' => number_format($itemCount, 0, ',', '.'), 'Rata-rata Nilai Transaksi' => 'Rp'.number_format((float) $summary->average, 2, ',', '.')] as $label => $value)
            <div class="col-12 col-sm-6 col-xl-3"><div class="card card-body border-0 shadow-sm h-100"><h2 class="h6 text-secondary">{{ $label }}</h2><p class="fs-4 fw-semibold text-break mb-0">{{ $value }}</p></div></div>
        @endforeach
    </div>
    <div class="row g-3 mb-4">
        <section class="col-12 col-lg-7"><div class="card card-body border-0 shadow-sm h-100"><h2 class="h5 mb-3">Top 10 Produk Terlaris</h2>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Nama Produk</th><th class="text-end">Quantity</th><th class="text-end">Total Penjualan</th></tr></thead><tbody>
                @forelse ($topProducts as $product)<tr><td>{{ $product->product_name }}</td><td class="text-end">{{ $product->quantity }}</td><td class="text-end text-nowrap">Rp{{ number_format((float) $product->revenue, 2, ',', '.') }}</td></tr>
                @empty<tr><td colspan="3" class="text-secondary py-4">Belum ada produk terjual pada periode ini.</td></tr>@endforelse
            </tbody></table></div>
        </div></section>
        <section class="col-12 col-lg-5"><div class="card card-body border-0 shadow-sm h-100"><h2 class="h5 mb-3">Metode Pembayaran</h2>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Metode</th><th class="text-end">Transaksi</th><th class="text-end">Nominal</th></tr></thead><tbody>
                @foreach (['cash' => 'Cash', 'qris_manual' => 'QRIS'] as $method => $label)<tr><td>{{ $label }}</td><td class="text-end">{{ $payments->get($method)?->transactions ?? 0 }}</td><td class="text-end text-nowrap">Rp{{ number_format((float) ($payments->get($method)?->revenue ?? 0), 2, ',', '.') }}</td></tr>@endforeach
                @foreach ($payments->except(['cash', 'qris_manual']) as $payment)<tr><td>{{ $payment->payment_type ?: 'Belum tercatat' }}</td><td class="text-end">{{ $payment->transactions }}</td><td class="text-end text-nowrap">Rp{{ number_format((float) $payment->revenue, 2, ',', '.') }}</td></tr>@endforeach
            </tbody></table></div>
        </div></section>
    </div>
    <section class="card border-0 shadow-sm"><h2 class="h5 p-3 mb-0">Riwayat Transaksi Lunas</h2>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Waktu Pembayaran (WIB)</th><th>Customer</th><th>Meja</th><th>Item</th><th>Total</th><th>Metode</th><th>Status</th></tr></thead><tbody>
            @forelse ($orders as $order)<tr>
                <td class="text-nowrap">{{ Carbon\CarbonImmutable::parse($order->payment_time, config('app.timezone'))->setTimezone($timezone)->format('d/m/Y H:i') }}</td>
                <td>{{ $order->customer_name ?: 'Nama belum tersedia' }}</td><td>{{ str_pad((string) $order->table?->table_no, 2, '0', STR_PAD_LEFT) }}</td><td>{{ $order->item_quantity ?? 0 }}</td><td class="text-nowrap">Rp{{ number_format((float) $order->total, 2, ',', '.') }}</td><td>{{ ['cash' => 'Cash', 'qris_manual' => 'QRIS'][$order->payment_type] ?? $order->payment_type ?? 'Belum tercatat' }}</td><td><span class="badge text-bg-success">PAID</span></td>
            </tr>@empty<tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada transaksi lunas yang sesuai filter.</td></tr>@endforelse
        </tbody></table></div>
        @if ($orders->hasPages())<div class="card-body">{{ $orders->links('pagination::bootstrap-5') }}</div>@endif
    </section>
@endsection
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const period = document.getElementById('report-period');
        const start = document.getElementById('report-start');
        const end = document.getElementById('report-end');

        function toggleCustomDate() {
            const isCustom = period.value === 'custom';

            start.disabled = !isCustom;
            end.disabled = !isCustom;
        }

        toggleCustomDate();

        period.addEventListener('change', toggleCustomDate);
    });
</script>