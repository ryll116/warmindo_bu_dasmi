(() => {
    'use strict';

    for (const form of document.querySelectorAll('#checkout-start, #checkout-submit')) {
        let submitting = false;
        const button = form.querySelector('button[type="submit"]');
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            if (submitting) {
                event.preventDefault();
                return;
            }
            submitting = true;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
        window.addEventListener('pageshow', () => {
            submitting = false;
            button.disabled = false;
            button.removeAttribute('aria-busy');
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
