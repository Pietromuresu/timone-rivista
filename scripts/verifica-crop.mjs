#!/usr/bin/env node
/**
 * Verifica visiva del ritaglio delle miniature (2026-10-30, Fase B1):
 * `node scripts/verifica-crop.mjs` (dopo `npm run build`).
 *
 * Su database e storage temporanei (come scripts/manuale-screenshot.mjs)
 * carica i casi di Database\Seeders\CropVerificationSeeder, poi salva in
 * docs/verifiche/crop/:
 * - card-*.png: la card di ogni caso nel timone (Chrome headless, zoom L);
 * - export-timone-*.png: le pagine dell'export PDF del timone (pdftoppm);
 * - sfogliabile-*.png: le stesse pagine nel PDF sfogliabile (pdftoppm).
 * Va guardato a occhio: ogni caso dice nel nome quali sesti colorati
 * (1–6, vedi Tests\Support\CropFixtures) devono comparire.
 *
 * Richiede Chrome, node ≥ 22 e poppler (pdftoppm). Variabili: CHROME_PATH,
 * PHP_BINARY, VERIFICA_PORT (8766).
 */
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'docs/verifiche/crop');
const PORT = Number(process.env.VERIFICA_PORT ?? 8766);
const BASE = `http://127.0.0.1:${PORT}`;
const PHP = process.env.PHP_BINARY ?? 'php';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Casi: numero (1 = A4, 2 = 210×270), pagina, nome del file e cosa si deve vedere. */
const CASES = [
    [1, 1, 'mezza-verticale-sinistra', 'sesti 1, 3, 5'],
    [1, 2, 'mezza-verticale-destra', 'sesti 2, 4, 6'],
    [1, 3, 'mezza-orizzontale-sopra', 'sesti 1, 2 e metà alta di 3, 4'],
    [1, 4, 'mezza-orizzontale-sotto', 'metà bassa di 3, 4 e sesti 5, 6'],
    [1, 5, 'terzo-verticale', 'striscia sinistra (2/3 della larghezza) di 1, 3, 5'],
    [1, 6, 'piedino', 'sesti 5, 6'],
    [1, 7, 'vetrine-quattro-angoli', 'in ogni angolo il suo quarto'],
    [1, 8, 'due-terzi', 'due terzi sinistri: 1, 3, 5 e parte di 2, 4, 6'],
    [1, 9, 'forma-a-L', '1, 2, 3, 5 e metà di 4 e 6; vetrina vuota in basso a destra'],
    [1, 10, 'tre-pagine-1', 'pagina 1·x intera'],
    [1, 11, 'tre-pagine-2', 'pagina 2·x intera'],
    [1, 12, 'tre-pagine-3', 'pagina 3·x, metà superiore: 3·1, 3·2 e metà di 3·3, 3·4'],
    [1, 13, 'gia-nel-formato', 'il file verde «MEZZA» intero'],
    [1, 14, 'abbondanza', 'come mezza verticale sinistra, senza il bordo dell\'abbondanza'],
    [2, 1, 'a4-su-210x270', 'metà sinistra dell\'A4, centrata in altezza'],
];

function findChrome() {
    const found = [process.env.CHROME_PATH, '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '/Applications/Chromium.app/Contents/MacOS/Chromium', '/usr/bin/google-chrome', '/usr/bin/chromium']
        .filter(Boolean).find((p) => fs.existsSync(p));
    if (!found) throw new Error('Chrome/Chromium non trovato: CHROME_PATH.');

    return found;
}

for (const tool of ['pdftoppm']) {
    if (spawnSync(tool, ['-v']).error) {
        console.error(`Serve ${tool} (poppler).`);
        process.exit(1);
    }
}

const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'timone-crop-'));
for (const dir of ['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs']) {
    fs.mkdirSync(path.join(TMP, 'storage', dir), { recursive: true });
}
fs.writeFileSync(path.join(TMP, 'db.sqlite'), '');
const env = {
    ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', APP_URL: BASE, DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(TMP, 'db.sqlite'),
    LARAVEL_STORAGE_PATH: path.join(TMP, 'storage'), CACHE_STORE: 'array', SESSION_DRIVER: 'file', QUEUE_CONNECTION: 'sync', BROADCAST_CONNECTION: 'log', MAIL_MAILER: 'array',
};
const children = [];
process.on('exit', () => {
    for (const child of children) child.kill('SIGTERM');
    try { fs.rmSync(TMP, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 }); } catch { /* profilo di Chrome ancora in uso */ }
});

const run = (args) => {
    const result = spawnSync(PHP, args, { cwd: ROOT, env, encoding: 'utf8' });
    if (result.status !== 0) throw new Error(result.stdout + result.stderr);

    return result.stdout;
};

console.log('Preparo i casi…');
run(['artisan', 'migrate:fresh', '--seed', '--seeder=CropVerificationSeeder', '--force']);
fs.mkdirSync(OUT, { recursive: true });
for (const old of fs.readdirSync(OUT).filter((f) => f.endsWith('.png'))) fs.rmSync(path.join(OUT, old));

// PDF sfogliabile: stesso compositore del job, scritto nella cartella temporanea.
const flipbook = path.join(TMP, 'sfogliabile.pdf');
run(['artisan', 'tinker', '--execute', `(new App\\Support\\MagazineExport\\MagazinePdfComposer(${JSON.stringify(path.join(TMP, 'work'))}))->compose(App\\Models\\Issue::find(1), ${JSON.stringify(flipbook)}); (new App\\Support\\MagazineExport\\MagazinePdfComposer(${JSON.stringify(path.join(TMP, 'work2'))}))->compose(App\\Models\\Issue::find(2), ${JSON.stringify(flipbook.replace('.pdf', '-2.pdf'))});`]);

const server = spawn(PHP, ['-S', `127.0.0.1:${PORT}`, path.join(ROOT, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.join(ROOT, 'public'), env, stdio: 'ignore' });
children.push(server);
const profile = path.join(TMP, 'chrome');
children.push(spawn(findChrome(), ['--headless=new', '--remote-debugging-port=0', `--user-data-dir=${profile}`, '--no-first-run', '--hide-scrollbars', '--force-color-profile=srgb', 'about:blank'], { stdio: 'ignore' }));

async function waitFor(check, what, timeout = 20000) {
    const start = Date.now();
    while (Date.now() - start < timeout) {
        const value = await check().catch(() => null);
        if (value) return value;
        await sleep(150);
    }
    throw new Error(`Tempo scaduto: ${what}`);
}

await waitFor(() => fetch(`${BASE}/login`).then((r) => r.ok), 'server PHP');
const port = await waitFor(() => fs.promises.readFile(path.join(profile, 'DevToolsActivePort'), 'utf8').then((t) => t.split('\n')[0]), 'Chrome');
const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json();
const ws = new WebSocket(target.webSocketDebuggerUrl);
let seq = 0;
const pending = new Map();
ws.onmessage = (event) => {
    const msg = JSON.parse(event.data);
    if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); }
};
await new Promise((r) => { ws.onopen = r; });
const send = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++seq;
    pending.set(id, (msg) => (msg.error ? reject(new Error(`${method}: ${msg.error.message}`)) : resolve(msg.result)));
    ws.send(JSON.stringify({ id, method, params }));
});
const evaluate = async (expression) => {
    const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description ?? expression);

    return result.result.value;
};
async function go(url) {
    await send('Page.navigate', { url });
    await sleep(300);
    await waitFor(() => evaluate(`document.readyState === 'complete' && (!document.querySelector('[x-data]') || !!window.Alpine)`), url);
    // Immagini caricate (anche quelle pigre: tutte in vista con la finestra alta).
    await waitFor(() => evaluate(`[...document.images].every((i) => i.complete)`), 'immagini');
    await sleep(400);
}

await send('Page.enable');
await send('Emulation.setDeviceMetricsOverride', { width: 1600, height: 4200, deviceScaleFactor: 2, mobile: false });
await go(`${BASE}/login`);
await evaluate(`(() => { const set = (q, v) => { const el = document.querySelector(q); el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); }; set('#email', 'verifica@esempio.test'); set('#password', 'verifica-crop'); document.querySelector('form').requestSubmit(); })()`);
await waitFor(() => evaluate(`location.pathname !== '/login'`), 'login');
await evaluate(`localStorage.setItem('timone.zoom', 'l'); localStorage.removeItem('timone.view'); localStorage.setItem('timone.contentPanel', JSON.stringify({ open: false }))`);

const cardRect = (position) => `(() => { const c = [...document.querySelectorAll('[data-page-id]')].filter((c) => !c.closest('[data-testid=cover-band]')).find((c) => ((c.querySelector('.drag-handle') ?? c.querySelector('span.font-semibold'))?.innerText.match(/(\\d+)\\s*$/) ?? [])[1] === '${position}'); if (!c) throw new Error('card ${position}'); c.scrollIntoView({ block: 'center' }); const r = c.getBoundingClientRect(); return { x: r.left + scrollX, y: r.top + scrollY, width: r.width, height: r.height }; })()`;

for (const issue of [1, 2]) {
    await go(`${BASE}/riviste/verifica-ritaglio/numeri/${issue}`);

    for (const [caseIssue, position, name] of CASES.filter((c) => c[0] === issue)) {
        const clip = await evaluate(cardRect(position));
        await sleep(200);
        const shot = await send('Page.captureScreenshot', { format: 'png', clip: { ...clip, scale: 1 }, captureBeyondViewport: false });
        fs.writeFileSync(path.join(OUT, `card-${String(caseIssue)}-${String(position).padStart(2, '0')}-${name}.png`), Buffer.from(shot.data, 'base64'));
    }

    // Export del timone con le miniature, scaricato con la sessione del browser.
    const pdf = await evaluate(`fetch('/riviste/verifica-ritaglio/numeri/${issue}/export/timone.pdf?withThumbnails=1').then((r) => r.arrayBuffer()).then((b) => { let s = ''; const u = new Uint8Array(b); for (let i = 0; i < u.length; i++) s += String.fromCharCode(u[i]); return btoa(s); })`);
    const timonePdf = path.join(TMP, `timone-${issue}.pdf`);
    fs.writeFileSync(timonePdf, Buffer.from(pdf, 'base64'));
    spawnSync('pdftoppm', ['-png', '-r', '110', timonePdf, path.join(OUT, `export-timone-${issue}`)]);

    // Sfogliabile: le pagine dei casi (indice = posizione + 2: prima I e II di copertina).
    const source = issue === 1 ? flipbook : flipbook.replace('.pdf', '-2.pdf');
    for (const [caseIssue, position, name] of CASES.filter((c) => c[0] === issue)) {
        spawnSync('pdftoppm', ['-png', '-r', '50', '-singlefile', '-f', String(position + 2), '-l', String(position + 2), source, path.join(OUT, `sfogliabile-${caseIssue}-${String(position).padStart(2, '0')}-${name}`)]);
    }
}

fs.writeFileSync(path.join(OUT, 'CASI.md'), `# Verifica del ritaglio delle miniature

Generato da \`node scripts/verifica-crop.mjs\` con i PDF di prova di \`tests/Support/CropFixtures.php\` (sesti colorati: 1 rosso, 2 arancio, 3 giallo, 4 verde, 5 blu, 6 viola).

| Numero | Pagina | Caso | Cosa si deve vedere |
|---|---|---|---|
${CASES.map(([i, p, n, w]) => `| ${i === 1 ? 'A4' : '210×270'} | ${p} | ${n} | ${w} |`).join('\n')}

File: \`card-<numero>-<pagina>-<caso>.png\` (timone), \`export-timone-<numero>-<foglio>.png\` (export del timone, tutte le pagine), \`sfogliabile-<numero>-<pagina>-<caso>.png\` (PDF sfogliabile).
`);
console.log(`Fatto: ${OUT}`);
process.exit(0);
