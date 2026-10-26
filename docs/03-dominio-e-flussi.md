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
2. `/riviste/{slug}/modifica` → `Magazines\Edit`: soglia pubblicitaria (`ad_threshold_percentage`), `battente_counted_in_total`, redattore di riferimento (`redattore_id`).
3. `/utenti/nuovo` → `Users\Create`: crea l'utente con il ruolo e sincronizza `magazine_user`.

## Flusso 3 — Creazione di un numero

1. `/riviste/{slug}/numeri/nuovo` → `App\Livewire\Issues\Create::save()` (`app/Livewire/Issues/Create.php:56`):
   - `authorize('create', Issue::class)` + `canAccessMagazine`;
   - validazione: `total_pages` da 0 a 2000;
   - `Issue::create([... 'status' => bozza])`.
2. **`App\Observers\IssueObserver::created()`** (registrato in `AppServiceProvider::boot`):
   - inserisce `total_pages` righe in `pages` (tipo `bianca`, stato `da_assegnare`);
   - `PageRoleAssigner::syncCoverRoles()` assegna i ruoli `copertina1`/`copertina4` alla prima e all'ultima pagina.
3. Opzionale "duplica struttura": `Issue::duplicateStructureFrom($source)` (`app/Models/Issue.php:105`) copia solo `content_type` per posizione. **Non** copia i contenuti.
4. Redirect a `issues.show`.

## Flusso 4 — Apertura del timone

`Issues\Show::mount()` (`authorize('view')`) monta:
- `Timone\Grid`: griglia o lista delle pagine, cruscotto pubblicitario, avvisi automatici (`App\Support\AutomaticChecks`), storico;
- `Timone\ContentCreate`: form "+ Nuovo contenuto" e pannello rubriche;
- `Timone\CoverSection`: ruoli copertina e battente;
- `Timone\AdReservations`: pubblicità prenotate e chiusura numero;
- `ActivityLogPanel` e `PageFileHistory`.

Presence: il client si unisce a `presence-issue.{id}` via Echo. La barra "utenti online" e il whisper "chi sta modificando cosa" sono in `resources/js/app.js` (store `pagePresence`).

## Flusso 5 — Creazione di un contenuto (articolo o pubblicità), con o senza file

File coinvolti, in ordine di esecuzione:

1. `resources/views/livewire/timone/content-create.blade.php`: form e drop zone. Il file viene caricato da Livewire su un disco temporaneo (`livewire-tmp`; regole in `config/livewire.php:84`: pdf/jpg/jpeg/png/webp, max 100 MB).
2. `ContentCreate::updatedFile()` (`app/Livewire/Timone/ContentCreate.php:225`): valida e conta le pagine del PDF (`ContentCreator::pdfPageCount()` → `PdfPageMeasurer::pageCount()`, che **richiede Imagick**). Propone l'occupazione uguale al numero di pagine.
3. `ContentCreate::save()` (`:261`):
   - `authorize('update', issue)`;
   - `ContentCreator::resolveOccupancy()`: l'occupazione è **obbligatoria**. Per la pubblicità deriva dal formato; con un PDF multipagina deve coincidere con il numero di pagine.
   - Se il form è stato aperto da una pagina ("📎 Carica materiale", quindi `anchorPageId` valorizzato) e il PDF ha più di una pagina, apre la **modale di conflitto multipagina** (`openMultipageConflict`). Le opzioni sono: sovrascrivere, saltare le pagine occupate, oppure "sposta dopo" (`confirmMultipageUploadWithShift`, che usa `App\Support\PageInserter`).
   - Altrimenti chiama `createContentAndFinish()`.
4. `App\Support\ContentCreator::create()` (`app/Support/ContentCreator.php:135`):
   - in transazione: `Content` + `Article` oppure `Advertisement`;
   - `ActivityLogger::log('content.created')`;
   - per **ogni pagina del PDF**, `storeFile()` salva una **copia completa del file** in `storage/app/private/contents/{content_id}/{uuid}.{ext}`, crea un `ContentFile` (`pdf_page_number` = N) e accoda **`GenerateContentFileThumbnail`**. Conseguenza: un PDF di 64 pagine da 100 MB occupa circa 6,4 GB su disco. Vedi [11-debito-tecnico.md](11-debito-tecnico.md).
5. Se ancorato a una pagina: `ContentPlacer::place()` (vedi flusso 6).
6. Broadcast `ContentCreated` / `ContentFileUploaded` / `ContentAssigned`.
7. Il worker esegue `App\Jobs\GenerateContentFileThumbnail` (vedi [08-job-e-scheduler.md](08-job-e-scheduler.md)):
   - miniatura PNG 150 dpi della pagina `pdf_page_number`;
   - per le pubblicità, controllo del formato (`PdfPageMeasurer::pageSizeMm` + `PdfFormatChecker::matches` con tolleranza `TIMONE_PDF_FORMAT_TOLERANCE_MM` e abbondanza di 3 mm per lato).

## Flusso 6 — Assegnazione di un contenuto a una pagina (drag dal pannello)

1. HTML5 drag & drop nativo dal chip del contenuto alla card della pagina (`resources/js/app.js`), che chiama `$wire.assignContent(contentId, pageId)`.
2. `Grid::assignContent()` (`app/Livewire/Timone/Grid.php:1658`): `authorize('update')`, poi verifica che contenuto e pagina siano dell'issue corrente e che la pagina non sia bloccata.
3. `App\Support\ContentPlacer::place()` (`app/Support/ContentPlacer.php:37`), **tutto o niente**:
   - `pagesSpanned()` pagine consecutive a partire da quella di drop; se il numero finisce prima, restituisce un messaggio;
   - nessuna pagina deve essere bloccata e ognuna deve avere spazio (`PagePercentageAllocator::fits`);
   - in transazione: `ContentPageAttacher::attach()` per ogni pagina (pivot `page_content` con `occupied_percentage` e `content_file_id` della pagina PDF corrispondente) e `syncPageContentType` (editoriale/pubblicità/mista);
   - se le pagine sono 2 o più: `PageGroupLinker::link()` crea un `PageGroup`.
4. Activity log + broadcast `ContentAssigned` (e `PageGroupsChanged`), poi `markPagesUpdated()`.

Correlati: `extendToPage` (`Grid.php:1738`), `updateContentPercentage` (`:1807`), `unassignContent` (`:1854`), `archiveContent`/`deleteUnassignedContent`.

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
