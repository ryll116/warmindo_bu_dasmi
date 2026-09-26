<aside class="admin-sidebar offcanvas-lg offcanvas-start text-bg-dark" tabindex="-1" id="admin-sidebar" aria-labelledby="sidebar-title">
    <div class="offcanvas-header border-bottom border-secondary p-4">
        <a href="{{ route('admin.home') }}" id="sidebar-title" class="text-white text-decoration-none fs-4 fw-bold">Baji Minasa <br> <span class="text-warning"><small>Coto Makassar dan Sop Konro</small></span></a>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#admin-sidebar" aria-label="Tutup menu"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-3" style="    
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            "
    >
        <!-- <p class="small text-uppercase text-white-50 px-3 mt-2">Management</p> -->
        @php
            $user = auth()->user();
        
            if ($user->isSuperAdmin()) {
                $menus = [
                    'admin.reports.sales' => 'Laporan Penjualan',
                    'admin.orders.index' => 'Kasir',
                    'admin.categories.index' => 'Manajemen Kategori',
                    'admin.products.index' => 'Manajemen Produk',
                    'admin.tables.index' => 'Manajemen Table',
                    'admin.users.index' => 'User Management',
                ];
            } elseif ($user->isAdmin()) {
                $menus = [
                    'admin.reports.sales' => 'Laporan Penjualan',
                    'admin.orders.index' => 'Kasir',
                    'admin.categories.index' => 'Manajemen Kategori',
                    'admin.products.index' => 'Manajemen Produk',
                    'admin.tables.index' => 'Manajemen Table',
                ];
            } else {
                $menus = [
                    'admin.orders.index' => 'Dashboard Kasir',
                ];
            }
        @endphp
        <nav class="nav nav-pills flex-column gap-2" aria-label="Menu admin">
            @foreach ($menus as $route => $label)
                @php($active = request()->routeIs(str_replace('.index', '.*', $route)))
                <a href="{{ route($route) }}" class="nav-link {{ $active ? 'active' : 'text-white-50' }}" @if ($active) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="mt-auto pt-5 px-3 small text-white-50">Warmindo {{ auth()->user()->isAdmin() || auth()->user()->isSuperAdmin() ? 'Admin' : 'Kasir' }}</div>
    </div>
</aside>
