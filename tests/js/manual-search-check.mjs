// Controllo della ricerca del manuale con le domande reali (2026-10-29).
// Lanciato da tests/Feature/ManualSearchTest.php con node:
//   node tests/js/manual-search-check.mjs <indice.json> <domande.json> <testi.json>
// Stampa un JSON: per ogni domanda i primi risultati; per ogni testo di
// tests/fixtures/manual-text.json le parole come le normalizza il browser.
import fs from 'node:fs';
import MiniSearch from 'minisearch';
import { createEngine, makeLanguage, search } from '../../resources/js/manual-search-core.js';

const [indexPath, queriesPath, textsPath] = process.argv.slice(2);
const data = JSON.parse(fs.readFileSync(indexPath, 'utf8'));
const engine = createEngine(MiniSearch, data);
const lang = makeLanguage(data.lingua);

const queries = queriesPath ? JSON.parse(fs.readFileSync(queriesPath, 'utf8')) : [];
const texts = textsPath ? JSON.parse(fs.readFileSync(textsPath, 'utf8')) : [];

console.log(JSON.stringify({
    risultati: queries.map((q) => ({ domanda: q.domanda, primi: search(engine, q.domanda, { limit: 5 }).map((r) => r.id) })),
    testi: texts.map((t) => ({ testo: t.testo, parole: lang.tokens(t.testo) })),
}));
