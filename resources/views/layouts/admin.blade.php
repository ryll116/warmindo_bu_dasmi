<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') · Warmindo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>
    @include('layouts.demo-watermark')
    <a href="#main-content" class="visually-hidden-focusable position-absolute bg-white p-3">Langsung ke konten</a>
    @include('admin.partials.sidebar')
    <div class="admin-main">
        @include('admin.partials.navbar')
        <main id="main-content" class="container-fluid p-3 p-md-4 p-xl-5">
            @foreach (['success' => 'success', 'error' => 'danger'] as $key => $style)
                @if (session($key))
                    <div class="alert alert-{{ $style }} alert-dismissible fade show" role="alert">
                        {{ session($key) }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup notifikasi"></button>
                    </div>
                @endif
            @endforeach
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <strong>Periksa kembali data yang diisi.</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    @stack('scripts')
</body>
</html>
