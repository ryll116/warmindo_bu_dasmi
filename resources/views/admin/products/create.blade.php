@extends('layouts.admin')

@section('title', 'Tambah Product')

@section('content')
    <a href="{{ route('admin.products.index') }}" class="text-decoration-none d-inline-block mb-3">&larr; Kembali ke Products</a>
    <h1 class="h3 fw-bold mb-4">Tambah Product</h1>
    <div class="card border-0 shadow-sm product-form">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.products.store') }}">
                @csrf
                @include('admin.products._form', ['submitLabel' => 'Simpan Product'])
            </form>
        </div>
    </div>
@endsection
