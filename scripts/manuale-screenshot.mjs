#!/usr/bin/env node
/**
 * Screenshot del manuale (2026-10-29, Fase 4): `npm run manuale:screenshot`.
 *
 * Rigenera tutte le immagini di public/manuale/img/ dall'interfaccia vera,
 * con dati INVENTATI (Database\Seeders\ManualScreenshotSeeder), su un
 * database e uno storage temporanei: il database e i file di sviluppo non
 * vengono toccati (LARAVEL_STORAGE_PATH). Va rilanciato quando cambia
 * l'interfaccia.
 *
 * Perché così e non Dusk/Browsershot/Playwright: nessuna dipendenza nuova.
 * Usa Chrome (o Chromium) già installato in modalità headless e il
 * protocollo DevTools, con il WebSocket integrato di node (node ≥ 22). Il
 * container di produzione non ha né Chrome né node, e l'immagine node del
 * docker-compose.dev.yml è node 20 su Alpine (Playwright non la supporta):
 * lo script gira sul computer di chi sviluppa, come `npm run build`.
 *
 * Viewport fissa 1440×1000, tema chiaro, zoom M, immagini WebP.
 *
 * Opzioni:
 *   --solo nome[,nome]  rigenera solo questi screenshot
 *   --misure            stampa la posizione (in %) dei punti da annotare,
 *                       da copiare nei blocchi `:::screenshot` del manuale
 * Variabili: CHROME_PATH (percorso di Chrome/Chromium), PHP_BINARY (php),
 *            MANUALE_PORT (8765).
 *
 * Dopo: `php artisan manuale:genera` (il file unico cita le immagini).
 */
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'public/manuale/img');
const MANUAL = path.join(ROOT, 'resources/manuale');
const PORT = Number(process.env.MANUALE_PORT ?? 8765);
const BASE = `http://127.0.0.1:${PORT}`;
const VIEWPORT = { width: 1440, height: 1000 };
const SCALE = 2; // immagini nitide anche su schermi ad alta densità
const QUALITY = 82;
const TOLERANCE = 4; // punti percentuali attorno all'elemento annotato
const EMAIL = 'laura.esempio@esempio.test';
const PASSWORD = 'manuale-demo';

const args = process.argv.slice(2);
const only = args.includes('--solo') ? args[args.indexOf('--solo') + 1].split(',') : null;
const measureOnly = args.includes('--misure');

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function findChrome() {
    const candidates = [
        process.env.CHROME_PATH,
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Chromium.app/Contents/MacOS/Chromium',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
    ].filter(Boolean);
    const found = candidates.find((p) => fs.existsSync(p));
    if (!found) throw new Error('Chrome/Chromium non trovato: indica il percorso con CHROME_PATH.');

    return found;
}

if (typeof WebSocket === 'undefined') {
    console.error('Serve node 22 o più recente (WebSocket integrato).');
    process.exit(1);
}
if (fs.existsSync(path.join(ROOT, 'public/hot'))) {
    console.error('C\'è public/hot (npm run dev attivo): fermalo e lancia npm run build, gli screenshot usano gli asset compilati.');
    process.exit(1);
}
if (!fs.existsSync(path.join(ROOT, 'public/build/manifest.json'))) {
    console.error('Mancano gli asset compilati: lancia prima npm run build.');
    process.exit(1);
}

// --- Database e storage temporanei --------------------------------------
const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'timone-manuale-'));
for (const dir of ['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs']) {
    fs.mkdirSync(path.join(TMP, 'storage', dir), { recursive: true });
}
fs.writeFileSync(path.join(TMP, 'db.sqlite'), '');
const env = {
    ...process.env,
    APP_ENV: 'local',
    APP_DEBUG: 'false',
    APP_URL: BASE,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.join(TMP, 'db.sqlite'),
    LARAVEL_STORAGE_PATH: path.join(TMP, 'storage'),
    CACHE_STORE: 'array',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
    BROADCAST_CONNECTION: 'log',
    MAIL_MAILER: 'array',
};
const PHP = process.env.PHP_BINARY ?? 'php';

const children = [];
function cleanup() {
    for (const child of children) child.kill('SIGTERM');
    // Chrome appena terminato può ancora scrivere nel suo profilo (dentro
    // TMP): qualche nuovo tentativo, e mai un errore dopo schermate riuscite.
    try {
        fs.rmSync(TMP, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 });
    } catch (error) {
        console.warn(`Cartella temporanea non cancellata (${TMP}): ${error.code}`);
    }
}
process.on('exit', cleanup);
process.on('SIGINT', () => process.exit(130));

console.log('Preparo i dati di esempio…');
const seed = spawnSync(PHP, ['artisan', 'migrate:fresh', '--seed', '--seeder=ManualScreenshotSeeder', '--force'], { cwd: ROOT, env, encoding: 'utf8' });
if (seed.status !== 0) {
    console.error(seed.stdout, seed.stderr);
    process.exit(1);
}

const server = spawn(PHP, ['-S', `127.0.0.1:${PORT}`, path.join(ROOT, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: path.join(ROOT, 'public'), env, stdio: 'ignore' });
children.push(server);

const profile = path.join(TMP, 'chrome');
const chrome = spawn(findChrome(), [
    '--headless=new', '--remote-debugging-port=0', `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check',
    '--hide-scrollbars', '--force-color-profile=srgb', '--lang=it-IT', '--disable-extensions', `--window-size=${VIEWPORT.width},${VIEWPORT.height}`, 'about:blank',
], { stdio: 'ignore' });
children.push(chrome);

async function waitFor(check, what, timeout = 20000) {
    const start = Date.now();
    while (Date.now() - start < timeout) {
        const value = await check().catch(() => null);
        if (value) return value;
        await sleep(150);
    }
    throw new Error(`Tempo scaduto aspettando ${what}`);
}

await waitFor(() => fetch(`${BASE}/login`).then((r) => r.ok), 'il server PHP');
const port = await waitFor(() => fs.promises.readFile(path.join(profile, 'DevToolsActivePort'), 'utf8').then((t) => t.split('\n')[0]), 'Chrome');
const target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json();

// --- Protocollo DevTools ---------------------------------------------------
const ws = new WebSocket(target.webSocketDebuggerUrl);
let seq = 0;
const pending = new Map();
const errors = [];
ws.onmessage = (event) => {
    const msg = JSON.parse(event.data);
    if (msg.id && pending.has(msg.id)) {
        pending.get(msg.id)(msg);
        pending.delete(msg.id);
    } else if (msg.method === 'Runtime.exceptionThrown') {
        errors.push(msg.params.exceptionDetails.exception?.description ?? msg.params.exceptionDetails.text);
    }
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

await send('Page.enable');
await send('Runtime.enable');
await send('Emulation.setDeviceMetricsOverride', { ...VIEWPORT, deviceScaleFactor: SCALE, mobile: false });
await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'light' }, { name: 'prefers-reduced-motion', value: 'reduce' }] });

async function go(url) {
    await send('Page.navigate', { url });
    await sleep(300);
    await waitFor(() => evaluate(`document.readyState === 'complete' && (!document.querySelector('[x-data]') || !!window.Alpine)`), url);
    // Niente cursore lampeggiante né animazioni nelle immagini.
    await evaluate(`(() => { const s = document.createElement('style'); s.textContent = '*,*::before,*::after{transition:none!important;animation:none!important;caret-color:transparent!important}'; document.head.appendChild(s); })()`);
    await sleep(500);
}

// Aiuti dentro la pagina: elementi per selettore o per testo, rettangoli.
const HELPERS = `window.__s = {
    el(sel) { const e = document.querySelector(sel); if (!e) throw new Error('Non trovato: ' + sel); return e; },
    text(sel, text) { const e = [...document.querySelectorAll(sel)].find((x) => x.innerText.trim().includes(text) && x.offsetParent !== null); if (!e) throw new Error('Non trovato: ' + sel + ' «' + text + '»'); return e; },
    card(position) { return [...document.querySelectorAll('[data-page-id]')].filter((c) => !c.closest('[data-testid=cover-band]')).find((c) => ((c.querySelector('.drag-handle') ?? c.querySelector('span.font-semibold'))?.innerText.match(/(\\d+)\\s*$/) ?? [])[1] === String(position)) ?? (() => { throw new Error('Card ' + position); })(); },
    leaf(scope, text) { const all = [...document.querySelectorAll(scope + ' *')].filter((x) => x.innerText?.trim().includes(text) && this.visible(x)); if (!all.length) throw new Error('Non trovato: «' + text + '» in ' + scope); return all.sort((a, b) => a.getBoundingClientRect().width * a.getBoundingClientRect().height - b.getBoundingClientRect().width * b.getBoundingClientRect().height)[0]; },
    below(e) { const sticky = document.querySelector('[data-testid=app-sticky]')?.getBoundingClientRect().height ?? 0; window.scrollTo(0, this.rect(e).y - sticky - 24); },
    visible(e) { const r = e?.getBoundingClientRect(); return !!r && r.width > 0 && r.height > 0; },
    cover(label) { const c = [...document.querySelectorAll('[data-testid=cover-band] [data-page-id]')].find((x) => x.innerText.trim().startsWith(label) || x.querySelector('span')?.innerText.includes(label)); if (!c) throw new Error('Copertina ' + label); return c; },
    rect(e) { const r = e.getBoundingClientRect(); return { x: r.left + scrollX, y: r.top + scrollY, width: r.width, height: r.height }; },
    union(list) { const rs = list.map((e) => this.rect(e)); const x = Math.min(...rs.map((r) => r.x)), y = Math.min(...rs.map((r) => r.y)); return { x, y, width: Math.max(...rs.map((r) => r.x + r.width)) - x, height: Math.max(...rs.map((r) => r.y + r.height)) - y }; },
};`;

async function login() {
    await go(`${BASE}/login`);
    await evaluate(`(() => { const set = (q, v) => { const el = document.querySelector(q); el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); }; set('#email', ${JSON.stringify(EMAIL)}); set('#password', ${JSON.stringify(PASSWORD)}); document.querySelector('form').requestSubmit(); })()`);
    await waitFor(() => evaluate(`location.pathname !== '/login'`), 'il login');
    await evaluate(`localStorage.setItem('timone.zoom', 'm'); localStorage.removeItem('timone.view')`);
}

let issueUrl = null;
/**
 * Apre il numero di esempio. `panel`: pannello Contenuti aperto o chiuso
 * (preferenza salvata nel browser, come per un utente); `height`: altezza
 * della finestra, più alta per gli screenshot di dettaglio così l'area
 * sta tutta sotto la fascia fissa senza far ridisporre la griglia.
 */
let currentScale = SCALE;
async function openIssue({ panel = false, height = VIEWPORT.height } = {}) {
    await send('Emulation.setDeviceMetricsOverride', { width: VIEWPORT.width, height, deviceScaleFactor: currentScale, mobile: false });
    await evaluate(`localStorage.setItem('timone.contentPanel', JSON.stringify({ open: ${panel} }))`);
    if (!issueUrl) {
        await go(`${BASE}/riviste/orto-e-cortile`);
        issueUrl = await evaluate(`[...document.querySelectorAll('a')].map((a) => a.href).find((h) => /\\/numeri\\/\\d+$/.test(h) && document.querySelector('a[href="' + h + '"]').innerText.includes('Dicembre'))`);
        if (!issueUrl) throw new Error('Numero di esempio non trovato');
    }
    await go(issueUrl);
    await evaluate(HELPERS);
    await evaluate(`window.scrollTo(0, 0)`);
}

/**
 * Gli screenshot. `area` restituisce il rettangolo (coordinate della
 * pagina) da fotografare; `points` gli elementi da annotare, per numero:
 * con --misure lo script stampa dove cadono in percentuale.
 */
const TALL = 1600;
const pad = (expr, m = 12) => `(() => { const r = ${expr}; return { x: r.x - ${m}, y: r.y - ${m}, width: r.width + ${2 * m}, height: r.height + ${2 * m} }; })()`;

const SHOTS = [
    {
        // La schermata che si vede lavorando: fascia fissa, copertine,
        // prime pagine e pannello Contenuti aperto.
        name: 'timone-intero',
        async prepare() {
            await openIssue({ panel: true });
            await evaluate(`__s.below(__s.el('[data-testid=cover-band]'))`);
            await sleep(500);
        },
        area: `({ x: scrollX, y: scrollY, width: innerWidth, height: innerHeight })`,
        points: {
            1: `__s.el('[data-testid=summary-bar]')`,
            2: `__s.el('[data-testid=timone-toolbar]')`,
            3: `__s.text('[data-testid=timone-toolbar] button', 'Griglia')`,
            4: `__s.el('[data-testid=cover-band]')`,
            5: `__s.card(2)`,
            6: `__s.el('[data-testid=content-panel]')`,
            7: `__s.el('[data-testid=manual-help-button]')`,
        },
    },
    {
        // Una card sola è piccola: densità 4×, così nella pagina (che mostra
        // le immagini a metà dei pixel) è grande il doppio e resta nitida.
        name: 'card-pagina',
        scale: 4,
        async prepare() { await openIssue({ height: TALL }); await evaluate(`__s.below(__s.card(8))`); await sleep(300); },
        area: pad(`__s.rect(__s.card(8))`),
        points: {
            1: `__s.card(8).querySelector('.drag-handle')`,
            2: `__s.card(8).querySelector('select')`,
            3: `__s.card(8).querySelector('[data-testid=page-comments-button]')`,
            4: `__s.card(8).querySelectorAll('[data-testid=layout-block]')[0]`,
            5: `__s.card(8).querySelectorAll('[data-testid=layout-block]')[1]`,
            6: `__s.card(8).querySelector('[data-testid=page-fill]')`,
        },
    },
    {
        name: 'formati-nella-card',
        async prepare() { await openIssue({ height: TALL }); await evaluate(`__s.below(__s.card(7))`); await sleep(300); },
        area: pad(`__s.union([7, 8, 9, 10, 11, 12, 13, 14].map((p) => __s.card(p)))`),
        points: {
            1: `__s.card(7)`, 2: `__s.card(8)`, 3: `__s.card(9)`, 4: `__s.card(10)`, 5: `__s.card(12)`, 6: `__s.card(13)`, 7: `__s.card(14)`, 8: `__s.card(18)`,
        },
    },
    {
        name: 'copertine',
        async prepare() { await openIssue({ height: TALL }); await evaluate(`__s.below(__s.el('[data-testid=cover-band]'))`); await sleep(300); },
        area: pad(`__s.rect(__s.el('[data-testid=cover-band]'))`, 8),
        points: {
            1: `__s.cover('I di copertina')`,
            2: `__s.cover('II di copertina')`,
            3: `__s.el('[data-testid=add-flap-I]')`,
        },
    },
    {
        name: 'pannello-contenuti',
        async prepare() {
            await openIssue({ panel: true });
            await waitFor(() => evaluate(`__s.visible(document.querySelector('[data-testid=content-panel]'))`), 'il pannello Contenuti');
            await sleep(400);
        },
        area: `(() => { const r = __s.rect(__s.el('[data-testid=content-panel]')); return { x: r.x, y: r.y, width: r.width, height: Math.min(r.height, 640) }; })()`,
        points: {
            1: `__s.el('[data-testid^=content-tab-]')`,
            2: `__s.el('[data-testid=content-search]')`,
            3: `__s.text('[data-testid=content-panel] button', 'Nuovo contenuto')`,
            4: `__s.text('[data-testid=content-panel] button', 'Carica più file')`,
            5: `__s.el('[data-testid=content-panel-item]')`,
        },
    },
    {
        name: 'selezione-multipla',
        async prepare() {
            await openIssue({ height: TALL });
            await evaluate(`__s.text('[data-testid=timone-toolbar] button', 'Selezione').click()`);
            await sleep(700);
            for (const [i, p] of [6, 16].entries()) {
                await waitFor(() => evaluate(`!!__s.card(${p}).querySelector('input[type=checkbox]')`), 'le caselle di selezione');
                await evaluate(`__s.card(${p}).querySelector('input[type=checkbox]').click()`);
                // Aspetta che Livewire abbia contato la pagina prima del clic successivo.
                await waitFor(() => evaluate(`/(^|\\D)${i + 1} pagin/.test(document.querySelector('[data-testid=selection-label]')?.innerText ?? '')`), 'la selezione').catch(async (e) => {
                    throw new Error(e.message + ' — etichetta: «' + await evaluate(`document.querySelector('[data-testid=selection-label]')?.innerText`) + '»');
                });
            }
            await waitFor(() => evaluate(`__s.visible(document.querySelector('[data-testid=selection-bar]'))`), 'la barra della selezione');
            await sleep(400);
        },
        area: pad(`__s.rect(__s.el('[data-testid=timone-sticky-band]'))`, 0),
        points: {
            1: `__s.text('[data-testid=timone-toolbar] button', 'Selezione')`,
            2: `__s.el('[data-testid=selection-label]')`,
            3: `__s.el('[data-testid=selection-bar] select')`,
            4: `__s.leaf('[data-testid=selection-bar]', 'Collega')`,
            5: `__s.leaf('[data-testid=selection-bar]', 'Blocca')`,
        },
    },
    {
        name: 'modifica-contenuto',
        async prepare() {
            await openIssue({ panel: true, height: TALL });
            await evaluate(`[...document.querySelectorAll('[data-testid=content-panel-item]')].find((i) => i.innerText.includes('Vasi Terracotta')).querySelector('button[title*=odific], button[aria-label*=odific]').click()`);
            await waitFor(() => evaluate(`!!document.querySelector('[data-testid=content-edit-modal]')`), 'la finestra Modifica contenuto');
            await sleep(700);
        },
        area: pad(`__s.rect(__s.el('[data-testid=content-edit-modal]').firstElementChild)`, 8),
        points: {},
    },
    {
        name: 'peso',
        async prepare() { await openIssue({ height: TALL }); await evaluate(`__s.below(__s.el('[data-testid=weight-panel]'))`); await sleep(300); },
        area: `__s.rect(__s.el('[data-testid=weight-panel]'))`,
        points: {
            1: `__s.leaf('[data-testid=weight-panel]', 'Peso copia')`,
            2: `__s.leaf('[data-testid=weight-panel]', 'Scaglione')`,
            3: `__s.leaf('[data-testid=weight-panel]', 'Tiratura')`,
            4: `__s.text('[data-testid=weight-panel] button', 'Modifica parametri')`,
            5: `__s.leaf('[data-testid=weight-panel]', 'Simula')`,
        },
    },
    {
        name: 'esporta-timone-pdf',
        async prepare() {
            await openIssue();
            await evaluate(`__s.el('[data-testid=timone-export] button').click()`);
            await sleep(400);
        },
        area: pad(`__s.union([__s.el('[data-testid=timone-export] button'), __s.el('[data-testid=timone-export] form')])`, 16),
        points: {
            1: `__s.el('[data-testid=timone-export] button')`,
            2: `__s.el('[data-testid=timone-export] form label')`,
            3: `__s.text('[data-testid=timone-export] form button', 'Scarica PDF')`,
        },
    },
    {
        name: 'chiedi-al-manuale',
        async prepare() {
            await openIssue();
            await evaluate(`__s.el('[data-testid=manual-help-button]').click()`);
            await sleep(300);
            await evaluate(`(() => { const i = document.querySelector('#manual-help-question'); i.value = 'come sposto una pagina'; i.dispatchEvent(new Event('input')); i.form.requestSubmit(); })()`);
            await waitFor(() => evaluate(`!!document.querySelector('#manual-help-panel [data-testid=manual-assistant-answer]')`), "la risposta dell'assistente");
            await sleep(400);
        },
        area: `(() => { const r = __s.rect(__s.el('#manual-help-panel')); return { x: r.x, y: r.y, width: r.width, height: Math.min(r.height, innerHeight) }; })()`,
        points: {
            1: `__s.el('#manual-help-question')`,
            2: `__s.el('#manual-help-panel .manual-assistant-chip')`,
            3: `__s.el('#manual-help-panel .manual-assistant-inbreve')`,
            4: `__s.el('#manual-help-panel [data-testid=manual-assistant-useful]')`,
        },
    },
];

/** Annotazioni scritte nei blocchi `:::screenshot nome` delle pagine. */
function annotationsInManual(name) {
    for (const file of fs.readdirSync(MANUAL).filter((f) => f.endsWith('.md'))) {
        const text = fs.readFileSync(path.join(MANUAL, file), 'utf8');
        const block = text.match(new RegExp(`^:::screenshot ${name}\\n([\\s\\S]*?)^:::`, 'm'));
        if (block) {
            return { file, points: Object.fromEntries([...block[1].matchAll(/^(\d+)\s*\|\s*([\d.,]+)%\s*\|\s*([\d.,]+)%/gm)].map((m) => [m[1], [Number(m[2].replace(',', '.')), Number(m[3].replace(',', '.'))]])) };
        }
    }

    return null;
}

// --- Via -------------------------------------------------------------------
fs.mkdirSync(OUT, { recursive: true });
await login();
let warnings = 0;

for (const shot of SHOTS) {
    if (only && !only.includes(shot.name)) continue;
    try {
        currentScale = shot.scale ?? SCALE;
        await shot.prepare();
        const area = await evaluate(shot.area);
        const clip = { x: Math.max(0, Math.round(area.x)), y: Math.max(0, Math.round(area.y)), width: Math.round(area.width), height: Math.round(area.height), scale: 1 };

        const measured = {};
        for (const [n, expr] of Object.entries(shot.points)) {
            // Rettangolo dell'elemento in % dell'immagine. Il numero proposto
            // è l'angolo in alto a sinistra (al centro coprirebbe il testo di
            // un bottone); nel manuale lo si può spostare un po', basta che
            // resti sull'elemento o a meno di TOLERANCE punti dai suoi bordi.
            const r = await evaluate(`(() => { const e = ${expr}; const b = e.getBoundingClientRect(); return { x: b.left + scrollX, y: b.top + scrollY, w: b.width, h: b.height }; })()`);
            const pct = (v) => Math.round(v * 1000) / 10;
            const box = [pct((r.x - clip.x) / clip.width), pct((r.y - clip.y) / clip.height), pct((r.x + r.w - clip.x) / clip.width), pct((r.y + r.h - clip.y) / clip.height)];
            measured[n] = { suggest: [Math.min(98, Math.max(2, box[0])), Math.min(98, Math.max(2, box[1]))], box };
        }

        if (!measureOnly) {
            // Le anteprime ritagliate si caricano pigre (loading="lazy", 2026-10-30):
            // si aspetta che quelle in vista siano arrivate.
            await waitFor(() => evaluate(`[...document.images].filter((i) => { const r = i.getBoundingClientRect(); return r.bottom > 0 && r.top < innerHeight && r.width > 0; }).every((i) => i.complete && i.naturalWidth > 0)`), 'le immagini');
            const { data } = await send('Page.captureScreenshot', { format: 'webp', quality: QUALITY, clip });
            fs.writeFileSync(path.join(OUT, `${shot.name}.webp`), Buffer.from(data, 'base64'));
        }

        console.log(`✓ ${shot.name} (${clip.width}×${clip.height})`);
        const written = annotationsInManual(shot.name);
        for (const [n, { suggest: [x, y], box }] of Object.entries(measured)) {
            const inManual = written?.points[n];
            const off = inManual && (inManual[0] < box[0] - TOLERANCE || inManual[0] > box[2] + TOLERANCE || inManual[1] < box[1] - TOLERANCE || inManual[1] > box[3] + TOLERANCE);
            if (measureOnly || off || (written && !inManual)) {
                console.log(`    ${n} | ${x}% | ${y}%${inManual ? `   (nel manuale: ${inManual[0]}% | ${inManual[1]}%${off ? ', fuori dal suo elemento' : ''})` : ''}`);
            }
            if (off) warnings++;
        }
        if (!written) console.log(`    ! nessun blocco «:::screenshot ${shot.name}» nelle pagine del manuale`);
    } catch (error) {
        warnings++;
        console.error(`✗ ${shot.name}: ${error.message}`);
    }
}

ws.close();
if (errors.length) console.log('Errori JavaScript nella pagina:\n  ' + errors.join('\n  '));
console.log(warnings ? `\n${warnings} avvisi: controlla le annotazioni sopra (un numero non indica più il suo elemento).` : '\nFatto. Ora: php artisan manuale:genera');
process.exit(warnings ? 1 : 0);
