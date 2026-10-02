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
        @php
            $user = auth()->user();
            $managementMenus = [];

            if ($user->isSuperAdmin() || $user->isAdmin()) {
                $menus = [
                    'admin.reports.sales' => 'Laporan Penjualan',
                    'admin.reports.attendance' => 'Laporan Absensi',
                    'admin.orders.index' => 'Kasir',
                ];
                $managementMenus = [
                    'admin.categories.index' => 'Manajemen Kategori',
                    'admin.products.index' => 'Manajemen Produk',
                    'admin.tables.index' => 'Manajemen Table',
                ];
                if ($user->isSuperAdmin()) {
                    $managementMenus['admin.users.index'] = 'Manajemen User';
                }
            } else {
                $menus = [
                    'admin.orders.index' => 'Dashboard Kasir',
                    'attendance.index' => 'Absensi Saya',
                ];
            }
            if ($user->isAdmin() || $user->isSuperAdmin() || $user->isKasir()) {
                $menus['admin.inventory.index'] = 'Inventory';
            }
        @endphp
        <nav class="nav nav-pills flex-column gap-2" aria-label="Menu admin">
            @foreach ($menus as $route => $label)
                @php($active = request()->routeIs(str_replace('.index', '.*', $route)))
                <a href="{{ route($route) }}" class="nav-link sidebar-menu-link {{ $active ? 'active' : 'text-white-50' }}" @if ($active) aria-current="page" @endif>@include('admin.partials.sidebar-icon', ['icon' => $route])<span class="sidebar-label">{{ $label }}</span></a>
            @endforeach
            @if ($managementMenus)
                @php($managementActive = request()->routeIs('admin.categories.*', 'admin.products.*', 'admin.tables.*', 'admin.users.*'))
                <button type="button" class="nav-link management-toggle text-start d-flex align-items-center justify-content-between gap-2 {{ $managementActive ? 'text-white' : 'text-white-50' }}" data-bs-toggle="collapse" data-bs-target="#management-submenu" aria-expanded="{{ $managementActive ? 'true' : 'false' }}" aria-controls="management-submenu">
                    <span class="sidebar-menu-link">@include('admin.partials.sidebar-icon', ['icon' => 'management'])<span class="sidebar-label">Management</span></span>
                    <svg class="management-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="collapse {{ $managementActive ? 'show' : '' }}" id="management-submenu">
                    <div class="management-submenu nav nav-pills flex-column gap-1 ms-3 ps-2">
                        @foreach ($managementMenus as $route => $label)
                            @php($active = request()->routeIs(str_replace('.index', '.*', $route)))
                            <a href="{{ route($route) }}" class="nav-link sidebar-menu-link {{ $active ? 'active' : 'text-white-50' }}" @if ($active) aria-current="page" @endif>@include('admin.partials.sidebar-icon', ['icon' => $route])<span class="sidebar-label">{{ $label }}</span></a>
                        @endforeach
                    </div>
                </div>
            @endif
        </nav>
        <div class="mt-auto pt-5 px-3 small text-white-50">Warmindo {{ auth()->user()->isAdmin() || auth()->user()->isSuperAdmin() ? 'Admin' : 'Kasir' }}</div>
    </div>
</aside>
