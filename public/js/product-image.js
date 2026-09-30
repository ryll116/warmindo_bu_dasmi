(() => {
    'use strict';
    const input = document.getElementById('product-image');
    if (!input) return;
    const preview = document.getElementById('product-image-preview');
    const remove = document.getElementById('remove-image');
    const feedback = document.getElementById('product-image-feedback');
    let objectUrl;

    function render() {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = undefined;
        feedback.textContent = '';
        preview.src = remove?.checked ? preview.dataset.placeholder : preview.dataset.existingSrc;
        const file = input.files[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            input.value = '';
            feedback.textContent = 'Pilih foto JPG, JPEG, PNG, atau WebP maksimal 2 MB.';
            return;
        }
        if (remove) remove.checked = false;
        objectUrl = URL.createObjectURL(file);
        preview.src = objectUrl;
    }
    input.addEventListener('change', render);
    remove?.addEventListener('change', () => {
        if (remove.checked) input.value = '';
        render();
    });
    window.addEventListener('pageshow', render);
    window.addEventListener('pagehide', () => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
})();
