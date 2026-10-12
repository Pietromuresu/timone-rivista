<?php

namespace App\Http\Controllers;

use App\Support\ManualRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;

/**
 * Manuale utente consultabile dall'app. La fonte di verità resta
 * MANUALE_UTENTE.md nella radice del progetto, letto a ogni richiesta:
 * nessuna copia in public/, nessun symlink, nessun cambio di permessi —
 * basta che il file sia leggibile dall'utente PHP, come il resto del
 * codice. Nessun componente Livewire: pagina di sola lettura.
 *
 * Bug 3 (2026-10-12): in produzione la pagina andava in 500 perché
 * `.dockerignore` escludeva tutti i *.md tranne README.md, quindi il file
 * non esisteva nell'immagine (in dev si vedeva grazie al bind mount).
 * Corretto `.dockerignore`; qui in più un file mancante mostra un
 * messaggio invece di un errore.
 */
class DocumentationController extends Controller
{
    public function show(): View
    {
        // Percorso in config solo per poterlo sostituire nei test (file
        // mancante); nessuno deve cambiarlo in esercizio.
        $path = config('app.manual_path');

        if (! File::isReadable($path)) {
            report(new \RuntimeException("Manuale utente non trovato o non leggibile: {$path}"));

            return view('documentation.show', ['contentHtml' => null, 'toc' => []]);
        }

        // APP_URL dal .env (via config/app.php): l'indirizzo reale di questa
        // installazione, non il localhost dello sviluppo.
        $rendered = ManualRenderer::render(File::get($path), [
            'APP_URL' => rtrim((string) config('app.url'), '/'),
        ]);

        return view('documentation.show', ['contentHtml' => $rendered['html'], 'toc' => $rendered['toc']]);
    }
}
