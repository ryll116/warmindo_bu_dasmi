@extends('layouts.admin')

@section('title', 'Edit Product')

@section('content')
    <a href="{{ route('admin.products.index') }}" class="text-decoration-none d-inline-block mb-3">&larr; Kembali ke Products</a>
    <h1 class="h3 fw-bold mb-4">Edit Product</h1>
    <div class="card border-0 shadow-sm product-form">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.products.update', $product) }}">
                @csrf
                @method('PUT')
                @include('admin.products._form', ['submitLabel' => 'Simpan Perubahan'])
            </form>
        </div>
    </div>
@endsection
