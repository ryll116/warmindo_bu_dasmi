@extends('layouts.customer')

@section('title', 'Login Admin / Kasir')

@section('content')
    <main class="container py-5">
        <div class="card border-0 shadow-sm mx-auto" style="max-width: 440px">
            <div class="card-body p-4">
                <h1 class="h3 fw-bold">Login Admin / Kasir</h1>
                <p class="text-secondary">Masuk menggunakan akun Warmindo Anda.</p>
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" maxlength="255" autocomplete="username" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" autocomplete="current-password" maxlength="255" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    </main>
@endsection
