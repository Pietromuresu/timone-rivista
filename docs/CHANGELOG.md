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

## Fase 7a — Disposizione dei contenuti nella pagina (2026-10-27)

### Visibili agli utenti
- Nella card (griglia e doppia pagina) ogni contenuto è un **blocco grande quanto il suo spazio reale**: pubblicità verticali (mezza verticale, 1/3 verticale, 2/3) come colonne a tutta altezza, gli altri come righe impilate, il piedino in fondo, lo spazio libero come riquadro tratteggiato «libero».
- Ogni blocco mostra l'**anteprima del proprio PDF** (prima un'unica immagine copriva tutta la card); senza PDF un fondo azzurro (articolo) o ambra (pubblicità). I segnaposto restano tratteggiati.
- I comandi del contenuto (spazio, ✏️, ↗, ✕) compaiono passando sul blocco o raggiungendolo con Tab. Vista Lista invariata.
- Tolta la barra laterale di riempimento della card (lo spazio libero si vede nei blocchi); resta l'etichetta «Libero: …/Piena».

### Tecniche
- `App\Support\PageLayoutPlanner` (puro), `App\Enums\ContentOrientation`, `AdFormat::orientation()`; `Page::layout()`, `Page::previewFileFor()` (estratto da `previewFile()`); `Page::contents()` ordinata per `page_content.id`; riga del contenuto con modalità `compact`; rimosso `PageFillStatus::barClasses()`. Nessuna migration.

## Fase 7b — Ordine dei contenuti nella pagina (2026-10-27)

### Visibili agli utenti
- Frecce **↑ ↓** tra i comandi di ogni contenuto (sulla card compaiono passando sul blocco) quando la pagina ha più contenuti: spostano il contenuto prima o dopo nell'ordine della pagina, e la disposizione si ridisegna subito (colonne verticali sinistra → destra, righe alto → basso, piedino sempre in fondo). Salvato e visibile anche ai colleghi; non disponibile su una pagina bloccata.

### Tecniche
- Migration `2026_10_27_400000_add_position_to_page_content_table` (`page_content.position`, nullable; righe esistenti numerate nell'ordine attuale; reversibile, verificata su copia MySQL).
- `App\Support\PageContentOrder` (puro), `Grid::moveContentInPage()`, evento `App\Events\PageContentsReordered` + listener; `Page::contents()` ordinata per posizione; `ContentPageAttacher` mette i nuovi contenuti in fondo.
- Test: `$content->pages()` con `pages.position` (la colonna `position` ora esiste anche sul pivot).

## Correzione — miniature nell'export PDF del timone (2026-10-27)

- **Visibile**: con l'opzione «miniature» il PDF esportato mostra di nuovo le anteprime, per pagine interne e copertine (la stessa anteprima della card del timone). Prima non compariva nessuna miniatura per il materiale caricato sui contenuti, cioè da settembre in poi.
- **Causa**: `exports/timone.blade.php` leggeva solo `$page->files` (PDF caricati direttamente sulla pagina, percorso tolto col Punto 4 del 2026-09-02); il test esistente verificava solo l'assenza di errori.
- **Tecnico**: `App\Support\ExportThumbnail::dataUri()` (stato pronto, file presente, formato supportato da dompdf: PNG/JPEG/GIF, WEBP solo se la GD lo legge), `Page::previewFile()` come fonte, `contents.files` caricato nel controller. Test che controllano l'immagine dentro il PDF (`/Subtype /Image`).
- **Seconda segnalazione** (stesso giorno, «c'è solo una miniatura, non tutte quelle della pagina»): ogni pagina dell'export mostra ora la **disposizione** dei suoi contenuti (stessi blocchi della card, Fase 7a), ciascuno con la propria miniatura. Tecnico: `App\Support\ExportPageLayout` (rettangoli in punti in un box a misura fissa, proporzioni del formato del numero), verificata rasterizzando un PDF reale con `pdftoppm`.


## Restyling dell'export PDF del timone (2026-10-27)

- Pagine tutte uguali con le proporzioni del formato, affiancate in doppie pagine come la rivista; contenuti disegnati come blocchi con miniatura e nome; stato come pallino colorato; intestazione con i numeri del riepilogo (pagine, spazio libero, % pubblicità, da assegnare, copertine, peso); legenda compatta; «Foglio N di M». Tecnico: `exports/partials/timone-sheet.blade.php`, `PageSpreadBuilder::slots()`, `ExportPageLayout` con etichette.

## Revisione 2, Fase 1 — Bug (2026-10-27)

### Visibili agli utenti
- **Nuovo numero**: scrivendo nel campo pagine (e nelle pubblicità previste, copie, bancali, grammature) le cifre non si perdono più e il cursore non salta; l'anteprima si aggiorna 0,6 s dopo l'ultima cifra; gli avvisi («non è un multiplo di 4», «non ci stanno», grammatura fuori scala) compaiono quando si esce dal campo, non mentre si scrive.
- **Nuovo numero con tante pubblicità previste**: non dà più «Text too long» (es. 60 mezze pagine su 120 pagine). Le note del numero hanno un messaggio chiaro se superano 2000 caratteri.
- **Comandi dei contenuti nella pagina**: ogni blocco ha un bottone «⋯» nell'angolo che apre i comandi (spazio, sposta prima/dopo, modifica, estendi, rimuovi) sopra tutto, anche per i blocchi da 1/4 allo zoom più piccolo. Prima con due contenuti frazionari sulla stessa pagina i comandi erano tagliati e non cliccabili.
- **Trascinamento**: avvicinando il puntatore al bordo inferiore della finestra, o al bordo inferiore della barra di riepilogo in alto, la pagina scorre da sola, più veloce vicino al bordo. Vale per contenuti, pagine, gruppi di pagine e copertine.
- **File fino a 100 MB** (prima 20 MB nel modulo contenuti). Barra di avanzamento anche nella modale di modifica; messaggio con il limite se il file è troppo grande.

### Tecniche
- `Issues\Create::$total_pages` da `?int` a `string` + `totalPages()`; componente `<x-live-field>`; `wire:model.live.debounce.600ms` (anche in `x-weight-field`).
- `activity_logs.description` → TEXT (migration `2026_10_27_500000`, reversibile); descrizione breve in `PlaceholderAdCreator`, posizioni in `new_values.placed`.
- `partials/content-controls.blade.php` (comandi condivisi tra vista lista e menu), menu `@teleport('body')` + `x-anchor` in `page-card`.
- `resources/js/drag-autoscroll.js`; Sortable con `scroll: false`; `data-sticky-top` su header e barra di riepilogo.
- `config/timone.php` → `upload_max_kb` (unico limite, anche per `config/livewire.php`); `ContentFormRules::maxKilobytes()/fileRule()/temporaryUploadMessage()`; `max_upload_time` 30; `Alpine.data('fileUpload')`; `PdfPageMeasurer::pageCount()` con Ghostscript; `timone:diagnose-upload` con soglie (PHP ≥ limite + 10%, tempi, spazio disco); `docker/php/uploads.ini` 110M/120M, `max_input_time` 300, `max_execution_time` 120.
- Nessuna dipendenza nuova; nessuna modifica a `AppServiceProvider` e agli altri file protetti.

## Revisione 2, Fase 2 — Interazione con pagine e contenuti (2026-10-27)

### Visibili agli utenti
- **Click su un contenuto = click sulla pagina** (seleziona la pagina, non apre più il file). Il file si apre dall'**occhio 👁** nel menu «⋯» del contenuto, in una nuova scheda; senza file l'occhio è grigio con «Nessun file caricato».
- **Comandi del timone sempre visibili**: riepilogo e comandi (zoom, viste, pagine interne, esporta…) in un'unica fascia ferma sotto la barra in alto (non sul telefono).
- **Griglia 6×6**: ogni contenuto ha un rettangolo esatto (mezza verticale 3×6, piedino 6×2, vetrina 3×3…); nel menu «⋯» frecce ← ↑ ↓ → (con scambio tra contenuti che formano un rettangolo) e «⟳ Ruota» (mezza pagina e 1/3 cambiano anche formato). 3/4 di pagina come forma a L. Le posizioni attuali sono state convertite senza cambiamenti visibili, tranne le vetrine 1/4 che diventano quadrate.
- **Anteprima ritagliata in base alla posizione**: un PDF a pagina intera mostra solo la parte del riquadro; un file già nella misura dell'annuncio si vede intero. Scelta manuale nella modale («Anteprima nel riquadro»).
- **«Posizione nel timone»** nel modulo di creazione e nella modale di modifica: il contenuto va subito da quella pagina (o copertina) in poi; se non c'è posto, errore con chi occupa la pagina e quanto manca, e nulla viene salvato.

### Tecniche
- `app/Support/PageGrid/` (`GridRect`, `GridShapes`, `PageGridPlanner`, `PageGridPositions`, `GridConversion`, `ThumbnailFit`); migration `2026_10_27_600000_add_grid_cells_to_page_content_table` (con conversione) e `2026_10_27_610000_add_file_fit_columns`; comandi `timone:grid-dry-run`, `timone:file-ratios`.
- `Grid::moveContentOnGrid()/rotateContentOnGrid()` al posto di `moveContentInPage()`; rimossi `App\Support\PageContentOrder` e i suoi test; `AdFormat::rotated()`; `ContentPlacer::check()` e messaggio con gli occupanti; `ContentUpdater::update(..., moveTo)`; `App\Support\PlacementChoice`; `Issue::pageAspectRatio()`; `ContentFileAspectRatio` nel job delle miniature; `ContentFileController::show` con `inline` esplicito.
- `phpunit.xml`: `memory_limit` 512M per i test.

## Revisione 2, Fase 3 — Pubblicità segnaposto dal modulo e caricamento multiplo (2026-10-28)

### Visibili agli utenti
- **Pubblicità senza cliente dal modulo «+ Nuovo contenuto»**: cliente e titolo facoltativi. Senza cliente e senza file è un segnaposto «Da assegnare» come quelli previsti alla creazione del numero (contato nel riepilogo); titolo vuoto → «Pubblicità 1/2 — da assegnare».
- **Regola del segnaposto cambiata**: diventa una pubblicità vera solo con un cliente o un file (prima bastava anche un titolo proprio); togliendo il cliente a una pubblicità senza file torna segnaposto.
- **Caricamento multiplo** («⇪ Carica più file»): trascina o scegli tanti PDF/immagini, tipo del lotto Articoli/Pubblicità; un contenuto da assegnare per file (titolo dal nome del file, pagine = pagine del PDF), caricati uno alla volta con stato e percentuale per file, riepilogo finale, «Riprova i falliti», duplicati creati come «… (2)» con avviso.
- Niente più toast «aggiornato da un altro utente» per le proprie modifiche quando il timone si aggiorna col polling.

### Tecniche
- `App\Support\AdPlaceholder` (regola e titolo automatico, unico punto); `PlannedAdKind::placeholderTitle()` delega; `ContentFormRules::rules()` senza il parametro `$placeholder`.
- `App\Livewire\Timone\BulkUpload`, `App\Support\BulkContentImporter`, `App\Support\BulkImportResult`, `Alpine.data('bulkUpload')`.
- Comando `php artisan timone:placeholder-sync [--issue=] [--dry-run]` per i dati esistenti.
- `Grid::onlyOwnChangesSince()` nel polling.
- Nessuna migration, nessuna dipendenza nuova, nessuna variabile d'ambiente nuova.

## Revisione 2, Fase 4 — Ruoli ed eliminazioni (2026-10-28)

### Visibili agli utenti
- **Commerciale**: crea, modifica e sposta le pubblicità (trascinamento, frecce, rotazione, pagina dalla modale, caricamento multiplo come Pubblicità) e le pagine senza articoli. Sugli articoli: niente maniglia, cursore 🚫, comandi disattivati con «Solo la redazione può spostare questo contenuto». Stati, blocchi, gruppi, pagine totali e scadenza restano della redazione.
- **Eliminazione di numeri e riviste** (solo admin): «🗑️ Elimina» accanto al numero e in «Modifica rivista»; riepilogo (pagine, contenuti, file, MB, elenco dei numeri) poi password + nome digitato; definitiva. Chi ha il numero aperto torna all'elenco con un avviso.
- **Eliminazione di utenti** (solo admin, da «Modifica utente»): stessa conferma con l'email; file, spostamenti e cronologia restano con «Utente eliminato»; sessioni chiuse. Mai sé stessi né l'ultimo admin. Tolta l'auto-eliminazione dal profilo.

### Tecniche
- `UserRole::editsTimone()/handlesAds()`, `App\Support\Permissions\TimoneAbilities`, `App\Policies\ContentPolicy`, `IssuePolicy::workOnAds/movePages`, `UserPolicy::delete`; `Grid::authorizeContent()/authorizePageMove()`.
- `App\Support\Deletion\` (`DeletionTarget`, `IssueDeletion`, `MagazineDeletion`, `UserDeletion`, `DeletionConfirmation`, `DeletionSummary`, `StoredFiles`, `DeletionTargets`), `App\Livewire\Admin\ConfirmDeletion`, job `App\Jobs\DeleteStoredFiles` (afterCommit), evento `App\Events\IssueDeleted`, `resources/js/deleted-issue-redirect.js`.
- Migration `2026_10_28_100000_keep_user_work_on_user_delete` (tre FK utente → nullOnDelete, solo vincoli; provata up/down/up su copia MySQL).
- Rimossi `ProfileController::destroy`, la rotta `profile.destroy` e `profile/partials/delete-user-form.blade.php`.
- Nessuna dipendenza nuova, nessuna variabile d'ambiente nuova.

## Revisione 2, Fase 5 — PDF sfogliabile della rivista (2026-10-28)

### Visibili agli utenti
- **«📖 PDF sfogliabile della rivista»** sopra il timone: un PDF con tutte le pagine nell'ordine di stampa (I, II, 1…N, III, IV), A4 verticale con la pagina in scala, i PDF/immagini dei contenuti nel loro riquadro (stesso ritaglio delle anteprime), «File mancante» dove manca il materiale, «Pagina bianca», numero di pagina sotto. Si apre a coppie come una rivista, con segnalibri.
- Generato in coda con stato visibile (In coda / In generazione / Pronto / Errore) e «⬇ Scarica»; opzione «Bassa risoluzione per anteprima» (file leggero, separato).

### Tecniche
- Dipendenze nuove (approvate): `setasign/fpdi` 2.6.9, `tecnickcom/tcpdf` 6.11.4. Ghostscript già presente.
- `App\Support\MagazineExport\` (`MagazinePdfComposer`, `MagazinePageSequence`, `PageBox`, `SourceFiles`, `Ghostscript`, `MagazineExportRequester`, `ComposeResult`), job `App\Jobs\GenerateMagazinePdf`, model `MagazineExport`, enum `MagazineExportStatus`/`MagazineExportVariant`, `MagazineExportController`, `App\Livewire\Timone\MagazineExportPanel`.
- Migration `2026_10_28_200000_create_magazine_exports_table`; config `timone.magazine_export` (`TIMONE_EXPORT_MEMORY_LIMIT`, `TIMONE_EXPORT_TIMEOUT`, facoltative, non in `.env.example`).
- `StoredFiles::forIssues()` include i PDF generati (eliminati con il numero).

## Correzione 2026-10-28 — dropdown della navigazione

- **Visibile**: il menu utente in alto a destra (Profilo, Esci) si apre anche nella pagina del profilo e nel manuale.
- **Tecnica**: `@livewireScripts` in `resources/views/layouts/app.blade.php` (Alpine arriva con lo script di Livewire, che prima era inserito solo nelle pagine con componenti Livewire); test `tests/Feature/NavigationScriptsTest.php`. Nessun file protetto toccato.

## Correzione 2026-10-28 (2) — dropdown in produzione e AppServiceProvider

- **Visibile**: il menu utente in alto a destra si apre anche in produzione.
- **Causa**: nel browser di produzione l'attributo `@click="open = ! open"` arrivava riscritto come `open=""` (il server lo mandava corretto). Le viste usano ora `x-on:click` (forma già usata ovunque nel progetto); test in `tests/Feature/NavigationScriptsTest.php`.
- **`app/Providers/AppServiceProvider.php`**: riportata nel repository la versione di produzione (richiesta esplicita dell'utente): binding `RequestRootSignedUploadUrl` commentato, macro per la verifica delle URL firmate su `APP_URL`. Test in `UploadDiagnosticsTest` aggiornato di conseguenza.

