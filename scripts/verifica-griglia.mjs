#!/usr/bin/env node
/**
 * Verifica della griglia ad aperture (2026-10-30, Fase C1):
 * `node scripts/verifica-griglia.mjs` (dopo `npm run build`).
 *
 * Con i dati del manuale (ManualScreenshotSeeder, database e storage
 * temporanei) apre il numero di esempio in Griglia e in Doppia pagina a
 * 390, 1366 e 1920 px con lo zoom S, M e L. Per ogni combinazione
 * controlla che le due pagine di ogni apertura stiano sulla stessa riga,
 * attaccate (sinistra prima della destra, distanza ≤ 4 px), che nessuna
 * pagina esca dallo schermo e che non ci sia scorrimento orizzontale; in
 * Doppia pagina, un'apertura per riga. Salva gli screenshot (zoom M) in
 * docs/verifiche/griglia/ e stampa il riepilogo; esce con errore se un
 * controllo fallisce. Richiede Chrome e node ≥ 22 (CHROME_PATH).
 */
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'docs/verifiche/griglia');
const PORT = 8767, BASE = `http://127.0.0.1:${PORT}`;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'timone-c0-'));
for (const d of ['app/private','app/public','framework/cache/data','framework/sessions','framework/views','logs']) fs.mkdirSync(path.join(TMP,'storage',d),{recursive:true});
fs.writeFileSync(path.join(TMP,'db.sqlite'),'');
const env = {...process.env, APP_ENV:'local', APP_DEBUG:'false', APP_URL:BASE, DB_CONNECTION:'sqlite', DB_DATABASE:path.join(TMP,'db.sqlite'), LARAVEL_STORAGE_PATH:path.join(TMP,'storage'), CACHE_STORE:'array', SESSION_DRIVER:'file', QUEUE_CONNECTION:'sync', BROADCAST_CONNECTION:'log', MAIL_MAILER:'array'};
const kids=[]; process.on('exit',()=>{for(const k of kids)k.kill();});
const r = spawnSync('php',['artisan','migrate:fresh','--seed','--seeder=ManualScreenshotSeeder','--force'],{cwd:ROOT,env,encoding:'utf8'}); if (r.status) { console.log(r.stdout,r.stderr); process.exit(1); }
kids.push(spawn('php',['-S',`127.0.0.1:${PORT}`,path.join(ROOT,'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],{cwd:path.join(ROOT,'public'),env,stdio:'ignore'}));
const profile=path.join(TMP,'chrome');
kids.push(spawn(process.env.CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',['--headless=new','--remote-debugging-port=0',`--user-data-dir=${profile}`,'--no-first-run','--hide-scrollbars','about:blank'],{stdio:'ignore'}));
async function waitFor(f,w,t=20000){const s=Date.now();while(Date.now()-s<t){const v=await f().catch(()=>null);if(v)return v;await sleep(150);}throw new Error(w);}
await waitFor(()=>fetch(`${BASE}/login`).then(r=>r.ok),'php');
const port=await waitFor(()=>fs.promises.readFile(path.join(profile,'DevToolsActivePort'),'utf8').then(t=>t.split('\n')[0]),'chrome');
const target=await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`,{method:'PUT'})).json();
const ws=new WebSocket(target.webSocketDebuggerUrl); let seq=0; const pend=new Map();
ws.onmessage=(e)=>{const m=JSON.parse(e.data); if(m.id&&pend.has(m.id)){pend.get(m.id)(m);pend.delete(m.id);}};
await new Promise(r=>ws.onopen=r);
const send=(method,params={})=>new Promise((res,rej)=>{const id=++seq;pend.set(id,m=>m.error?rej(new Error(method+m.error.message)):res(m.result));ws.send(JSON.stringify({id,method,params}));});
const ev=async(x)=>{const r=await send('Runtime.evaluate',{expression:x,returnByValue:true,awaitPromise:true}); if(r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description??x); return r.result.value;};
async function go(u){await send('Page.navigate',{url:u});await sleep(400);await waitFor(()=>ev(`document.readyState==='complete'&&(!document.querySelector('[x-data]')||!!window.Alpine)`),u);await sleep(800);}
await send('Page.enable');
await send('Emulation.setDeviceMetricsOverride',{width:1366,height:900,deviceScaleFactor:1,mobile:false});
await go(`${BASE}/login`);
await ev(`(()=>{const s=(q,v)=>{const e=document.querySelector(q);e.value=v;e.dispatchEvent(new Event('input',{bubbles:true}))};s('#email','laura.esempio@esempio.test');s('#password','manuale-demo');document.querySelector('form').requestSubmit();})()`);
await waitFor(()=>ev(`location.pathname!=='/login'`),'login');
await go(`${BASE}/riviste/orto-e-cortile`);
const issueUrl=await ev(`[...document.querySelectorAll('a')].map(a=>a.href).find(h=>/\\/numeri\\/\\d+$/.test(h)&&document.querySelector('a[href="'+h+'"]').innerText.includes('Dicembre'))`);
fs.mkdirSync(OUT,{recursive:true});
for (const old of fs.readdirSync(OUT).filter((f) => f.endsWith('.png'))) fs.rmSync(path.join(OUT, old));
// Coppie: figli del contenitore a due a due (pagine e posti vuoti), come in app.css.
const CHECK = `(() => {
  const grid = document.querySelector('[data-testid=spread-grid]');
  const kids = [...grid.children];
  const problems = []; const rows = new Set();
  for (let i = 0; i < kids.length; i += 2) {
    const a = kids[i].getBoundingClientRect(), b = kids[i + 1]?.getBoundingClientRect();
    if (!b) continue;
    rows.add(Math.round(a.top));
    if (Math.abs(a.top - b.top) > 1) problems.push('coppia ' + (i / 2 + 1) + ' su due righe');
    if (b.left - a.right > 4 || b.left < a.right - 1) problems.push('coppia ' + (i / 2 + 1) + ' non attaccata (' + Math.round(b.left - a.right) + ' px)');
    if (a.left < 0 || b.right > innerWidth) problems.push('coppia ' + (i / 2 + 1) + ' fuori dallo schermo');
  }
  const perRow = Math.round(kids.length / 2 / rows.size);
  return { problems, perRow: kids.filter((k, i) => i % 2 === 0 && Math.round(k.getBoundingClientRect().top) === Math.round(kids[0].getBoundingClientRect().top)).length, scroll: document.documentElement.scrollWidth > innerWidth };
})()`;
let failures = 0;
for (const [w, mobile] of [[390, true], [1366, false], [1920, false]]) {
  for (const zoom of ['s', 'm', 'l']) {
    for (const view of ['griglia', 'doppia']) {
      await send('Emulation.setDeviceMetricsOverride',{width:w,height:900,deviceScaleFactor:1,mobile});
      await ev(`localStorage.setItem('timone.zoom','${zoom}');localStorage.setItem('timone.contentPanel',JSON.stringify({open:false}))`);
      await go(issueUrl);
      await ev(`[...document.querySelectorAll('button')].find(b=>b.innerText.trim()==='${view==='griglia'?'Griglia':'Doppia pagina'}').click()`);
      await sleep(1500);
      const r = await ev(CHECK);
      if (view === 'doppia' && r.perRow !== 1) r.problems.push('Doppia pagina con ' + r.perRow + ' aperture per riga');
      if (r.scroll) r.problems.push('scorrimento orizzontale');
      failures += r.problems.length;
      console.log(`${view.padEnd(8)} ${String(w).padStart(4)} px zoom ${zoom.toUpperCase()}: ${r.perRow} aperture per riga${r.problems.length ? ' — ' + r.problems.join('; ') : ' — ok'}`);
      if (zoom === 'm') {
        const h=await ev(`Math.ceil(document.documentElement.scrollHeight)`);
        const top=await ev(`document.querySelector('[data-testid=spread-grid]').getBoundingClientRect().top + scrollY - 10`);
        await send('Emulation.setDeviceMetricsOverride',{width:w,height:Math.min(h,6000),deviceScaleFactor:1,mobile});
        await sleep(800);
        const shot=await send('Page.captureScreenshot',{format:'png',clip:{x:0,y:top,width:w,height:Math.min(h-top,2600),scale:1}});
        fs.writeFileSync(path.join(OUT,`${view}-${w}.png`),Buffer.from(shot.data,'base64'));
      }
    }
  }
}
// Shift+clic nel browser: Ctrl+clic sulla pagina 2, Shift+clic sulla 5 → 4 pagine selezionate.
await send('Emulation.setDeviceMetricsOverride',{width:1366,height:900,deviceScaleFactor:1,mobile:false});
await ev(`localStorage.setItem('timone.zoom','m')`);
await go(issueUrl);
const card = (n) => `[...document.querySelectorAll('[data-testid=spread-grid] [data-page-id]')].find((c) => ((c.querySelector('.drag-handle') ?? c.querySelector('span.font-semibold'))?.innerText.match(/(\\d+)\\s*$/) ?? [])[1] === '${n}')`;
const clickWith = (n, mods) => ev(`${card(n)}.dispatchEvent(new MouseEvent('click', { bubbles: true, ${mods} }))`);
await clickWith(2, 'ctrlKey: true');
await waitFor(() => ev(`(document.querySelector('[data-testid=selection-label]')?.innerText ?? '').includes('1 pagina')`), 'selezione di 1 pagina');
await clickWith(5, 'shiftKey: true');
const label = await waitFor(() => ev(`(() => { const t = document.querySelector('[data-testid=selection-label]')?.innerText ?? ''; return /4 pagine/.test(t) ? t : null; })()`), 'selezione di 4 pagine').catch(() => null);
console.log('Shift+clic dalla 2 alla 5: ' + (label ?? 'NON riuscito'));
if (!label) failures++;

process.exit(failures ? 1 : 0);
