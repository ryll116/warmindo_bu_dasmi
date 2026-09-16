<aside class="admin-sidebar offcanvas-lg offcanvas-start text-bg-dark" tabindex="-1" id="admin-sidebar" aria-labelledby="sidebar-title">
    <div class="offcanvas-header border-bottom border-secondary p-4">
        <a href="{{ route('admin.products.index') }}" id="sidebar-title" class="text-white text-decoration-none fs-4 fw-bold">Warmindo<span class="text-warning">.</span></a>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#admin-sidebar" aria-label="Tutup menu"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-3">
        <p class="small text-uppercase text-white-50 px-3 mt-2">Management</p>
        <nav class="nav nav-pills flex-column gap-2" aria-label="Menu admin">
            @foreach (['admin.dashboard' => 'Dashboard', 'admin.orders.index' => 'Kasir', 'admin.categories.index' => 'Categories', 'admin.products.index' => 'Products', 'admin.tables.index' => 'Tables'] as $route => $label)
                @php($active = request()->routeIs(str_replace('.index', '.*', $route)))
                <a href="{{ route($route) }}" class="nav-link {{ $active ? 'active' : 'text-white-50' }}" @if ($active) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="mt-auto pt-5 px-3 small text-white-50">Warmindo Admin</div>
    </div>
</aside>
