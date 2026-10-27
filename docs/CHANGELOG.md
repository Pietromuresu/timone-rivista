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

## Fase 3 — Occupazione a frazioni di pagina (2026-10-27)

### Visibili agli utenti
- **Occupazione con frazioni**: nel modulo di creazione la select unica è sostituita da **"Pagine intere"** + **"Frazione"** (nessuna, 1/4, 1/3, 1/2, 2/3, 3/4), con il totale sotto («Occupa 2 e 1/2 pagine»). Almeno uno dei due > 0. Vale per articoli e pubblicità.
- **Pubblicità**: il formato **propone** l'occupazione (compila i due campi), modificabile; accanto a ogni formato è indicato lo spazio («(1/2 di pagina)»). Rimosso il campo "Percentuale manuale" (sostituito dai due campi).
- **Distribuzione** sulle pagine: pagine intere in sequenza e la frazione sull'ultima (2 e 1/2 → intera, intera, 1/2).
- **Spazio per pagina** sulla card: menu da 1/12 a «intera» al posto del numero percentuale libero.
- **Capienza**: una pagina contiene al massimo una pagina intera di contenuti; oltre, errore bloccante che dice quanto spazio resta («resta libero solo 1/3 di pagina»).
- **Riempimento visibile**: barra sul bordo sinistro della card ed etichetta «○ Vuota» / «◐ Libero: 1/3» / «● Piena» (in Lista la sola etichetta); pagina piena scura con ●, distinguibile anche senza colori. «⚠ Oltre capienza» in rosso per eventuali anomalie dei dati convertiti.
- **Carico pubblicitario esatto** (in dodicesimi): tre terzi di pagina su 3 pagine = 33,33%.
- Export PDF del timone: lo spazio di ogni contenuto è in frazioni («1/2 di pagina»), non più in percentuale.

### Tecniche
- `App\Support\Twelfths` (unità e formattazione), `App\Support\PageCapacity` (sostituisce `PagePercentageAllocator`, rimosso), `App\Enums\PageFillStatus`; `App\Support\PageOccupancy` riscritto in dodicesimi (`fromParts()`, `distribution()`, `perExtraPage()`); `AdFormat::twelfths()` sostituisce `defaultPercentage()`; `Advertisement::occupiedPercentage()` e `Content::occupiedPercentagePerPage()` rimossi.
- `Grid::updateContentOccupancy()` sostituisce `updateContentPercentage()`; evento `ContentAssigned` con `twelfths` (int) al posto di `percentage`.
- `ContentCreate`: proprietà `whole_pages` (`?int`) + `fraction_twelfths`, `updatedFormat()`; `ContentCreator::occupancyFromFields()`; errore sotto la chiave `occupancy`.
- Partial `livewire/timone/partials/page-content-entry.blade.php` (riga contenuto, prima triplicata tra card e lista) e `page-fill.blade.php`.
- **Migration** `2026_10_27_100000_convert_occupancy_to_twelfths` (reversibile): `contents.occupancy_twelfths`, `page_content.occupied_twelfths` (default 12); colonne vecchie rese nullable e non più usate. Conversione: `App\Support\Occupancy\OccupancyConversionPlanner` + `OccupancyConversion`; dry-run `php artisan timone:occupancy-dry-run`. Verificata su copia MySQL dei dati di sviluppo.
- `timone:missing-occupancy` dice che non serve più su un database convertito.
- Nessuna dipendenza nuova.

## Fase 4 — Gestione contenuti (2026-10-27)

### Visibili agli utenti
- **Modifica di un contenuto**: ✏️ sulla riga del contenuto in pagina o sul chip in "Contenuti da assegnare" apre la finestra "Modifica contenuto" con tutti i campi della creazione (tipo, titolo, rubrica, campi articolo/pubblicità, occupazione, materiale di stampa). Le modifiche valgono su tutte le pagine del contenuto; cambiare l'occupazione lo ridistribuisce dalla prima pagina (tutto o niente); cambiare il PDF sostituisce quello vecchio e ogni pagina riceve la sua pagina del PDF.
- **Nessuna miniatura separata**: il PDF/immagine di stampa resta l'anteprima del contenuto (la miniatura richiesta dalla specifica è stata implementata e poi tolta su indicazione dell'utente: il PDF principale è già la miniatura).
- **Rinomina inline**: doppio click sul nome del contenuto in pagina, Invio salva, Esc annulla. Aggiorna tutte le pagine e gli altri utenti collegati. Per una pubblicità si cambia il cliente (il nome mostrato).

### Tecniche
- `App\Livewire\Timone\ContentEdit` (modale), `App\Support\ContentUpdater`, `App\Support\ContentFormRules` (regole condivise con `ContentCreate`), trait `App\Livewire\Concerns\HasOccupancyFields`; `ContentCreator::assertPdfFits()` e `storeFiles()` pubblici.
- `Grid::renameContent()`; `ContentListChanged` con azioni `updated`/`renamed`.
- `Content::previewImageUrl()`, `Page::previewImageUrl()` (URL dell'anteprima pronta, prima costruito nei Blade). Nessuna migration.
- Partial `content-edit.blade.php`; riga del contenuto con rinomina e ✏️.
- Nessuna dipendenza nuova.

## Fase 5 — Pubblicità previste alla creazione del numero (2026-10-27)

### Visibili agli utenti
- **"Nuovo numero"**: sezione «📢 Pubblicità previste» con quante pubblicità a pagina intera, 2/3, 1/2, piedino (1/3) e vetrina (1/4). Mentre scrivi mostra le pagine pubblicitarie equivalenti, la percentuale sulle pagine interne, le posizioni e un errore (che blocca la creazione) se non ci stanno.
- Alla creazione diventano **pubblicità segnaposto** già nel timone: le prime 4 (di qualsiasi formato, prima le pagine intere) riempiono tutte le copertine II, III, IV, I (avviso sulla I), le altre nelle pagine interne una sì e una no da pagina 2, frazioni a seguire una per pagina, poi le pagine libere dal fondo. Il posizionamento non si ripete mai dopo.
- Segnaposto riconoscibili: contorno tratteggiato ed etichetta **«Da assegnare»**; titolo automatico «Pubblicità 1/2 — da assegnare». Contano nella percentuale pubblicitaria e nel nuovo contatore «Pubblicità da assegnare (segnaposto)» del cruscotto, per formato.
- Un segnaposto diventa una pubblicità vera quando riceve il cliente (modale o doppio click sul nome), un titolo proprio o il PDF.

### Tecniche
- `App\Enums\PlannedAdKind`; `App\Support\AdPlacement\AdPlacementService` (puro) + `AdPlacementPlan`; `PlaceholderAdCreator`; `PlaceholderAdCounter` (sostituito nella Fase 6 da `IssueSummary`). Config `timone.ad_placement` (`cover_order`, `start_page`/`TIMONE_AD_PLACEMENT_START_PAGE`, `step`/`TIMONE_AD_PLACEMENT_STEP`).
- Migration `2026_10_27_300000_add_placeholder_ads`: `contents.is_placeholder`, `advertisements.client` nullable (reversibile; verificata su copia MySQL dei dati di sviluppo).
- `ContentFormRules::rules(..., placeholder:)`; `ContentUpdater` e `Grid::renameContent()` tolgono il flag.
- Nessuna dipendenza nuova.

## Fase 6 — Riepilogo stile dashboard (2026-10-27)

### Visibili agli utenti
- **Barra di riepilogo** sempre visibile sotto l'header del timone: pagine interne piene / parziali / bianche, spazio libero totale («2 e 5/6 pagine»), percentuale di pubblicità sulle pagine interne, pubblicità da assegnare (segnaposto) per formato, copertine assegnate/libere, peso copia e scaglione.
- **Clic su «bianche» o «Da assegnare»**: evidenzia quelle pagine (contorno azzurro, le altre attenuate) in griglia, doppia pagina e lista; secondo clic per togliere.
- **Niente più soglia di allarme** né colori di avviso sul carico pubblicitario: tolti dal cruscotto, dagli export CSV/PDF e dai moduli della rivista. Resta solo la percentuale.
- **Bug corretto**: cercare un titolo nei "Contenuti da assegnare" cambiava la percentuale pubblicitaria.

### Tecniche
- `App\Support\IssueSummary` (sostituisce `PlaceholderAdCounter`), partial `summary-bar.blade.php`, `Grid::$summaryHighlight` + `toggleSummaryHighlight()`, `--app-header-height` nel layout.
- Rimossi `Grid::updateAdThreshold()` e la soglia da `Magazines\Create/Edit`, `Magazine` (fillable/cast), factory ed export; colonna `magazines.ad_threshold_percentage` lasciata, non usata. Nessuna migration.

