import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function screen(edit = false) {
    const listeners = {};
    const requests = [];
    const attributes = {};
    const code = { value: edit ? 'MNU-007' : '', dataset: edit ? {} : { previewUrl: '/admin/products/next-code' }, setAttribute: (key, value) => { attributes[key] = value; }, removeAttribute: key => { delete attributes[key]; } };
    const category = { value: '', addEventListener: (name, fn) => { listeners[name] = fn; } };
    const status = {};
    vm.runInNewContext(readFileSync(new URL('../public/js/product-code.js', import.meta.url), 'utf8'), {
        document: { getElementById: id => ({ product_code: code, category_id: category, 'product-code-status': status })[id] },
        window: { addEventListener: (name, fn) => { listeners[name] = fn; } },
        location: { href: 'https://warmindo.test/admin/products/create' }, URL, AbortController, AbortSignal,
        fetch: (url, options) => new Promise((resolve, reject) => { requests.push({ url, options, resolve, reject }); }),
    });
    const choose = value => { category.value = value; return listeners.change(); };
    const respond = (index, body, status = 200) => requests[index].resolve({ status, ok: status === 200, json: async () => body });
    return { code, status, requests, listeners, choose, respond, attributes };
}

test('empty category clears preview without sending a request', async () => {
    const page = screen();
    await page.choose('');
    assert.equal(page.code.value, '');
    assert.equal(page.requests.length, 0);
});

test('preview is read-only GET with selected category and no cache', async () => {
    const page = screen();
    const pending = page.choose('12');
    assert.equal(page.requests[0].url.searchParams.get('category_id'), '12');
    assert.equal(page.requests[0].options.cache, 'no-store');
    assert.equal(page.requests[0].options.method, undefined);
    page.respond(0, { product_code: 'MNU-010' });
    await pending;
    assert.equal(page.code.value, 'MNU-010');
    assert.equal(page.attributes['aria-busy'], undefined);
});

test('a delayed response cannot replace preview for a newer category', async () => {
    const page = screen();
    const first = page.choose('1');
    const second = page.choose('2');
    page.respond(1, { product_code: 'MNM-004' });
    await second;
    page.respond(0, { product_code: 'MNU-010' });
    await first;
    assert.equal(page.code.value, 'MNM-004');
});

test('validation and network errors clear stale code and remain user-friendly', async () => {
    const page = screen();
    page.code.value = 'MNU-010';
    const invalid = page.choose('3');
    page.respond(0, { errors: { category_id: ['Kode kategori belum tersedia.'] } }, 422);
    await invalid;
    assert.equal(page.code.value, '');
    assert.equal(page.status.textContent, 'Kode kategori belum tersedia.');
    const failed = page.choose('4');
    page.requests[1].reject(new Error('Internal exception'));
    await failed;
    assert.match(page.status.textContent, /Preview belum tersedia/);
    assert.doesNotMatch(page.status.textContent, /Internal exception/);
});

test('edit form does not register preview handlers or replace existing code', () => {
    const page = screen(true);
    assert.equal(page.code.value, 'MNU-007');
    assert.equal(Object.keys(page.listeners).length, 0);
    assert.equal(page.requests.length, 0);
});
