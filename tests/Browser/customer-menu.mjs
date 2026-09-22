import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { spawn } from 'node:child_process';
import { mkdir, mkdtemp, readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import net from 'node:net';

const root = process.cwd();
const browserPassword = randomBytes(24).toString('hex');
const output = await mkdtemp(path.join(tmpdir(), 'warmindo-mobile-'));
await writeFile(path.join(output, 'browser.sqlite'), '');
await mkdir(path.join(output, 'sessions'));
const chromePath = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(check, message, timeout = 20000) {
    const end = Date.now() + timeout;
    let last;
    while (Date.now() < end) {
        try { const value = await check(); if (value) return value; } catch (error) { last = error; }
        await sleep(100);
    }
    throw new Error(message + (last ? ': ' + last.message : ''));
}
const probe = net.createServer();
await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
const port = probe.address().port;
await new Promise(resolve => probe.close(resolve));
const origin = 'http://127.0.0.1:' + port;
const server = spawn('php', ['-d', 'extension=pdo_sqlite', '-S', '127.0.0.1:' + port, '-t', 'public', 'tests/Browser/customer-menu-router.php'], { cwd: root, env: { ...process.env, WARMINDO_BROWSER_DIR: output, WARMINDO_BROWSER_PASSWORD: browserPassword, APP_DEMO_MODE: 'true' }, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
let logs = '';
server.stderr.on('data', chunk => { logs = (logs + chunk).slice(-6000); });
server.stdout.on('data', () => {});
const chrome = spawn(chromePath, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', '--user-data-dir=' + path.join(output, 'profile'), 'about:blank'], { windowsHide: true, stdio: 'ignore' });
let socket;
try {
    await until(async () => (await fetch(origin + '/menu/valid-menu-token')).ok, 'Fixture server did not start');
    const debugPort = await until(async () => (await readFile(path.join(output, 'profile', 'DevToolsActivePort'), 'utf8')).split('\n')[0], 'Chrome did not start');
    const pages = await (await fetch('http://127.0.0.1:' + debugPort + '/json/list')).json();
    socket = new WebSocket(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    let sequence = 0;
    const pending = new Map();
    const exceptions = [];
    socket.onmessage = event => {
        const message = JSON.parse(event.data);
        if (message.id) {
            const task = pending.get(message.id);
            if (!task) return;
            pending.delete(message.id);
            if (message.error) task.reject(new Error(JSON.stringify(message.error)));
            else task.resolve(message.result);
        } else if (message.method === 'Runtime.exceptionThrown') {
            exceptions.push(message.params.exceptionDetails.text);
        }
    };
    const cdp = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++sequence;
        pending.set(id, { resolve, reject });
        socket.send(JSON.stringify({ id, method, params }));
    });
    async function evaluate(expression) {
        const result = await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    }
    await cdp('Page.enable');
    await cdp('Runtime.enable');
    async function navigate(url, hasMenu = true) {
        await cdp('Page.navigate', { url });
        await until(async () => await evaluate('location.href === ' + JSON.stringify(url) + ' && document.readyState === "complete"'), 'Navigation failed: ' + url);
        if (hasMenu) {
            await until(async () => await evaluate('!!window.warmindoMenu && !!window.bootstrap && !!document.getElementById("cart-items")'), 'Menu scripts did not load');
        }
    }
    await navigate(origin + '/menu/valid-menu-token');
    await evaluate('localStorage.clear()');
    await cdp('Emulation.setDeviceMetricsOverride', { width: 360, height: 800, deviceScaleFactor: 1, mobile: true });
    await evaluate('document.getElementById("menu-search").value = "tELUr"; document.getElementById("menu-search").dispatchEvent(new Event("input"))');
    assert.equal(await evaluate('document.querySelectorAll(".menu-suggestion").length'), 6);
    assert.equal(await evaluate('document.querySelector(".menu-suggestion mark").textContent'), 'Telur');
    assert.equal(await evaluate('document.querySelector(".menu-suggestion small").textContent'), 'Rp15.000');
    assert.ok(await evaluate('(() => { const r = document.getElementById("menu-suggestions").getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth; })()'));
    await evaluate('document.querySelector(".menu-suggestion").click()');
    await until(() => evaluate('document.readyState === "complete" && new URLSearchParams(location.search).get("search") === "Indomie Goreng Telur Spesial 1" && document.querySelectorAll(".menu-product").length === 1'), 'Suggestion did not filter products');
    await navigate(origin + '/menu/valid-menu-token?category=2');
    await evaluate('document.getElementById("menu-search").value = "telur"; document.getElementById("menu-search").dispatchEvent(new Event("input"))');
    assert.equal(await evaluate('document.getElementById("menu-suggestions").hidden'), true, 'Suggestions ignored active category');
    await evaluate('document.getElementById("menu-search").value = "segar"; document.getElementById("menu-search").dispatchEvent(new Event("input"))');
    assert.equal(await evaluate('document.querySelectorAll(".menu-suggestion").length'), 6);
    await evaluate('document.body.dispatchEvent(new PointerEvent("pointerdown", {bubbles:true}))');
    assert.equal(await evaluate('document.getElementById("menu-suggestions").hidden'), true);
    await evaluate('document.getElementById("menu-search").dispatchEvent(new Event("input")); document.getElementById("menu-search").value = ""; document.getElementById("menu-search").dispatchEvent(new Event("input"))');
    assert.equal(await evaluate('document.getElementById("menu-suggestions").hidden'), true);
    console.log('PASS autocomplete matching, limit, highlight, selection, category, dismissal, mobile bounds');
    const viewportResults = [];
    assert.ok(await evaluate('document.querySelector(".demo-watermark").textContent.includes("DEMO VERSION")'));
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".demo-watermark")).pointerEvents'), 'none');
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".demo-watermark")).position'), 'fixed');
    for (const width of [360, 390, 430, 768, 1024, 1440]) {
        await cdp('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width <= 430 });
        await navigate(origin + '/menu/valid-menu-token');
        assert.ok(await evaluate(`(() => {
            const button = document.querySelector('.menu-product .increase');
            const r = button.getBoundingClientRect();
            return document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2).closest('button') === button;
        })()`), 'Watermark intercepts product button at ' + width);
        await evaluate('localStorage.clear(); window.dispatchEvent(new StorageEvent("storage", {key: null})); document.querySelector(".menu-product .increase").click(); document.querySelector(".menu-product .increase").click();');
        assert.equal(await evaluate('document.getElementById("cart-count").textContent'), '2 item');
        assert.equal(await evaluate('document.getElementById("cart-total").textContent'), 'Rp30.000');
        assert.deepEqual(await evaluate('JSON.parse(localStorage.getItem("warmindo:cart:v1:valid-menu-token"))'), {
            version: 2,
            items: { '1': { product_id: 1, product_name: 'Indomie Goreng Telur Spesial 1', price: '15000.00', quantity: 2, subtotal: '30000.00' } },
        });
        const layout = await evaluate(`(() => {
            const side = document.querySelector(".category-sidebar").getBoundingClientRect();
            const grid = document.querySelector(".product-grid").getBoundingClientRect();
            const links = [...document.querySelectorAll(".category-link")].map(el => el.getBoundingClientRect());
            const overflows = [...document.querySelectorAll(".menu-product")].some(el => el.scrollWidth > el.clientWidth + 1);
            return { width: innerWidth, documentWidth: document.documentElement.scrollWidth, sidebarRight: side.right, gridLeft: grid.left, vertical: links[1].top >= links[0].bottom, columns: getComputedStyle(document.querySelector(".product-grid")).gridTemplateColumns.split(" ").length, cardOverflow: overflows };
        })()`);
        assert.ok(layout.documentWidth <= width, JSON.stringify(layout));
        assert.ok(layout.sidebarRight < layout.gridLeft && layout.vertical, JSON.stringify(layout));
        assert.equal(layout.columns, width < 768 ? 2 : width < 1024 ? 3 : 4);
        assert.equal(layout.cardOverflow, false);
        await cdp('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false }).then(image => writeFile(path.join(output, width + '.png'), Buffer.from(image.data, 'base64')));
        await evaluate('document.getElementById("open-cart").click()');
        await until(() => evaluate('document.getElementById("cart-drawer").classList.contains("show")'), 'Cart drawer did not open');
        const drawerBounds = await evaluate(`(() => { const r = document.getElementById("cart-drawer").getBoundingClientRect(); return {left:r.left,right:r.right,top:r.top,bottom:r.bottom}; })()`);
        assert.ok(drawerBounds.left >= 0 && drawerBounds.right <= width + 1 && drawerBounds.top >= 0 && drawerBounds.bottom <= 901);
        await evaluate('document.querySelector("#cart-items .increase").click()');
        assert.equal(await evaluate('document.getElementById("drawer-total").textContent'), 'Rp45.000');
        assert.equal(await evaluate('document.querySelector(".menu-product output").textContent'), '3');
        assert.equal(await evaluate('document.querySelector("#cart-items .cart-line-total").textContent'), 'Rp45.000');
        await evaluate('document.querySelector("#cart-items .decrease").click(); document.querySelector("#cart-drawer .btn-close").click()');
        await until(() => evaluate('!document.getElementById("cart-drawer").classList.contains("show")'), 'Drawer did not close');
        await evaluate('window.scrollTo(0, document.body.scrollHeight)');
        await sleep(150);
        assert.ok(await evaluate('document.querySelector(".menu-product:last-child").getBoundingClientRect().bottom <= document.getElementById("cart-bar").getBoundingClientRect().top'), 'Cart overlaps final product at ' + width);
        viewportResults.push(layout);
        console.log('PASS viewport ' + width);
    }
    await cdp('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
    await navigate(origin + '/menu/valid-menu-token?category=2&search=Segar');
    assert.equal(await evaluate('document.getElementById("cart-count").textContent'), '2 item', 'Cart lost across filtering');
    assert.equal(await evaluate('document.querySelectorAll(".menu-product").length'), 6);
    await evaluate('document.getElementById("open-cart").click()');
    await until(() => evaluate('document.getElementById("cart-drawer").classList.contains("show")'), 'Drawer did not open after filter');
    assert.ok(await evaluate('document.getElementById("cart-items").textContent.includes("Indomie")'), 'Filtered-out cart product disappeared');
    await evaluate('document.querySelector(".menu-product .increase").click()');
    assert.equal(await evaluate('document.getElementById("cart-count").textContent'), '3 item');
    assert.equal(await evaluate('document.getElementById("drawer-total").textContent'), 'Rp45.000');
    await evaluate('document.querySelector(".menu-product .decrease").click()');
    await navigate(origin + '/menu/other-menu-token');
    assert.equal(await evaluate('document.getElementById("cart-bar").hidden'), true, 'Cart leaked between tables');
    await navigate(origin + '/menu/valid-menu-token');
    assert.equal(await evaluate('document.getElementById("cart-count").textContent'), '2 item');
    assert.equal(await evaluate('JSON.parse(localStorage.getItem("warmindo:cart:v1:valid-menu-token")).version'), 2, 'Legacy cart was not upgraded');
    await evaluate(`localStorage.setItem("warmindo:cart:v1:valid-menu-token", JSON.stringify({version:2,items:{"1":{quantity:2,product_name:"Wrong name",price:"1.00",subtotal:"2.00"},"2":{quantity:-1},"3":{quantity:1.5},"4":null,"9999":{quantity:2}}}))`);
    await navigate(origin + '/menu/valid-menu-token');
    assert.equal(await evaluate('document.getElementById("cart-total").textContent'), 'Rp30.000', 'Stored price must use the current catalog');
    assert.equal(await evaluate('JSON.parse(localStorage.getItem("warmindo:cart:v1:valid-menu-token")).items[1].product_name'), 'Indomie Goreng Telur Spesial 1');
    await evaluate('document.querySelector(".menu-product .decrease").click(); document.querySelector(".menu-product .decrease").click()');
    assert.equal(await evaluate('document.getElementById("cart-bar").hidden'), true);
    assert.equal(await evaluate('document.querySelector(".menu-product output").textContent'), '0');
    await evaluate(`localStorage.setItem("warmindo:cart:v1:valid-menu-token", JSON.stringify({version:1,items:{"1":-2,"2":2,"9999":5,"3":1.5,"4":"4"}}))`);
    await navigate(origin + '/menu/valid-menu-token');
    assert.equal(await evaluate('document.getElementById("cart-count").textContent'), '2 item');
    await evaluate('localStorage.setItem("warmindo:cart:v1:valid-menu-token", "{broken")');
    await navigate(origin + '/menu/valid-menu-token');
    assert.equal(await evaluate('document.getElementById("cart-bar").hidden'), true);
    await evaluate('document.querySelectorAll(".menu-product .increase")[5].click(); document.querySelectorAll(".menu-product .increase")[5].click()');
    assert.equal(await evaluate('document.getElementById("cart-total").textContent'), 'Rp35.001', 'Fractional prices must sum exactly');
    await evaluate('document.getElementById("open-cart").click()');
    await until(() => evaluate('document.getElementById("cart-drawer").classList.contains("show")'), 'Drawer did not open for removal');
    await evaluate('document.querySelector("#cart-items .decrease").click(); document.querySelector("#cart-items .decrease").click()');
    assert.equal(await evaluate('document.getElementById("cart-empty").hidden'), false);
    assert.equal(await evaluate('document.getElementById("cart-bar").hidden'), true);
    const blockedStorage = await cdp('Page.addScriptToEvaluateOnNewDocument', { source: 'Storage.prototype.setItem = function () { throw new Error("Storage blocked"); };' });
    await navigate(origin + '/menu/valid-menu-token');
    await evaluate('document.querySelector(".menu-product .increase").click()');
    assert.equal(await evaluate('document.getElementById("cart-count").textContent'), '1 item');
    assert.ok(await evaluate('document.getElementById("cart-feedback").textContent.includes("Penyimpanan browser tidak tersedia")'));
    await cdp('Page.removeScriptToEvaluateOnNewDocument', { identifier: blockedStorage.identifier });
    await navigate(origin + '/menu/valid-menu-token');
    await evaluate('document.querySelector(".menu-product .increase").click(); document.getElementById("open-cart").click()');
    await until(() => evaluate('document.getElementById("cart-drawer").classList.contains("show")'), 'Checkout cart did not open');
    await evaluate('document.querySelector("#checkout-start button").click()');
    await until(() => evaluate('document.readyState === "complete" && !!document.getElementById("checkout-submit")'), 'Checkout review did not load');
    assert.ok(await evaluate('document.body.textContent.includes("Rp15.000")'));
    assert.ok(await evaluate('localStorage.getItem("warmindo:cart:v1:valid-menu-token") !== null'), 'Review cleared cart');
    const reviewUrl = await evaluate('location.href');
    await evaluate('document.getElementById("customer-name").value = "Evan"; document.getElementById("order-notes").value = "Tidak pedas"; document.getElementById("customer-name").value = "Evan"; document.getElementById("payment-cash").checked = true; document.querySelector("#checkout-submit button").click()');
    await until(() => evaluate('document.readyState === "complete" && !!document.getElementById("checkout-success")'), 'Checkout success did not load');
    assert.ok(await evaluate('document.body.textContent.includes("pending") && document.body.textContent.includes("unpaid")'));
    assert.equal(await evaluate('localStorage.getItem("warmindo:cart:v1:valid-menu-token")'), null, 'Successful checkout did not clear cart');
    const successUrl = await evaluate('location.href');
    await navigate(origin + '/menu/valid-menu-token');
    assert.equal(await evaluate('document.getElementById("cart-bar").hidden'), true);
    await evaluate('document.querySelector(".menu-product .increase").click()');
    await navigate(successUrl, false);
    assert.ok(await evaluate('localStorage.getItem("warmindo:cart:v1:valid-menu-token") !== null'), 'Reopening success erased a new cart');
    await navigate(reviewUrl, false);
    await evaluate('document.getElementById("customer-name").value = "Evan"; document.getElementById("payment-cash").checked = true; document.querySelector("#checkout-submit button").click()');
    await until(() => evaluate('document.readyState === "complete" && !!document.getElementById("checkout-success")'), 'Retry did not return success');
    assert.ok(await evaluate('localStorage.getItem("warmindo:cart:v1:valid-menu-token") !== null'), 'Retry of an old order erased a new cart');
    console.log('PASS checkout review, notes, success, cart clearing, success revisit and retry');
    await navigate(origin + '/login', false);
    await evaluate('document.getElementById("email").value = "browser@example.test"; document.getElementById("password").value = ' + JSON.stringify(browserPassword) + '; document.querySelector("form").requestSubmit();');
    await until(() => evaluate('location.pathname === "/admin/products" && document.readyState === "complete"'), 'Admin login failed');
    await navigate(origin + '/admin/orders', false);
    await until(() => evaluate('!!document.getElementById("orders-list")'), 'Cashier list missing');
    for (const width of [390, 768, 1440]) {
        await cdp('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width === 390 });
        assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Cashier overflow at ' + width);
    }
    await until(() => evaluate('document.getElementById("orders-refresh-status").textContent.startsWith("Diperbarui ") && !document.getElementById("orders-refresh-status").textContent.includes("otomatis setiap")'), 'Cashier polling did not refresh');
    await evaluate('document.querySelector("#orders-list a.btn").click()');
    await until(() => evaluate('document.readyState === "complete" && document.body.textContent.includes("Konfirmasi Pesanan")'), 'Cashier detail missing');
    assert.ok(await evaluate('document.body.textContent.includes("Nama Pemesan: Evan")'));
    await evaluate('document.querySelector("button[data-bs-target=\\"#add-order-menu\\"]").click()');
    await until(() => evaluate('document.getElementById("add-order-menu").classList.contains("show")'), 'Add menu modal missing');
    await evaluate('document.getElementById("add-menu-search").value = "Segar"; document.getElementById("add-menu-search").dispatchEvent(new Event("input")); document.getElementById("add-menu-product").value = "7"; document.getElementById("add-menu-quantity").value = "2"; document.querySelector("#add-order-menu button[type=submit]").click()');
    await until(() => evaluate('document.readyState === "complete" && document.body.textContent.includes("Total Rp45.000")'), 'Added item did not update total');
    for (const [next, label] of [['confirmed', 'Mulai Proses'], ['processing', 'Selesaikan Pesanan']]) {
        await evaluate('document.querySelector("input[name=order_status]").form.querySelector("button[type=submit]").click()');
        await until(() => evaluate('document.readyState === "complete" && document.body.textContent.includes(' + JSON.stringify(label) + ')'), 'Status did not advance to ' + next);
    }
    await evaluate('document.querySelector("button.btn-success[data-bs-target]").click()');
    await until(() => evaluate('document.getElementById("payment-confirmation").classList.contains("show")'), 'Payment modal missing');
    await evaluate('document.getElementById("payment-type").value = "qris_manual"; document.querySelector("#payment-confirmation button[type=submit]").click()');
    await until(() => evaluate('document.readyState === "complete" && !document.getElementById("payment-confirmation") && document.body.textContent.includes("PAID")'), 'Payment did not become paid');
    await evaluate('document.querySelector("input[name=order_status]").form.querySelector("button[type=submit]").click()');
    await until(() => evaluate('document.readyState === "complete" && document.body.textContent.includes("Completed")'), 'Order not completed');
    await navigate(origin + '/admin/orders', false);
    assert.equal(await evaluate('document.querySelectorAll("#orders-list article").length'), 0);
    await navigate(origin + '/admin/orders?tab=completed', false);
    assert.equal(await evaluate('document.querySelectorAll("#orders-list article").length'), 1);
    console.log('PASS cashier polling, responsive layout, detail, status flow, manual QRIS payment and completed tab');
    const receiptUrl = await evaluate('document.querySelector("#orders-list a[href$=\\"/receipt\\"]").href');
    await navigate(receiptUrl, false);
    assert.ok(await evaluate('document.querySelector(".receipt").textContent.includes("Evan") && document.querySelector(".receipt").textContent.includes("QRIS Manual") && document.querySelector(".receipt").textContent.includes("Rp45.000")'));
    assert.equal(await evaluate('document.querySelectorAll(".receipt-item").length'), 2);
    await evaluate('window.printCalls = 0; window.print = () => window.printCalls++; document.getElementById("print-receipt").click();');
    assert.equal(await evaluate('window.printCalls'), 1, 'Print button must invoke window.print');
    await evaluate('document.querySelector(".receipt-item h2").textContent = "Indomie Spesial ".repeat(15) + "X".repeat(100);');
    for (const width of [360, 768, 1440]) {
        await cdp('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width === 360 });
        assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Receipt screen overflow at ' + width);
    }
    await cdp('Emulation.setEmulatedMedia', { media: 'print' });
    await cdp('Emulation.setDeviceMetricsOverride', { width: 303, height: 900, deviceScaleFactor: 1, mobile: false });
    assert.ok(await evaluate(`(() => {
        const receipt = document.querySelector('.receipt');
        const hidden = ['.admin-sidebar', '.navbar', '.receipt-actions', '.demo-watermark'];
        return hidden.every(selector => getComputedStyle(document.querySelector(selector)).display === 'none')
            && receipt.scrollWidth <= receipt.clientWidth
            && receipt.getBoundingClientRect().width <= 303
            && document.querySelector('.receipt-item h2').getBoundingClientRect().height > 36;
    })()`), 'Print must hide unrelated UI and wrap long names within 80mm');
    await writeFile(path.join(output, 'receipt-print.png'), Buffer.from((await cdp('Page.captureScreenshot', { captureBeyondViewport: true })).data, 'base64'));
    await evaluate('const items = document.querySelector(".receipt-items"); for (let i = 0; i < 30; i++) items.append(items.lastElementChild.cloneNode(true));');
    assert.ok(await evaluate('document.querySelector(".receipt").getBoundingClientRect().height > 900 && document.querySelector(".receipt").scrollWidth <= document.querySelector(".receipt").clientWidth'), 'Long receipt must grow without horizontal clipping');
    await cdp('Emulation.setEmulatedMedia', { media: 'screen' });
    await cdp('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
    await navigate(origin + '/admin/orders?tab=completed', false);
    console.log('PASS receipt values, print action, 80mm print CSS, long names, long receipts and hidden dashboard/watermark');
    assert.equal(await evaluate('document.querySelectorAll("#order-notifications .toast").length'), 0, 'Old orders triggered a toast');
    const newOrderIds = await evaluate(`(async () => {
        const ids = [];
        const menu = await (await fetch('/menu/valid-menu-token')).text();
        const csrf = new DOMParser().parseFromString(menu, 'text/html').querySelector('#checkout-start input[name="_token"]').value;
        for (let count = 0; count < 2; count++) {
            const review = await fetch('/menu/valid-menu-token/checkout/review', {method:'POST', body: new URLSearchParams({_token:csrf, 'items[0][product_id]':'1', 'items[0][quantity]':'3'})});
            const document = new DOMParser().parseFromString(await review.text(), 'text/html');
            const token = document.querySelector('input[name="checkout_token"]').value;
            const result = await fetch('/menu/valid-menu-token/checkout', {method:'POST', body:new URLSearchParams({_token:csrf, checkout_token:token, customer_name:"Evan", payment_type:"cash"})});
            if (!result.ok || !result.url.includes('/success')) throw new Error('Notification fixture checkout failed');
            ids.push(token);
        }
        window.dispatchEvent(new Event('focus'));
        window.document.dispatchEvent(new Event('visibilitychange'));
        return ids;
    })()`);
    await until(() => evaluate('!!document.querySelector("#order-notifications .toast.show")'), 'New order toast did not appear');
    assert.equal(await evaluate('document.querySelectorAll("#order-notifications .toast").length'), 1, 'Toasts should queue');
    assert.ok(await evaluate('document.getElementById("order-notifications").textContent.includes("Meja 05") && document.getElementById("order-notifications").textContent.includes("3 item • Rp45.000")'));
    assert.ok(newOrderIds.includes(await evaluate('document.querySelector("#order-notifications .toast").dataset.orderId')));
    const firstToast = await evaluate('document.querySelector("#order-notifications .toast").dataset.orderId');
    await evaluate('document.querySelector("#order-notifications .btn-close").click()');
    await until(() => evaluate('!!document.querySelector("#order-notifications .toast.show") && document.querySelector("#order-notifications .toast").dataset.orderId !== ' + JSON.stringify(firstToast)), 'Queued toast did not appear');
    assert.ok(await evaluate('document.getElementById("order-notifications").textContent.includes("Evan")'));
    await cdp('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
    assert.ok(await evaluate('(() => { const r = document.querySelector("#order-notifications .toast").getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth; })()'), 'Toast overflows mobile screen');
    await until(() => evaluate('document.querySelectorAll("#order-notifications .toast").length === 0'), 'Toast did not auto-hide', 15000);
    await evaluate('document.dispatchEvent(new Event("visibilitychange"))');
    await sleep(1000);
    assert.equal(await evaluate('document.querySelectorAll("#order-notifications .toast").length'), 0, 'Duplicate notification on later polling');
    console.log('PASS new-order toast, initial baseline, filtered monitoring, queue, dismiss, auto-hide, mobile bounds and deduplication');
    await navigate(origin + '/menu/inactive-menu-token', false);
    assert.ok(await evaluate('document.body.textContent.includes("Menu belum dapat dibuka")'));
    assert.equal(exceptions.length, 0, JSON.stringify(exceptions));
    await writeFile(path.join(output, 'results.json'), JSON.stringify({ viewportResults, interactions: 'passed' }, null, 2));
    console.log('PASS cart persistence, table isolation, quantity controls, totals, invalid storage, inactive token');
    console.log('Screenshots: ' + output);
} catch (error) {
    console.error(logs);
    throw error;
} finally {
    if (socket) socket.close();
    chrome.kill();
    server.kill();
}
