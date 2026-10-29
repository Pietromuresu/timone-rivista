# 02 — Architettura

[← Indice](README.md) · Vedi anche: [03-dominio-e-flussi.md](03-dominio-e-flussi.md), [04-database.md](04-database.md)

## Struttura cartelle (commentata)

```text
app/
├── Console/Commands/      4 comandi artisan custom (vedi 08-job-e-scheduler.md)
├── Enums/                 13 enum PHP nativi backed-string: stati, ruoli, formati ADV (vedi 12-glossario.md)
├── Events/                13 eventi realtime, tutti ShouldBroadcastNow su PresenceChannel issue.{id}
├── Http/
│   ├── Controllers/       Controller "classici": export PDF/CSV, stream file, manuale, profilo
│   │   └── Auth/          Scaffold Laravel Breeze (login, reset password, verifica email). Registrazione rimossa
│   └── Requests/          LoginRequest, ProfileUpdateRequest (Breeze)
├── Jobs/                  Generazione miniature PDF/immagini (in coda)
├── Livewire/              Le schermate dell'app (componenti Livewire 3)
│   ├── Magazines/         Index (dashboard), Show, Create, Edit
│   ├── Issues/            Show (contenitore del timone), Create
│   ├── Timone/            Grid (il timone vero e proprio, copertine comprese), ContentCreate,
│   │                      AdReservations, ActivityLogPanel, PageFileHistory, WeightPanel (pannello Peso)
│   └── Users/             Index, Create, Edit (solo Admin)
├── Mail/                  IssueDeadlineReminder (mail di sollecito scadenza stampa)
├── Models/                14 modelli Eloquent (vedi 04-database.md)
├── Observers/             IssueObserver: crea le pagine bianche alla creazione di un numero
├── Policies/              MagazinePolicy, IssuePolicy, UserPolicy (auto-discovery)
├── Providers/             AppServiceProvider: registra l'observer, forza root URL da APP_URL
├── Support/               classi di logica di dominio "pura" (statiche, testabili senza Livewire); PageGrid/ = griglia 6×6 della pagina (revisione 2, Fase 2)
│   └── Weight/            Calcolo peso rivista: calculator puro, DTO readonly di input e risultato, parametri, limiti di grammature e formato (WeightLimits/ParameterRange), anteprima dei form (WeightPreview/PaperLine), formattazione
└── View/Components/       AppLayout, GuestLayout (Breeze)
bootstrap/app.php          Routing, trustProxies('*'), health check /up
config/timone.php          Configurazione specifica del progetto (2 chiavi)
config/peso_rivista.php    Scaglioni di peso per il calcolo peso rivista
database/migrations/       35 migration (vedi 04-database.md)
database/seeders/          DatabaseSeeder: dataset demo (NON usare in produzione)
database/factories/        Factory per tutti i modelli di dominio
docker/                    nginx/default.conf, php/{opcache,uploads}.ini, mysql/backup.sh
lang/it/                   Traduzioni italiane di auth/passwords/validation
resources/views/           Blade: layouts, livewire/*, exports/* (template PDF), emails/*, documentation/*
resources/js/              app.js (direttive Alpine, Sortable, presence), echo.js (client Reverb), bootstrap.js (axios)
resources/css/app.css      Tailwind
routes/web.php             Tutte le rotte applicative (non c'è routes/api.php)
routes/auth.php            Rotte di autenticazione (Breeze, senza /register)
routes/channels.php        Autorizzazione dei canali broadcast
routes/console.php         Scheduler
tests/                     Pest: Unit/ (logica App\Support), Feature/ (HTTP, Livewire, Jobs, Console)
setup.sh                   Avvio a un comando via Docker (vedi 01-setup-locale.md)
setup-old.sh               Versione precedente di setup.sh, non referenziata da nulla (codice morto)
CLAUDE_PROMPTS/            Specifica originale e prompt di lavoro usati durante lo sviluppo
```

## Pattern usati

1. **Livewire come livello di orchestrazione.** Ogni schermata è un componente Livewire (`routes/web.php` mappa le rotte direttamente su classi Livewire full-page). Il componente valida l'input, chiama `authorize()` e poi una classe `App\Support`, salva, registra l'attività e invia il broadcast. Non ci sono Service container, Repository o Action.
2. **`App\Support`: logica pura statica.** Calcoli e regole di dominio stanno in classi con metodi `static`, senza stato. Molte non toccano Eloquent e sono unit-testate in `tests/Unit/`. Esempi:
   - `PageReorderer` (nuove posizioni dopo un drag)
   - `Twelfths` (unità "dodicesimo di pagina" e unica formattazione in frazioni) e `PageCapacity` (capienza 12 per pagina, vuota/parziale/piena, messaggio "non ci sta") — dalla Fase 3 (2026-10-27) al posto di `PagePercentageAllocator`
   - `AdLoadCalculator` (carico pubblicitario, in dodicesimi)
   - `PageOccupancy` (value object dell'occupazione totale in dodicesimi, con la distribuzione 12, 12, 6…)
   - `AdPlacement\AdPlacementService` (regola di posizionamento delle pubblicità previste, Fase 5), `AdPlacement\PlaceholderAdCreator`
   - `PageLayoutPlanner` (disposizione proporzionale dei contenuti nella card, Fase 7a: rettangoli in % da dodicesimi + orientamento)
   - `IssueSummary` (barra di riepilogo, Fase 6: compone PageCapacity, AdLoadCalculator, peso e segnaposto senza calcoli propri)
   - `ContentCreator` / `ContentPlacer` (creazione e posizionamento di un contenuto), `ContentUpdater` (modifica, Fase 4), `ContentFormRules` (regole condivise dai moduli di creazione e modifica)

   La scelta "statico, nessuna interfaccia" è voluta e motivata in `CLAUDE.md` (regola 3) e in `HANDOFF.md`.
3. **Enum come fonte di verità per stati e varianti.** Etichette, colori e classi Tailwind stanno negli enum (es. `PageStatus::colorClasses()`, `AdFormat`).
4. **Policy di Laravel per l'autorizzazione** (`app/Policies`, auto-discovery), chiamate con `$this->authorize(...)` nei componenti e con `Gate::authorize(...)` nei controller.
5. **Eventi broadcast + listener Livewire.** Le mutazioni sul timone emettono un evento `ShouldBroadcastNow` sul canale presence `issue.{id}`. Gli altri client lo ricevono con `#[On('echo-presence:issue.{issue.id},.NomeEvento')]` in `app/Livewire/Timone/Grid.php` e si ri-renderizzano.
6. **Activity log applicativo.** `App\Support\ActivityLogger::log()` scrive su `activity_logs`. Non usa pacchetti esterni (niente spatie/activitylog).

## Flusso di una richiesta tipica

### Caricamento pagina (es. apertura del timone di un numero)

```text
Browser GET /riviste/{slug}/numeri/{issue}
 └─ nginx (docker/nginx/default.conf)  → fastcgi app:9000 → public/index.php
     └─ bootstrap/app.php (middleware web + trustProxies)
         └─ routes/web.php: middleware auth + verified, scopeBindings (issue deve appartenere al magazine)
             └─ App\Livewire\Issues\Show::mount()  → $this->authorize('view', $issue)  (Show.php:25)
                 └─ view livewire/issues/show.blade.php monta i componenti annidati:
                    Timone\Grid, Timone\ContentCreate, Timone\AdReservations, ...
                     └─ Grid::render() (Grid.php:2003): carica tutte le pagine con eager loading,
                        calcola spread, carico pubblicitario, avvisi automatici
```

### Interazione (es. drag&drop di una pagina)

```text
Browser (Sortable.js, direttiva x-sortable in resources/js/app.js)
 └─ $wire.movePage(pageId, toPosition)  → POST /livewire/update (CSRF gestito da Livewire)
     └─ Grid::movePage() (Grid.php:476)
         ├─ authorize('update', issue)
         ├─ controlli: pagina dell'issue? bloccata? gruppo collegato?
         ├─ DB::transaction + lockForUpdate sull'issue + confronto reorder_version (lock ottimistico)
         ├─ App\Support\PageReorderer::move() calcola le nuove posizioni
         ├─ doppio UPDATE (offset temporaneo) per rispettare unique(issue_id, position)
         ├─ PageReorderLog::create, increment reorder_version
         ├─ ActivityLogger::log   (le copertine non entrano mai qui: Grid le rifiuta)
         ├─ broadcast(new PageMoved(...))->toOthers()   ← sincrono verso Reverb
         └─ Issue::markPagesUpdated()  (aggiorna pages_updated_at per il polling di fallback)
 └─ Gli altri browser: Reverb → Echo → Livewire listener onPageMoved() → re-render
```

## Diagramma dei componenti

```text
                    ┌──────────────┐
  Browser ──HTTP──▶ │ nginx :80    │──fastcgi──▶ ┌──────────────┐      ┌───────────┐
     │              └──────────────┘             │ app (FPM)    │─────▶│ mysql 8.4 │
     │                                           │ Laravel 12   │      └───────────┘
     │                                           │ Livewire 3   │─────▶┌───────────┐
     │                                           └──────┬───────┘      │ redis 7   │ cache + queue
     │                                                  │ broadcast()  └─────▲─────┘
     │  WebSocket (ws://host:8080)               ┌──────▼───────┐            │
     └─────────────────────────────────────────▶ │ reverb :8080 │            │
                                                 └──────────────┘            │
                     ┌──────────────┐   queue:work redis                     │
                     │ worker       │────────────────────────────────────────┘
                     │ miniature,   │   Imagick + Ghostscript → storage/app (volume storage_data)
                     │ mail         │   SMTP (MAIL_*) — di default "log"
                     └──────────────┘
                     ┌──────────────┐
                     │ scheduler    │ schedule:work → timone:send-deadline-reminders (07:30 UTC)
                     └──────────────┘
                     ┌──────────────┐
                     │ adminer :8081│ GUI DB (sempre avviato, anche in "produzione")
                     └──────────────┘
```

`app`, `worker`, `reverb` e `scheduler` usano **la stessa immagine** (stage `app` del `Dockerfile`) con comandi diversi. `nginx` è uno stage separato che contiene solo `public/` con gli asset buildati.

## Decisioni architetturali non ovvie (e perché)

Fonte principale: `HANDOFF.md`, sezione "Decisioni architetturali da ricordare" e voci di sessione.

| Decisione | Perché | Dove |
|---|---|---|
| Eventi `ShouldBroadcastNow` (sincroni) invece di `ShouldBroadcast` (in coda) | Il realtime funziona senza un worker. **Effetto collaterale**: se Reverb non è raggiungibile, l'azione dell'utente fallisce con eccezione e nelle azioni di massa resta applicata a metà | `app/Events/*`, `HANDOFF.md` §Decisioni (ultima voce) |
| Canale presence registrato come `issue.{issueId}`, senza prefisso `presence-` | È Echo ad aggiungere il prefisso: registrarlo con `presence-` bloccava tutti | `routes/channels.php` |
| Fallback a polling `wire:poll.1s="checkForUpdates"` solo quando il WebSocket è disconnesso e nessun drag è in corso | Resilienza se Reverb è giù; evita morph a metà drag | `resources/views/livewire/timone/grid.blade.php:94-96`, `Grid::checkForUpdates()` (`Grid.php:1135`) |
| Lock ottimistico con `issues.reorder_version` + `lockForUpdate` | Due utenti che riordinano in contemporanea: chi arriva secondo riceve un messaggio, la sua modifica non viene applicata | `Grid::movePage()` |
| Doppio UPDATE con offset temporaneo nel riordino | Vincolo `unique(issue_id, position)` su `pages` | `Grid.php` dentro `movePage` |
| `Grid.php` lasciato monolitico (2151 righe) | Split giudicato troppo costoso: rompe le firme usate da centinaia di test e moltiplica le query di `render()`. La logica pura è comunque estratta in `App\Support` | `HANDOFF.md` §Decisioni (prima voce) |
| Livewire fissato a 3.x | Lo stack richiesto usa la semantica di Livewire 3 | `composer.json` |
| Non si importa `alpinejs` a parte | Livewire 3 include già Alpine: due istanze rompevano `$wire` e il drag&drop | commento in testa a `resources/js/timone-alpine.js` |
| Store/direttive Alpine in `resources/js/timone-alpine.js`, importato **per primo** da `app.js` (2026-10-26) | Gli `import` ES girano prima del corpo del modulo: un'eccezione in `echo.js` impediva di registrare gli store (`$store.pagePresence` undefined). `echo.js` ora non lancia più (try/catch, polling come ripiego) | `resources/js/app.js`, `resources/js/echo.js` |
| Animazione dei gruppi con il plugin **MultiDrag** di SortableJS + FLIP con la Web Animations API (2026-10-26) | MultiDrag è nello stesso pacchetto `sortablejs` (nessuna dipendenza nuova); il server resta l'unica fonte dell'ordine finale, il JS anima solo. La posizione di drop si calcola dall'ordine reale dei `[data-page-id]`, non da `evt.newIndex` (che contava anche gli spacer della vista doppia) | `resources/js/timone-alpine.js`, `Grid::nudgePage()`, `PageReorderer::nudgeTarget()` |
| Zoom S/M/L in `localStorage` (store `timoneZoom`), variabili CSS su `<html>` | Preferenza personale del browser, non stato condiviso: niente Livewire/database; su `<html>` perché il morph di Livewire non può toccarle | `resources/js/timone-alpine.js`, `grid.blade.php` |
| Eventi Livewire **locali** `content-created` / `content-list-changed` tra componenti della stessa pagina (2026-10-26) | I broadcast sono `toOthers()`: chi creava un contenuto non lo vedeva in `Grid` (altro componente) senza ricaricare. Per gli altri utenti: `ContentListChanged` (broadcast) + `issues.pages_updated_at` (polling) | `ContentCreate`, `Grid`, `AdReservations`, `app/Events/ContentListChanged.php` |
| `URL::forceRootUrl(APP_URL)` | Le URL generate (miniature) usavano l'header Host sbagliato dietro proxy | `app/Providers/AppServiceProvider.php` |
| Copertine come righe di `pages` con `cover_slot` e `position` NULL, escluse da `Issue::pages()` (Fase 2, 2026-10-26) | Riusano contenuti, anteprime, stato e blocco delle pagine senza duplicare nulla, ma restano fuori da sequenza, riordino e conteggi: l'esclusione sta alla fonte (la relazione), non sparsa nei chiamanti. Sostituisce la scelta "A" del 2026-09-01 (copertine dentro `total_pages`, solo etichetta diversa) | `app/Enums/CoverSlot.php`, `Issue::pages()/covers()/allPages()`, `App\Support\IssueCovers` |
| URL firmate (upload temporaneo Livewire) verificate su `APP_URL` (versione di produzione, nel repository dal 2026-10-28) | Dietro il proxy la richiesta arriva con un host interno: firmata su `APP_URL` e verificata sull'host in arrivo, ogni upload falliva con 401. In produzione la soluzione adottata ricostruisce l'URL da verificare da `APP_URL` (macro `alternateHasCorrectSignature`, `alternateHasValidSignature`, `Request::hasValidSignature`); il binding del 2026-10-26 a `RequestRootSignedUploadUrl` (firma sull'indirizzo reale) è commentato perché in produzione non funzionava | `AppServiceProvider::register()` (file protetto, CLAUDE.md §4), test `UploadDiagnosticsTest` |
| Proprietà numeriche Livewire `?int` con default `null` + trait `NormalizesEmptyNumericInput` (2026-10-26) | Livewire "unset"-ta una proprietà `int` che riceve `''` e non riassegna un `null` in idratazione | `app/Livewire/Concerns/NormalizesEmptyNumericInput.php` |
| …ma un input numerico **live** usa una proprietà `string` + parsing (revisione 2, 2026-10-27) | Con `int` il server rimanda il valore convertito, Livewire lo tratta come una modifica del server e riscrive il campo mentre si digita (cifre perse, "focus perso") | `Issues\Create::$total_pages` / `totalPages()`, `resources/views/components/live-field.blade.php` |
| Comandi dei blocchi della pagina in un menu «⋯» teleportato nel `<body>` (revisione 2, 2026-10-27) | Dentro i blocchi (overflow-hidden) i comandi dei contenuti frazionari venivano tagliati. `@teleport` + `x-anchor` sono già nel bundle di Livewire; `wire:*`/`$wire` funzionano attraverso il teleport | `partials/page-card.blade.php`, `partials/content-controls.blade.php` |
| Manuale utente a pagine (2026-10-29) | Un file per argomento in `resources/manuale/` (front matter + blocchi `:::nome`), letto a ogni richiesta da `ManualRepository` e convertito da `ManualRenderer::renderPage()` (stesso escape dell'HTML di prima; l'HTML dei blocchi lo scrive il renderer). Una pagina per indirizzo (`/manuale/{slug}`), `/manuale` d'ingresso; ancore vecchie con uno script e la mappa `LegacyAnchors`. `MANUALE_UTENTE.md` resta come file generato perché `config/app.php` (protetto) punta lì e README/docs/CLAUDE.md lo citano. Front matter con un parser minimo: `symfony/yaml` è solo dipendenza di sviluppo | `app/Support/Manual/*`, `DocumentationController`, `documentation/*.blade.php` |
| Ricerca nel manuale tutta nel browser (2026-10-29) | Indice JSON da `/manuale/indice.json` (`ManualSearchIndex`: un documento per pagina e per sezione `##`, cache con l'impronta dei file), MiniSearch nel browser con la normalizzazione italiana di `ItalianText` (regole spedite nel JSON, algoritmo uguale in `manual-search-core.js`, stessi casi di test nei due linguaggi), sinonimi in `resources/manuale/sinonimi.yml`. Punteggio: titoli e parole chiave pesano di più; i sinonimi valgono meno della parola scritta (0,5/√n; 0,8 se la parola scritta nel manuale non c'è); chi trova tutte le parole cercate batte chi ne trova una (punteggio × copertura²). La logica è un modulo puro, provato anche con node nella suite | `app/Support/Manual/{ItalianText,ManualSearchIndex,ManualSynonyms}.php`, `resources/js/manual-search*.js`, `tests/js/manual-search-check.mjs` |
| Griglia a doppia pagina a colonne fisse (2026-10-29) | Coppie dalla posizione (`PageSpreadBuilder::layout()`/`side()`, mai salvate). Markup: page-card e slot vuoti figli diretti del contenitore Sortable (vincolo del 2026-09-02). CSS (`.timone-spreads` in `app.css`): ogni coppia = cinque colonne (½ spazio, sinistra, 2px, destra, ½ spazio), ogni figlio va nella sua colonna in base all'ordine (`:nth-child`), N coppie per riga con container query e zoom. Scartati dopo prove con drag reale: margini dal lato per ordine (spostamenti a catena sotto il puntatore) e margini scritti dal server (coppie scollate durante il drag) | `PageSpreadBuilder`, `grid.blade.php`, `app.css` |
| Segnature calcolate, mai salvate (2026-10-29) | `App\Support\Signatures\SignaturePlan` (pura) riceve pagine interne, facciate di copertina, dimensione e «copertine incluse» (questi due dalla rivista) e dà conteggio, scomposizione, avviso, suggerimento e segnatura di ogni pagina. Indipendente dal peso: le due classi leggono le stesse quantità (`total_pages`, `coverPageCount()`) ma cambiano per motivi diversi. Confini sulla griglia con attributi `data-signature`/`data-signature-start` e CSS (`app.css`), accesi da `html[data-signatures]` (store Alpine `timoneView`): l'interruttore non fa richieste | `SignaturePlan`, `summary-bar`, `page-card`, `page-row`, `Magazines\Edit` |
| Commenti per pagina con segnale di polling separato (2026-10-29) | Il commento è legato a `pages.id` (segue la pagina negli spostamenti). Scritture in `App\Support\Comments\PageCommentWriter`, letture e stato letto in `PageCommentReader`, destinatari/menzioni in `CommentRecipients`, pagine eliminate in `DeletedPageComments`; mail `App\Notifications\PageCommentPosted` in coda. Nessun broadcast (scelta dell'utente): ogni scrittura aggiorna `issues.comments_updated_at` (non `pages_updated_at`, che farebbe ridisegnare la griglia a tutti); `Grid::checkForUpdates()` (senza Reverb) e `Grid::checkForCommentUpdates()` (con Reverb: sempre attivo) mandano al browser i soli contatori (evento `comment-counts` → store Alpine `pageComments`) con `skipRender()`. Il pannello è un componente a sé, `App\Livewire\Timone\PageComments` | `app/Support/Comments/*`, `PageCommentPolicy`, `Grid`, `page-comments.blade.php` |
| Annulla/ripeti con pattern a comandi e fotografie (2026-10-28) | Ogni azione annullabile passa da `TimelineActionService::run()`: blocca la riga del numero, fotografa le tabelle del timone (`TimelineSnapshot`: pages, page_content, page_groups, contents con articoli/pubblicità/righe dei file), esegue l'azione esistente senza cambiarla, rifotografa e salva in `timone_azioni` solo righe e campi cambiati (`TimelineDiff`). Annulla/ripeti riapplicano «prima»/«dopo» con un solo applicatore generico (`TimelineApplier`: conflitti campo per campo, ordine delle tabelle, posizioni/slot in due passi, contenuti nel cestino, controllo di spazio e griglia) invece di un'azione inversa per tipo; permessi in `TimelineAuthorizer` (stesse regole di `TimoneAbilities`). Creazione/modifica con file in modalità non transazionale: i job delle miniature devono trovare le righe scritte | `app/Support/Timeline/*`, `App\Models\TimelineAction`, `Grid::recordStep()/undo()/redo()` |
| Pannello contenuti fisso, una sola istanza (2026-10-28) | La sezione «Contenuti da assegnare» nel flusso costringeva a risalire. Ora è un `<aside>` fixed in `grid.blade.php` (markup di `Timone\Grid`, come prima: nessun componente nuovo) aperto dal bottone «Contenuti» della fascia; stato aperto/larghezza nello store Alpine `contentPanel` (localStorage). ≥1440 px colonna a destra sotto la fascia (`top: var(--app-sticky-height)`) e `main` con `padding-right` (CSS su `html[data-content-panel="docked"]`); sotto, tendina max 40vh che fa da bordo alto dell'auto-scroll (`data-sticky-top` solo in tendina). Dentro anche `ContentCreate` e `BulkUpload` (spostati). Filtri/ordine puri in `App\Support\ContentPanelFilter`; assegnazione unica `Grid::placeUnassignedContent()` (drag, `assignContentToSelection()`, `assignContentToTarget()`) → `ContentPlacer` | `grid.blade.php`, `partials/content-panel-item.blade.php`, `resources/js/timone-alpine.js`, `resources/css/app.css` |
| Un solo contenitore sticky per header e fascia del timone (2026-10-28) | Prima due sticky (header z-40, fascia z-30 con `top: var(--app-header-height)`) e il pannello della selezione nel flusso della pagina, irraggiungibile in fondo al timone. Ora `layouts/app.blade.php` ha un contenitore `sticky top-0 z-40` (`data-testid="app-sticky"`, `data-sticky-top`) con header e slot `#timone-sticky-slot`; `Timone\Grid` ci porta la fascia (riepilogo, comandi, riga della selezione) con `@teleport`. L'altezza si misura SOLO lì (ResizeObserver → `--app-sticky-height`, 0 quando non è sticky: sotto i 640 px col timone) e la usano `scroll-padding-top` (`resources/css/app.css`) e l'auto-scroll del drag (`stickyTopEdge()` sul fondo di `data-sticky-top`). z-index: contenitore 40, modali/dropdown/toast 50, popover «⋯» dei contenuti 60 (verificato) | `layouts/app.blade.php`, `grid.blade.php`, `partials/selection-bar.blade.php`, `App\Support\PageSelectionActions` |
| Auto-scroll del drag fatto da noi su `dragover`, non da Sortable (revisione 2, 2026-10-27) | Con il drag nativo Sortable lascia la finestra allo scroll del browser, che sotto la fascia sticky non partiva; contenuti e copertine non passano da Sortable | `resources/js/drag-autoscroll.js`, `data-sticky-top` |
| Limite di upload in una sola chiave `timone.upload_max_kb` (revisione 2, 2026-10-27) | La regola del modulo (20 MB) era più stretta di PHP/nginx/Livewire (100 MB) e nessuno se ne accorgeva | `config/timone.php`, `ContentFormRules::maxKilobytes()`, `config/livewire.php` |
| Griglia 6×6 con rettangoli salvati su `page_content.grid_*` (revisione 2, Fase 2.3, 2026-10-27; sostituisce l'impilamento calcolato della Fase 7a) | Le frecce in 4 direzioni richiedono una posizione persistente; 6×6 rappresenta esattamente tutti i formati (1 dodicesimo = 3 celle). Calcolo puro (`PageGridPlanner`) deterministico per i contenuti senza rettangolo, un solo punto che scrive (`PageGridPositions`). 3/4, 7/12, 11/12 come forma a L fluida (scelta dell'utente) | `app/Support/PageGrid/`, `Page::layout()` |
| Ritaglio delle anteprime solo in CSS (revisione 2, Fase 2.4) | Le proporzioni del file si misurano una volta (dalla miniatura) e il ritaglio dipende dalla posizione, che cambia spesso: rigenerare immagini a ogni spostamento sarebbe lento e inutile | `App\Support\PageGrid\ThumbnailFit`, `content_files.aspect_ratio` |
| Segnaposto come colonna memorizzata + regola unica (revisione 2, Fase 3.1) | La regola («senza cliente e senza file») serve a cruscotto e filtri su tanti contenuti: calcolarla al volo richiederebbe file e inserzionista per ognuno. Una sola classe la calcola e la scrive | `App\Support\AdPlaceholder`, `contents.is_placeholder` |
| Caricamento multiplo: coda nel browser, un file per richiesta (revisione 2, Fase 3.2) | Un lotto unico supera `post_max_size`/nginx già con due PDF di stampa e un file rifiutato farebbe fallire tutti; l'azione Livewire ritorna l'esito per file invece di errori di componente | `App\Livewire\Timone\BulkUpload`, `Alpine.data('bulkUpload')`, `App\Support\BulkContentImporter` |
| Permessi del timone in un oggetto (`TimoneAbilities`) oltre alle policy (revisione 2, Fase 4.1) | La vista chiede "posso spostare questo?" per decine di elementi a render: accesso e ruolo si leggono una volta, le domande sono pure; le policy usano le stesse regole, quindi bottone e 403 non divergono | `App\Support\Permissions\TimoneAbilities`, `IssuePolicy`, `ContentPolicy` |
| Eliminazioni definitive con conferma in due passaggi e target intercambiabili (revisione 2, Fase 4.2–4.3) | Scelta dell'utente: niente cestino. Un componente unico (`Admin\ConfirmDeletion`) parla con l'interfaccia `DeletionTarget` (numero, rivista, utente); i file si cancellano dopo il commit | `App\Support\Deletion\*`, `App\Jobs\DeleteStoredFiles` |
| PDF sfogliabile con FPDI + TCPDF in un job (revisione 2, Fase 5, scelta dell'utente tra 4 proposte) | Pagine dei PDF importate vettoriali (qualità di stampa, testo selezionabile), ritaglio vero con clipping, layout a doppia pagina e segnalibri già nella libreria; Ghostscript (già nell'immagine) per i PDF 1.5+ compressi e la bassa risoluzione. In coda perché i sorgenti pesano centinaia di MB | `App\Support\MagazineExport\*`, `App\Jobs\GenerateMagazinePdf` |
| `trustProxies(at: '*')` | "Intranet interna" | `bootstrap/app.php` |
| File su disco `local` (`storage/app/private`), serviti solo tramite controller con `Gate::authorize('view', issue)` | I PDF non devono essere pubblici | `PageFileController`, `ContentFileController` |
| `VITE_REVERB_*` passate come build arg Docker | `.env` è escluso dal contesto di build: senza i build arg il bundle JS avrebbe `undefined` | `Dockerfile` stage 1, `docker-compose.yml` |
| `bootstrap/cache/*.php` escluso dall'immagine e rigenerato con `package:discover` | Il manifest generato con le dev-dependency rompe l'immagine `--no-dev` | `.dockerignore`, `Dockerfile` |
| Ogni servizio compose ha `build.target` esplicito | L'ultimo stage del Dockerfile è nginx: senza target, `app` diventava nginx | `docker-compose.yml`, `HANDOFF.md` §Decisioni |
| `display_errors=Off` in PHP | Un warning di `post_max_size` stampato in HTML corrompeva il JSON di Livewire durante l'upload | `docker/php/uploads.ini` |
| Occupazione di pagina obbligatoria; nessun fallback "1 pagina" | Il fallback silenzioso era la causa del bug "PDF multipagina su una sola pagina" | `App\Support\ContentCreator`, `ContentPlacer`, migration `2026_10_12_*` |
| Occupazione in **dodicesimi di pagina interi** (2026-10-27), non percentuali decimali | 12 è il minimo comune multiplo di 1/4, 1/3, 1/2, 2/3, 3/4: somme e confronti esatti (1/3 × 3 = pagina piena, prima 99,9%). Colonne vecchie lasciate, non più lette | `App\Support\Twelfths`, `PageCapacity`, `PageOccupancy`, migration `2026_10_27_100000_*` |
| `fakerphp/faker` in `require` (non dev) | Il seeder demo deve funzionare anche nell'immagine `--no-dev` | `composer.json`, `HANDOFF.md` §Decisioni |
| Ruoli come colonna `users.role` (enum), non un pacchetto permessi | Semplicità: 4 ruoli fissi | `app/Enums/UserRole.php` |

## Autenticazione e autorizzazione

- **Guard**: `web` (sessione), driver sessione `database` (`.env.example`: `SESSION_DRIVER=database`). Non c'è API, non c'è Sanctum né token.
- **Rotte**: tutto il gruppo applicativo richiede `auth` + `verified` (`routes/web.php:30`). In pratica `verified` **non blocca nessuno**: `User` non implementa `MustVerifyEmail` (l'import è commentato in `app/Models/User.php:5`), e il middleware controlla la verifica solo per i modelli che lo implementano. Le rotte `verify-email` di Breeze esistono ma non sono necessarie.
- **Registrazione pubblica disattivata** (`routes/auth.php`, commento in testa): gli account si creano solo da `/utenti/nuovo` (Admin).
- **Policy** (`app/Policies`):
  - `MagazinePolicy`: `view` se `canAccessMagazine()`; `create`, `update` e `delete` solo Admin.
  - `IssuePolicy`: `view` se ha accesso alla rivista; `create` se Admin o Redattore; `update` (tutto il timone) se ha accesso alla rivista **e** (Admin o Redattore); `workOnAds` (dal 2026-10-28) anche il Commerciale; `movePages` = `update`, oppure Commerciale se nessuna delle pagine che si spostano contiene articoli; `delete` solo Admin.
  - `ContentPolicy` (dal 2026-10-28): `create` (con il tipo), `update`, `move` sul singolo contenuto: Admin/Redattore sempre, Commerciale solo sulle pubblicità.
  - `UserPolicy`: tutto solo Admin; `delete` mai su sé stessi né sull'ultimo admin (con il motivo, `Response::deny`).
  - **Regole in un solo punto**: `UserRole::editsTimone()`/`handlesAds()` (quali ruoli) e `App\Support\Permissions\TimoneAbilities` (cosa su un numero); le policy e le viste del timone (maniglie, frecce, tooltip) leggono da lì.
- **Helper centrali**: `User::isAdmin()` e `User::canAccessMagazine()` (`app/Models/User.php:72-80`). L'Admin vede tutto; gli altri solo le riviste collegate in `magazine_user`.
- **Canale broadcast** `issue.{issueId}`: autorizzato se `canAccessMagazine()` (`routes/channels.php`).
- **Componenti annidati** (`Grid`, `ContentCreate`, `AdReservations`): il loro `mount()` **non** chiama `authorize('view')` e si affida a `Issues\Show::mount()`. È un finding "media" dell'audit interno, non corretto (vedi [11-debito-tecnico.md](11-debito-tecnico.md)).
