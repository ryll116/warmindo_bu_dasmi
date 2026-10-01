<header class="navbar bg-white border-bottom px-3 px-md-4 py-3">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#admin-sidebar" aria-controls="admin-sidebar" aria-label="Buka menu navigasi">Menu</button>
        <button class="btn btn-outline-secondary d-none d-lg-inline-flex align-items-center gap-2" type="button" id="sidebar-toggle" aria-controls="admin-sidebar" aria-expanded="true" aria-label="Sembunyikan sidebar">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M13 9l3 3-3 3"/></svg>
            Menu
        </button>
        <span class="fw-semibold">{{ auth()->user()->isAdmin() || auth()->user()->isSuperAdmin() ? 'Admin Panel' : 'Dashboard Kasir' }}</span>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="text-break">{{ auth()->user()->name }}</span>
        <span class="badge rounded-pill text-bg-light border">{{ strtoupper(auth()->user()->role) }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Logout</button>
        </form>
    </div>
</header>
