(() => {
    'use strict';
    const code = document.getElementById('product_code');
    if (!code?.dataset.previewUrl) return;
    const category = document.getElementById('category_id');
    const status = document.getElementById('product-code-status');
    let version = 0;
    let controller;

    async function preview() {
        const current = ++version;
        controller?.abort();
        code.value = '';
        status.textContent = '';
        status.className = 'small mt-1 text-secondary';
        code.removeAttribute('aria-busy');
        if (!category.value) return;
        controller = new AbortController();
        code.setAttribute('aria-busy', 'true');
        status.textContent = 'Menyiapkan kode produk…';
        try {
            const url = new URL(code.dataset.previewUrl, location.href);
            url.searchParams.set('category_id', category.value);
            const response = await fetch(url, {
                headers: { Accept: 'application/json' }, cache: 'no-store',
                signal: AbortSignal.any([controller.signal, AbortSignal.timeout(7000)]),
            });
            if (current !== version) return;
            if (response.status === 422) {
                const data = await response.json();
                if (current !== version) return;
                status.textContent = data.errors?.category_id?.[0] || 'Kategori tidak dapat digunakan untuk membuat kode.';
                status.className = 'small mt-1 text-danger';
                return;
            }
            if (!response.ok || response.redirected) throw new Error('Preview unavailable');
            const data = await response.json();
            if (current !== version) return;
            code.value = data.product_code;
            status.textContent = 'Kode final dapat berubah jika admin lain menyimpan produk lebih dahulu.';
        } catch {
            if (current !== version) return;
            status.textContent = 'Preview belum tersedia. Pilih ulang kategori untuk mencoba lagi. Kode tetap dihitung saat disimpan.';
            status.className = 'small mt-1 text-danger';
        } finally {
            if (current === version) code.removeAttribute('aria-busy');
        }
    }

    category.addEventListener('change', preview);
    window.addEventListener('pageshow', preview);
})();
