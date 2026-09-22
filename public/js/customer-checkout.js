(() => {
    'use strict';

    for (const form of document.querySelectorAll('#checkout-start, #checkout-submit')) {
        let submitting = false;
        const button = form.querySelector('button[type="submit"]');
        const originalLabel = button.textContent;
        let originallyDisabled = button.disabled;
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            if (submitting) {
                event.preventDefault();
                return;
            }
            submitting = true;
            originallyDisabled = button.disabled;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.textContent = 'Memproses…';
        });
        window.addEventListener('pageshow', () => {
            if (!submitting) return;
            submitting = false;
            button.disabled = originallyDisabled;
            button.removeAttribute('aria-busy');
            button.textContent = originalLabel;
        });
    }

    const success = document.getElementById('checkout-success');
    if (success?.dataset.clearCart === 'true') {
        try {
            localStorage.removeItem(`warmindo:cart:v1:${success.dataset.token}`);
        } catch {
            document.getElementById('checkout-storage-message').hidden = false;
        }
    }
})();
