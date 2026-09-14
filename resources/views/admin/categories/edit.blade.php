@extends('layouts.admin')

@section('title', 'Edit Category')

@section('content')
    <a href="{{ route('admin.categories.index') }}" class="text-decoration-none d-inline-block mb-3">&larr; Kembali ke Categories</a>
    <h1 class="h3 fw-bold mb-4">Edit Category</h1>
    <div class="card border-0 shadow-sm product-form">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.categories.update', $category) }}">
                @csrf
                @method('PUT')
                @include('admin.categories._form', ['submitLabel' => 'Simpan Perubahan'])
            </form>
        </div>
    </div>
@endsection
