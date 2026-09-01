import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        // Il container "vite" (docker-compose.dev.yml) lancia "vite --host
        // 0.0.0.0" per essere raggiungibile da fuori il container sulla rete
        // Docker — ma senza "origin" esplicito, il plugin Laravel scrive
        // quello stesso indirizzo di bind (0.0.0.0) dentro public/hot, che
        // Blade (@vite()) usa poi per generare gli URL degli <script> nella
        // pagina. Il BROWSER sull'host non può risolvere 0.0.0.0 (dal 2024
        // Chrome/Chromium lo blocca esplicitamente per la vulnerabilità
        // "0.0.0.0 Day", con net::ERR_ADDRESS_INVALID) — risultato: ogni
        // asset (app.js, quindi Echo/Alpine store custom/Sortable/wire:poll)
        // falliva silenziosamente al caricamento, in incognito e in
        // normale allo stesso modo (non è mai stata cache del browser),
        // portando sia il realtime via Reverb sia il fallback a polling a
        // non attivarsi mai (bug scoperto il 2026-09-01 indagando la
        // segnalazione "il polling non funziona"). "origin" fissa l'URL che
        // finisce nella pagina, indipendente dall'host di bind del server.
        origin: 'http://localhost:5173',
    },
});
