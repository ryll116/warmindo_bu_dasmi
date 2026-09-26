(() => {
    'use strict';
    const form = document.getElementById('manual-order-form');
    if (!form) return;
    const rows = [...form.querySelectorAll('[data-manual-product]')];
    const summary = document.getElementById('manual-summary-items');
    const inputs = document.getElementById('manual-item-inputs');
    const feedback = document.getElementById('manual-order-feedback');
    const submit = form.querySelector('button[type="submit"]');
    const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 });
    const quantity = row => Number(row.querySelector('input').value);
    const selected = () => rows.filter(row => quantity(row) > 0);
    function render() {
        summary.replaceChildren();
        inputs.replaceChildren();
        let total = 0;
        let count = 0;
        selected().forEach((row, index) => {
            const qty = quantity(row);
            const cents = Number(row.dataset.price.replace('.', ''));
            total += cents * qty;
            count += qty;
            const line = document.createElement('div');
            line.className = 'border-bottom py-3';
            const name = document.createElement('strong');
            name.className = 'd-block text-break';
            name.textContent = row.dataset.name;
            const resto = document.createElement('small');
            resto.className = 'd-block text-secondary';
            resto.textContent = row.dataset.resto || 'Resto belum ditentukan';
            const amount = document.createElement('p');
            amount.className = 'my-2';
            amount.textContent = `${qty} × ${money.format(cents / 100)} = ${money.format(cents * qty / 100)}`;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-outline-danger btn-sm';
            remove.textContent = 'Hapus';
            remove.setAttribute('aria-label', `Hapus ${row.dataset.name} ${row.dataset.resto}`);
            remove.addEventListener('click', () => { row.querySelector('input').value = 0; render(); });
            line.append(name, resto, amount, remove);
            summary.append(line);
            for (const [key, value] of Object.entries({ product_id: row.dataset.productId, quantity: qty })) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `items[${index}][${key}]`;
                input.value = value;
                inputs.append(input);
            }
        });
        if (!count) summary.textContent = 'Belum ada menu. Tekan + untuk menambahkan.';
        document.getElementById('manual-total').textContent = money.format(total / 100);
        document.getElementById('manual-item-count').textContent = `${count} item`;
        submit.disabled = !!form.dataset.submitting || !count || selected().length > 100;
        for (const row of rows) {
            row.querySelector('[data-quantity-change="-1"]').disabled = quantity(row) <= 0;
            row.querySelector('[data-quantity-change="1"]').disabled = quantity(row) >= 99;
        }
    }
    for (const row of rows) {
        const input = row.querySelector('input');
        row.querySelectorAll('[data-quantity-change]').forEach(button => button.addEventListener('click', () => {
            input.value = Math.max(0, Math.min(99, (Number(input.value) || 0) + Number(button.dataset.quantityChange)));
            render();
        }));
        input.addEventListener('input', () => {
            if (input.validity.valid && Number.isInteger(Number(input.value))) render();
        });
    }
    function filter() {
        const search = document.getElementById('manual-search').value.trim().toLocaleLowerCase('id-ID');
        const category = document.getElementById('manual-category').value;
        for (const row of rows) {
            row.hidden = !`${row.dataset.name} ${row.dataset.resto}`.toLocaleLowerCase('id-ID').includes(search)
                || (!!category && category !== row.dataset.category);
            row.classList.toggle('d-none', row.hidden);
        }
        document.getElementById('manual-empty').hidden = rows.some(row => !row.hidden);
    }
    document.getElementById('manual-search').addEventListener('input', filter);
    document.getElementById('manual-category').addEventListener('change', filter);
    const oldItems = JSON.parse(document.getElementById('manual-old-items').textContent);
    for (const item of Object.values(oldItems || {})) {
        const row = rows.find(row => row.dataset.productId === String(item?.product_id));
        if (row) row.querySelector('input').value = Math.max(0, Math.min(99, Math.trunc(Number(item.quantity) || 0)));
        else {
            feedback.textContent = 'Ada menu yang tidak tersedia lagi dan dikeluarkan dari pesanan. Periksa kembali ringkasan.';
            feedback.classList.remove('d-none');
        }
    }
    form.addEventListener('submit', event => {
        if (!selected().length || selected().length > 100) {
            event.preventDefault();
            feedback.textContent = 'Pilih minimal satu menu, maksimal 100 jenis menu.';
            feedback.classList.remove('d-none');
        }
    });
    window.addEventListener('pageshow', () => queueMicrotask(render));
    render();
})();
