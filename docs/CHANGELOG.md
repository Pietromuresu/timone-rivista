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

## 2026-10-28 — Azioni sulle pagine selezionate nella fascia fissa

### Visibili agli utenti
- Le azioni sulla selezione (Deseleziona, Tutte, Stato, Collega, Scollega, Blocca, Sblocca) sono una riga della fascia fissa in alto: utilizzabili anche con pagine selezionate in fondo al timone. «3 pagine selezionate: 12–14»; azioni non disponibili attenuate con il motivo nel tooltip; «Altro ⋯» su schermi stretti; il Commerciale vede solo seleziona/deseleziona.
- Bottone ⌃ per ridurre il riepilogo a una riga (ricordato nel browser). Titolo «Timone · N pagine interne» tolto (ripeteva il riepilogo): i comandi stanno su una riga a 1366 px.
- Esiti ed errori di blocco/collegamento compaiono nella fascia. Una pagina eliminata da un collega sparisce dalla selezione al primo aggiornamento.

### Tecniche
- Un solo contenitore sticky (`layouts/app.blade.php`, `#timone-sticky-slot`, `--app-sticky-height`); la fascia arriva con `@teleport`. Via `--app-header-height` e `--timone-sticky-height`.
- `App\Support\PageSelectionActions` (etichetta e motivi), `partials/selection-bar.blade.php`, pulizia della selezione in `Grid::render()`. Nessun metodo delle azioni modificato.
- Altezza misurata (Chrome): 138 px senza selezione, 169 px con la riga di selezione a 1366×768 (25% dell'area visibile, 22% della finestra), 134 px compressa.

## 2026-10-28 — Pannello contenuti sempre raggiungibile (fase 1 di «contenuti + annulla»)

### Visibili agli utenti
- Bottone **«📚 Contenuti · N da assegnare»** nella fascia fissa: pannello a colonna destra da 1440 px (larghezza regolabile), a tendina sotto la fascia sugli schermi più piccoli; aperto/larghezza ricordati.
- Schede Da assegnare / Tutti / Segnaposto, filtro tipo, ricerca, ordine ultimi creati o A–Z; «Nuovo contenuto» e «Caricamento multiplo» dentro il pannello.
- Assegnazione senza risalire: drag (con auto-scroll), «Assegna alla selezione», «Vai a pag.» (numero o I–IV). Un contenuto assegnato da un collega sparisce dal pannello e un rilascio «vecchio» viene rifiutato con un messaggio.
- Nel riepilogo «Da assegnare» diventa **«Segnaposto»**. Etichette brevi nella barra comandi («Storico», «Scambio», «Selezione»; nome completo nel tooltip).

### Tecniche
- `App\Support\ContentPanelFilter`, `partials/content-panel-item.blade.php` (sostituisce `unassigned-content-chip`), store Alpine `contentPanel`, `Grid::assignContentToSelection()`, `assignContentToTarget()`, `placeUnassignedContent()` (con il controllo «già assegnato»), `Grid::$contentTab/$contentType/$contentSort`. Errori di assegnazione/estensione/rinomina nella fascia.
- Nessuna migration, nessuna dipendenza.

## 2026-10-28 — Annulla / ripeti (fase 2 di «contenuti + annulla»)

### Visibili agli utenti
- **Ctrl/⌘+Z** annulla, **Ctrl/⌘+Shift+Z** e **Ctrl+Y** ripetono; bottoni ↶ ↷ nella fascia con l'azione nel tooltip; toast di 4 secondi «Annullato: …» con «Ripeti»; menu 🕘 «Cronologia».
- Solo le proprie azioni, per numero, fino a 50 passi in 24 ore. Annullabili: spostamenti di pagine/gruppi/copertine, assegnazioni e rimozioni, frecce e rotazione, spazio, collega/scollega, stato e blocco, rinomina, modifica, creazione (caricamento multiplo = un passo), archivia/ripristina, eliminazione (ora recuperabile 24 ore).
- Annulla rifiutato con il motivo se un collega ha modificato le stesse righe o se lo spazio è stato occupato; il passo resta come «non annullabile». La modifica con PDF sostituito non si annulla.

### Tecniche
- `app/Support/Timeline/` (`TimelineActionService`, `TimelineSnapshot`, `TimelineDiff`, `TimelineApplier`, `TimelineAuthorizer`, `TimelineLabeler`, `TimelineConflict`), model `TimelineAction`, evento `TimelineApplied`, comando `timone:history-prune` (ogni ora).
- Migration `2026_10_28_300000_add_soft_deletes_to_contents`, `2026_10_28_310000_create_timone_azioni_table`; `Content` con `SoftDeletes`.
- Azioni avvolte senza cambiarne la logica: `Grid` (25 metodi via `recordStep()`), `ContentCreate::save()/confirmMultipageUpload()`, `ContentEdit::save()`, `BulkUpload::importUploaded()` (parametro lotto), `AdReservations::deleteReservation()`.

## 2026-10-29 — Annulla: mai il lavoro dei colleghi

### Visibili agli utenti
- Annullare una creazione, una modifica o un caricamento multiplo non disfa più quello che un collega ha fatto sullo stesso numero mentre il file si caricava (prima poteva succedere, senza avviso).

### Tecniche
- `TimelineDiff::onlyContents()` e parametro `$scope` di `TimelineActionService::run()`, usato da `ContentCreate`, `ContentEdit` e `BulkUpload` (le azioni senza transazione). `ContentEdit` legge l'id prima dell'azione (`close()` lo azzera).
- Tre test nuovi in `TimelineUndoRedoTest` (creazione, modifica + caricamento multiplo, nessun passo di un altro utente in annulla/ripeti/cronologia); i primi due falliscono senza la correzione.

## 2026-10-29 — Commenti sulle pagine con notifica (fase 1 di «commenti, 32esimi, doppia pagina»)

### Visibili agli utenti
- 💬 su ogni pagina (griglia, doppia pagina, lista, copertine): numero di commenti, giallo se ce ne sono di non letti; pannello a destra con elenco, nuovo commento, modifica/eliminazione dei propri (un Admin elimina anche quelli altrui).
- Mail ai colleghi scelti, a «tutti gli utenti di questa rivista» o menzionati con @Nome Cognome; mai all'autore, mai due volte; link diretto alla pagina.
- Nella testata del numero «💬 N commenti non letti» con l'elenco (e la scheda «Tutti»); i commenti seguono la pagina quando si sposta e restano come «Pagina 47, eliminata» se la pagina viene eliminata.
- Sola lettura legge i commenti, non li scrive.
- Esc con una finestra aperta chiude solo la finestra, senza togliere la selezione delle pagine.
- Mail dell'app con i testi fissi in italiano.

### Tecniche
- Migration `2026_10_29_100000_create_page_comments_tables` (`page_comments`, `page_comment_reads`, `issues.comments_updated_at`).
- `App\Support\Comments\{CommentRecipients, PageCommentWriter, PageCommentReader, DeletedPageComments}`, `App\Models\PageComment`, `App\Notifications\PageCommentPosted`, `App\Policies\PageCommentPolicy`, `TimoneAbilities::canComment()`, componente `App\Livewire\Timone\PageComments`.
- `Grid`: `checkForCommentUpdates()` (polling sempre attivo con Reverb), `checkForUpdates()` controlla anche i commenti, `refreshCommentCounts()`; contatori al browser con l'evento `comment-counts` (store Alpine `pageComments`) senza render.
- `lang/it.json` (testi del modello mail di Laravel).

## 2026-10-29 — Segnature / 32esimi (fase 2 di «commenti, 32esimi, doppia pagina»)

### Visibili agli utenti
- Barra in alto: «Segnature: 3 trentaduesimi + 24 pagine (16 + 8)», avviso se il totale non è multiplo di 4, suggerimento «+8 = 128 · −24 = 96»; si aggiorna anche per i colleghi quando cambiano le pagine totali.
- Griglia, doppia pagina e lista: fascia colorata alternata per segnatura e confine rosso con «32° n. 2» (in doppia pagina tra le due pagine della coppia [32 | 33]); bottone «32°» nella barra per nasconderli (preferenza personale).
- «Modifica rivista» → «📐 Segnature di stampa»: pagine per segnatura (8/16/32/64) e copertina inclusa (di default no).

### Tecniche
- `App\Support\Signatures\SignaturePlan` (pura, `tests/Unit/SignaturePlanTest.php`), migration `2026_10_29_200000_add_signature_settings_to_magazines`, store Alpine `timoneView`, stili in `app.css`.

## 2026-10-29 — Griglia a doppia pagina (fase 3 di «commenti, 32esimi, doppia pagina»)

### Visibili agli utenti
- «Doppia pagina» diventa una griglia: più coppie per riga (secondo schermo e zoom), pari a sinistra e dispari a destra, coppie attaccate, riquadri «II di copertina» e «III di copertina» dove mancano le copertine; nessuno scorrimento orizzontale.
- Trascinamento (anche di più pagine) e frecce come in Griglia; durante il drag le coppie si ricompongono e si vede dove cade la pagina.
- Il conteggio «Segnature» va a capo sugli schermi stretti (prima dava scorrimento orizzontale sotto i 780 px).

### Tecniche
- `PageSpreadBuilder::side()` e `::layout()` (coppie dalla posizione), `.timone-spreads` in `app.css` (colonne fisse, container query, `html[data-zoom]` dallo store `timoneZoom`).
- Test: `tests/Unit/PageSpreadBuilderTest.php` (accoppiamento), `tests/Feature/Livewire/SpreadGridTest.php`; aggiornati `TimoneGridTest` (il bordo tra le pagine della coppia ora è del contenitore) e `TimoneGridDoublePageAlignmentTest` (slot vuoti al posto di `ml-auto`).

## 2026-10-29 — Manuale utente: una pagina per argomento (fase 1 di «manuale, ricerca, assistente»)

### Visibili agli utenti
- Il manuale è diviso in 46 pagine brevi in 7 categorie; `/manuale` è la pagina d'ingresso («Inizia qui», «Cosa vuoi fare?» con 12 riquadri, indice), `/manuale/<slug>` una pagina. Ogni pagina inizia con «In breve», poi «Dettagli» richiudibili, riquadri «Attenzione»/«Suggerimento», «Vedi anche».
- Nuove pagine: «Legenda» visiva, «Scorciatoie da tastiera» con Mac e Windows affiancati, «Problemi frequenti» (messaggio → significato → cosa fare), «Glossario», «Novità», «Note per l'amministratore».
- I link vecchi del manuale (`/manuale#9bis-…`) portano alla pagina nuova; su telefono l'indice è un menu e le tabelle diventano elenchi.
- Corrette 7 incoerenze del vecchio manuale e i nomi dei bottoni cambiati nell'app.

### Tecniche
- `resources/manuale/*.md` (front matter, blocchi `:::nome`), `App\Support\Manual\{ManualPage, ManualBlocks, ManualRepository, ManualCompiler, ManualVerifier, LegacyAnchors}`, `ManualRenderer::renderPage()`, route `documentation.page`, viste `documentation/index|page`, stili `.manual-*` in `app.css`.
- `MANUALE_UTENTE.md` generato da `php artisan manuale:genera`; `php artisan manuale:verifica` contro la copia archiviata `docs/archivio/MANUALE_UTENTE_2026-10-29.md`; inventario `docs/manuale/INVENTARIO.md`.
- `config/timone.php`: `manual_dir`, `manual_archive` (config/app.php non toccato). `.dockerignore`: eccezione `!resources/manuale/*.md`.

## 2026-10-29 — Manuale: ricerca e pagine (fase 2 di «manuale, ricerca, assistente»)

### Visibili agli utenti
- Campo «Cerca nel manuale» grande in testa a ogni pagina del manuale (`/` o Ctrl/⌘+K per andarci): risultati mentre scrivi con il frammento e la parola evidenziata, frecce e Invio, la sezione si evidenzia all'arrivo; se non trova nulla propone parole vicine e i compiti più frequenti. Capisce sinonimi (togliere = rimuovere = eliminare…), desinenze (sposta, spostare, spostamento) e qualche errore di battitura.
- Indice con «Mostrami le pagine per: Tutti / Redattore / Commerciale / Admin / Sola lettura» (predefinito sul proprio ruolo, «Mostra tutto» riporta le altre) e «In questa pagina» con la sezione corrente evidenziata.
- # accanto ai titoli copia il link della sezione; «↑ Torna su»; stampa senza menu e con i Dettagli aperti.
- Font Figtree in locale: nessuna pagina carica più risorse esterne.

### Tecniche
- `App\Support\Manual\{ItalianText, ManualSearchIndex, ManualSynonyms}`, route `documentation.search-index` (`/manuale/indice.json`, cache con l'impronta dei file, ETag), `resources/manuale/sinonimi.yml`, `resources/js/manual-search-core.js` (puro) e `manual-search.js` (Alpine), viste `documentation/partials/{search,back-to-top}`.
- npm: `minisearch` (import dinamico), `@fontsource/figtree`.
- Test: `tests/Unit/ItalianTextTest.php`, `tests/Feature/ManualSearchTest.php` (indice, route, cache, sinonimi, normalizzazione uguale nel browser e 15 domande reali con node), `tests/js/manual-search-check.mjs`, `tests/fixtures/manual-{text,queries}.json`.

## 2026-10-29 — Manuale: assistente «Chiedi al manuale» (fase 3 di «manuale, ricerca, assistente»)

### Visibili agli utenti
- Bottone tondo **?** in basso a sinistra in ogni schermata e riquadro «Chiedi al manuale» nella pagina del Manuale: si scrive una domanda e compare il riquadro «In breve» della pagina che risponde, con «Leggi tutto →». Sei domande pronte. Dal timone l'assistente preferisce le pagine sul timone.
- Voti **👍 Utile** / **👎 Non è questo**, una volta per risposta; dopo 👎 tre altre pagine con **«👍 È questa»**. Se non è sicuro: «Forse cercavi» con tre pagine; se non trova nulla: invito a riformulare e alcuni compiti.
- Per gli admin: pagina «Cosa chiedono gli utenti» con domande senza risposta, domande imparate, risposte probabilmente sbagliate, ultimo ricalcolo e un'unica azione «Disattiva».
- Nuova pagina del manuale «Chiedi al manuale» (47 pagine).

### Tecniche
- `App\Support\Manual\{SearchEngine, QuestionKey, ManualQuestions, ManualAssistant, ManualAssistantFactory, ManualAssistantDesk, AnswerToken, ManualFeedbackRecorder, ManualLearning, ManualLearningGate, ManualLearningPublisher, ManualInsights, ManualContexts, LearnedCorrections, ArrayLearnedCorrections, StoredLearnedCorrections}`, `ManualAssistantController`, `Livewire\Manual\Questions`, `resources/js/manual-assistant.js`, viste `documentation/partials/{assistant,assistant-list,help-button}`.
- `resources/manuale/domande.yml` (61 voci, 266 domande); le 15 domande della ricerca spostate in `resources/manuale/domande-di-controllo.yml`.
- Migrazione `2026_10_29_300000_create_manual_assistant_tables` (6 tabelle). Comandi `manuale:apprendi` (03:15) e `manuale:pulisci` (03:05).
- Nessuna dipendenza nuova.
- Test: `tests/Unit/ManualLearningTest.php`, `tests/Feature/ManualAssistantTest.php`, test di parità PHP/browser in `ManualSearchTest.php`, `tests/fixtures/manual-assistant-queries.json`.

## 2026-10-29 — Manuale: screenshot annotati (fase 4 di «manuale, ricerca, assistente»)

### Visibili agli utenti
- Dieci pagine del manuale hanno uno screenshot dell'app con dati di esempio inventati e numeri cerchiati spiegati sotto l'immagine: timone intero, card di una pagina, formati pubblicitari, copertine, pannello Contenuti, selezione multipla, finestra «Modifica contenuto», Peso, esportazione PDF, «Chiedi al manuale».

### Tecniche
- `npm run manuale:screenshot` (`scripts/manuale-screenshot.mjs`): Chrome headless via protocollo DevTools, nessuna dipendenza nuova; `Database\Seeders\ManualScreenshotSeeder` su DB e storage temporanei; WebP 2× (4× per la card) in `public/manuale/img/`.
- Blocco `:::screenshot nome` con `alt:` e righe `N | x% | y% | testo` (`App\Support\Manual\ManualScreenshot`, `ManualScreenshotFiles`), sovrapposizione in HTML/CSS (`ManualRenderer::screenshot()`), immagini anche in `MANUALE_UTENTE.md`; config `timone.manual_images`; nginx mette in cache anche i `.webp`.
- `data-testid` nuovi: `weight-panel`, `timone-export`.
- Test: `tests/Feature/ManualScreenshotsTest.php`.


## 2026-10-30 — Manuale: aiuto contestuale (fase 5 di «manuale, ricerca, assistente»)

### Visibili agli utenti
- Piccola **?** tonda accanto a: titolo del pannello ⚖️ Peso, barra delle pagine selezionate, messaggio «Modalità scambio attiva», «Modifica pagine interne», comandi della bandella nella fascia copertine, «Quanto spazio occupa» nelle finestre di creazione e modifica di un contenuto. Apre la pagina (o la sezione) del manuale in una nuova scheda: il lavoro in corso non si perde.
- Il bottone **?** fisso in basso a sinistra con il contesto della schermata c'era già (fase 3).
- Pagina admin «Cosa chiedono gli utenti»: non dice più di modificare `domande.yml`.

### Tecniche
- Componente anonimo `<x-manual-help slug anchor label>` (`resources/views/components/manual-help.blade.php`), stile `.manual-context-help` in `app.css`.
- Test `tests/Feature/ManualContextHelpTest.php`: ogni `<x-manual-help>` e ogni `route('documentation.page', '…')` scritti nelle view puntano a una pagina e a una sezione esistenti (slug fissi obbligatori); i sei argomenti hanno la loro «?»; la «?» compare nelle schermate.
- Screenshot rigenerati (le «?» compaiono in copertine, selezione multipla, peso, modifica contenuto); nessuna annotazione spostata.

## 2026-10-30 — Battenti di copertina (fase A1 di «battenti, crop delle miniature, griglia accoppiata»)

### Visibili agli utenti
- La **bandella** è diventata **battente**, e i battenti sono due: **battente di I** (2 pagine prima della I di copertina) e **battente di IV** (2 pagine dopo la IV). Nella fascia «📘 Copertine»: «+ Battente I», «+ Battente IV», «− Togli battente I/IV» (grigio, con il motivo nel tooltip, se una pagina ha un contenuto o è bloccata). Pagine «📘 Battente I · 1», «· 2», ecc.
- Ordine di sfoglio in fascia, export del timone e PDF sfogliabile: battente I, I, II, pagine interne, III, IV, battente IV. Nel PDF sfogliabile la pagina interna 1 resta sempre a destra.
- Peso e 32esimi (con copertine incluse): 4, 6 o 8 facciate di copertina. Percentuale pubblicità e pagine interne non cambiano.
- Formato «Battente copertina»: occupa le 2 pagine di un battente e va solo lì.
- Impostazioni della rivista e «Nuovo numero»: «Battenti di copertina» (nessuno / I / IV / entrambi) al posto del numero «Pagine copertina».
- Le bandelle esistenti diventano la pagina 1 del battente di I con tutto il loro contenuto; la pagina 2 nasce vuota.

### Tecniche
- `App\Enums\CoverSlot` (`BI1`, `BI2`, `I`…`IV`, `BIV1`, `BIV2`; `front()`/`back()`), nuovi `CoverFlap`, `CoverFlaps`; `IssueCovers::addFlap()/removeFlap()/removalBlocker()/flaps()`; `Grid::addFlap()/removeFlap()` (eventi `CoversChanged` `battente_added`/`battente_removed`, registro `cover.battente_*`); `ContentPlacer` (battente da pagina 1 = 2 pagine, formato battente solo su battente); `AdFormat::BattenteCopertina` 24/12.
- Migration `2026_10_30_100000_convert_bandella_to_cover_flaps` (colonna `magazines.default_cover_flaps`, `App\Support\Covers\FlapConversion`, `down()` con controllo), comando `php artisan timone:battenti-dry-run`.
- Componente `<x-cover-flaps-field>`; `Issue::$initialCoverFlaps` letto da `IssueObserver`.
- Test: `tests/Feature/Livewire/TimoneCoverFlapsTest.php` più i test di copertine, export, peso, segnature e commenti aggiornati.

## 2026-10-30 — Crop delle miniature (fasi B0–B1 di «battenti, crop delle miniature, griglia accoppiata»)

### Visibili agli utenti
- Un contenuto che occupa una parte di pagina mostra **solo la sua parte** del file a pagina intera (mezza verticale a sinistra → metà sinistra del PDF), non più tutto il file rimpicciolito. Vale nel timone, nell'export PDF del timone e nel PDF sfogliabile, con la stessa porzione.
- Forme a L: ogni pezzo mostra la sua parte. PDF con abbondanza: ritaglio sulla pagina rifilata. Contenuti su più pagine: ogni pagina la sua pagina del PDF.
- Un file già della misura dell'annuncio si vede intero; «Anteprima nel riquadro» permette sempre di forzare la scelta.
- Anteprime ritagliate più nitide (miniatura da 1200 px, caricata dopo la griglia).
- Export PDF del timone: l'anteprima non è più stirata nel riquadro.
- Corretto: un clic nel timone (es. «☑️ Selezione») partito nello stesso istante del controllo dei commenti veniva eseguito ma la schermata non si aggiornava.

### Tecniche
- `App\Support\PageGrid\ThumbnailCrop` → `CropPlan` (sostituisce `ThumbnailFit`): regola «Automatico» nuova (intero solo con la misura del riquadro), pagina rifilata (TrimBox / formato + 3 mm / intera), finestre per pezzo, stile CSS e disegno per FPDI dalla stessa fonte.
- `App\Support\PdfBoxes` (TrimBox con il parser FPDI), `ContentFileExtras`, `CroppedThumbnails` (PNG ritagliati con GD per dompdf, cache per file + finestra); job `CompleteContentFileThumbnail`, `WarmCroppedThumbnail`; `?size=large` sulla rotta della miniatura; `timone:file-ratios` esteso.
- `Grid::skipRenderIfOnlyPolling()` al posto di `skipRender()` nei due polling (test `TimoneGridPollingBatchTest`).
- Migration `2026_10_30_200000_add_crop_columns_to_content_files` (`trim_box`, `large_thumbnail_path`, solo colonne nuove).
- Verifica: `tests/Support/CropFixtures.php` (PDF di prova a sesti colorati), `tests/Feature/ThumbnailCropReproTest.php`, `ThumbnailCropCacheTest.php`, `ThumbnailCropCardTest.php`, `tests/Unit/ThumbnailCropTest.php`; `node scripts/verifica-crop.mjs` + `Database\Seeders\CropVerificationSeeder` → immagini in `docs/verifiche/crop/`.
