@extends('layouts.customer')

@section('title', 'Menu belum dapat dibuka')

@section('content')
    <main class="menu-unavailable">
        <a class="visually-hidden-focusable" href="#error-title">Langsung ke pesan</a>
        <div class="brand mb-4">warmindo<span>.</span></div>
        <img src="{{ asset('images/product-placeholder.svg') }}" width="160" height="160" alt="" class="rounded-4 mb-4">
        <h1 id="error-title" class="h4 fw-semibold">Menu belum dapat dibuka</h1>
        <p class="text-secondary">QR meja tidak dikenali atau meja sedang tidak aktif.</p>
        <p class="text-secondary mb-0">Silakan scan ulang QR di meja Anda atau minta bantuan staf Warmindo.</p>
    </main>
@endsection
