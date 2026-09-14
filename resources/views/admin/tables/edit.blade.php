@extends('layouts.admin')

@section('title', 'Edit Table')

@section('content')
    <a href="{{ route('admin.tables.index') }}" class="text-decoration-none d-inline-block mb-3">&larr; Kembali ke Tables</a>
    <h1 class="h3 fw-bold mb-4">Edit Table</h1>
    <div class="card border-0 shadow-sm product-form">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.tables.update', $table) }}">
                @csrf
                @method('PUT')
                @include('admin.tables._form', ['submitLabel' => 'Simpan Perubahan'])
            </form>
        </div>
    </div>
@endsection
