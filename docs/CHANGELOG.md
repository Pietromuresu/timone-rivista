# Changelog

[← Indice](README.md)

Modifiche per fase a partire dal batch "revisione con gli utenti" (prompt del 2026-10-26: Fasi 1–7). La cronologia precedente, più dettagliata, è in `HANDOFF.md`.

## Fase 1 — Bug (2026-10-26)

### Visibili agli utenti
- **Header fisso**: la barra in alto resta visibile scorrendo il timone; l'auto-scroll durante il trascinamento parte già sotto l'header.
- **Numero di pagine vuoto** nella creazione di un numero: niente più errore della pagina, errore inline sotto il campo; anteprima del peso ferma finché il valore non è valido. Stessa protezione su tutte le select/campi numerici con `wire:model` tipizzati (`?int`).
- **Contenuto creato visibile subito** in "Contenuti da assegnare" e in "📌 Pubblicità prenotate", senza ricaricare. Archivia/ripristina/elimina un contenuto non assegnato ed elimina prenotazione si propagano agli altri utenti (Reverb o polling).
- **Gruppi e selezioni multiple che si muovono insieme** durante il drag (bordo tratteggiato, immagine di trascinamento con tutte le pagine) e transizione animata finale; **frecce da tastiera** animate, che spostano l'intero gruppo collegato di un posto.
- **Errore Alpine `$store.pagePresence` undefined** eliminato (causa: ordine di caricamento dei moduli JS).
- **Testo bianco negli input** delle pagine/modali: colori espliciti e leggibili in tema chiaro e scuro.
- **Pagine più grandi** con zoom **S/M/L** ricordato per browser e proporzioni del formato reale del numero; più spazio tra le pagine.
- **Upload**: il riquadro mostra il limite di dimensione reale del server e rifiuta subito i file troppo grandi; un rifiuto del server mostra un messaggio chiaro invece di "caricamento non riuscito".

### Tecniche
- `App\Livewire\Concerns\NormalizesEmptyNumericInput` (trait) — vedi `docs/10-debugging.md`.
- Evento Livewire locale `content-created` / `content-list-changed`; nuovo evento broadcast `App\Events\ContentListChanged`.
- `Grid::nudgePage()` + `App\Support\PageReorderer::nudgeTarget()`; posizione di drop calcolata dall'ordine reale delle pagine (corregge di passaggio l'indice che in vista doppia contava anche gli spacer).
- `resources/js/timone-alpine.js` (store, `x-sortable` con MultiDrag, magic `$animatedMove`/`$flipAfterDrop`, store `timoneZoom`) importato per primo da `app.js`; `echo.js` non lancia più senza chiave Reverb.
- `App\Support\Uploads\RequestRootSignedUploadUrl` (URL firmato dell'upload sull'indirizzo reale), `UploadLimits`, `UploadEnvironmentInspector`, comando `php artisan timone:diagnose-upload`, log `upload_failed`.
- Nessuna migration, nessuna dipendenza nuova (MultiDrag è nel pacchetto `sortablejs` già presente).

### Bug 8 — PDF multipagina su una pubblicità (implementato dopo l'ok sul flusso)
- Visibile: un PDF più lungo del formato non è più rifiutato; il modulo chiede «usa tutte le N pagine» oppure «usa solo le pagine da X a Y» (dicitura dedicata per il formato copertina) e riassume cosa succederà. Ogni pagina del timone mostra la pagina del PDF corrispondente.
- Tecnico: `App\Support\AdPdfPagePlan` (puro); `ContentCreator::pagePlan()` (chiavi `pdf_page_from`/`pdf_page_to`); `ContentFile.pdf_page_number` = numero originale nel PDF; `ContentPlacer`/`ContentCreate::attachConfirmedPages()` associano i file per posizione nell'ordine del PDF; percentuale 100 quando la scelta supera le pagine del formato. Nessuna migration.
