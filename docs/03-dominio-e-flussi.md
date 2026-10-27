# 03 — Dominio e flussi di business

[← Indice](README.md) · Vedi anche: [12-glossario.md](12-glossario.md), [04-database.md](04-database.md), `MANUALE_UTENTE.md` (vista utente degli stessi flussi)

## Modello di dominio in breve

```text
Magazine (rivista) ──< Issue (numero) ──< Page (pagina, posizione 1..N)
     │                     │                  │  ▲
     │                     │                  │  │ page_content (pivot: % occupata, file della pagina PDF)
     │                     └──< Content ──────┘──┘
     │                           ├── Article (1:1, se type=articolo)
     │                           ├── Advertisement (1:1, se type=pubblicita)
     │                           └──< ContentFile (PDF/immagine, una riga per pagina del PDF)
     ├──< Section (rubrica)
     ├── redattore (User di riferimento per i solleciti)
     └──< magazine_user >── User (accesso alla rivista)
Issue ──< PageGroup (pagine collegate che si muovono insieme)
Issue ──< ActivityLog, PageReorderLog
Page  ──< PageFile (LEGACY: PDF caricato direttamente sulla pagina, prima del 2026-09-02)
```

## Ruoli e permessi

Fonte: `app/Policies/*`, `app/Enums/UserRole.php`, `MANUALE_UTENTE.md` §14.

| Ruolo (`users.role`) | Vede | Modifica il timone | Crea numeri | Crea/modifica riviste | Gestisce utenti |
|---|---|---|---|---|---|
| `admin` | tutte le riviste | sì | sì | sì | sì |
| `redattore` | riviste collegate in `magazine_user` | sì, sulle sue riviste | sì, sulle sue riviste | no | no |
| `commerciale` | riviste collegate | no (sola lettura) | no | no | no |
| `sola_lettura` | riviste collegate | no | no | no | no |

`commerciale` e `sola_lettura` oggi si comportano in modo identico (`MANUALE_UTENTE.md` §14, nota). Gli export CSV/PDF richiedono solo `view`.

---

## Flusso 1 — Login

1. `GET /` → redirect a `login` o `dashboard` (`routes/web.php:26`).
2. `POST /login` → `App\Http\Controllers\Auth\AuthenticatedSessionController::store` → `App\Http\Requests\Auth\LoginRequest` (rate limiting Breeze: 5 tentativi per email+IP).
3. La sessione è salvata nella tabella `sessions` (`SESSION_DRIVER=database`).
4. Reset password: `forgot-password` → mail con link (**richiede una mail funzionante**, vedi [07-integrazioni.md](07-integrazioni.md#mail-smtp)).

## Flusso 2 — Creazione rivista e accesso utenti (Admin)

1. `/riviste/nuova` → `App\Livewire\Magazines\Create` (`authorize('create', Magazine::class)`, solo Admin). Lo slug è generato in `Magazine::booted()` (`app/Models/Magazine.php:38`).
2. `/riviste/{slug}/modifica` → `Magazines\Edit`: redattore (`battente_counted_in_total` non è più nel modulo dalla Fase 2), di riferimento (`redattore_id`).
3. `/utenti/nuovo` → `Users\Create`: crea l'utente con il ruolo e sincronizza `magazine_user`.

## Flusso 3 — Creazione di un numero

1. `/riviste/{slug}/numeri/nuovo` → `App\Livewire\Issues\Create::save()` (`app/Livewire/Issues/Create.php:56`):
   - `authorize('create', Issue::class)` + `canAccessMagazine`;
   - validazione: `total_pages` (solo pagine **interne**, dalla Fase 2 del 2026-10-26) da 0 a 2000;
   - `Issue::create([... 'status' => bozza])`.
2. **`App\Observers\IssueObserver::created()`** (registrato in `AppServiceProvider::boot`):
   - inserisce `total_pages` pagine interne in `pages` (posizioni 1…N, tipo `bianca`, stato `da_assegnare`);
   - `App\Support\IssueCovers::ensure()` crea **sempre** le copertine I, II, III, IV (righe di `pages` con `cover_slot` e `position` NULL; prima della Fase 2 nascevano solo I e IV, come ruolo della prima/ultima pagina) e la **bandella** se `pagine_copertina` ≥ 6; poi allinea `issues.pagine_copertina` (4 o 6).
3. Opzionale "duplica struttura": `Issue::duplicateStructureFrom($source)` copia solo `content_type` per posizione (pagine interne) e per slot (copertine). **Non** copia i contenuti.
4. **Pubblicità previste** (Fase 5, 2026-10-27): `App\Support\AdPlacement\PlaceholderAdCreator::create($issue, $counts)` — vedi sotto. Se il piano non ci sta, `save()` si ferma prima di creare il numero (errore `plannedAds`).
5. Redirect a `issues.show`, con gli avvisi del posizionamento in sessione (`ad-placement-warnings`).

## Pubblicità previste e segnaposto (Fase 5, 2026-10-27)

- **Modulo** "Nuovo numero": quante pubblicità a pagina intera, 2/3, 1/2, piedino (1/3), vetrina (1/4) (`App\Enums\PlannedAdKind`, proprietà `Issues\Create::$plannedAds`). Anteprima live dallo stesso piano usato al salvataggio (`AdPlacementPlan`): pagine equivalenti totali, percentuale sulle pagine interne (copertine escluse, come nel cruscotto), posizioni, errore se non ci stanno.
- **Regola** `App\Support\AdPlacement\AdPlacementService::plan()` (pura, chiavi `config('timone.ad_placement')`): 1) le prime pubblicità di **qualsiasi formato** (prima le intere, poi 2/3, 1/2, 1/3, 1/4) riempiono tutte le copertine di fila nell'ordine `cover_order` (II, III, IV, I; la I genera un avviso) — precisazione dell'utente del 2026-10-27; 2) le pagine intere rimaste su `start_page`, `start_page + step`… (2, 4, 6…); 3) le frazionarie rimaste continuano la stessa sequenza, una per pagina; 4) finita la sequenza, pagine libere dal fondo; senza posto → `unplaced`.
- **Creazione** `PlaceholderAdCreator`: un `Content` pubblicità per ognuna, `is_placeholder = true`, titolo «Pubblicità 1/2 — da assegnare», `Advertisement` con `client` NULL, formato da `PlannedAdKind::format()` (la mezza nasce orizzontale), stato «in trattativa»; attaccata alla pagina/copertina del piano con `ContentPageAttacher` (spazio = formato, max 12). Log `issue.placeholder_ads_created`. **Solo alla creazione del numero**: nessun altro punto la richiama, niente ricalcoli.
- **Da segnaposto a pubblicità vera**: `ContentUpdater` toglie il flag quando arrivano cliente, titolo diverso da quello automatico o PDF (o il contenuto diventa un articolo); `Grid::renameContent()` quando gli si dà un nome (diventa il cliente). Nella modale il cliente non è obbligatorio per un segnaposto (`ContentFormRules::rules(..., placeholder: true)`).
- **Visibilità**: contorno tratteggiato + etichetta «Da assegnare» (`partials/page-content-entry`, chip dei contenuti da assegnare). Contatore «Pubblicità da assegnare (segnaposto)» per formato nel cruscotto e nella barra di riepilogo: `App\Support\IssueSummary` (tutto il numero, copertine comprese; fino alla Fase 6 era `PlaceholderAdCounter`, rimosso). Nella percentuale contano come ogni pubblicità (`AdLoadCalculator`).

## Copertine (Fase 2, 2026-10-26)

- **Modello**: una copertina è una riga di `pages` con `cover_slot` (`App\Enums\CoverSlot`: `I`, `II`, `III`, `IV`, `bandella`) e `position` NULL. Resta una riga di `pages` per riusare contenuti, anteprime, stato, blocco e note senza duplicazioni; esce però dalla sequenza: `Issue::pages()` restituisce **solo le interne** (1…`total_pages`), `Issue::covers()` le copertine, `Issue::allPages()` entrambe. Vincolo `unique(issue_id, cover_slot)`.
- **Numerazione**: le pagine interne sono numerate dalla loro `position` (niente più `PageNumberer`). `total_pages` = pagine interne; `Issue::displayedTotalPages()` restituisce lo stesso valore (resta per i chiamanti esistenti).
- **Conteggi**: cruscotto pubblicitario, avvisi, pagine bianche e percentuali usano solo le interne; le copertine hanno un conteggio a parte nell'intestazione del timone.
- **Spostamenti**: `Grid::movePage()`/`nudgePage()` rifiutano le copertine; le copertine non sono nel contenitore Sortable, quindi una pagina interna non può finirci dentro. Tra copertine: drag nativo (`text/cover-id`) → `Grid::swapCovers()` → `IssueCovers::swap()` (bandella esclusa, copertine bloccate escluse).
- **Contenuti su più pagine partendo da una copertina** (`ContentPlacer::coverTargets()`): dalla II la II e poi le pagine 1, 2…; dalla III le ultime pagine interne e poi la III; I, IV e bandella una sola pagina. Mai in un gruppo collegato.
- **Bandella**: `Grid::addBandella()`/`removeBandella()` (solo se vuota e non bloccata) → `IssueCovers`; vale 2 facciate: `issues.pagine_copertina` = 4 o 6, scritto da `IssueCovers::syncPrintedPages()` e mostrato in sola lettura nella modale del Peso.
- **Evento**: `App\Events\CoversChanged` (scambio, bandella) ascoltato da `Grid` e `WeightPanel`.
- **Conversione dei numeri esistenti**: migration `2026_10_26_200002_convert_cover_pages_to_slots` (piano in `App\Support\Covers\CoverConversionPlanner`, scritture e rollback in `CoverConversion`, registro `cover_conversion_logs`); anteprima con `php artisan timone:covers-dry-run`. Vedi [04-database.md](04-database.md).

## Occupazione in dodicesimi (Fase 3, 2026-10-27)

- **Unità**: il dodicesimo di pagina, sempre un intero. 12 = pagina intera; 1/4 = 3, 1/3 = 4, 1/2 = 6, 2/3 = 8, 3/4 = 9. Perché 12: è il minimo comune multiplo delle frazioni di redazione e listino, quindi somme e confronti sono esatti (1/3 + 1/3 + 1/3 = 12, pagina piena; con le vecchie percentuali 33,3 × 3 = 99,9). Costante e formattazione in frazioni ("1/3", "2 e 1/2 pagine"): `App\Support\Twelfths`, unico punto in cui un intero diventa testo.
- **Occupazione totale del contenuto**: `contents.occupancy_twelfths` (30 = 2 pagine e mezza), value object `App\Support\PageOccupancy`. Nel modulo: "Pagine intere" + "Frazione" (`PageOccupancy::fromParts()`, risolto da `ContentCreator::resolveOccupancy()`/`occupancyFromFields()`); almeno uno dei due > 0. Per la pubblicità il formato la **propone** (`AdFormat::twelfths()`, `ContentCreate::updatedFormat()`), modificabile.
- **Distribuzione**: `PageOccupancy::distribution()` — pagine intere in sequenza, la frazione sull'ultima (30 → 12, 12, 6). La usano `ContentPlacer::place()` e `ContentCreate::attachConfirmedPages()`.
- **Spazio su una pagina**: pivot `page_content.occupied_twelfths` (1–12), modificabile per pagina con `Grid::updateContentOccupancy()` (select sulla card). Estendere a una pagina in più (`extendToPage`) assegna `PageOccupancy::perExtraPage()` (una pagina intera, o tutto il contenuto se è meno di una pagina).
- **Capienza**: `App\Support\PageCapacity` — somma per pagina ≤ 12, altrimenti errore bloccante con quanto spazio resta (`insufficientSpaceMessage()`); stato `App\Enums\PageFillStatus` (vuota/parziale/piena/oltre capienza), mostrato sulla card da `partials/page-fill.blade.php` (barra + etichetta "Libero: 1/3"/"Piena").
- **Mappatura formati** (`AdFormat::twelfths()`): pagina intera, 1ª romana, controsommario, elenco inserzionisti, controeditoriale, pubbliredazionale, copertina 2ª/3ª/4ª, battente copertina → 12; doppia pagina → 24; 2/3 → 8; mezza orizzontale/verticale → 6; 1/3 orizzontale/verticale, piedino → 4; 1/4 (vetrina) → 3.
- **Carico pubblicitario**: `AdLoadCalculator` somma i dodicesimi pubblicitari (pivot per le assegnate, `occupancy_twelfths` per le prenotate) e divide per pagine interne × 12, arrotondando solo alla fine.
- **Conversione dei dati esistenti** (migration `2026_10_27_100000_convert_occupancy_to_twelfths`, dry-run `php artisan timone:occupancy-dry-run`): pivot = dodicesimo più vicino alla vecchia percentuale (mai 0, mai oltre 12); contenuto = vecchia occupazione dichiarata (frazioni → 3/4/6/8, N pagine → 12·N), pubblicità senza percentuale manuale → formato, con percentuale manuale → pagine × dodicesimo della percentuale. Le pagine che dopo l'arrotondamento superano 12 sono segnalate, non corrette.

## Riepilogo stile dashboard (Fase 6, 2026-10-27)

- **Dove**: `resources/views/livewire/timone/partials/summary-bar.blade.php`, primo elemento di `grid.blade.php`, `sticky` con `top: var(--app-header-height)`: l'altezza reale dell'header fisso la misura uno snippet Alpine (ResizeObserver) in `layouts/app.blade.php`. z-30 (sotto l'header z-40 e le modali z-50).
- **Dati**: `App\Support\IssueSummary::summarize()` in `Grid::render()`, sulle collezioni già caricate: pagine interne piene/parziali/bianche e spazio libero (`PageCapacity`, dodicesimi), % pubblicitaria (`AdLoadCalculator`), segnaposto per formato e pagine che li ospitano (pagine, copertine, non assegnati), copertine assegnate/libere, peso copia e scaglione (`MagazineWeightCalculator::calculate()` con gli stessi parametri del pannello Peso). Nessuna query propria.
- **Evidenziazione**: i bottoni «bianche» e «Da assegnare» chiamano `Grid::toggleSummaryHighlight('blank'|'placeholders')`; `render()` passa `highlightPageIds`; card e righe corrispondenti hanno contorno azzurro (`data-highlighted`), le altre `opacity-40`. Evidenziare e non filtrare: posizioni e drag&drop restano invariati.
- **Soglie tolte**: niente più soglia di allarme né colori di avviso del carico pubblicitario (cruscotto, export CSV/PDF, moduli rivista; `Grid::updateAdThreshold()` rimosso). La colonna `magazines.ad_threshold_percentage` resta, non usata.
- **Bug corretto**: le prenotate del carico pubblicitario venivano dalla lista "Contenuti da assegnare" già filtrata dalla ricerca per titolo; ora `render()` carica tutti i non assegnati e filtra in memoria solo l'elenco mostrato.
- **Perché non un componente Livewire a sé** (proposta del debito #52): dovrebbe ripetere tutti i listener realtime di Grid (spostamenti, assegnazioni, stati, copertine…) e il polling per restare aggiornato; come partial di Grid si aggiorna gratis a ogni re-render. La logica è comunque fuori da Grid (`IssueSummary`).

## Flusso 4 — Apertura del timone

`Issues\Show::mount()` (`authorize('view')`) monta:
- `Timone\Grid`: griglia o lista delle pagine, cruscotto pubblicitario, avvisi automatici (`App\Support\AutomaticChecks`), storico;
- `Timone\ContentCreate`: form "+ Nuovo contenuto" e pannello rubriche;
- le **copertine** non hanno più un componente a sé (il vecchio `Timone\CoverSection` è stato rimosso con la Fase 2): `Grid` le mostra tutte in un'unica fascia in cima, prima della pagina 1 (`partials/cover-band.blade.php`, ordine I, bandella, II, III, IV), con le stesse card delle pagine. Regole: vedi "Copertine" sotto;
- `Timone\AdReservations`: pubblicità prenotate e chiusura numero;
- `ActivityLogPanel` e `PageFileHistory`.

Presence: il client si unisce a `presence-issue.{id}` via Echo. La barra "utenti online" e il whisper "chi sta modificando cosa" sono in `resources/js/app.js` (store `pagePresence`).

## Flusso 5 — Creazione di un contenuto (articolo o pubblicità), con o senza file

File coinvolti, in ordine di esecuzione:

1. `resources/views/livewire/timone/content-create.blade.php`: form e drop zone. Il file viene caricato da Livewire su un disco temporaneo (`livewire-tmp`; regole in `config/livewire.php:84`: pdf/jpg/jpeg/png/webp, max 100 MB).
2. `ContentCreate::updatedFile()` (`app/Livewire/Timone/ContentCreate.php:225`): valida e conta le pagine del PDF (`ContentCreator::pdfPageCount()` → `PdfPageMeasurer::pageCount()`, che **richiede Imagick**). Per un articolo con i campi occupazione ancora vuoti propone tante pagine intere quante quelle del PDF.
3. `ContentCreate::save()` (`:261`):
   - `authorize('update', issue)`;
   - `ContentCreator::resolveOccupancy()`: l'occupazione (dodicesimi, dai campi `whole_pages` + `fraction_twelfths`) è **obbligatoria**. Per un articolo, con un PDF multipagina le pagine attraversate devono coincidere con le pagine del PDF. Per la pubblicità la propone il formato (modificabile); **dal 2026-10-26 (bug 8)** se il PDF ha più pagine di quante ne occupa il formato il modulo chiede quali usare (`App\Support\AdPdfPagePlan`): **tutte** (occupazione = N pagine intere) oppure **da X a Y** (occupazione del formato se Y−X+1 coincide con la sua, altrimenti Y−X+1 pagine). La scelta arriva a `ContentCreator` come `pdf_page_from`/`pdf_page_to`; senza scelta l'errore è sul campo `pdfPageChoice`, non più sul file. Un PDF con *meno* pagine del formato resta com'era (occupazione del formato).
   - Se il form è stato aperto da una pagina ("📎 Carica materiale", quindi `anchorPageId` valorizzato) e le pagine del PDF **usate** sono più di una, apre la **modale di conflitto multipagina** (`openMultipageConflict`). Le opzioni sono: sovrascrivere, saltare le pagine occupate, oppure "sposta dopo" (`confirmMultipageUploadWithShift`, che usa `App\Support\PageInserter`).
   - Altrimenti chiama `createContentAndFinish()`.
4. `App\Support\ContentCreator::create()` (`app/Support/ContentCreator.php:135`):
   - in transazione: `Content` + `Article` oppure `Advertisement`;
   - `ActivityLogger::log('content.created')`;
   - per **ogni pagina del PDF**, `storeFile()` salva una **copia completa del file** in `storage/app/private/contents/{content_id}/{uuid}.{ext}`, crea un `ContentFile` (`pdf_page_number` = numero ORIGINALE della pagina nel PDF: con un intervallo 2–3 i file sono 2 e 3) e accoda **`GenerateContentFileThumbnail`**. Conseguenza: un PDF di 64 pagine da 100 MB occupa circa 6,4 GB su disco. Vedi [11-debito-tecnico.md](11-debito-tecnico.md).
5. Se ancorato a una pagina: `ContentPlacer::place()` (vedi flusso 6). Le pagine del timone ricevono i `ContentFile` **in ordine** di `pdf_page_number` (la prima pagina del timone riceve la prima pagina scelta, non la "pagina 1" del PDF) — stesso criterio in `ContentCreate::attachConfirmedPages()`.
6. Broadcast `ContentCreated` / `ContentFileUploaded` / `ContentAssigned`.
7. Il worker esegue `App\Jobs\GenerateContentFileThumbnail` (vedi [08-job-e-scheduler.md](08-job-e-scheduler.md)):
   - miniatura PNG 150 dpi della pagina `pdf_page_number`;
   - per le pubblicità, controllo del formato (`PdfPageMeasurer::pageSizeMm` + `PdfFormatChecker::matches` con tolleranza `TIMONE_PDF_FORMAT_TOLERANCE_MM` e abbondanza di 3 mm per lato).

## Flusso 6 — Assegnazione di un contenuto a una pagina (drag dal pannello)

1. HTML5 drag & drop nativo dal chip del contenuto alla card della pagina (`resources/js/app.js`), che chiama `$wire.assignContent(contentId, pageId)`.
2. `Grid::assignContent()` (`app/Livewire/Timone/Grid.php:1658`): `authorize('update')`, poi verifica che contenuto e pagina siano dell'issue corrente e che la pagina non sia bloccata.
3. `App\Support\ContentPlacer::place()` (`app/Support/ContentPlacer.php:37`), **tutto o niente**:
   - `pagesSpanned()` pagine consecutive a partire da quella di drop; se il numero finisce prima, restituisce un messaggio;
   - nessuna pagina deve essere bloccata e ognuna deve avere spazio per la sua quota della distribuzione 12, 12, …, frazione (`PageCapacity::fits`);
   - in transazione: `ContentPageAttacher::attach()` per ogni pagina (pivot `page_content` con `occupied_twelfths` e `content_file_id` della pagina PDF corrispondente) e `syncPageContentType` (editoriale/pubblicità/mista);
   - se le pagine sono 2 o più: `PageGroupLinker::link()` crea un `PageGroup`.
4. Activity log + broadcast `ContentAssigned` (e `PageGroupsChanged`), poi `markPagesUpdated()`.

Correlati: `extendToPage` (`Grid.php:1738`), `updateContentOccupancy`, `unassignContent` (`:1854`), `archiveContent`/`deleteUnassignedContent`.

## Flusso 6bis — Modifica e rinomina di un contenuto (Fase 4, 2026-10-27)

1. **Apertura**: ✏️ sul chip dei contenuti da assegnare (`partials/unassigned-content-chip.blade.php`) o sulla riga del contenuto in pagina (`partials/page-content-entry.blade.php`) → evento browser `open-content-edit` → `App\Livewire\Timone\ContentEdit::open()` (`authorize('update')`, contenuto dello stesso numero, carica i campi; occupazione nei campi con `HasOccupancyFields::fillOccupancyFields()`).
2. **Salvataggio** `ContentEdit::save()`: regole `App\Support\ContentFormRules` (le stesse della creazione), poi `App\Support\ContentUpdater::update()`:
   - occupazione dai campi (obbligatoria); il PDF che resterà (nuovo o attuale) deve coincidere con le pagine attraversate (`ContentCreator::assertPdfFits()`);
   - se cambiano occupazione o file e una pagina occupata è bloccata → errore, nulla salvato;
   - in transazione: campi del contenuto e dell'articolo/pubblicità (cambio tipo = riga dell'altro tipo eliminata, `syncPageContentType` su ogni pagina); se l'occupazione è cambiata e il contenuto è posizionato → **ridistribuzione**: scioglie il gruppo collegato se coincide con le sue pagine, stacca il contenuto, `ContentPlacer::place()` dalla prima pagina in ordine di sfoglio (`assignedPagesInReadingOrder()`: copertine, poi interne); se non ci sta → `ValidationException`, rollback;
   - fuori transazione (i job delle miniature devono trovare le righe): sostituzione del materiale (`replaceFiles()`: file e righe vecchie eliminati, `ContentCreator::storeFiles()`, pivot `content_file_id` per posizione);
   - log `content.updated` con i soli campi cambiati.
3. Broadcast `ContentListChanged` (`updated`), `markPagesUpdated()`, evento locale `content-list-changed` (Grid e AdReservations si aggiornano).
4. **Rinomina inline**: doppio click sul nome nella riga del contenuto (Alpine, stato solo nel browser) → Invio → `Grid::renameContent()`: titolo per un articolo, cliente per una pubblicità (ciò che `displayLabel()` mostra); log `content.renamed`, broadcast `ContentListChanged` (`renamed`).
5. **Anteprima**: il PDF/immagine di stampa è anche la miniatura del contenuto (nessuna immagine separata, decisione dell'utente). URL dell'anteprima pronta: `Content::previewImageUrl()` (chip), `Page::previewImageUrl()` (card e riga).

## Flusso 7 — Riordino delle pagine

Vedi il diagramma in [02-architettura.md](02-architettura.md#interazione-es-dragdrop-di-una-pagina). Varianti:
- **singola pagina**: `Grid::movePage()` (`:476`);
- **gruppo collegato**: se la pagina ha `page_group_id`, si muove tutto il gruppo (`moveBlockOfPages`);
- **selezione multipla come blocco**: `moveSelectedBlock()` (`:778`) → `PageReorderer::moveBlock()`, evento unico `PagesBlockMoved`;
- **modalità scambio**: `toggleSwapMode()` / `selectForSwap()` (`:623`, `:636`), evento `PagesSwapped`.

Ogni spostamento scrive `page_reorder_logs` e incrementa `issues.reorder_version` (lock ottimistico). Una pagina bloccata (`locked_at`) non si muove.

## Flusso 8 — Stato pagina e approvazione per la stampa

1. `Grid::changePageStatus()` (`:1154`), oppure `bulkChangeStatus()` per la selezione multipla.
2. Il passaggio a **`ok_stampa`** è bloccato se la pagina non ha un PDF (`Page::previewFile()`, che considera sia il `ContentFile` sia il `PageFile` legacy).
3. `AutomaticChecks` segnala (senza bloccare): pagine `revisionata`/`ok_stampa` senza contenuti o senza PDF, contenuti su pagine non consecutive.

## Flusso 9 — Blocco pagina

`Grid::togglePageLock()` (`:1255`) imposta `pages.locked_at`/`locked_by` ed emette `PageLocked`/`PageUnlocked`. Una pagina bloccata non può essere spostata, modificata, ricevere contenuti, né essere rimossa da un ridimensionamento.

## Flusso 10 — Modifica del numero totale di pagine

`Grid::resizePages()` (`:265`) + `App\Support\PageCountResizer::impact()`:
- se il numero aumenta, aggiunge pagine in coda;
- se diminuisce: è vietato se le pagine da rimuovere sono bloccate; se hanno contenuti chiede conferma (`$confirmed`);
- evento `IssuePageCountUpdated`.

Limite noto: non è consapevole dei gruppi di pagine (`HANDOFF.md`, "Debito noto").

## Flusso 11 — Pubblicità prenotate e chiusura del numero

- Una pubblicità senza materiale né pagina è una **prenotazione**. Lo stato del materiale (`prenotato` / `assegnato` / `completo`) è calcolato da `App\Support\AdMaterialStatusResolver`.
- Il cruscotto (`App\Support\AdLoadCalculator::summarize`) include le prenotazioni nel carico.
- `AdReservations::closeIssue()` (`app/Livewire/Timone/AdReservations.php:89`) porta l'issue a `chiuso` **solo se** tutte le pubblicità sono `completo`.
  - Nota: nel codice non trovo un blocco delle modifiche su un numero chiuso (`IssuePolicy::update` non guarda lo stato). Lo stato `chiuso` serve a escludere il numero dal "numero corrente" (`Magazine::currentIssue`, `Magazine.php:88`) e dai solleciti.
  - Nota: lo stato `in_lavorazione` esiste nell'enum ma nessun codice in `app/` lo imposta.

## Flusso 12 — Export

| Export | Rotta | Codice | Libreria |
|---|---|---|---|
| Timone completo PDF | `issues.export.timone-pdf` | `TimoneExportController::pdf` (`app/Http/Controllers/TimoneExportController.php:25`), view `resources/views/exports/timone.blade.php` | barryvdh/laravel-dompdf |
| Carico pubblicitario CSV | `issues.export.ad-dashboard-csv` | `AdDashboardExportController::csv` (`:18`, `streamDownload`) | nativo |
| Carico pubblicitario PDF | `issues.export.ad-dashboard-pdf` | `AdDashboardExportController::pdf` (`:61`), view `exports/ad-dashboard.blade.php` | dompdf |

## Flusso 13 — Sollecito scadenza stampa

1. Lo scheduler (`routes/console.php`) esegue `timone:send-deadline-reminders` ogni giorno alle 07:30 (ora di `APP_TIMEZONE`, default **UTC**).
2. `App\Console\Commands\SendDeadlineReminders::handle()` seleziona le issue con `print_deadline_at` impostato e `deadline_reminder_sent_at` nullo.
3. `App\Support\DeadlineReminderChecker::isDue()` richiede che l'issue non sia `chiuso`, che la rivista abbia `redattore_id` e che `giorni alla scadenza <= TIMONE_DEADLINE_REMINDER_DAYS_BEFORE` (default 3). Anche le scadenze già passate vengono sollecitate.
4. `Mail::to(redattore)->queue(new IssueDeadlineReminder)` (in coda, la spedisce il worker), poi `deadline_reminder_sent_at = now()`. Cambiare la scadenza (`Grid::updatePrintDeadline`) azzera il flag.

## Flusso 14 — Manuale in-app

`GET /manuale` → `DocumentationController::show` legge `config('app.manual_path')` (= `MANUALE_UTENTE.md`), sostituisce `{{APP_URL}}` e lo converte con `App\Support\ManualRenderer` (CommonMark, HTML grezzo escapato). Se il file manca, mostra un messaggio + `report()` invece di un 500. `.dockerignore` include esplicitamente `!MANUALE_UTENTE.md`.

## Calcolo peso rivista

Peso teorico di una copia del numero, con margine d'errore, scaglione di spedizione e peso della tiratura. Riproduce il foglio Excel usato in redazione. Tutto il calcolo sta in `app/Support/Weight/MagazineWeightCalculator.php`, una classe pura senza Eloquent, Livewire né `config()`: scaglioni e limiti di plausibilità di grammature e formato (`WeightLimits`) arrivano come parametri.

### Formule

Unità di misura:

| Termine | Unità |
|---|---|
| Formato (base, altezza) | salvato in **mm interi** (`formato_base_mm`, `formato_altezza_mm`); nel calcolo viene diviso per 10 (cm) |
| Grammatura | g/m² |
| Pagine | facciate |
| Pesi della copia | g |
| Tiratura | kg |

```text
area_cm2         = (formato_base_mm / 10) × (formato_altezza_mm / 10)
peso_interno_g   = area_cm2 × grammatura_interno   / 10000 × (pagine_interno   / 2)
peso_copertina_g = area_cm2 × grammatura_copertina / 10000 × (pagine_copertina / 2)
peso_copia_g     = (peso_interno_g + peso_copertina_g) × (1 + maggiorazione_percentuale / 100)
peso_netto_kg    = peso_copia_g × n_copie / 1000
peso_lordo_kg    = peso_netto_kg + n_bancali × peso_bancale_kg
```

Da dove vengono i numeri della formula:

- **`area × grammatura / 10000`**: è il peso in grammi di **un foglio**, perché cm² × g/m² diviso 10000 cm²/m² dà grammi.
- **`/ 2`**: ogni foglio fisico ha due facciate, cioè due pagine.
- Le costanti di conversione sono in `MagazineWeightCalculator` (`MM_PER_CM`, `CM2_PER_M2`, `PAGES_PER_SHEET`, `GRAMS_PER_KG`).
- **Maggiorazione e peso del bancale sono parametri**, non costanti: `maggiorazione_percentuale` (default 2,00) e `peso_bancale_kg` (default 20,00).

### Grammatura, formato e peso di una pagina

Si inserisce la **grammatura** scritta sulla carta (g/m², es. 80 per l'interno, 170 per la copertina), **non** il peso di una pagina: il peso a pagina lo calcola il sistema da formato e grammatura. Una pagina è mezzo foglio (un foglio ha due facciate):

```text
peso_pagina_g = (base_mm / 1000) × (altezza_mm / 1000) × grammatura / 2
              = MagazineWeightCalculator::pesoPagina(base_mm, altezza_mm, grammatura)
```

Interno e copertina pesano diversamente perché hanno grammature diverse. Esempi:

| Formato | Interno 80 g/m² | Copertina 170 g/m² |
|---|---|---|
| NM 210 × 270 mm | 2,268 g/pag. × 208 pag. = 471,744 g | 4,8195 g/pag. × 6 pag. = 28,917 g |
| 210 × 297 mm | 2,4948 g/pag. | 5,30145 g/pag. |

NM: (471,744 + 28,917) × 1,02 = **510,67422 g**. `pesoPagina()` è il mattone dell'unica formula: `weights()` calcola interno e copertina con `pesoPagine()` (= `pesoPagina() × pagine`), e l'anteprima nei form mostra lo stesso valore sotto ogni grammatura (es. "80 g/m² → 2,268 g a pagina · 208 pagine = 471,7 g", `App\Support\Weight\WeightPreview` + `PaperLine`). Nessun altro punto (Livewire, Blade, JS) ricalcola pesi.

**Errori di unità**: due errori danno un peso copia di pochi grammi invece di ~500 g:
- grammatura scritta come peso di una pagina: 0,8 al posto di 80 → (21 × 27 × 0,8) / 10000 × 104 = 4,717 g invece di 471,744 g;
- formato scritto in centimetri: 21 × 27 al posto di 210 × 270 mm → area 100 volte più piccola, NM = 5,107 g invece di 510,674 g.

Per questo grammature e formato hanno un intervallo ammesso in `config/peso_rivista.php` (limiti inclusi): `grammatura.min`/`max` = 30–400 g/m², `formato.min`/`max` = 50–1000 mm per base e altezza. Entrambi passano da `App\Support\Weight\WeightLimits` (due `ParameterRange` con i rispettivi messaggi):
- **nei form** (validazione server, `WeightParameters::rules()` / `messages()`):
  - grammatura sotto 30 → "Inserisci la grammatura della carta in g/m² (es. 80), non il peso di una pagina. Il peso a pagina viene calcolato automaticamente."; sopra 400 → "Valore troppo alto per una carta da rivista, controlla l'unità di misura.";
  - formato sotto 50 → "Inserisci il formato in millimetri (es. 210 × 270), non in centimetri."; sopra 1000 → "Valore troppo alto per il formato di una rivista, controlla l'unità di misura (millimetri).";
- **nel calcolo** (`MagazineWeightCalculator::calculate(..., WeightLimits)`): un valore fuori intervallo già salvato nel DB dà un risultato **incompleto** con errore esplicito (es. "Formato base 21 mm fuori dall'intervallo ammesso (50–1000 mm). Inserisci il formato in millimetri…"), mai un peso plausibile ma sbagliato. `Issue::weight()`, il pannello e le anteprime passano sempre `WeightLimits::fromConfig()`. Con un formato fuori intervallo, anche la riga del peso a pagina non mostra un peso ma rimanda al formato.

I campi accettano sia la virgola sia il punto decimale (`80`, `80,5`, `80.5`): `WeightParameters::normalizeInput()` converte la virgola prima di validazione, anteprima e salvataggio.

Le **pagine interne** non sono un campo salvato: sono `issues.total_pages` (dalla Fase 2 le copertine non ne fanno più parte). Le **pagine di copertina** non sono più un parametro scritto a mano per il numero: sono le copertine che esistono (`Issue::coverPageCount()`: 4, o 6 con la bandella), vedi `Issue::weightInput()`. Prima della Fase 2 si calcolava `total_pages − pagine_copertina` e un avviso segnalava ruoli copertina incoerenti: l'avviso è stato tolto perché non può più scattare.

**Precisione**: tutti i calcoli usano float nativi, senza bcmath. I decimal arrivano da Eloquent come stringhe e vengono convertiti in un punto solo, `MagazineWeightInput::fromArray()`. Nessun passaggio intermedio è arrotondato. L'arrotondamento avviene solo in visualizzazione (`app/Support/Weight/WeightFormatter.php`):

| Valore | Formato |
|---|---|
| Peso copia | 1 decimale |
| Margine | grammo intero per eccesso |
| kg | 2 decimali |

La formattazione è italiana: virgola per i decimali, punto per le migliaia.

### Margine d'errore (caso peggiore)

Il peso è teorico: la carta reale scosta dalla grammatura nominale, il refilo varia e la maggiorazione è una stima. Il margine **non** è un'approssimazione lineare. La formula completa viene ricalcolata due volte:

```text
peso_max = formula con  base + tolleranza_formato_mm,  altezza + tolleranza_formato_mm,
                        grammature × (1 + tolleranza_grammatura_perc / 100),
                        maggiorazione + tolleranza_maggiorazione_perc
peso_min = formula con  base − tolleranza_formato_mm,  altezza − tolleranza_formato_mm,
                        grammature × (1 − tolleranza_grammatura_perc / 100),
                        maggiorazione − tolleranza_maggiorazione_perc
errore_sopra_g = peso_max − peso_nominale
errore_sotto_g = peso_nominale − peso_min
margine_g      = ceil(max(errore_sopra_g, errore_sotto_g))
```

| Tolleranza | Default | Cosa rappresenta |
|---|---|---|
| `tolleranza_formato_mm` | 1,00 mm | variazione del refilo su base e altezza |
| `tolleranza_grammatura_perc` | 4,00 % | scostamento della carta reale dalla grammatura nominale (interno e copertina) |
| `tolleranza_maggiorazione_perc` | 1,00 punti % | incertezza sulla maggiorazione (2 % ± 1 → tra 1 % e 3 %) |

Prima del `ceil` il valore viene arrotondato a 9 decimali (`CEIL_GUARD_DECIMALS`): così il rumore del double non fa salire di un grammo un margine esattamente intero.

### Scaglioni

Limiti in `config/peso_rivista.php` (`scaglioni`, oggi 250, 350, 500, 1000 g). Una copia ricade nel **primo scaglione il cui limite è maggiore o uguale** al peso (`MagazineWeightCalculator::bracketFor()`):

| Peso | Scaglione |
|---|---|
| 249,9 g | 250 |
| 250,0 g | 250 (limite incluso) |
| 250,01 g | 350 |
| 509 g | 1000 |
| 1000,0 g | 1000 |
| 1000,1 g | Fuori scaglione |

Campi del risultato:

| Campo | Significato |
|---|---|
| `scaglione` | scaglione del peso nominale |
| `scaglionePeggiore` | scaglione di `peso_max` |
| `aRischio` | `scaglionePeggiore ≠ scaglione` |
| `grammiAlLimite` | limite dello scaglione − peso nominale (positivo = spazio residuo) |

Badge nel pannello:
- **verde**: dentro lo scaglione anche nel caso peggiore;
- **giallo**: a rischio, con il messaggio "Rischio di passare allo scaglione X g";
- **rosso**: fuori scaglione.

### Validazioni (`MagazineWeightCalculator`, errori e avvisi)

**Errori bloccanti**: il risultato è `complete = false`, senza pesi e senza eccezioni.
- base o altezza fuori da `WeightLimits` (50–1000 mm) e grammature fuori da `WeightLimits` (30–400 g/m²); quando il calcolo è chiamato senza limiti (solo test unitari) si controlla soltanto che siano > 0;
- pagine interne o di copertina **dispari** ("mezzo foglio");
- tolleranze negative;
- maggiorazione − tolleranza maggiorazione < 0;
- tolleranza formato ≥ formato;
- tolleranza grammatura ≥ 100 %.

**Avvisi**:
- pagine interne non multiple di 4 (segnature);
- bancali senza peso del bancale.

**Parametri mancanti**: risultato `complete = false` con l'elenco `missing`. In UI compare "Parametri peso da configurare". `n_copie` e `n_bancali` non sono obbligatori: senza, manca solo il peso della tiratura.

### Flusso dei parametri

1. **Default della rivista** (`magazines.*`): si impostano in `/riviste/{slug}/modifica` (`App\Livewire\Magazines\Edit`), con la nota "Valgono solo per i nuovi numeri". Le modifiche vanno in cronologia con l'azione `magazine.weight_defaults_changed`. Anche qui c'è il peso a pagina sotto le grammature e un'anteprima, calcolata su "Pagine totali per l'anteprima (non salvate)" (`Magazines\Edit::$previewTotalPages`, proposte dall'ultimo numero della rivista) e confrontata con i default salvati.
2. **Copia alla creazione del numero**: `App\Livewire\Issues\Create::mount()` pre-compila `$weight` con `WeightParameters::formValuesFrom($magazine)`. La sezione "Carta e peso" del form permette di cambiarli; i campi diversi dal default mostrano il badge "modificato" (`modifiedWeightFields()`) e l'anteprima live usa `wire:model.live.debounce.300ms`. Al salvataggio, `Issue::create([... ...WeightParameters::toDatabase($validated['weight'])])`.
3. **Modifica sul numero**: pulsante "Modifica parametri" del pannello Peso → modale → `WeightPanel::saveParams()`. Serve il permesso `update` sull'issue, cioè Admin o Redattore della rivista. Le modifiche vanno in cronologia con l'azione `issue.weight_params_changed`. La modale si apre con la sezione **"Carta"** (grammatura interno, grammatura copertina, pagine copertina), con sotto ogni grammatura il peso a pagina e il totale della sezione. Tutti i campi aggiornano con debounce 300 ms un'**anteprima prima del salvataggio** (`WeightPreview::fromForm(..., saved: $issue->weight())`): peso copia ± margine, scaglione e differenza dal valore salvato (es. "−60,1 g rispetto al valore salvato (510,7 g), passa da scaglione 1.000 g a 500 g."). I campi diversi dal default della rivista hanno il badge "modificato"; "Ripristina default rivista" (`WeightPanel::restoreMagazineDefaults()`) ricarica nella modale formato, carta, maggiorazione e tolleranze attuali della rivista, senza salvare e senza toccare tiratura e peso reale. **Il salvataggio scrive solo sul numero, mai sui default della rivista.** Pagine copertina: numero pari tra 2 e 12 (`config/peso_rivista.php` → `pagine_copertina`).
4. **Ricalcolo quando cambiano le pagine**: il pannello ricalcola sempre da `total_pages` e si ri-renderizza su tre eventi:
   - `IssuePageCountUpdated` via Reverb, per le modifiche di altri utenti;
   - l'evento Livewire `issue-page-count-changed`, emesso da `Grid::resizePages()` (`app/Livewire/Timone/Grid.php:341`) per il proprio client;
   - lo stesso evento emesso da `Grid::checkForUpdates()` (`:1150`), cioè dal polling di fallback senza Reverb.

**Regola: cambiare i default della rivista non tocca i numeri già creati.** Il numero ha una **copia** dei parametri e `Issue::weightInput()` legge solo le colonne dell'issue. Motivo: se cambia la carta della testata, i numeri già usciti non devono cambiare peso. È coperto da `tests/Feature/Livewire/IssueWeightTest.php` ("changing the magazine defaults does not change the weight of an existing issue").

### Calibrazione con il peso reale

1. Dopo la stampa, si pesa una copia e si inserisce il valore in "Peso reale misurato (g)" nella modale del pannello (`issues.peso_reale_g`).
2. Il pannello mostra lo scostamento in g e in % rispetto al teorico, più la **maggiorazione suggerita**:
   `maggiorazione_suggerita = (peso_reale_g / (peso_interno_g + peso_copertina_g) − 1) × 100`
   cioè la maggiorazione che avrebbe dato esattamente il peso reale (`MagazineWeightCalculator::suggestedSurcharge()`).
3. Il pulsante "Copia nei default della rivista" chiede conferma (`wire:confirm`) e scrive la maggiorazione **solo nei default della rivista**, arrotondata a 2 decimali (`WeightPanel::applySuggestedSurcharge()`, solo per chi può modificare la rivista, cioè l'Admin). Non modifica mai il numero corrente né gli altri numeri.

**Perché non si applica da sola**: una singola pesata può essere sbagliata (copia anomala, bilancia, carta di un lotto diverso). La decisione resta a una persona, e vale solo per i numeri futuri.

### Simulazione +/- pagine

Il campo "Simula +/- pagine interne" chiama `MagazineWeightCalculator::simulate()` e mostra peso e scaglione con N pagine in più o in meno. È un calcolo puro, non scrive su database.

`addablePages()` indica quante pagine si possono ancora aggiungere restando nello scaglione attuale:
- a passi di 2 pagine (un foglio);
- sia sul peso nominale sia sul peso massimo (margine compreso);
- `null` se il numero è già fuori scaglione.

### Casi di verifica (foglio Excel)

Tolleranze di default (1 mm, 4 %, 1 punto). Gli stessi valori sono in `tests/Unit/MagazineWeightCalculatorTest.php`.

| Caso | Formato | Interno pag. / g | Cop. pag. / g | Copie | Bancali | Interno g | Copertina g | Copia g | Netto kg | Lordo kg | ± g | Scaglione |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| NM | 210 × 270 mm | 208 / 80 | 6 / 170 | 8200 | 5 | 471,744 | 28,917 | 510,67422 | 4187,528604 | 4287,528604 | 31 | 1000 (peggiore 1000) |
| GL | 210 × 297 mm | 80 / 80 | 4 / 170 | 6200 | 5 | 199,584 | 21,2058 | 225,205596 | 1396,2746952 | 1496,2746952 | 14 | 250 (peggiore 250) |
| F&C | 210 × 297 mm | 112 / 80 | 4 / 170 | 9500 | 5 | 279,4176 | 21,2058 | 306,635868 | 2913,040746 | 3013,040746 | 19 | 350 (peggiore 350) |

Esempio a mano, GL:
- area = 21 × 29,7 = 623,7 cm²
- interno = 623,7 × 80 / 10000 × 40 = 199,584 g
- copertina = 623,7 × 170 / 10000 × 2 = 21,2058 g
- copia = 220,7898 × 1,02 = 225,205596 g
- netto = 225,205596 × 6200 / 1000 = 1396,2746952 kg
- lordo = 1396,2746952 + 5 × 20 = 1496,2746952 kg

Nel timone, NM è un numero con `total_pages` = 208 (pagine interne) e la bandella (6 pagine di copertina). Prima della Fase 2 lo stesso numero si scriveva come 214 pagine totali.

### File coinvolti, in ordine di esecuzione

1. `config/peso_rivista.php`: scaglioni, intervallo grammature, limiti pagine copertina.
2. `app/Livewire/Magazines/Edit.php` + `resources/views/livewire/magazines/edit.blade.php`: default della rivista.
3. `app/Livewire/Issues/Create.php` + `resources/views/livewire/issues/create.blade.php`: copia dei default, anteprima.
4. `app/Models/Issue.php` → `weightInput()` / `weight()`: costruisce l'input dalle colonne dell'issue e da `total_pages`.
5. `app/Support/Weight/MagazineWeightInput.php` (DTO readonly) → `MagazineWeightCalculator.php` → `MagazineWeightResult.php` (DTO readonly).
6. `app/Support/Weight/WeightParameters.php`: elenco campi, etichette, regole e messaggi di validazione, normalizzazione della virgola, campi "modificati", condivisi dai tre form. `WeightLimits.php` + `ParameterRange.php`: limiti di grammature e formato (config) con i messaggi, condivisi da validazione e calcolo. `WeightPreview.php` + `PaperLine.php`: anteprima dei form (peso a pagina, differenza dal salvato).
7. `app/Livewire/Timone/WeightPanel.php` + `resources/views/livewire/timone/weight-panel.blade.php`: pannello, modale, calibrazione, simulazione. Montato in `resources/views/livewire/issues/show.blade.php`.
8. `resources/views/components/weight-summary.blade.php`, `weight-field.blade.php` (campo + riga peso a pagina) e `weight-preview.blade.php` (anteprima + differenza): componenti Blade condivisi. `app/Support/Weight/WeightFormatter.php`: formattazione.
9. `app/Livewire/Timone/Grid.php:341` e `:1150`: evento `issue-page-count-changed`.
