/**
 * L'ORDINE di questi due import è voluto (Fase 1, bug 5, 2026-10-26).
 *
 * Gli import ES vengono valutati in sequenza e PRIMA del corpo del modulo:
 * quando gli store Alpine (pagePresence, realtimeFallback, …) erano
 * registrati qui nel corpo di app.js, un'eccezione durante la valutazione
 * di './bootstrap' → './echo' (es. `new Echo()` con VITE_REVERB_APP_KEY
 * mancante al momento del `npm run build`: pusher-js lancia "You must pass
 * your app key") interrompeva l'intero modulo prima ancora di arrivare a
 * `document.addEventListener('alpine:init', …)`. Livewire avviava comunque
 * Alpine al DOMContentLoaded, e ogni espressione del timone esplodeva con
 * "Cannot read properties of undefined (reading 'editorFor')" — lo store
 * semplicemente non esisteva. Registrando gli store in un modulo a sé,
 * importato per primo, un problema di Echo non può più toglierli di mezzo
 * (e './echo' ora non lancia comunque più: vedi lì).
 */
import './timone-alpine';
import { installDragAutoScroll } from './drag-autoscroll';
import './deleted-issue-redirect';
import './bootstrap';

// Revisione 2, Fase 1, bug 4: auto-scroll durante ogni trascinamento
// (pagine, gruppi, contenuti, copertine) — vedi drag-autoscroll.js.
installDragAutoScroll();
