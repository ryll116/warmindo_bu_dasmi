<span class="sidebar-icon" aria-hidden="true">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
        @switch($icon)
            @case('admin.reports.sales')
                <path d="M4 3v17h17M8 15v-4m5 4V8m5 7V5"/>
                @break
            @case('admin.reports.attendance')
                <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18m-13 5 3 3 5-5"/>
                @break
            @case('admin.orders.index')
                <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6m-6 4h3"/>
                @break
            @case('attendance.index')
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
                @break
            @case('admin.categories.index')
                <path d="M3 3h8l10 10-8 8L3 11V3Z"/><circle cx="7.5" cy="7.5" r="1"/>
                @break
            @case('admin.products.index')
            @case('admin.inventory.index')
                <path d="m12 3 9 5v9l-9 5-9-5V8l9-5Zm-9 5 9 5 9-5m-9 5v9M7.5 5.5l9 5"/>
                @break
            @case('admin.tables.index')
                <rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M3 15h18M9 10v10m6-10v10"/>
                @break
            @case('admin.users.index')
                <circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m1-16a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 4v2"/>
                @break
            @case('management')
                <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
                @break
        @endswitch
    </svg>
</span>
