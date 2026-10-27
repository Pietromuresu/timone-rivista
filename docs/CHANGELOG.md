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

## Fase 2 — Copertine separate dalle pagine (2026-10-26)

### Visibili agli utenti
- Le copertine **I, II, III, IV** sono elementi separati del numero, sempre tutte e quattro (prima nascevano solo I e IV), tutte insieme in una fascia azzurra in cima al timone, prima della pagina 1 (I, bandella, II, III, IV), con l'etichetta «📘 I di copertina». Una prima versione aveva III e IV in una seconda fascia dopo l'ultima pagina: spostate in cima su richiesta dell'utente.
- Le **pagine interne partono da 1** e il numero di pagine del numero è solo quello delle interne (anche nel modulo di creazione: «Numero di pagine interne (copertine escluse)»). Intestazione del timone: «N pagine interne · copertine 4 (X assegnate)».
- Sulle copertine si assegnano contenuti e pubblicità come sulle pagine; una copertina si trascina solo su un'altra copertina (scambio), mai tra le pagine interne, e viceversa.
- Pubblicità su più pagine partendo da una copertina: dalla II continua sulle pagine 1, 2…; dalla III occupa le ultime pagine interne e la III; I e IV una pagina sola.
- **Bandella** aggiungibile/rimovibile dalla fascia copertine: 6 pagine di copertina nel peso invece di 4. Nel pannello Peso le pagine di copertina di un numero non si scrivono più a mano.
- Cruscotto pubblicitario, avvisi ed export contano solo le pagine interne; l'export PDF ha una sezione «Copertine».
- Tolta l'opzione «il battente conta nel totale pagine» dalle impostazioni della rivista.

### Tecniche
- Colonna `pages.cover_slot` (`App\Enums\CoverSlot`), `pages.position` nullable, `unique(issue_id, cover_slot)`; `Issue::pages()` = sole interne, `covers()`, `allPages()`, `coverPageCount()`.
- `App\Support\IssueCovers` (creazione, bandella, scambio, `pagine_copertina`); `IssueObserver` crea sempre I–IV; evento `App\Events\CoversChanged`; `Grid::swapCovers()/addBandella()/removeBandella()`; `ContentPlacer::coverTargets()`.
- Rimossi `PageRole`, `PageRoleAssigner`, `PageNumberer`, `Timone\CoverSection` (e i loro test); tolto l'avviso del peso "ruoli copertina ≠ pagine copertina".
- **Migration di dati** `2026_10_26_200002_convert_cover_pages_to_slots` con registro `cover_conversion_logs` (reversibile, anche per i numeri creati dopo), anteprima `php artisan timone:covers-dry-run`. Verificata su una copia MySQL dei dati di sviluppo: andata e ritorno identici.
