import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

/**
 * Fase 1, bug 5 (2026-10-26): `new Echo()` senza chiave Reverb (variabile
 * VITE_REVERB_APP_KEY assente quando si è eseguito `npm run build`) lancia
 * un'eccezione di pusher-js durante la valutazione del modulo, che prima
 * si portava dietro tutto app.js (store Alpine compresi, vedi app.js).
 * Senza Echo il timone resta comunque utilizzabile: lo store
 * `realtimeFallback` (resources/js/timone-alpine.js) vede `window.Echo`
 * assente e attiva il polling di Livewire (`wire:poll`, grid.blade.php).
 * L'avviso in console spiega a chi fa debug perché il realtime è spento.
 */
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (! reverbKey) {
    console.warn('[timone] VITE_REVERB_APP_KEY non impostata al momento della build: aggiornamento in tempo reale disattivato, si usa il polling.');
} else {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } catch (error) {
        console.warn('[timone] Impossibile inizializzare Laravel Echo: aggiornamento in tempo reale disattivato, si usa il polling.', error);
    }
}
