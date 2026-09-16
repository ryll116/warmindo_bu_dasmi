@extends('layouts.admin')
@section('title', 'QR Meja '.$table->table_no)
@section('content')
    <a href="{{ route('admin.tables.index') }}" class="btn btn-outline-secondary mb-3">Kembali ke Tables</a>
    <div class="card border-0 shadow-sm mx-auto text-center" style="max-width: 560px">
        <div class="card-body p-3 p-md-4">
            <h1 class="h3">Meja {{ str_pad((string) $table->table_no, 2, '0', STR_PAD_LEFT) }}</h1>
            <img src="{{ $qrImage }}" alt="QR Code Meja {{ $table->table_no }}" class="img-fluid" width="400" height="400">
            <p>Scan untuk memesan dari Meja {{ str_pad((string) $table->table_no, 2, '0', STR_PAD_LEFT) }}</p>
            <p class="small text-break"><a href="{{ $menuUrl }}">{{ $menuUrl }}</a></p>
            @unless ($table->is_available)<p class="text-warning-emphasis">Meja sedang nonaktif. Menu dapat dibuka setelah meja diaktifkan.</p>@endunless
            <a href="{{ route('admin.tables.qr.download', $table) }}" class="btn btn-primary">Download QR</a>
            <p class="small text-secondary mt-3 mb-0">Format SVG, tetap tajam saat diperbesar untuk dicetak.</p>
        </div>
    </div>
@endsection
