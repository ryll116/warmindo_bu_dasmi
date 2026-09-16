@extends('layouts.admin')

@section('title', 'Tables')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Tables <span class="badge text-bg-light border fs-6 align-middle">{{ $tables->total() }}</span></h1>
            <p class="text-secondary mb-0">Kelola nomor meja dan status ketersediaannya.</p>
        </div>
        <a href="{{ route('admin.tables.create') }}" class="btn btn-primary">+ Tambah Table</a>
    </div>
    @include('admin.partials.filters', [
        'indexRoute' => 'admin.tables.index',
        'searchPlaceholder' => 'Cari nomor meja',
        'showStatus' => true,
    ])
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Daftar Table Warmindo</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="ps-4">No</th>
                        <th scope="col">Nomor Meja</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tables as $table)
                        <tr>
                            <td class="ps-4">{{ $tables->firstItem() + $loop->index }}</td>
                            <td class="fw-semibold text-break">{{ $table->table_no }}</td>
                            <td><span class="badge rounded-pill {{ $table->is_available ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $table->is_available ? 'Active' : 'Inactive' }}</span></td>
                            <td class="pe-4">
                                <div class="d-flex justify-content-end flex-wrap gap-2">
                                    <a href="{{ route('admin.tables.qr', $table) }}" class="btn btn-sm btn-outline-secondary">QR Code</a>
                                    <a href="{{ route('admin.tables.edit', $table) }}" class="btn btn-sm btn-outline-primary" aria-label="Edit {{ $table->table_no }}">Edit</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-record" data-delete-url="{{ route('admin.tables.destroy', $table) }}" data-record-name="{{ $table->table_no }}" aria-label="Hapus {{ $table->table_no }}">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-5">
                            @if (request()->filled('search') || request()->filled('status'))
                                <h2 class="h5">Tidak ada hasil yang sesuai</h2>
                                <p class="text-secondary mb-0">Ubah pencarian atau tekan Reset untuk melihat semua data.</p>
                            @else
                                <h2 class="h5">Belum ada meja</h2>
                                <a href="{{ route('admin.tables.create') }}" class="btn btn-primary mt-2">Tambah Table</a>
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tables->hasPages())
            <div class="card-body border-top pb-0">{{ $tables->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
    @include('admin.partials.delete-modal', ['recordLabel' => 'Table'])
@endsection
