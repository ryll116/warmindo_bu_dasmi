import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

async function fixture(file, { disabled = false, confirm = true, cancelled = false } = {}) {
    const button = {
        disabled, textContent: 'Simpan', attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
    };
    const form = new EventTarget();
    form.dataset = { confirm: 'Lanjutkan?' };
    form.querySelector = () => button;
    if (cancelled) form.addEventListener('submit', event => event.preventDefault());
    const window = new EventTarget();
    window.confirm = () => confirm;
    const document = { querySelectorAll: () => [form], getElementById: () => null };
    runInNewContext(await readFile(new URL('../../public/js/' + file, import.meta.url), 'utf8'), { document, window });
    const submit = () => form.dispatchEvent(new Event('submit', { cancelable: true }));
    return { button, form, window, submit };
}

for (const file of ['customer-checkout.js', 'cashier-orders.js']) {
    test(file + ': loading, duplicate prevention and recovery on pageshow', async () => {
        const { button, window, submit } = await fixture(file);
        assert.equal(submit(), true);
        assert.equal(button.disabled, true);
        assert.equal(button.textContent, 'Memproses…');
        assert.equal(button.attributes['aria-busy'], 'true');
        assert.equal(submit(), false);
        window.dispatchEvent(new Event('pageshow'));
        assert.equal(button.disabled, false);
        assert.equal(button.textContent, 'Simpan');
        assert.equal(button.attributes['aria-busy'], undefined);
        assert.equal(submit(), true);
    });
    test(file + ': cancelled submission remains usable', async () => {
        const { button, submit } = await fixture(file, { cancelled: true });
        assert.equal(submit(), false);
        assert.equal(button.disabled, false);
        assert.equal(button.textContent, 'Simpan');
    });
    test(file + ': pageshow preserves business-disabled buttons', async () => {
        const { button, window } = await fixture(file, { disabled: true });
        window.dispatchEvent(new Event('pageshow'));
        assert.equal(button.disabled, true);
    });
}

test('cashier: cancelling destructive confirmation does not lock the button', async () => {
    const { button, submit } = await fixture('cashier-orders.js', { confirm: false });
    assert.equal(submit(), false);
    assert.equal(button.disabled, false);
    assert.equal(button.textContent, 'Simpan');
});
