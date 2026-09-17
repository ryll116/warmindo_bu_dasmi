@extends('layouts.admin')
@section('title', 'Edit User')
@section('content')
    <h1 class="h3 fw-bold mb-4">Edit User</h1>
    <div class="card border-0 shadow-sm product-form"><div class="card-body p-4">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf @method('PUT')
            @include('admin.users._form')
        </form>
    </div></div>
@endsection
