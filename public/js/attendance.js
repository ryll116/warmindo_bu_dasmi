document.querySelectorAll('[data-attendance-form]').forEach((form) => {
    const button = form.querySelector('button[type="submit"]');
    const message = form.querySelector('[data-location-error]');
    let attempt = 0;
    button.dataset.originalText = button.textContent;

    function reset() {
        form.dataset.submitting = 'false';
        button.disabled = button.dataset.shiftLocked === 'true';
        button.textContent = button.dataset.originalText;
        form.querySelectorAll('[data-location-input]').forEach(input => input.remove());
    }

    function fail(text) {
        reset();
        message.textContent = text;
        message.hidden = false;
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (form.dataset.submitting === 'true' || button.dataset.shiftLocked === 'true') return;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;

        message.hidden = true;
        if (!window.isSecureContext) {
            fail('Akses lokasi memerlukan HTTPS. Gunakan alamat website yang aman atau localhost untuk development.');
            return;
        }
        if (!navigator.geolocation) {
            fail('Perangkat/browser Anda tidak mendukung akses lokasi.');
            return;
        }

        form.dataset.submitting = 'true';
        button.disabled = true;
        button.textContent = 'Mengambil lokasi...';
        const currentAttempt = ++attempt;

        try {
            navigator.geolocation.getCurrentPosition((position) => {
                if (currentAttempt !== attempt) return;
                const values = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                };
                for (const [name, value] of Object.entries(values)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    input.dataset.locationInput = 'true';
                    form.appendChild(input);
                }
                button.textContent = 'Menyimpan...';
                HTMLFormElement.prototype.submit.call(form);
            }, (error) => {
                if (currentAttempt !== attempt) return;
                const messages = {
                    1: 'Akses lokasi diperlukan untuk melakukan absensi.',
                    2: 'Lokasi perangkat tidak dapat ditentukan.',
                    3: 'Tidak dapat memperoleh lokasi. Silakan coba kembali.',
                };
                fail(messages[error.code] || 'Tidak dapat memperoleh lokasi. Silakan coba kembali.');
            }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        } catch {
            fail('Tidak dapat memperoleh lokasi. Silakan coba kembali.');
        }
    });

    window.addEventListener('pageshow', () => {
        attempt++;
        reset();
    });
});
