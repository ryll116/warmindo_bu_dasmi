@extends('layouts.admin')

@section('title', 'Tambah Category')

@section('content')
    <a href="{{ route('admin.categories.index') }}" class="text-decoration-none d-inline-block mb-3">&larr; Kembali ke Categories</a>
    <h1 class="h3 fw-bold mb-4">Tambah Category</h1>
    <div class="card border-0 shadow-sm product-form">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.categories.store') }}">
                @csrf
                @include('admin.categories._form', ['submitLabel' => 'Simpan Category'])
            </form>
        </div>
    </div>
@endsection
