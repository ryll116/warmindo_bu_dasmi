(() => {
    'use strict';

    const config = window.warmindoMenu;
    if (!config) return;

    const catalog = config.catalog;
    const storageKey = `warmindo:cart:v1:${config.token}`;
    const limit = 99;
    const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
    const feedback = document.getElementById('cart-feedback');
    const itemsContainer = document.getElementById('cart-items');
    const drawer = document.getElementById('cart-drawer');
    let storageAvailable = true;

    function money(cents) {
        const fraction = cents % 100n;
        return `Rp ${numberFormat.format(cents / 100n)}${fraction ? ',' + fraction.toString().padStart(2, '0') : ''}`;
    }

    function price(id) {
        const [whole, fraction = ''] = catalog[id].price.split('.');
        return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0').slice(0, 2));
    }

    function normalize(raw) {
        const result = {};
        if (!raw || raw.version !== 1 || !raw.items || typeof raw.items !== 'object') return result;
        for (const [id, quantity] of Object.entries(raw.items)) {
            if (/^[1-9]\d*$/.test(id) && Object.hasOwn(catalog, id) && Number.isInteger(quantity) && quantity > 0) {
                result[id] = Math.min(limit, quantity);
            }
        }
        return result;
    }

    function load() {
        try {
            return normalize(JSON.parse(localStorage.getItem(storageKey)));
        } catch {
            return {};
        }
    }

    let cart = load();

    function persist() {
        try {
            localStorage.setItem(storageKey, JSON.stringify({ version: 1, items: cart }));
        } catch {
            storageAvailable = false;
            feedback.textContent = 'Penyimpanan browser tidak tersedia. Keranjang hanya bertahan di halaman ini.';
        }
    }

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function createRow(id) {
        const row = element('article', 'cart-item');
        row.dataset.cartRow = id;
        const detail = element('div');
        detail.append(element('h3', '', catalog[id].name), element('p', '', `${money(price(id))} / porsi`));
        const controls = element('div', 'cart-item-controls');
        for (const action of ['decrease', 'quantity', 'increase']) {
            if (action === 'quantity') {
                const output = element('output', 'quantity-value');
                output.setAttribute('aria-label', `Jumlah ${catalog[id].name}`);
                controls.append(output);
            } else {
                const button = element('button', `quantity-button ${action}`, action === 'increase' ? '+' : '−');
                button.type = 'button';
                button.dataset.cartAction = action;
                button.dataset.id = id;
                button.setAttribute('aria-label', `${action === 'increase' ? 'Tambah' : 'Kurangi'} ${catalog[id].name}`);
                controls.append(button);
            }
        }
        row.append(detail, controls, element('strong', 'cart-line-total'));
        return row;
    }

    function render() {
        let quantity = 0;
        let total = 0n;
        for (const [id, count] of Object.entries(cart)) {
            quantity += count;
            total += price(id) * BigInt(count);
        }

        document.getElementById('cart-bar').hidden = quantity === 0;
        document.getElementById('cart-footer').hidden = quantity === 0;
        document.getElementById('cart-empty').hidden = quantity !== 0;
        document.getElementById('cart-count').textContent = `${quantity} item`;
        document.getElementById('cart-total').textContent = money(total);
        document.getElementById('drawer-total').textContent = money(total);

        document.querySelectorAll('[data-quantity-control]').forEach(control => {
            const count = cart[control.dataset.quantityControl] || 0;
            const decrease = control.querySelector('.decrease');
            const output = control.querySelector('output');
            decrease.hidden = count === 0;
            decrease.disabled = count === 0;
            output.hidden = count === 0;
            output.textContent = count;
            control.querySelector('.increase').disabled = count >= limit;
        });

        itemsContainer.querySelectorAll('[data-cart-row]').forEach(row => {
            if (!cart[row.dataset.cartRow]) {
                const containedFocus = row.contains(document.activeElement);
                row.remove();
                if (containedFocus) drawer.querySelector('.btn-close').focus();
            }
        });

        for (const [id, count] of Object.entries(cart)) {
            let row = itemsContainer.querySelector(`[data-cart-row="${id}"]`);
            if (!row) {
                row = createRow(id);
                itemsContainer.append(row);
            }
            row.querySelector('output').textContent = count;
            row.querySelector('.increase').disabled = count >= limit;
            row.querySelector('.cart-line-total').textContent = money(price(id) * BigInt(count));
        }
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-cart-action]');
        if (!button || button.disabled || !Object.hasOwn(catalog, button.dataset.id)) return;
        const id = button.dataset.id;
        const count = Math.max(0, Math.min(limit, (cart[id] || 0) + (button.dataset.cartAction === 'increase' ? 1 : -1)));
        if (count) cart[id] = count;
        else delete cart[id];
        persist();
        render();
        if (storageAvailable) feedback.textContent = `${catalog[id].name}: ${count} porsi di keranjang.`;
    });

    window.addEventListener('storage', event => {
        if (event.key !== storageKey && event.key !== null) return;
        cart = load();
        render();
    });

    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            cart = load();
            render();
        }
    });

    const header = document.querySelector('.menu-header');
    new ResizeObserver(() => {
        document.documentElement.style.setProperty('--menu-header-height', `${header.offsetHeight}px`);
    }).observe(header);

    persist();
    render();
})();
