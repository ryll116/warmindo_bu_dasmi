<header class="navbar bg-white border-bottom px-3 px-md-4 py-3">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#admin-sidebar" aria-controls="admin-sidebar" aria-label="Buka menu navigasi">Menu</button>
        <span class="fw-semibold">{{ auth()->user()->isAdmin() ? 'Admin Panel' : 'Dashboard Kasir' }}</span>
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
