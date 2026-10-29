/**
 * Ricerca nel manuale (2026-10-29), parte pura: niente DOM, niente fetch.
 * La usa la pagina del manuale (manual-search.js) e anche il test con node
 * (tests/js/manual-search-check.mjs) sulle domande reali: le due strade
 * usano la stessa identica logica.
 *
 * Le regole della lingua (accenti, parole vuote, desinenze) arrivano dal
 * server nel JSON dell'indice (App\Support\Manual\ItalianText): qui c'è
 * solo lo stesso algoritmo, controllato contro tests/fixtures/manual-text.json.
 *
 * Perché MiniSearch e non Fuse.js: MiniSearch indicizza le parole (prefisso,
 * tolleranza agli errori di battitura, pesi per campo, normalizzazione
 * nostra al momento di spezzare il testo); Fuse confronta stringhe intere
 * in modo approssimato, più lento e meno preciso su testi lunghi.
 */

/** Pesi dei campi: titoli e parole chiave contano più del testo. */
export const BOOST = { titolo: 4, sezione: 3, parole_chiave: 3, sinonimi: 2, testo: 1 };

/**
 * Peso dei sinonimi rispetto alla parola scritta (che vale 1). Diminuisce
 * con la grandezza del gruppo (radice quadrata), così un gruppo grande
 * (togliere/eliminare/cancellare…) non porta in cima una pagina solo perché
 * ne contiene tanti. Se però la parola scritta nel manuale non compare mai
 * (forme irregolari come «tolgo»), la domanda si regge sui sinonimi: allora
 * valgono quasi quanto lei.
 */
export const SYNONYM_WEIGHT = 0.5;
export const SYNONYM_WEIGHT_UNKNOWN_WORD = 0.8;

/** Pagine di riferimento generale: compaiono, ma non scavalcano la pagina che spiega. */
const DOCUMENT_BOOST = { novita: 0.4, 'note-amministratore': 0.6, glossario: 0.8 };

export function makeLanguage(lingua) {
    const stop = new Set(lingua.paroleVuote);
    const fold = (text) => text.toLowerCase().replace(/[^\u0000-\u007f]/g, (ch) => lingua.piega[ch] ?? ch);

    const words = (text) => fold(text).split(/[^a-z0-9]+/).filter(Boolean);

    const stem = (word) => {
        if (/\d/.test(word) || word.length <= lingua.radiceMinima) return word;

        for (const suffix of lingua.desinenze) {
            if (word.endsWith(suffix) && word.length - suffix.length >= lingua.radiceMinima) {
                return word.slice(0, -suffix.length);
            }
        }

        return word;
    };

    const tokens = (text) => words(text).filter((w) => !stop.has(w)).map(stem);

    return { fold, words, stem, tokens, isStopword: (w) => stop.has(w) };
}

/**
 * @param {typeof import('minisearch').default} MiniSearch
 * @param {object} data il JSON di /manuale/indice.json
 */
export function createEngine(MiniSearch, data) {
    const lang = makeLanguage(data.lingua);
    const index = new MiniSearch({
        idField: 'id',
        fields: ['titolo', 'sezione', 'parole_chiave', 'sinonimi', 'testo'],
        storeFields: ['slug', 'url', 'ancora', 'titolo', 'sezione', 'categoria', 'testo', 'ruoli'],
        tokenize: (text) => lang.words(text ?? ''),
        processTerm: (term) => (lang.isStopword(term) ? null : lang.stem(term)),
    });
    index.addAll(data.documenti);

    // Radice → parole del suo gruppo di sinonimi (più gruppi si uniscono).
    const synonyms = new Map();
    for (const group of data.sinonimi) {
        const stems = new Set(group.map((w) => lang.stem(lang.words(w)[0])));
        for (const s of stems) {
            const known = synonyms.get(s) ?? new Set();
            group.forEach((w) => known.add(w));
            synonyms.set(s, known);
        }
    }

    return { index, lang, synonyms, data };
}

/**
 * Risultati per la query: al massimo due per pagina (la sezione migliore
 * e una seconda), in ordine di punteggio.
 *
 * @returns {Array<{id: string, url: string, slug: string, titolo: string, sezione: ?string, categoria: string, testo: string, score: number, terms: string[]}>}
 */
export function search(engine, query, { limit = 8, perPage = 2 } = {}) {
    const words = engine.lang.words(query).filter((w) => !engine.lang.isStopword(w));
    if (words.length === 0) return [];

    const queries = words.map((word) => {
        const group = engine.synonyms.get(engine.lang.stem(word));
        const alternatives = group ? [...new Set([word, ...group])] : [word];

        // Una stringa sola: MiniSearch numera i termini di una stringa, e
        // boostTerm riconosce la parola scritta (indice 0) dai sinonimi. Con
        // un'alternativa per sotto-ricerca ognuna sarebbe «la prima».
        return { combineWith: 'OR', queries: [alternatives.join(' ')] };
    });

    // Parole scritte che nel manuale non compaiono (nemmeno come inizio di parola).
    const unknown = new Set(
        words.map((w) => engine.lang.stem(w)).filter((stem) => engine.index.search(stem, { prefix: true, fields: ['titolo', 'sezione', 'parole_chiave', 'sinonimi', 'testo'] }).length === 0),
    );

    const raw = engine.index.search(
        { combineWith: 'OR', queries },
        {
            boost: BOOST,
            prefix: (term) => term.length >= 3,
            // Solo parole lunghe: su parole corte un errore ammesso cambia
            // parola («togli» → «sogli»).
            fuzzy: (term) => (term.length >= 6 ? 0.2 : false),
            boostDocument: (id) => DOCUMENT_BOOST[String(id).split('#')[0]] ?? 1,
            // In ogni gruppo la prima è la parola scritta, le altre sono sinonimi.
            boostTerm: (term, i, terms) => {
                if (i === 0) return 1;

                return unknown.has(terms[0]) ? SYNONYM_WEIGHT_UNKNOWN_WORD : SYNONYM_WEIGHT / Math.sqrt(terms.length - 1);
            },
        },
    );

    // Quante delle parole cercate trova ogni risultato (la parola o un suo
    // sinonimo): una pagina che le trova tutte batte una che ne trova una
    // sola, anche se quella una è nel titolo.
    const groups = queries.map((q) => new Set(engine.lang.words(q.queries[0]).map(engine.lang.stem)));
    const coverage = (hit) => {
        const matched = Object.keys(hit.match ?? {});
        const found = groups.filter((stems) => matched.some((m) => [...stems].some((s) => m.startsWith(s) || s.startsWith(m)))).length;

        return found / groups.length;
    };
    const ranked = raw
        .map((hit) => ({ ...hit, score: hit.score * coverage(hit) ** 2 }))
        .sort((a, b) => b.score - a.score);

    const perSlug = new Map();
    const results = [];
    for (const hit of ranked) {
        const count = perSlug.get(hit.slug) ?? 0;
        if (count >= perPage) continue;
        perSlug.set(hit.slug, count + 1);
        results.push({ ...hit, terms: hit.terms ?? [] });
        if (results.length >= limit) break;
    }

    return results;
}

/** Parole del gruppo di sinonimi delle parole cercate, non già scritte: «Prova con…». */
export function suggestions(engine, query, max = 6) {
    const typed = new Set(engine.lang.words(query));
    const out = new Set();
    for (const word of typed) {
        for (const alt of engine.synonyms.get(engine.lang.stem(word)) ?? []) {
            if (!typed.has(alt)) out.add(alt);
        }
    }

    return [...out].slice(0, max);
}

/**
 * Frammento del testo attorno alla prima parola trovata, già escapato,
 * con le parole trovate in <mark>.
 */
export function snippet(engine, text, terms, size = 160) {
    const escape = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const stems = new Set(terms.map((t) => engine.lang.stem(t)));
    const matches = (word) => {
        const w = engine.lang.fold(word).replace(/[^a-z0-9]/g, '');
        return w !== '' && [...stems].some((s) => s && engine.lang.stem(w).startsWith(s));
    };

    const parts = text.split(/(\s+)/);
    let first = parts.findIndex((p) => p.trim() !== '' && matches(p));
    if (first < 0) first = 0;

    let start = first;
    let length = 0;
    while (start > 0 && length < size / 3) { start--; length += parts[start].length; }
    let end = first;
    length = 0;
    while (end < parts.length && length < size) { length += parts[end].length; end++; }

    // Solo le lettere della parola, non la punteggiatura attaccata («rimuovere:»).
    const mark = (p) => {
        const [, before, core, after] = p.match(/^([^\p{L}\p{N}]*)(.*?)([^\p{L}\p{N}]*)$/u);

        return `${escape(before)}<mark>${escape(core)}</mark>${escape(after)}`;
    };
    const body = parts.slice(start, end).map((p) => (p.trim() !== '' && matches(p) ? mark(p) : escape(p))).join('');

    return (start > 0 ? '… ' : '') + body.trim() + (end < parts.length ? ' …' : '');
}
