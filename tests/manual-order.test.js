import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

class Element {
    constructor() {
        this.children = [];
        this.listeners = {};
        this.dataset = {};
        this.value = '';
        this.validity = { valid: true };
        this.classList = { remove() {}, toggle() {} };
    }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren() { this.children = []; }
    setAttribute() {}
    addEventListener(type, fn) { (this.listeners[type] ??= []).push(fn); }
    fire(type, event = {}) { for (const fn of this.listeners[type] ?? []) fn(event); }
}

function screen(oldItems = []) {
    const ids = Object.fromEntries(['manual-order-form', 'manual-summary-items', 'manual-item-inputs', 'manual-order-feedback', 'manual-total', 'manual-item-count', 'manual-search', 'manual-category', 'manual-empty', 'manual-old-items'].map(id => [id, new Element()]));
    ids['manual-old-items'].textContent = JSON.stringify(oldItems);
    const submit = new Element();
    const rows = ['1', '2'].map((id, index) => {
        const row = new Element();
        row.dataset = { productId: id, name: 'Nasi Goreng', resto: `Resto ${index ? 'B' : 'A'}`, price: index ? '22000.50' : '20000.25', category: id };
        const input = new Element();
        input.value = 0;
        const minus = new Element();
        minus.dataset.quantityChange = '-1';
        const plus = new Element();
        plus.dataset.quantityChange = '1';
        row.querySelector = selector => selector === 'input' ? input : selector.includes('-1') ? minus : plus;
        row.querySelectorAll = () => [minus, plus];
        return row;
    });
    const form = ids['manual-order-form'];
    form.querySelectorAll = () => rows;
    form.querySelector = () => submit;
    const window = new Element();
    vm.runInNewContext(readFileSync(new URL('../public/js/manual-order.js', import.meta.url), 'utf8'), {
        document: { getElementById: id => ids[id], createElement: () => new Element() },
        Intl, window, queueMicrotask,
    });
    return { ids, rows, submit, form, window, add: index => rows[index].querySelector('[data-quantity-change="1"]').fire('click') };
}

test('same-name products stay separate by ID, totals use cents, and removing one preserves the other', () => {
    const page = screen();
    assert.equal(page.submit.disabled, true);
    page.add(0);
    page.add(0);
    page.add(1);
    const inputs = page.ids['manual-item-inputs'].children;
    assert.deepEqual(inputs.map(input => [input.name, input.value]), [
        ['items[0][product_id]', '1'], ['items[0][quantity]', 2],
        ['items[1][product_id]', '2'], ['items[1][quantity]', 1],
    ]);
    assert.match(page.ids['manual-total'].textContent, /62\.001/);
    assert.equal(page.submit.disabled, false);
    page.ids['manual-summary-items'].children[0].children[3].fire('click');
    assert.equal(page.ids['manual-item-count'].textContent, '1 item');
    assert.equal(page.ids['manual-item-inputs'].children[0].value, '2');
});

test('search and category filter do not discard selected products', () => {
    const page = screen();
    page.add(0);
    page.ids['manual-search'].value = 'resto b';
    page.ids['manual-search'].fire('input');
    assert.equal(page.rows[0].hidden, true);
    assert.equal(page.rows[1].hidden, false);
    assert.equal(page.ids['manual-item-count'].textContent, '1 item');
    page.ids['manual-category'].value = '1';
    page.ids['manual-category'].fire('change');
    assert.equal(page.ids['manual-empty'].hidden, false);
});

test('failed submission restores available cart and warns about removed products', () => {
    const page = screen([{ product_id: 1, quantity: 3 }, { product_id: 999, quantity: 2 }]);
    assert.equal(page.ids['manual-item-count'].textContent, '3 item');
    assert.match(page.ids['manual-order-feedback'].textContent, /tidak tersedia/);
    assert.equal(page.ids['manual-item-inputs'].children.length, 2);
});

test('quantity is capped and submitting state keeps submit disabled', async () => {
    const page = screen([{ product_id: 1, quantity: 99 }]);
    page.add(0);
    assert.equal(page.ids['manual-item-count'].textContent, '99 item');
    page.form.dataset.submitting = 'true';
    page.add(1);
    assert.equal(page.submit.disabled, true);
    delete page.form.dataset.submitting;
    page.window.fire('pageshow');
    await new Promise(resolve => queueMicrotask(resolve));
    assert.equal(page.submit.disabled, false);
});
