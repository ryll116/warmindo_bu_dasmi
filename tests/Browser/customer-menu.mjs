import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtemp, readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import net from 'node:net';

const root = process.cwd();
const output = await mkdtemp(path.join(tmpdir(), 'warmindo-mobile-'));
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
const server = spawn('php', ['-d', 'extension=pdo_sqlite', '-S', '127.0.0.1:' + port, '-t', 'public', 'tests/Browser/customer-menu-router.php'], { cwd: root, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
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
    const viewportResults = [];
    for (const width of [360, 390, 430, 768, 1024, 1440]) {
        await cdp('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width <= 430 });
        await navigate(origin + '/menu/valid-menu-token');
        await evaluate('localStorage.clear(); window.dispatchEvent(new StorageEvent("storage", {key: null})); document.querySelector(".menu-product .increase").click(); document.querySelector(".menu-product .increase").click();');
        assert.equal(await evaluate('document.getElementById("cart-count").textContent'), '2 item');
        assert.equal(await evaluate('document.getElementById("cart-total").textContent'), 'Rp 30.000');
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
        assert.equal(await evaluate('document.getElementById("drawer-total").textContent'), 'Rp 45.000');
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
    await navigate(origin + '/menu/other-menu-token');
    assert.equal(await evaluate('document.getElementById("cart-bar").hidden'), true, 'Cart leaked between tables');
    await navigate(origin + '/menu/valid-menu-token');
    assert.equal(await evaluate('document.getElementById("cart-count").textContent'), '2 item');
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
    assert.equal(await evaluate('document.getElementById("cart-total").textContent'), 'Rp 35.001', 'Fractional prices must sum exactly');
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
