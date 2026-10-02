@extends('layouts.admin')

@section('title', 'Inventory')

@section('content')
    <h1 class="h3 fw-bold">Inventory</h1>
    <p class="text-secondary">Tanggal operasional {{ $operationalDate }} · {{ config('attendance.timezone') }}. Saldo global diteruskan antar shift.</p>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            @if ($activeSession)
                <h2 class="h5">Session aktif: {{ config('attendance.shifts.'.$activeSession->shift_type.'.label', $activeSession->shift_type) }} · {{ $activeSession->session_date->format('d/m/Y') }}</h2>
                <p>Catat seluruh pemakaian manual sebelum menutup atau menyerahkan shift. Opening fisik cukup di awal hari; shift berikutnya melanjutkan saldo.</p>
                <form method="post" action="{{ route('admin.inventory.close', $activeSession) }}">
                    @csrf
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="manual_usage_complete" value="1" id="manual-complete" required>
                        <label class="form-check-label" for="manual-complete">Seluruh pemakaian manual session ini sudah dicatat</label>
                    </div>
                    <button class="btn btn-outline-primary">Tutup session</button>
                    @if ($activeSession->shift_type === 'morning')
                        <button class="btn btn-primary" formaction="{{ route('admin.inventory.handover', $activeSession) }}">Handover ke shift siang</button>
                    @endif
                </form>
            @else
                <h2 class="h5">Buka session inventory</h2>
                <form method="post" action="{{ route('admin.inventory.open') }}" class="d-flex gap-2 align-items-end">
                    @csrf
                    <div>
                        <label class="form-label" for="shift-type">Shift</label>
                        <select name="shift_type" id="shift-type" class="form-select" required>
                            @foreach (config('attendance.shifts') as $key => $shift)
                                <option value="{{ $key }}">{{ $shift['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-primary">Buka session</button>
                </form>
            @endif
        </div>
    </div>

    @if ($activeSession && $items->where('is_active', true)->isNotEmpty())
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5">Catat stok / hitung fisik</h2>
                <p class="text-secondary">Jumlah stock in, pemakaian, dan waste diisi positif; sistem mencatat pengurangan dengan tanda minus. Adjustment memakai selisih bertanda (+/-). Opening/closing hanya mengisi stok fisik dan tidak mengubah saldo.</p>
                <form method="post" action="{{ route('admin.inventory.movement') }}" class="row g-3">
                    @csrf
                    <input type="hidden" name="inventory_session_id" value="{{ $activeSession->id }}">
                    <input type="hidden" name="reference_key" value="{{ old('reference_key', $referenceKey) }}">
                    <div class="col-md-6">
                        <label for="inventory-item" class="form-label">Bahan / kemasan</label>
                        <select name="inventory_item_id" id="inventory-item" class="form-select" required>
                            @foreach ($items->where('is_active', true) as $item)
                                <option value="{{ $item->id }}" @selected(old('inventory_item_id') == $item->id)>{{ $item->item_name }} · {{ $item->unit }} · saldo {{ $item->current_stock }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="movement-type" class="form-label">Operasi</label>
                        <select name="movement_type" id="movement-type" class="form-select" required>
                            @foreach (['stock_in' => 'Stock in', 'manual_usage' => 'Pemakaian manual', 'waste' => 'Waste', 'adjustment' => 'Adjustment', 'opening' => 'Physical opening', 'closing' => 'Physical closing'] as $key => $label)
                                <option value="{{ $key }}" @selected(old('movement_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="quantity" class="form-label">Jumlah perubahan (kosongkan untuk opening/closing)</label>
                        <input type="number" step="0.001" name="quantity" id="quantity" class="form-control" value="{{ old('quantity') }}">
                    </div>
                    <div class="col-md-6">
                        <label for="physical-stock" class="form-label">Stok fisik (khusus opening/closing)</label>
                        <input type="number" min="0" step="0.001" name="physical_stock" id="physical-stock" class="form-control" value="{{ old('physical_stock') }}">
                    </div>
                    <div class="col-12">
                        <label for="movement-notes" class="form-label">Catatan / alasan (wajib untuk adjustment)</label>
                        <textarea name="notes" id="movement-notes" class="form-control" maxlength="2000">{{ old('notes') }}</textarea>
                    </div>
                    <div class="col-12"><button class="btn btn-primary">Simpan movement</button></div>
                </form>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Saldo inventory</h2></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Kode</th><th>Item</th><th>Unit</th><th>Saldo</th><th>Minimum</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr><td>{{ $item->item_code }}</td><td>{{ $item->item_name }}</td><td>{{ $item->unit }}</td><td>{{ $item->current_stock }}</td><td>{{ $item->minimum_stock }}</td><td>{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-4">Belum ada master item inventory. Tambahkan master item sebelum mencatat stok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5">Ledger &amp; variance</h2>
            <p class="small mb-0">Variance = stok fisik − saldo sebelum checkpoint. Jika perlu koreksi, buat adjustment terpisah dengan alasan. Checkpoint hanya dapat dicatat sekali per item/session/jenis. Catat physical closing sebelum menutup session akhir hari.</p>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Waktu</th><th>Session</th><th>Item</th><th>Operasi</th><th>Jumlah</th><th>Sebelum</th><th>Sesudah</th><th>Fisik</th><th>Variance</th><th>Operator / catatan</th></tr></thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td>{{ $movement->created_at?->timezone(config('attendance.timezone'))->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $movement->session?->session_date->format('d/m/Y') }} {{ $movement->session?->shift_type }}</td>
                            <td>{{ $movement->item?->item_name }}</td><td>{{ $movement->movement_type }}</td>
                            <td>{{ $movement->quantity }}</td><td>{{ $movement->stock_before }}</td><td>{{ $movement->stock_after }}</td>
                            <td>{{ $movement->physical_stock ?? '—' }}</td><td>{{ $movement->variance() ?? '—' }}</td>
                            <td>{{ $movement->creator?->name ?? 'System / historis' }}<br>{{ $movement->notes }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center py-4">Belum ada stock movement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $movements->links('pagination::bootstrap-5') }}</div>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Session terakhir</h2></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Tanggal</th><th>Shift</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr><td>{{ $session->session_date->format('d/m/Y') }}</td><td>{{ config('attendance.shifts.'.$session->shift_type.'.label', $session->shift_type) }}</td><td>{{ $session->status }}</td></tr>
                    @empty
                        <tr><td colspan="3">Belum ada session.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection