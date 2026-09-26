import { test } from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

test('customer cart uses server catalog promo price, replaces stored prices, and submits only identity and quantity', () => {
    class Element {
        constructor(tag = 'div') { this.tag = tag; this.children = []; this.dataset = {}; this.listeners = {}; }
        append(...nodes) { this.children.push(...nodes); }
        replaceChildren() { this.children = []; }
        setAttribute() {}
        addEventListener(name, callback) { this.listeners[name] = callback; }
        querySelectorAll(selector) {
            const matches = node => selector === '[data-cart-row]' ? !!node.dataset.cartRow
                : selector.startsWith('.') ? (node.className || '').split(' ').includes(selector.slice(1)) : node.tag === selector;
            return this.children.flatMap(node => [...(matches(node) ? [node] : []), ...node.querySelectorAll(selector)]);
        }
        querySelector(selector) {
            if (selector.startsWith('[data-cart-row=')) return this.children.find(node => node.dataset.cartRow === '1');
            return this.querySelectorAll(selector)[0];
        }
    }
    const ids = {};
    const get = id => ids[id] ??= new Element();
    let stored = JSON.stringify({ version: 2, items: { 1: { quantity: 2, price: '1.00', subtotal: '2.00', disc: 99 } } });
    vm.runInNewContext(readFileSync(new URL('../public/js/customer-menu.js', import.meta.url), 'utf8'), {
        window: { warmindoMenu: { token: 'table', catalog: { 1: { name: 'Coto', price: '25500.00' } } }, addEventListener() {} },
        document: { getElementById: get, createElement: tag => new Element(tag), querySelectorAll: () => [], querySelector: () => new Element(), addEventListener() {} },
        localStorage: { getItem: () => stored, setItem: (key, value) => { stored = value; } },
        ResizeObserver: class { observe() {} }, Intl,
    });
    assert.equal(get('cart-total').textContent, 'Rp51.000');
    assert.equal(get('drawer-total').textContent, 'Rp51.000');
    assert.equal(JSON.parse(stored).items[1].price, '25500.00');
    assert.equal(JSON.parse(stored).items[1].subtotal, '51000.00');
    get('checkout-start').listeners.submit({ preventDefault() { assert.fail('Cart should not be empty'); } });
    assert.deepEqual(get('checkout-items').children.map(input => [input.name, input.value]), [
        ['items[0][product_id]', '1'], ['items[0][quantity]', 2],
    ]);
});
