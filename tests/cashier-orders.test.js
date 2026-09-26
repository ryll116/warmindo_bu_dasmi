import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../public/js/cashier-orders.js', import.meta.url), 'utf8');

function dashboard() {
    const listeners = {};
    const modalListeners = {};
    const requests = [];
    const button = { textContent: 'Konfirmasi Pesanan', disabled: false, setAttribute() {}, removeAttribute() {} };
    const form = { action: '/admin/orders/order/status', querySelector: () => button };
    const list = { dataset: { monitor: 'monitor' }, innerHTML: 'Pending', contains: () => false, querySelectorAll: () => [button] };
    const modal = { classList: { contains: () => false }, querySelectorAll: () => [], addEventListener: (name, callback) => { modalListeners[name] = callback; } };
    const feedback = {};
    const status = {};
    const elements = { 'orders-list': list, 'dashboard-payment': modal, 'orders-action-feedback': feedback, 'orders-refresh-status': status };
    let interval;
    vm.runInNewContext(source, {
        document: {
            querySelectorAll: () => [], getElementById: id => elements[id], hidden: false,
            addEventListener: (name, callback) => { listeners[name] = callback; },
        },
        window: {}, location: { href: '/admin/orders?tab=active' }, Intl, Date, Error, TypeError, SyntaxError,
        FormData: class {}, AbortSignal: { timeout() {} },
        setInterval: (callback, milliseconds) => { assert.equal(milliseconds, 8000); interval = callback; },
        fetch: (url, options) => new Promise(resolve => { requests.push({ url, options, resolve }); }),
    });
    const submit = () => listeners.submit({ target: { closest: () => form }, preventDefault() {} });
    const respond = (index, body, code = 200) => requests[index].resolve({ ok: code === 200, status: code, json: async () => body });
    return { requests, button, list, feedback, status, submit, respond, poll: () => interval() };
}

const flush = () => new Promise(resolve => setImmediate(resolve));

test('double submit sends one write and refreshes through the existing filtered polling endpoint', async () => {
    const page = dashboard();
    const action = page.submit();
    await page.submit();
    assert.equal(page.requests.length, 1);
    assert.equal(page.button.disabled, true);
    assert.equal(page.requests[0].options.method, 'POST');
    page.respond(0, { message: 'Berhasil' });
    await flush();
    assert.equal(page.requests[1].url, '/admin/orders?tab=active');
    assert.equal(page.requests[1].options.headers['X-Orders-Partial'], '1');
    page.respond(1, { html: 'Confirmed', monitor: 'monitor', notifications: [] });
    await action;
    assert.equal(page.list.innerHTML, 'Confirmed');
    assert.equal(page.button.disabled, false);
    assert.equal(page.feedback.className, 'alert alert-success');
});

test('stale validation failure restores controls and refreshes authoritative state', async () => {
    const page = dashboard();
    const action = page.submit();
    page.respond(0, { errors: { order_status: ['Status sudah berubah.'] } }, 422);
    await flush();
    page.respond(1, { html: 'Processing', monitor: 'monitor', notifications: [] });
    await action;
    assert.equal(page.feedback.textContent, 'Status sudah berubah.');
    assert.equal(page.feedback.className, 'alert alert-danger');
    assert.equal(page.button.disabled, false);
    assert.equal(page.list.innerHTML, 'Processing');
});

test('a delayed poll cannot overwrite the result of a newer action', async () => {
    const page = dashboard();
    const poll = page.poll();
    const action = page.submit();
    page.respond(1, { message: 'Berhasil' });
    await flush();
    page.respond(2, { html: 'Confirmed', monitor: 'monitor', notifications: [] });
    await action;
    page.respond(0, { html: 'Pending', monitor: 'monitor', notifications: [] });
    await poll;
    assert.equal(page.list.innerHTML, 'Confirmed');
});

test('server errors stay generic and the action can be retried', async () => {
    const page = dashboard();
    const action = page.submit();
    page.respond(0, { message: 'SQLSTATE secret exception' }, 500);
    await flush();
    page.respond(1, { html: 'Pending', monitor: 'monitor', notifications: [] });
    await action;
    assert.equal(page.feedback.className, 'alert alert-danger');
    assert.doesNotMatch(page.feedback.textContent, /SQLSTATE|secret/);
    assert.equal(page.button.disabled, false);
    assert.equal(page.list.innerHTML, 'Pending');
});
