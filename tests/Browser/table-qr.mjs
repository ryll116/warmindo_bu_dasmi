import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdir, mkdtemp, readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import net from 'node:net';

const root = process.cwd();
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
const server = spawn('php', ['-d', 'extension=pdo_sqlite', '-S', '127.0.0.1:' + port, '-t', 'public', 'tests/Browser/customer-menu-router.php'], { cwd: root, env: { ...process.env, WARMINDO_BROWSER_DIR: output, APP_URL: origin }, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
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
    await navigate(origin + '/admin/tables/1/qr', false);
    await evaluate(`new Promise((resolve, reject) => { const script = document.createElement('script'); script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js'; script.onload = resolve; script.onerror = () => reject(new Error('QR decoder failed to load')); document.head.append(script); })`);
    const decoded = await evaluate(`(async () => {
        async function decode(src) {
            const image = new Image(); image.src = src; await image.decode();
            const canvas = document.createElement('canvas'); canvas.width = 1024; canvas.height = 1024;
            const ctx = canvas.getContext('2d'); ctx.drawImage(image, 0, 0, 1024, 1024);
            const pixels = ctx.getImageData(0, 0, 1024, 1024);
            const result = jsQR(pixels.data, 1024, 1024);
            if (!result) throw new Error('QR cannot be decoded');
            return result.data;
        }
        const preview = await decode(document.querySelector('img').src);
        const link = [...document.querySelectorAll('a')].find(a => a.textContent === 'Download QR');
        const response = await fetch(link.href);
        const blob = URL.createObjectURL(await response.blob());
        const download = await decode(blob); URL.revokeObjectURL(blob);
        return {preview, download, disposition: response.headers.get('Content-Disposition')};
    })()`);
    assert.equal(decoded.preview, origin + '/menu/valid-menu-token');
    assert.equal(decoded.download, decoded.preview);
    assert.ok(decoded.disposition.includes('meja-05-qr.svg'));
    await navigate(decoded.download);
    assert.ok(await evaluate('document.body.textContent.includes("Meja 05")'));
    assert.equal(exceptions.length, 0);
    console.log('PASS QR preview and downloaded SVG independently decoded to table 05 customer menu');
} catch (error) {
    console.error(logs);
    throw error;
} finally {
    if (socket) socket.close();
    chrome.kill();
    server.kill();
}
