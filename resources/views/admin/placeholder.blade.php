@extends('layouts.admin')

@section('title', $title)

@section('content')
    <h1 class="h3 fw-bold mb-4">{{ $title }}</h1>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5">Modul belum tersedia</h2>
            <p class="text-secondary">Halaman {{ $title }} akan dikembangkan pada tahap berikutnya.</p>
            <a href="{{ route('admin.products.index') }}" class="btn btn-primary">Kelola Products</a>
        </div>
    </div>
@endsection
