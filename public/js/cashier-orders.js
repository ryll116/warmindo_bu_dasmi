(() => {
    'use strict';

    document.querySelectorAll('[data-cashier-action]').forEach(form => {
        const button = form.querySelector('button[type="submit"]');
        const originalLabel = button.textContent;
        const originallyDisabled = button.disabled;
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            if (form.dataset.submitting) {
                event.preventDefault();
                return;
            }
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = 'true';
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.textContent = 'Memproses…';
        });
        window.addEventListener('pageshow', () => {
            if (!form.dataset.submitting) return;
            delete form.dataset.submitting;
            button.disabled = originallyDisabled;
            button.removeAttribute('aria-busy');
            button.textContent = originalLabel;
        });
    });

    document.getElementById('add-menu-search')?.addEventListener('input', event => {
        const select = document.getElementById('add-menu-product');
        const keyword = event.target.value.trim().toLocaleLowerCase('id-ID');
        for (const option of select.options) {
            option.hidden = !!option.value && !option.dataset.name.toLocaleLowerCase('id-ID').includes(keyword);
            if (option.hidden && option.selected) select.value = '';
        }
    });

    const list = document.getElementById('orders-list');
    if (!list) return;
    const status = document.getElementById('orders-refresh-status');
    const notifications = document.getElementById('order-notifications');
    const knownOrders = new Set();
    const queue = [];
    const currency = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    let monitor = list.dataset.monitor;
    let showingToast = false;

    function showNextToast() {
        if (showingToast || !queue.length || !window.bootstrap?.Toast) return;
        showingToast = true;
        const order = queue.shift();
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.setAttribute('role', 'status');
        const header = document.createElement('div');
        header.className = 'toast-header';
        const title = document.createElement('strong');
        title.className = 'me-auto';
        title.textContent = 'Pesanan Baru';
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.dataset.bsDismiss = 'toast';
        close.setAttribute('aria-label', 'Tutup notifikasi');
        header.append(title, close);
        const body = document.createElement('div');
        body.className = 'toast-body';
        const table = document.createElement('strong');
        table.textContent = `Meja ${order.table}`;
        const reference = document.createElement('p');
        reference.className = 'fw-semibold text-break mb-2';
        reference.textContent = order.customer_name;
        toast.dataset.orderId = order.id;
        const summary = document.createElement('p');
        summary.textContent = `${order.quantity} item • Rp${currency.format(Number(order.total))}`;
        body.append(reference, table, summary);
        toast.append(header, body);
        notifications.append(toast);
        const instance = new bootstrap.Toast(toast, { delay: 9000 });
        toast.addEventListener('hidden.bs.toast', () => {
            instance.dispose();
            toast.remove();
            showingToast = false;
            showNextToast();
        }, { once: true });
        instance.show();
    }
    let pending = false;
    async function refresh() {
        if (pending || document.hidden || list.contains(document.activeElement)) return;
        pending = true;
        try {
            const response = await fetch(location.href, {
                headers: { 'X-Orders-Partial': '1', 'X-Order-Monitor': monitor, Accept: 'application/json' },
                cache: 'no-store', signal: AbortSignal.timeout(7000),
            });
            if (!response.ok || response.redirected) throw new Error('Refresh failed');
            const data = await response.json();
            monitor = data.monitor;
            if (!list.contains(document.activeElement)) list.innerHTML = data.html;
            for (const order of data.notifications) {
                if (knownOrders.has(order.id)) continue;
                knownOrders.add(order.id);
                queue.push(order);
            }
            showNextToast();
            status.textContent = `Diperbarui ${new Date().toLocaleTimeString('id-ID')}. Otomatis setiap 8 detik.`;
        } catch {
            status.textContent = 'Pembaruan terhenti sementara. Akan dicoba kembali otomatis.';
        } finally {
            pending = false;
        }
    }
    setInterval(refresh, 8000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
