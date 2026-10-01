import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function screen({ locked = false, secure = true, supported = true, confirmed = true } = {}) {
    const listeners = {};
    const requests = [];
    const inputs = [];
    const button = { dataset: { shiftLocked: String(locked) }, disabled: locked, textContent: 'Absen Pulang' };
    const message = { hidden: true, textContent: '' };
    let submissions = 0;
    const form = {
        dataset: { confirm: 'Konfirmasi pulang?' },
        querySelector: selector => selector === '[data-location-error]' ? message : button,
        querySelectorAll: () => [...inputs],
        appendChild: input => inputs.push(input),
        addEventListener: (name, fn) => { listeners[name] = fn; },
    };
    vm.runInNewContext(readFileSync(new URL('../public/js/attendance.js', import.meta.url), 'utf8'), {
        document: {
            querySelectorAll: () => [form],
            createElement: () => {
                const input = { dataset: {}, remove() { inputs.splice(inputs.indexOf(input), 1); } };
                return input;
            },
        },
        window: { isSecureContext: secure, confirm: () => confirmed, addEventListener: (name, fn) => { listeners[name] = fn; } },
        navigator: supported ? { geolocation: { getCurrentPosition: (success, error, options) => requests.push({ success, error, options }) } } : {},
        HTMLFormElement: { prototype: { submit() { submissions++; } } },
    });
    return { requests, button, message, inputs, listeners, submit: () => listeners.submit({ preventDefault() {} }), get submissions() { return submissions; } };
}

test('location is only requested on submit and double click is blocked', () => {
    const page = screen();
    assert.equal(page.requests.length, 0);
    page.submit();
    page.submit();
    assert.equal(page.requests.length, 1);
    assert.equal(page.button.disabled, true);
    assert.equal(page.button.textContent, 'Mengambil lokasi...');
    assert.equal(page.requests[0].options.maximumAge, 0);
    assert.equal(page.requests[0].options.timeout, 15000);
    assert.equal(page.submissions, 0);
});

test('successful location submits coordinates and accuracy without client radius decision', () => {
    const page = screen();
    page.submit();
    page.requests[0].success({ coords: { latitude: -6.2, longitude: 106.8, accuracy: 500 } });
    assert.equal(page.submissions, 1);
    assert.deepEqual(page.inputs.map(input => [input.name, input.value]), [['latitude', -6.2], ['longitude', 106.8], ['accuracy', 500]]);
    assert.equal(page.button.textContent, 'Menyimpan...');
    assert.equal(page.button.disabled, true);
});

for (const [code, text] of [[1, 'Akses lokasi diperlukan'], [2, 'Lokasi perangkat tidak dapat ditentukan'], [3, 'Tidak dapat memperoleh lokasi']]) {
    test(`location error ${code} prevents attendance and restores retry button`, () => {
        const page = screen();
        page.submit();
        page.requests[0].error({ code, message: 'private browser error' });
        assert.equal(page.submissions, 0);
        assert.equal(page.button.disabled, false);
        assert.equal(page.message.hidden, false);
        assert.ok(page.message.textContent.startsWith(text));
        assert.equal(page.inputs.length, 0);
        page.submit();
        assert.equal(page.requests.length, 2);
    });
}

test('insecure or unsupported context cannot submit attendance', () => {
    for (const options of [{ secure: false }, { supported: false }]) {
        const page = screen(options);
        page.submit();
        assert.equal(page.requests.length, 0);
        assert.equal(page.submissions, 0);
        assert.equal(page.message.hidden, false);
        assert.equal(page.button.disabled, false);
    }
});

test('shift lock and cancelled confirmation prevent location request', () => {
    for (const options of [{ locked: true }, { confirmed: false }]) {
        const page = screen(options);
        page.listeners.pageshow();
        page.submit();
        assert.equal(page.requests.length, 0);
        assert.equal(page.submissions, 0);
    }
});

test('returning to page removes old coordinates and requests a fresh location', () => {
    const page = screen();
    page.submit();
    page.requests[0].success({ coords: { latitude: 0, longitude: 0, accuracy: 12 } });
    page.listeners.pageshow();
    assert.equal(page.inputs.length, 0);
    assert.equal(page.button.disabled, false);
    page.submit();
    page.requests[1].success({ coords: { latitude: 0.001, longitude: 0, accuracy: 14 } });
    assert.equal(page.inputs[0].value, 0.001);
    assert.equal(page.submissions, 2);
});

test('stale location callback after page restoration does not submit', () => {
    const page = screen();
    page.submit();
    page.listeners.pageshow();
    page.requests[0].success({ coords: { latitude: 0, longitude: 0, accuracy: 1 } });
    assert.equal(page.submissions, 0);
});
