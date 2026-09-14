@extends('layouts.admin')

@section('title', 'Tambah Table')

@section('content')
    <a href="{{ route('admin.tables.index') }}" class="text-decoration-none d-inline-block mb-3">&larr; Kembali ke Tables</a>
    <h1 class="h3 fw-bold mb-4">Tambah Table</h1>
    <div class="card border-0 shadow-sm product-form">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.tables.store') }}">
                @csrf
                @include('admin.tables._form', ['submitLabel' => 'Simpan Table'])
            </form>
        </div>
    </div>
@endsection
