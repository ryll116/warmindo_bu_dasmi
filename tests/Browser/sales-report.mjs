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
const server = spawn('php', ['-d', 'extension=pdo_sqlite', '-S', '127.0.0.1:' + port, '-t', 'public', 'tests/Browser/customer-menu-router.php'], { cwd: root, env: { ...process.env, WARMINDO_BROWSER_DIR: output, WARMINDO_BROWSER_PASSWORD: browserPassword, APP_URL: origin }, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
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
    await navigate(origin + '/login', false);
    await evaluate('document.getElementById("email").value = "browser@example.test"; document.getElementById("password").value = ' + JSON.stringify(browserPassword) + '; document.querySelector("form").requestSubmit();');
    await until(() => evaluate('location.pathname === "/admin/products" && document.readyState === "complete"'), 'Admin login failed');
    await navigate(origin + '/menu/valid-menu-token');
    await evaluate(`(async () => {
        const csrf=document.querySelector('#checkout-start input[name="_token"]').value;
        const review=await fetch('/menu/valid-menu-token/checkout/review',{method:'POST',body:new URLSearchParams({_token:csrf,'items[0][product_id]':'1','items[0][quantity]':'2'})});
        const page=new DOMParser().parseFromString(await review.text(),'text/html');
        const token=page.querySelector('input[name="checkout_token"]').value;
        await fetch('/menu/valid-menu-token/checkout',{method:'POST',body:new URLSearchParams({_token:csrf,checkout_token:token,customer_name:'Evan'})});
        const paid=await fetch('/admin/orders/'+token+'/payment',{method:'POST',body:new URLSearchParams({_token:csrf,_method:'PATCH',payment_type:'cash'})});
        if(!paid.ok) throw new Error('Fixture payment failed');
    })()`);
    await navigate(origin + '/admin/reports/sales', false);
    assert.ok(await evaluate('document.body.textContent.includes("Rp30.000,00") && document.body.textContent.includes("Evan")'));
    for(const width of [390,768,1440]) {
        await cdp('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width===390});
        assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth'),'Report overflow at '+width);
    }
    await navigate(origin + '/admin/reports/sales?period=yesterday',false);
    assert.ok(await evaluate('document.body.textContent.includes("Tidak ada transaksi lunas")'));
    await navigate(origin + '/admin/reports/sales?period=today&search=Evan',false);
    assert.ok(await evaluate('document.body.textContent.includes("Rp30.000,00")'));
    assert.equal(exceptions.length,0);
    console.log('PASS report paid sale, date filter, search, mobile/tablet/desktop layout');
} catch (error) {
    console.error(logs);
    throw error;
} finally {
    if (socket) socket.close();
    chrome.kill();
    server.kill();
}
