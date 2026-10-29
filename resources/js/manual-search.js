/**
 * Pagine del manuale (2026-10-29): ricerca, filtro per ruolo nell'indice,
 * sezione corrente evidenziata, copia link, «torna su», stampa con i
 * «Dettagli» aperti. Componenti Alpine registrati qui; la logica di ricerca
 * è in manual-search-core.js (pura, provata anche con node).
 *
 * MiniSearch e l'indice (/manuale/indice.json) si caricano solo la prima
 * volta che si usa la ricerca: chi apre il timone non scarica nulla.
 */
import { createEngine, search, snippet, suggestions } from './manual-search-core';

let enginePromise = null;

function loadEngine(url) {
    enginePromise ??= Promise.all([import('minisearch'), fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })])
        .then(async ([{ default: MiniSearch }, response]) => {
            if (!response.ok) throw new Error('Indice del manuale non disponibile');

            return createEngine(MiniSearch, await response.json());
        })
        .catch((error) => {
            enginePromise = null;
            throw error;
        });

    return enginePromise;
}

/** Il campo è il bersaglio di una scorciatoia solo se non si sta scrivendo altrove. */
function typingElsewhere(event) {
    const el = event.target;

    return el instanceof HTMLElement && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName));
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.data('manualSearch', (indexUrl) => ({
        query: '',
        results: [],
        tried: [],
        active: -1,
        open: false,
        loading: false,
        failed: false,
        engine: null,

        init() {
            // «/» o Ctrl/⌘+K portano al campo da qualunque punto della pagina.
            window.addEventListener('keydown', (event) => {
                const isSlash = event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey;
                const isK = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k';
                if ((isSlash && !typingElsewhere(event)) || isK) {
                    event.preventDefault();
                    this.$refs.input.focus();
                    this.$refs.input.select();
                }
            });
        },

        async ensureEngine() {
            if (this.engine) return this.engine;
            this.loading = true;
            try {
                this.engine = await loadEngine(indexUrl);
            } catch (error) {
                this.failed = true;
            } finally {
                this.loading = false;
            }

            return this.engine;
        },

        async update() {
            const engine = await this.ensureEngine();
            if (!engine) return;

            const query = this.query.trim();
            this.results = query === '' ? [] : search(engine, query).map((r) => ({
                ...r,
                html: snippet(engine, r.testo, r.terms.length ? r.terms : engine.lang.words(query)),
            }));
            this.tried = query !== '' && this.results.length === 0 ? suggestions(engine, query) : [];
            this.active = this.results.length ? 0 : -1;
            this.open = query !== '';
        },

        move(step) {
            if (!this.results.length) return;
            this.active = (this.active + step + this.results.length) % this.results.length;
            this.$nextTick(() => this.$refs.list?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' }));
        },

        go(result = this.results[this.active]) {
            if (!result) return;
            window.location.href = result.url;
        },

        tryWord(word) {
            this.query = word;
            this.update();
            this.$refs.input.focus();
        },

        close() {
            this.open = false;
        },
    }));

    /**
     * Filtro dell'indice per ruolo: le pagine pensate per un altro ruolo si
     * nascondono, ma «mostra tutto» le riporta (non si nasconde mai nulla del
     * tutto). Predefinito sul ruolo di chi legge; la scelta resta nel browser.
     */
    Alpine.data('manualNav', (defaultRole) => ({
        role: defaultRole,
        showAll: false,

        init() {
            try {
                const saved = window.localStorage.getItem('manuale.ruolo');
                if (saved) this.role = saved;
            } catch (error) {
                // Preferenza non leggibile: resta il ruolo di chi legge.
            }
            this.$watch('role', (value) => {
                this.showAll = false;
                try { window.localStorage.setItem('manuale.ruolo', value); } catch (error) { /* non ricordata */ }
            });
        },

        visible(roles) {
            return this.role === 'tutti' || this.showAll || roles.split(' ').includes(this.role);
        },

        hiddenCount() {
            if (this.role === 'tutti' || this.showAll) return 0;

            return [...this.$root.querySelectorAll('[data-ruoli]')].filter((el) => !el.dataset.ruoli.split(' ').includes(this.role)).length;
        },
    }));

    /** «In questa pagina»: la sezione che si sta leggendo è evidenziata. */
    Alpine.data('manualToc', () => ({
        current: null,

        init() {
            const headings = [...document.querySelectorAll('[data-testid="manual-page"] h2 > a[id]')];
            if (!headings.length || !('IntersectionObserver' in window)) return;

            const observer = new IntersectionObserver((entries) => {
                const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
                if (visible.length) this.current = visible[0].target.id;
            }, { rootMargin: '0px 0px -70% 0px' });
            headings.forEach((h) => observer.observe(h));
        },
    }));

    Alpine.data('manualBackToTop', () => ({
        shown: false,
        init() {
            const update = () => { this.shown = window.scrollY > 600; };
            window.addEventListener('scroll', update, { passive: true });
            update();
        },
        top() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            document.querySelector('#manual-search-input')?.focus({ preventScroll: true });
        },
    }));
});

/**
 * Icona accanto a ogni titolo: copia il link alla sezione invece di
 * saltarci (ci si è già). L'indirizzo resta nel link, quindi con il tasto
 * destro o senza JavaScript funziona come prima.
 */
document.addEventListener('click', async (event) => {
    const link = event.target.closest?.('.manuale-content a.heading-permalink');
    if (!link) return;
    event.preventDefault();

    const url = new URL(link.getAttribute('href'), window.location.href).toString();
    try {
        await navigator.clipboard.writeText(url);
        link.dataset.copied = '1';
        setTimeout(() => delete link.dataset.copied, 1500);
    } catch (error) {
        window.location.hash = link.getAttribute('href');
    }
});

/** Arrivando su una sezione (#ancora), la si evidenzia per un attimo. */
function flashTarget() {
    const id = decodeURIComponent(window.location.hash.slice(1));
    const anchor = id ? document.getElementById(id) : null;
    const block = anchor?.closest('h2, h3') ?? anchor;
    if (!block) return;
    block.classList.remove('manual-flash');
    void block.offsetWidth;
    block.classList.add('manual-flash');
}
window.addEventListener('hashchange', flashTarget);
document.addEventListener('DOMContentLoaded', flashTarget);

/** Stampa: i «Dettagli» si aprono e poi tornano com'erano. */
let closedForPrint = [];
window.addEventListener('beforeprint', () => {
    closedForPrint = [...document.querySelectorAll('.manuale-content details:not([open])')];
    closedForPrint.forEach((d) => { d.open = true; });
});
window.addEventListener('afterprint', () => {
    closedForPrint.forEach((d) => { d.open = false; });
    closedForPrint = [];
});
