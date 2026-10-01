(() => {
    const toggle = document.getElementById('sidebar-toggle');
    if (!toggle) return;

    const key = 'warmindo.sidebar.collapsed';
    let collapsed = false;
    try {
        collapsed = localStorage.getItem(key) === 'true';
    } catch {
        // The toggle still works when browser storage is unavailable.
    }

    function render() {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', String(!collapsed));
        toggle.setAttribute('aria-label', collapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar');
    }

    toggle.addEventListener('click', () => {
        collapsed = !collapsed;
        render();
        try {
            localStorage.setItem(key, String(collapsed));
        } catch {
            // Persistence is optional; the current page remains usable.
        }
    });
    render();
})();
