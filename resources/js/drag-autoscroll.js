/**
 * Auto-scroll durante il trascinamento (revisione 2, Fase 1, bug 4,
 * 2026-10-27): trascinando un contenuto o una pagina verso il bordo
 * superiore o inferiore della finestra, la pagina deve scorrere fino alle
 * ultime pagine e alle copertine III/IV.
 *
 * Perché non bastava Sortable: il timone usa il drag&drop NATIVO del
 * browser (Sortable senza forceFallback per le pagine, draggable="true" per
 * i contenuti da assegnare e le copertine). Con il drag nativo, in Chrome e
 * Firefox, l'AutoScroll di Sortable lascia lo scroll della finestra al
 * browser (Sortable.js, _handleAutoScroll: esce se il contenitore che
 * scorre è la finestra) e i contenuti/copertine non passano proprio da
 * Sortable. Lo scroll nativo del browser parte solo a pochi pixel dal
 * bordo: in alto quei pixel sono coperti dalla fascia sticky
 * (header + barra di riepilogo), quindi verso l'alto non partiva mai, e in
 * basso serviva una precisione al pixel.
 *
 * Qui un unico gestore per TUTTI i drag (pagine, gruppi di pagine,
 * contenuti, copertine): ascolta `dragover` sul documento, calcola la
 * distanza dal bordo del contenitore che scorre davvero (il primo antenato
 * con overflow scrollabile, altrimenti la finestra) e scorre con una
 * velocità che cresce avvicinandosi al bordo. Il bordo superiore della
 * finestra è il fondo della fascia sticky: gli elementi marcati
 * `data-sticky-top` (dal 2026-10-28 uno solo: il contenitore sticky di
 * layouts/app.blade.php con header e fascia del timone). Sortable ha
 * `scroll: false` (timone-alpine.js) per non sommare due scroll.
 */

// Zona sensibile (px dal bordo utile) e velocità massima (px per frame).
const ZONE = 110;
const MAX_SPEED = 28;
// dragover arriva ogni ~50 ms anche a mouse fermo; oltre questo silenzio
// il puntatore è uscito dalla finestra o il drag è finito senza dragend.
const IDLE_MS = 250;

let active = false;
let velocity = 0;
let scroller = null;
let lastOverAt = 0;
let frame = null;

function isScrollable(el) {
    if (! (el instanceof Element) || el === document.body || el === document.documentElement) return false;

    const overflowY = getComputedStyle(el).overflowY;

    return (overflowY === 'auto' || overflowY === 'scroll') && el.scrollHeight > el.clientHeight + 1;
}

function scrollContainerOf(target) {
    for (let el = target; el; el = el.parentElement) {
        if (isScrollable(el)) return el;
    }

    return null; // la finestra
}

/** Fondo della fascia sticky in alto (0 se non c'è). */
export function stickyTopEdge() {
    let edge = 0;

    document.querySelectorAll('[data-sticky-top]').forEach((el) => {
        const rect = el.getBoundingClientRect();

        // Solo se è davvero in cima (sticky agganciato), non più giù nel flusso.
        // 0.75 e non 0.5 (2026-10-28): la tendina del pannello contenuti sotto
        // la fascia arriva al 40% della finestra e conta come bordo alto.
        if (rect.height > 0 && rect.top <= edge + 1 && rect.bottom < window.innerHeight * 0.75) {
            edge = Math.max(edge, rect.bottom);
        }
    });

    return edge;
}

/**
 * Velocità di scroll (px per frame, negativa = verso l'alto) per un
 * puntatore a `y` in un'area visibile [top, bottom]: zero fuori dalla zona
 * sensibile, crescente (quadratica) verso il bordo. Esportata per i test.
 */
export function speedFor(y, top, bottom, zone = ZONE, max = MAX_SPEED) {
    const size = Math.min(zone, (bottom - top) / 3);

    if (y < top + size) {
        const depth = Math.min(1, (top + size - y) / size);

        return -Math.ceil(max * depth * depth);
    }

    if (y > bottom - size) {
        const depth = Math.min(1, (y - (bottom - size)) / size);

        return Math.ceil(max * depth * depth);
    }

    return 0;
}

function tick() {
    frame = null;

    if (! active || velocity === 0 || performance.now() - lastOverAt > IDLE_MS) return;

    if (scroller) {
        scroller.scrollTop += velocity;
    } else {
        window.scrollBy(0, velocity);
    }

    frame = requestAnimationFrame(tick);
}

function onDragOver(event) {
    if (! active) return;

    lastOverAt = performance.now();
    scroller = scrollContainerOf(event.target);

    const area = scroller
        ? (({ top, bottom }) => ({ top: Math.max(top, stickyTopEdge()), bottom: Math.min(bottom, window.innerHeight) }))(scroller.getBoundingClientRect())
        : { top: stickyTopEdge(), bottom: window.innerHeight };

    velocity = speedFor(event.clientY, area.top, area.bottom);

    if (velocity !== 0 && frame === null) frame = requestAnimationFrame(tick);
}

function stop() {
    active = false;
    velocity = 0;

    if (frame !== null) cancelAnimationFrame(frame);
    frame = null;
}

export function installDragAutoScroll() {
    document.addEventListener('dragstart', () => { active = true; lastOverAt = performance.now(); }, true);
    document.addEventListener('dragover', onDragOver, true);
    document.addEventListener('dragend', stop, true);
    document.addEventListener('drop', stop, true);
}
