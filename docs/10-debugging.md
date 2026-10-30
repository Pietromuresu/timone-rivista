# 10 — Debugging

[← Indice](README.md) · Vedi anche: [08-job-e-scheduler.md](08-job-e-scheduler.md), [09-deploy.md](09-deploy.md), [11-debito-tecnico.md](11-debito-tecnico.md)

> Prima regola di questo progetto, imparata a caro prezzo (`HANDOFF.md`, sessioni 2026-07-31 e 2026-08-31): **prima di cercare un bug nel codice, verifica che i container stiano davvero eseguendo il codice attuale.** Più volte un "bug" segnalato era uno stack Docker non ricostruito dopo un `git pull`.
> ```bash
> git log -1 --oneline && docker compose ps && docker images | grep timone-rivista   # data di creazione delle immagini vs data dell'ultimo commit
> ```

## Dove sono i log

| Cosa | Dove (Docker) | Come leggerlo |
|---|---|---|
| Log applicativo Laravel (eccezioni, `report()`, mail con `MAIL_MAILER=log`) di `app`, `worker`, `scheduler` | `storage/logs/laravel.log`, **un unico file condiviso** sul volume `storage_logs` | `docker compose exec app tail -f storage/logs/laravel.log` |
| Log Laravel del container `reverb` | `storage/logs/laravel.log` **dentro il container** `reverb`, che **non** monta `storage_logs`: si perde se il container viene ricreato | `docker compose exec reverb tail -f storage/logs/laravel.log` |
| Errori PHP-FPM di basso livello (prima del bootstrap di Laravel, es. `POST Content-Length exceeds the limit`) | stderr del container (`error_log = /proc/self/fd/2`, `docker/php/uploads.ini`) | `docker compose logs -f app` |
| Output del worker (job processati o falliti) | stdout | `docker compose logs -f worker` |
| Output dello scheduler | stdout | `docker compose logs -f scheduler` |
| Connessioni WebSocket | stdout di Reverb | `docker compose logs -f reverb`; per più dettaglio, avvia temporaneamente `php artisan reverb:start --debug` |
| Accessi ed errori HTTP (502, 413, 404) | stdout/stderr di nginx | `docker compose logs -f nginx` |
| MySQL / Redis | stdout | `docker compose logs mysql redis` |
| Job falliti | tabella `failed_jobs` | `php artisan queue:failed` |
| **Audit delle azioni utente** (chi ha spostato cosa e quando) | tabella `activity_logs` (+ `page_reorder_logs`) | Pannello "Cronologia" nel timone, oppure Adminer / tinker: `App\Models\ActivityLog::where('issue_id', X)->latest('created_at')->take(50)->get()` |
| Errori JavaScript (Echo, Alpine, Sortable, Livewire) | **console del browser** dell'utente | DevTools → Console e Network (filtro WS per il WebSocket, `livewire/update` per le richieste Livewire) |

- **In locale senza Docker**: `storage/logs/laravel.log`, oppure `php artisan pail` (incluso in `composer dev`). Pail **non** è disponibile nell'immagine di produzione (dev-dependency).
- **Rotazione**: con `LOG_STACK=single`, `laravel.log` cresce senza limite. Per la produzione valuta `LOG_STACK=daily` (+ `LOG_DAILY_DAYS`). Al 2026-10-26 il file locale di sviluppo è di circa 450 KB.
- **Monitoraggio esterno** (Sentry, uptime, alert): **nessuno** nel repo (niente Telescope, Sentry, Horizon, Debugbar). ⚠️ DA VERIFICARE se esiste qualcosa lato server.

## Attivare il debug in sicurezza

- **Mai `APP_DEBUG=true` in produzione**: la pagina d'errore espone variabili d'ambiente e stack trace, e la versione installata di Laravel ha anche una XSS nota proprio nella pagina di debug (CVE-2026-102279, vedi [06-dipendenze.md](06-dipendenze.md)).
- Alternative:
  1. `LOG_LEVEL=debug` nel `.env` → `docker compose up -d` (ricrea i container) → riproduci → leggi `laravel.log` → riporta a `warning`.
  2. Riproduci da tinker con l'utente coinvolto: `auth()->login(App\Models\User::find(ID));` poi chiama le classi `App\Support` direttamente.
  3. Per un componente Livewire: i test `Livewire::test(Grid::class, ['issue' => $issue])->call('movePage', ...)` riproducono quasi tutti i flussi (vedi `tests/Feature/Livewire/*`).
- Se proprio serve il debug sul server: limita l'accesso (es. tunnel SSH) e rimetti `APP_DEBUG=false` subito dopo, con un nuovo `up -d`.

## Strumenti disponibili

```bash
docker compose exec app php artisan about            # versioni, driver, stato delle cache
docker compose exec app php artisan tinker           # REPL (laravel/tinker è in require)
docker compose exec app php artisan route:list --except-vendor
docker compose exec app php artisan migrate:status
docker compose exec app php artisan queue:failed
docker compose exec app php artisan schedule:list
docker compose exec app php artisan timone:missing-occupancy
docker compose exec app php artisan timone:occupancy-dry-run [--issue=ID]   # occupazione: percentuali → dodicesimi, sola lettura
docker compose exec app php -m | grep -Ei 'imagick|redis|pcntl|gd'   # estensioni effettivamente caricate
docker compose exec app php -r 'print_r(Imagick::queryFormats("PDF"));'   # array vuoto = policy PDF di ImageMagick attiva
docker compose exec redis redis-cli llen queues:default    # job in attesa (prefisso: REDIS_PREFIX, vedi nota sotto)
```

- **Adminer**: http://<host>:8081 (server `mysql`, credenziali `DB_USERNAME`/`DB_PASSWORD`).
- Il nome della chiave Redis della coda include il prefisso `REDIS_PREFIX` (default: slug di `APP_NAME` + `_database_`). Se `llen` restituisce 0, elenca le chiavi con `redis-cli --scan --pattern '*queues*'`.

---

## Sintomo → causa probabile → dove guardare → come risolvere

### Pagina bianca / errore 500

| Causa probabile | Dove guardare | Soluzione |
|---|---|---|
| Eccezione applicativa | `laravel.log` (ultima entry `local.ERROR` / `production.ERROR`) | In base all'errore |
| `APP_KEY` vuota o cambiata | log: `No application encryption key` / `The MAC is invalid` | Ripristina l'`APP_KEY` originale dal backup del `.env`. Generarne una nuova invalida sessioni e snapshot Livewire |
| DB irraggiungibile | log: `SQLSTATE[HY000] [2002]`; `docker compose ps mysql` | Riavvia `mysql` e verifica `DB_HOST=mysql` / `DB_PASSWORD` |
| Password DB nel `.env` diversa da quella del volume MySQL esistente | log: `Access denied for user` | La password vale solo alla **prima** inizializzazione del volume `mysql_data`: rimetti quella vecchia o cambiala in MySQL con `ALTER USER` |
| Redis giù (cache su Redis) | log: `Connection refused [tcp://redis:6379]` | `docker compose up -d redis` |
| `/manuale` → 500 | `MANUALE_UTENTE.md` mancante dall'immagine (bug 3 del 2026-10-12, già corretto: oggi mostra un messaggio + `report()`) | Verifica `!MANUALE_UTENTE.md` in `.dockerignore` |
| Permessi su `storage/` | log o stderr: `Permission denied` / `failed to open stream` | Vedi "Permessi file" sotto |
| Migration non applicata (colonna mancante) | log: `Unknown column` | `php artisan migrate:status` → `migrate --force` (attenzione alla migration bloccante, sotto) |

### Errore **502 Bad Gateway**

- **Dopo una ricreazione dei container** (in particolare il secondo `./setup.sh dev`): nginx ha in cache il vecchio IP del container `app` (`HANDOFF.md:1173`).
  - Soluzione: `docker compose restart nginx`.
- `app` non healthy: `docker compose ps`, `docker compose logs app`.

### Azione nel timone che "non fa nulla" o mostra un errore Livewire generico

1. **Reverb irraggiungibile.** Ogni mutazione chiama `broadcast()` in modo sincrono: se Reverb è giù, la richiesta fallisce **dopo** aver già scritto sul DB. Nelle azioni di massa lo stato resta **applicato a metà** (`HANDOFF.md` §Decisioni, ultima voce).
   - Log: `Illuminate\Broadcasting\BroadcastException` / `Could not resolve host: reverb` / `cURL error 7`.
   - Soluzione: `docker compose ps reverb`, `docker compose up -d reverb`; controlla `REVERB_HOST=reverb` e `REVERB_PORT=8080`. Poi fai ricaricare la pagina agli utenti.
2. **Conflitto di riordino** ("Il timone è stato aggiornato da un altro utente"): è voluto (lock ottimistico `issues.reorder_version`), basta riprovare.
3. **Pagina bloccata**: è voluto. Sblocca da UI (icona lucchetto) o con tinker: `Page::find(ID)->update(['locked_at'=>null,'locked_by'=>null])`.
4. **Utente senza permessi** (`commerciale`/`sola_lettura`, o redattore non collegato alla rivista): `AuthorizationException`. Nei componenti Livewire diventa un 403 e non un errore visibile nei test. Verifica `users.role` e `magazine_user`.
5. **Snapshot Livewire scaduto o non valido** dopo un deploy: fai ricaricare la pagina.

### Drag&drop, presence o aggiornamenti realtime non funzionano (lato browser)

| Causa | Come riconoscerla | Soluzione |
|---|---|---|
| `VITE_REVERB_APP_KEY` vuota nel bundle | Console: `[timone] VITE_REVERB_APP_KEY non impostata…` e avviso giallo "aggiornamento in tempo reale non disponibile". **Fino al 2026-10-26** `new Echo()` lanciava `You must pass your app key` e interrompeva **tutto** `app.js`: niente Alpine store (errore `$store.pagePresence` undefined / `editorFor`), niente Sortable. Ora gli store sono in un modulo caricato per primo (`resources/js/timone-alpine.js`) ed `echo.js` non lancia più: il timone funziona in polling | Verifica `REVERB_APP_KEY` nel `.env`, poi `docker compose up -d --build` |
| `VITE_REVERB_HOST/PORT/SCHEME` sbagliati per l'URL pubblico | Network → WS: connessione fallita verso `localhost:8080`, oppure `ws://` bloccato su una pagina `https://` (mixed content) | Allinea le `VITE_REVERB_*` al dominio/proxy reale e ricostruisci (vedi [09-deploy.md](09-deploy.md#5-https-e-websocket)) |
| `/livewire/livewire.js` restituisce 404 | Network | La location statica `.js` di nginx deve avere `try_files ... /index.php` (già presente in `docker/nginx/default.conf`; bug del 2026-07-29) |
| Due istanze di Alpine | Console: `Detected multiple instances of Alpine running`, `$wire is not defined` | Non importare `alpinejs` in `app.js` (vedi commento in testa a `resources/js/timone-alpine.js`) |
| Asset da un Vite dev server non attivo | Gli `<script>` puntano a `localhost:5173` | Cancella `public/hot` (solo in locale) |
| Asset vecchi dopo un deploy | Comportamento "vecchio" solo per qualche utente | Refresh forzato. Gli asset hanno nomi hashati, ma la cache nginx è di 7 giorni sugli statici |
| WebSocket giù | Avviso giallo nel timone "⚠️ Aggiornamento in tempo reale non disponibile" (`grid.blade.php:618-622`); parte il polling ogni 1 s (`grid.blade.php:95`) | L'app continua a funzionare con il polling: risolvi Reverb |

### Errore Alpine `$store.pagePresence` undefined (`editorFor`)

Sintomo: in console `Cannot read properties of undefined (reading 'editorFor')` (o `startEditing`, `connected`), drag&drop morto. **Causa trovata il 2026-10-26**: gli store Alpine erano registrati nel corpo di `resources/js/app.js`, che con gli `import` ES viene eseguito **dopo** `./bootstrap` → `./echo`; se `new Echo()` lanciava (chiave Reverb assente al build) il modulo si interrompeva prima di registrare il listener `alpine:init`, e Livewire avviava comunque Alpine al `DOMContentLoaded` senza store. Correzione: store, direttiva `x-sortable` e magic in `resources/js/timone-alpine.js`, importato **per primo** da `app.js`; `echo.js` in try/catch; espressioni Blade con `?.` come rete di sicurezza. Nessun `wire:navigate` nel progetto: gli store sono registrati una volta per caricamento pagina.

Se ricompare: (1) console → il primo errore della pagina è quello vero; (2) `app.js` è stato caricato? (Network; `public/hot` rimasto in locale fa puntare gli asset a un Vite dev server spento); (3) asset ricostruiti dopo l'aggiornamento (`docker compose up -d --build`).

### Input numerico svuotato → errore della pagina

Sintomo storico (risolto il 2026-10-26): svuotando "Numero totale di pagine" nella creazione numero la pagina andava in errore (`PropertyNotFoundException` / "must not be accessed before initialization"). Causa: Livewire, ricevendo `''` su una proprietà `int`, la "unset"-ta; e in idratazione non riassegna un `null` a una proprietà tipizzata (resta il default di classe). Regola per i nuovi campi numerici legati con `wire:model`: proprietà `?int` con default `= null` (valore iniziale in `mount()`) + trait `App\Livewire\Concerns\NormalizesEmptyNumericInput`; validazione `required|integer` per l'errore inline. Per testo libero numerico il progetto usa proprietà `string` + parsing (es. `Grid::$newTotalPages`). Test: `tests/Feature/Livewire/EmptyNumericInputTest.php`.

### Focus perso / cifre che spariscono negli input Livewire (revisione 2, 2026-10-27)

Sintomo: in "Nuovo numero", scrivendo velocemente nel campo pagine il cursore saltava e alcune cifre sparivano ("1234" diventava "13"); l'avviso «Non è un multiplo di 4» compariva al primo carattere. **Causa trovata riproducendo in Chrome**: il campo era `wire:model.live` su una proprietà `?int`. Il server rimandava il valore convertito (`12`, non `"12"`); Livewire confronta il valore inviato con quello ricevuto e, se diversi, considera la proprietà cambiata dal server e riscrive il campo. Se nel frattempo l'utente aveva scritto un'altra cifra, la risposta arrivata in ritardo la cancellava. Il nodo dell'input non veniva sostituito: non era un problema di `wire:key` né di `@if`.

Regole che ne derivano:
- un input numerico **live** va legato a una proprietà `string`, con il parsing in un metodo (es. `Issues\Create::totalPages()`), mai a `int`/`?int`;
- anteprime con `wire:model.live.debounce.600ms`;
- avvisi che dipendono dal valore dentro `<x-live-field>` con `x-show="! editing"`: compaiono all'uscita dal campo, non mentre si scrive (`resources/views/components/live-field.blade.php`).

Per riprodurre un problema simile serve un browser vero (i test Livewire non simulano le richieste sovrapposte): scrivi più cifre in meno di mezzo secondo e confronta il valore finale. Test di regressione lato PHP: `tests/Feature/Livewire/IssueCreateLiveInputTest.php` (il valore torna identico a come è stato inviato).

### Auto-scroll durante il trascinamento (revisione 2, 2026-10-27)

Sintomo: trascinando un contenuto o una pagina verso il bordo della finestra la pagina non scorreva, quindi non si arrivava alle ultime pagine né alle copertine III/IV. **Causa**: il timone usa il drag&drop nativo del browser (SortableJS senza `forceFallback` per le pagine, `draggable="true"` per contenuti e copertine). Con il drag nativo, l'auto-scroll di Sortable lascia la finestra allo scroll del browser, che parte solo a pochi pixel dal bordo: in alto quei pixel sono sotto la fascia sticky (header + riepilogo). Correzione: un unico gestore per tutti i drag (`resources/js/drag-autoscroll.js`) su `dragover`, con la zona sensibile che parte dal fondo degli elementi `data-sticky-top` e velocità crescente verso il bordo; Sortable ha `scroll: false`.

Se non scorre:
1. gli asset sono aggiornati (`npm run build` / `docker compose up -d --build`)?
2. Un nuovo elemento sticky in alto deve avere `data-sticky-top`, altrimenti la zona sensibile finisce sotto di lui. Dal 2026-10-28 c'è un solo elemento sticky (vedi sotto): meglio aggiungere ciò che deve restare in alto DENTRO quel contenitore.

### Pagine coperte dalla fascia / auto-scroll che parte troppo presto (2026-10-28)

- L'altezza della parte fissa in alto si calcola in un solo punto: il contenitore `data-testid="app-sticky"` in `layouts/app.blade.php` (header + fascia del timone portata con `@teleport('#timone-sticky-slot')` da `grid.blade.php`). Un ResizeObserver scrive `--app-sticky-height` sull'`<html>`; vale `0px` se il contenitore non è sticky (sotto i 640 px col timone).
- Pagine finite sotto la fascia dopo un focus/`scrollIntoView`: controllare in DevTools che `--app-sticky-height` corrisponda all'altezza del contenitore e che `html` abbia `scroll-padding-top: calc(var(--app-sticky-height) + .5rem)` (`resources/css/app.css`).
- Auto-scroll del drag che parte troppo presto o troppo tardi: la zona sensibile superiore parte dal fondo dell'elemento `data-sticky-top` (`stickyTopEdge()` in `resources/js/drag-autoscroll.js`); se qualcuno aggiunge un altro sticky fuori dal contenitore, la zona finisce sotto di lui.
- Riga della selezione che non compare: c'è solo con la modalità selezione attiva; se la fascia non compare affatto, `#timone-sticky-slot` manca nel layout o Alpine non è partito (il teleport lo fa Alpine).
- Nei test headless su macOS: Ctrl+clic è il tasto destro (nessun `click`), usare ⌘; `Emulation.setDeviceMetricsOverride` di Chrome può far perdere gli eventi del mouse simulati: meglio `--window-size`.
3. Se la lista delle pagine viene messa in un `div` con `overflow-y: auto`, il gestore scorre quel `div` (il primo antenato scrollabile del punto sotto il puntatore), non la finestra.

Verifica rapida in console durante un drag: `document.dispatchEvent(new DragEvent('dragover', { clientY: innerHeight - 5 }))` dopo un `dragstart` deve far scorrere la pagina.

### Griglia 6×6: contenuti nel posto sbagliato, frecce grigie (revisione 2, 2026-10-27)

| Sintomo | Causa probabile | Dove guardare / come risolvere |
|---|---|---|
| Dopo l'aggiornamento un contenuto è in un posto diverso da prima | Il suo rettangolo della Fase 7a non era esatto in celle (es. una vetrina 1/4 disegnata come riga a tutta larghezza): è stato messo nel primo posto libero | `php artisan timone:grid-dry-run --issue=ID` → righe «riposizionata»; spostarlo con le frecce |
| Freccia grigia | Si uscirebbe dalla pagina, o il posto è occupato da un contenuto con cui non forma un rettangolo (scambio impossibile) | Il tooltip lo dice; `PageGridPlanner::moveTarget()` |
| Un 3/4 non ha frecce | È una forma a L: riempie lo spazio lasciato dagli altri | Spostare gli altri contenuti |
| Striscia rossa tratteggiata in fondo alla pagina | Pagina oltre capienza (dati vecchi): quel contenuto non ci sta | Ridurre lo spazio di un contenuto |
| «⟳ Ruota» grigio | Quadrato (vetrina, pagina intera) o ruotato non c'è posto | — |
| Anteprima intera invece che ritagliata (o viceversa) | `content_files.aspect_ratio` vuoto (file vecchio) o proporzioni oltre il 5% da quelle del numero (formato del numero nel pannello Peso) | `php artisan timone:file-ratios --dry-run`; override nella modale «Anteprima nel riquadro» |
| Click sull'anteprima non apre più il PDF | Voluto (Fase 2.1): il click seleziona la pagina | Menu «⋯» → 👁 Apri il file |

### Upload in produzione ("non si caricano media")

Checklist completa con comandi e righe di log: [09-deploy.md §6bis](09-deploy.md#6bis-checklist-in-produzione-non-si-caricano-media-fase-1-2026-10-26). In breve: `php artisan timone:diagnose-upload` sul server, poi cerca `upload_failed` in `storage/logs/laravel.log` dopo un tentativo.

### Upload PDF bloccato / "si blocca al caricamento"

| Causa | Dove guardare | Soluzione |
|---|---|---|
| File oltre il limite dell'app | Messaggio «Il file supera il limite di 100 MB…» (nel browser prima dell'invio, o dal server) | È voluto: limite in `config/timone.php` → `upload_max_kb` (unico punto). Per alzarlo vanno alzati anche PHP e nginx: [09-deploy.md §6ter](09-deploy.md#6ter-valori-server-per-file-fino-a-100-mb-revisione-2-fase-1-2026-10-27) |
| File > `upload_max_filesize` (110M) o richiesta > `post_max_size` (120M) | `docker compose logs app`: `POST Content-Length ... exceeds the limit` | `php artisan timone:diagnose-upload` → «Limiti PHP» (avvisa sotto soglia); valori in `docker/php/uploads.ini` e `client_max_body_size 120m` (nginx, e nel reverse proxy se presente): **vanno alzati insieme** |
| 413 da nginx o dal proxy | log di nginx | `client_max_body_size` ≥ 110M (apache: `LimitRequestBody`) |
| Upload grande interrotto a metà | `timone:diagnose-upload` → «Tempi PHP»; timeout del proxy | `max_input_time` ≥ 300, `max_execution_time` ≥ 120, `proxy_read_timeout`/`fastcgi_read_timeout` ≥ 300 s; `max_upload_time` di Livewire (30 minuti) |
| Upload che fallisce senza messaggio con più utenti | `timone:diagnose-upload` → «Spazio disco temporaneo» | Spazio libero per `livewire-tmp` ≥ 2 GB |
| Richiesta di upload lenta prima del salvataggio | Conteggio pagine del PDF | Dal 2026-10-27 lo fa Ghostscript senza rasterizzare (`PdfPageMeasurer::pageCount()`); se `gs` manca ripiega su Imagick, che su un PDF di stampa può impiegare decine di secondi: installa `ghostscript` |
| "PDF illeggibile" | Imagick o Ghostscript assenti, policy PDF attiva, PDF corrotto | `php -m`, `Imagick::queryFormats("PDF")`; prova il PDF in locale con `gs` |
| Pubblicità con PDF di più pagine del formato | Riquadro "📄 Il PDF ha N pagine, il formato … ne occupa F" nel modulo | Non è un errore dal 2026-10-26: scegliere "usa tutte" o "solo da X a Y" (`App\Support\AdPdfPagePlan`). Un errore rosso sotto quel riquadro indica un intervallo non valido |
| Occupazione non indicata | Errore sotto i campi "Pagine intere"/"Frazione" | È voluto: l'occupazione è obbligatoria, almeno uno dei due campi > 0 (`MANUALE_UTENTE.md` §5bis) |

### Occupazione e riempimento delle pagine (dodicesimi, dal 2026-10-27)

| Sintomo | Causa probabile | Dove guardare / come risolvere |
|---|---|---|
| "Spazio insufficiente sulla pagina N: … resta libero solo 1/3 di pagina" | La somma dei dodicesimi sulla pagina supererebbe 12 | È il controllo voluto (`App\Support\PageCapacity`). Ridurre un contenuto dal menu sulla card o scegliere un'altra pagina |
| Card con «⚠ Oltre capienza (+…)» in rosso | Somma > 12 su una pagina: può nascere solo dalla conversione dei dati vecchi (percentuali arrotondate al dodicesimo) | `php artisan timone:occupancy-dry-run --issue=ID` mostra quali assegnazioni sono state arrotondate; ridurre a mano |
| Contenuto che prende più/meno spazio del previsto dopo l'aggiornamento | Il vecchio valore era una percentuale non esatta (es. 49,8% → 1/2, 33% → 1/3) | Dry-run come sopra (riga "(arrotondato)"); i valori originali restano in `page_content.occupied_percentage` |
| Pubblicità che occupa più pagine del formato | Scelta "usa tutte le N pagine" del PDF (bug 8): l'occupazione diventa N pagine intere, indipendentemente dai campi del modulo | `ContentCreator::resolveOccupancy()` |
| Percentuale pubblicitaria del cruscotto diversa da prima | Ora è calcolata sui dodicesimi (esatta) e le prenotate contano la loro occupazione, non più la percentuale di listino (es. piedino 32,6% → 1/3) | `App\Support\AdLoadCalculator` |

### Modifica di un contenuto (Fase 4, dal 2026-10-27)

| Sintomo | Causa probabile | Dove guardare / come risolvere |
|---|---|---|
| "Nuova occupazione non applicabile alle pagine: …" | La ridistribuzione dalla prima pagina non ci sta (numero finito, pagina successiva piena o bloccata) | Voluto (tutto o niente, `ContentUpdater::redistribute()`): liberare spazio o spostare prima il contenuto |
| "La pagina N è bloccata: sbloccala prima di cambiare occupazione o PDF…" | Una pagina occupata dal contenuto è bloccata | Sbloccare la pagina; gli altri campi si possono salvare comunque |
| "Il PDF allegato ha N pagine, ma l'occupazione … ne attraversa M" sotto il file | Il nuovo PDF non coincide con l'occupazione (in modifica non c'è la scelta "usa tutte / da X a Y") | Cambiare prima l'occupazione, poi caricare il PDF |
| Rinomina che "non salva" | Esc o clic fuori annullano; nome vuoto rifiutato (errore rosso sopra la griglia) | `Grid::renameContent()`, chiave d'errore `rename` |

### Pubblicità previste e segnaposto (Fase 5, dal 2026-10-27)

| Sintomo | Causa probabile | Dove guardare / come risolvere |
|---|---|---|
| "Le pubblicità previste non ci stanno…" creando un numero | Più pubblicità che posti: una per pagina interna, più le 4 copertine per le pagine intere | Voluto (`AdPlacementService`, `Issues\Create::save()`): ridurre le pubblicità o aumentare le pagine |
| Pubblicità sulla I di copertina | Più di 3 pubblicità a pagina intera: la I è l'ultima copertina usata | Avviso in cima al numero appena creato; spostarla a mano o cambiare `ad_placement.cover_order` |
| Posizioni diverse da 2, 4, 6… | `TIMONE_AD_PLACEMENT_START_PAGE`/`_STEP` nel `.env` | `php artisan config:show timone.ad_placement` |
| Un segnaposto resta «Da assegnare» dopo la modifica | Non ha ancora cliente, titolo proprio o PDF (cambiare solo stato/formato/spazio non basta) | `contents.is_placeholder`; `ContentUpdater::updateFields()` |
| Le pubblicità previste non si ricalcolano cambiando le pagine | Voluto: il posizionamento avviene solo alla creazione del numero | — |

### Export PDF del timone senza miniature

| Sintomo | Causa probabile | Dove guardare / come risolvere |
|---|---|---|
| Nessuna miniatura nel PDF anche con «miniature» spuntato (versioni prima del 2026-10-27) | L'export leggeva solo i PDF caricati sulla pagina (percorso tolto il 2026-09-02) | Corretto: `App\Support\ExportThumbnail` usa `Page::previewFile()` |
| Manca la miniatura di una pagina | Anteprima non ancora pronta (⏳) o fallita, file mancante su disco, oppure immagine WEBP con una GD senza supporto WEBP | `content_files.thumbnail_status`, `thumbnail_path`; `php -r 'var_dump(function_exists("imagecreatefromwebp"));'` |

### Miniature che restano "in elaborazione" o falliscono

1. `docker compose ps worker`: il worker gira? Senza worker i job restano in Redis per sempre.
2. `docker compose logs worker`: i job vengono processati?
3. `laravel.log`: eccezioni con `report()` dal job (Imagick/Ghostscript: `PolicyError`, `FailedToExecuteCommand 'gs'`).
4. `content_files.thumbnail_status` in Adminer: `failed` significa errore di rendering (nessun retry automatico); `pending` significa che il job non è mai partito.
5. Rilancio: vedi [08-job-e-scheduler.md](08-job-e-scheduler.md#appjobsgeneratecontentfilethumbnail).
6. Timeout del worker (60 s) su PDF enormi: nel log del worker compare `has been attempted too many times or run too long`.

### Code bloccate

| Causa | Dove guardare | Soluzione |
|---|---|---|
| Worker fermo o in crash loop | `docker compose ps worker`, `docker compose logs worker` | `docker compose up -d worker` |
| Worker con codice vecchio dopo un deploy | — | `docker compose up -d` (ricrea) oppure `docker compose restart worker` |
| Redis giù o pieno | `docker compose logs redis`; `redis-cli info memory` | Riavvia Redis. I dati della coda persistono grazie ad `appendonly yes` |
| `QUEUE_CONNECTION` diverso da `redis` | `.env` | I job finirebbero in un'altra coda (es. tabella `jobs`) che il worker (`queue:work redis`) non legge |
| Job che fallisce sempre | `php artisan queue:failed` | Correggi e poi `queue:retry` |

### Mail non partite

1. `MAIL_MAILER`: con `log` (default) le mail sono in `laravel.log`, non vengono inviate.
2. Il worker gira? Il sollecito è **in coda**.
3. `php artisan queue:failed`: errori SMTP (`Connection could not be established`, `535 Authentication failed`).
4. Il sollecito non è partito affatto? Controlla `DeadlineReminderChecker::isDue`:
   - `print_deadline_at` impostata;
   - rivista con **redattore di riferimento** (`magazines.redattore_id`);
   - issue non `chiuso`;
   - `deadline_reminder_sent_at` NULL;
   - giorni alla scadenza `<= TIMONE_DEADLINE_REMINDER_DAYS_BEFORE`;
   - scheduler attivo (`docker compose logs scheduler`).
5. Il flag `deadline_reminder_sent_at` viene impostato all'**accodamento**: se l'invio è fallito, va azzerato a mano (vedi [08-job-e-scheduler.md](08-job-e-scheduler.md#timonesend-deadline-reminders)).
6. Orario: 07:30 nel fuso `APP_TIMEZONE`, cioè **UTC** di default.

### Integrazione esterna che fallisce

Le uniche esterne o collaterali sono Reverb, SMTP e Imagick/Ghostscript: vedi le sezioni sopra e [07-integrazioni.md](07-integrazioni.md).

### Problemi di permessi file

- Nell'immagine `storage` e `bootstrap/cache` appartengono a `www-data` (`Dockerfile`).
- In **dev** (`docker-compose.dev.yml`) il bind mount porta i file dell'host con l'UID dell'host. Per questo `storage/framework`, `vendor` e `bootstrap/cache` sono volumi anonimi. Sintomo storico: avviso `tempnam()` sulle view compilate.
- Un comando lanciato come root (`docker compose exec -u root app ...`) può creare file non scrivibili da `www-data`. Soluzione: `docker compose exec -u root app chown -R www-data:www-data storage bootstrap/cache`.
- Il volume `storage_data` è condiviso tra `app`, `worker` e `scheduler` (non `reverb`, che non tocca i file).

### Cache / config stale

| Sintomo | Causa | Soluzione |
|---|---|---|
| Una modifica PHP non ha effetto (prod) | Codice dentro l'immagine + OPcache `validate_timestamps=0` | `docker compose build && docker compose up -d` |
| Una modifica PHP non ha effetto (dev con bind mount) | Probabilmente OPcache con `validate_timestamps=0` (non sovrascritto in dev). ⚠️ DA VERIFICARE | `docker compose -f docker-compose.yml -f docker-compose.dev.yml restart app` |
| Una variabile del `.env` non viene letta | Container non ricreati, oppure `config:cache` eseguito in passato | `docker compose up -d`; `php artisan config:clear` in ogni container PHP |
| Una `VITE_*` cambiata non ha effetto | È inserita nel bundle al build | `docker compose up -d --build` |
| Una classe Tailwind usata solo in un enum non viene generata | `tailwind.config.js` deve scansionare anche `app/` (bug reale del 2026-07-31, già corretto) | Verifica `content:` in `tailwind.config.js` |
| `PailServiceProvider not found` | `bootstrap/cache/*.php` dell'host finito nel build | `php artisan package:discover` (il Dockerfile lo fa) |
| URL generati con l'host sbagliato | `APP_URL` errato (viene forzato da `AppServiceProvider`) | Correggi `APP_URL` e ricrea i container |

### Errori di build degli asset

| Errore | Causa | Soluzione |
|---|---|---|
| `npm ci` fallisce nella build Docker | `package-lock.json` non allineato a `package.json` | `npm install` in locale, poi commit del lockfile |
| Bundle con `undefined` per Reverb | Build arg `VITE_REVERB_*` non passati | Usa `docker compose build` (che li passa), non un `docker build` manuale senza `--build-arg` |
| Vite dev non raggiungibile dal browser | `server.origin` fisso su `http://localhost:5173` (`vite.config.js`) | Funziona solo con il browser sulla stessa macchina. Per un altro host, modifica `origin` |
| `@apply` senza effetto in un `<style>` dentro Blade | Quel CSS non passa da PostCSS (bug documentato in Fase 6.1) | Metti le regole in `resources/css/app.css` |

### Lentezza DB / app lenta

- `Grid::render()` (`app/Livewire/Timone/Grid.php:2003`) ricarica **tutte** le pagine del numero con tutti i contenuti e i file **a ogni azione** di ogni utente: su numeri molto grandi (max 2000 pagine consentite) è il primo sospettato.
- **Polling a 1 s**: con Reverb giù, ogni utente con il timone aperto fa una richiesta al secondo (`checkForUpdates`, leggera finché `pages_updated_at` non cambia). Con molti utenti il carico cresce: **ripristina Reverb**.
- Attiva lo slow query log di MySQL (`SET GLOBAL slow_query_log=1; SET GLOBAL long_query_time=1;`) e cerca query senza indice. Gli indici sono elencati in [04-database.md](04-database.md#query-e-indici-rilevanti-per-le-prestazioni).
- Per misurare: in tinker `DB::enableQueryLog()` + `Livewire::test(...)` in ambiente di test, oppure Debugbar in locale (non installato).
- Disco pieno (copie multiple dei PDF): `df -h`, `docker system df -v`.

### Peso rivista

Come funziona: [03-dominio-e-flussi.md](03-dominio-e-flussi.md#calcolo-peso-rivista). Campi: [04-database.md](04-database.md#parametri-del-peso-su-magazines-e-issues-migration-2026_10_26_).

| Sintomo | Causa probabile | Dove guardare | Soluzione |
|---|---|---|---|
| **Peso di pochi grammi** (es. 4,7 g o 5,1 g per un numero da ~200 pagine) | Errore di unità di misura. 1) **Grammatura inserita come peso a pagina**: 0,8 invece di 80 g/m² → (21 × 27 × 0,8) / 10000 × 104 = 4,717 g invece di 471,744 g. 2) **Formato in cm invece che in mm** (21 × 27 invece di 210 × 270): area 100 volte più piccola → ~5,1 g invece di 510,7 g. Dal 2026-10-26 entrambi sono bloccati dalla validazione (grammature 30–400 g/m², formato 50–1000 mm) e un valore sporco già nel DB fa mostrare un errore invece del peso; il sintomo può quindi comparire solo se i limiti in `config/peso_rivista.php` sono stati allargati | Campi "Grammatura interno/copertina (g/m²)", "Base (mm)", "Altezza (mm)" della modale "Modifica parametri" del pannello Peso del numero: sotto ogni grammatura la riga "… g/m² → … g a pagina" rende evidente un valore fuori scala (21 × 27 mm a 80 g/m² → 0,023 g a pagina, invece di 2,268). Query sotto per trovare tutti i numeri sospetti | Correggi i valori **del numero** dalla modale (grammatura scritta sulla carta, es. 80/170; formato in mm, es. 210 × 270). Se il valore sbagliato veniva dai default della rivista, correggi anche "Modifica rivista", altrimenti i prossimi numeri lo ricopiano |
| Il pannello mostra "Grammatura … fuori dall'intervallo ammesso (30–400 g/m²)" o "Formato base/altezza … fuori dall'intervallo ammesso (50–1000 mm)" | Valore salvato prima della validazione (es. il numero 3 del DB locale aveva 21 × 27), o limiti in `config/peso_rivista.php` cambiati | Modale "Modifica parametri" del numero | Inserisci la grammatura in g/m² e il formato in mm. Comportamento voluto: un dato sporco non deve produrre un peso plausibile ma sbagliato |
| Peso diverso dall'Excel | 1) Si stanno guardando i parametri della **rivista** invece che del **numero**: il peso usa solo le colonne dell'issue, copiate alla creazione. 2) Formato inserito in cm invece che in mm (`21` invece di `210`): il peso risulta 100 volte più piccolo. 3) Maggiorazione diversa da 2. 4) `pagine_copertina` del numero diverso da quello dell'Excel, quindi pagine interne diverse | Modale "Modifica parametri" del pannello Peso, oppure tinker (sotto): `weightInput()` mostra i valori usati | Correggi i parametri **del numero**. Ricorda che pagine interne = `total_pages − pagine_copertina` (es. NM: 214 − 6 = 208) |
| Il pannello mostra "Parametri peso da configurare" | Colonne NULL sul numero: tipicamente un numero creato prima delle migration `2026_10_26_*`, o una rivista senza default al momento della creazione | Riga "Mancano: …" nel pannello; `issues.formato_base_mm` ecc. in Adminer | Imposta i valori dal pannello del numero. Per i numeri futuri, imposta i default in "Modifica rivista" |
| Il peso non si aggiorna dopo aver cambiato le pagine | Il pannello si ri-renderizza solo su `IssuePageCountUpdated` (Reverb) o sull'evento Livewire `issue-page-count-changed` (emesso da `Grid::resizePages()` e `Grid::checkForUpdates()`) | Console del browser (Reverb connesso?); `docker compose logs reverb` | Ricarica la pagina, che ricalcola sempre da `issues.total_pages`. Se succede spesso, vedi la sezione "Drag&drop, presence o aggiornamenti realtime" sopra |
| I parametri modificati da un altro utente non compaiono | Limite noto: `saveParams()` non emette eventi realtime | — | Ricarica la pagina (vedi [11-debito-tecnico.md](11-debito-tecnico.md)) |
| "Le pagine interne (N) devono essere un numero pari" | `total_pages − pagine_copertina` è dispari (es. 213 pagine totali con 6 di copertina) | Pannello Peso; "Modifica pagine totali" nel timone | Porta le pagine totali a un numero pari, oppure correggi `pagine_copertina` (4 o 6). Non è un bug: mezzo foglio non si stampa |
| "Le pagine interne risulterebbero negative" | `total_pages` < `pagine_copertina` (numero appena creato con 0 pagine) | Pagine totali del numero | Imposta il numero di pagine del timone |
| Avviso "Nel timone ci sono X pagine con ruolo copertina…" | I ruoli Copertina II/III/Battente non sono assegnati nel pannello Copertina, oppure `pagine_copertina` è sbagliato | Pannello "Copertina & battente" | Solo un avviso: il peso usa comunque `pagine_copertina` |
| Peso reale molto diverso dal teorico | Grammatura reale della carta diversa dalla nominale (scheda tecnica della cartiera); maggiorazione da ricalibrare; peso reale inserito male (copia anomala, tara della bilancia) | Blocco calibrazione del pannello: scostamento in g e % | Se lo scostamento è sistematico su più numeri, usa "Copia nei default della rivista" (solo Admin) per la maggiorazione suggerita, oppure correggi la grammatura. Non viene mai applicato da solo |
| "La maggiorazione suggerita è fuori dall'intervallo ammesso" | Peso reale inferiore alla sola carta (suggerimento negativo) o assurdo | `issues.peso_reale_g` | Ricontrolla il peso reale inserito |
| Lo scaglione di un numero vecchio è cambiato | Sono stati modificati gli scaglioni in `config/peso_rivista.php`: lo scaglione non è salvato | `git log config/peso_rivista.php` | Comportamento voluto (vedi [05-configurazione.md](05-configurazione.md#configpeso_rivistaphp-calcolo-peso-rivista)) |

**Ricalcolare a mano un numero** (sostituisci `ID` con l'id dell'issue, visibile nell'URL `/riviste/{slug}/numeri/{ID}`):

```bash
docker compose exec app php artisan tinker --execute='$r = App\Models\Issue::findOrFail(ID)->weight(); dump($r->complete, $r->missing, $r->errors, $r->pesoCopiaG, $r->margineG, $r->scaglione, $r->input->pagineInterno);'
# tutti i valori di input e di risultato:
docker compose exec app php artisan tinker --execute='dump(App\Models\Issue::findOrFail(ID)->weight());'
```

`weight()` è di sola lettura e non scrive nulla. Il comando è stato verificato il 2026-10-26: su un numero NM-like stampa `true`, `[]`, `[]`, `510.67422`, `31`, `1000`, `208`.

**Trovare numeri e riviste con grammature o formato fuori scala** (sola lettura; i limiti 30–400 sono quelli di `config/peso_rivista.php`, il formato sotto 50 mm è quasi certamente in cm):

```bash
docker compose exec app php artisan tinker --execute='
$bad = fn ($q) => $q->where(fn ($w) => $w->where("grammatura_interno", "<", 30)->orWhere("grammatura_interno", ">", 400)->orWhere("grammatura_copertina", "<", 30)->orWhere("grammatura_copertina", ">", 400)->orWhere("formato_base_mm", "<", 50)->orWhere("formato_altezza_mm", "<", 50));
foreach ($bad(DB::table("issues"))->get(["id", "magazine_id", "title", "formato_base_mm", "formato_altezza_mm", "grammatura_interno", "grammatura_copertina"]) as $r) echo "numero ", json_encode($r), "\n";
foreach ($bad(DB::table("magazines"))->get(["id", "name", "formato_base_mm", "formato_altezza_mm", "grammatura_interno", "grammatura_copertina"]) as $r) echo "rivista ", json_encode($r), "\n";'
```

Oppure in SQL (Adminer):

```sql
SELECT id, magazine_id, title, formato_base_mm, formato_altezza_mm, grammatura_interno, grammatura_copertina
FROM issues
WHERE grammatura_interno < 30 OR grammatura_interno > 400
   OR grammatura_copertina < 30 OR grammatura_copertina > 400
   OR formato_base_mm < 50 OR formato_altezza_mm < 50;
-- stessa WHERE su `magazines` (colonne id, name, …) per i default delle riviste
```

Non correggere i valori con un UPDATE alla cieca: vanno ricontrollati sulla scheda della carta e corretti dalla modale del numero, così la modifica resta in cronologia (`issue.weight_params_changed`).

---

## Punti fragili specifici di questo progetto

1. **Migration bloccante `page_occupancy`**: `migrate` si ferma finché esistono contenuti senza occupazione ([04-database.md](04-database.md#migration-bloccante-page_occupancy)). Prima di ogni deploy: `php artisan timone:missing-occupancy`.
2. **Broadcast sincrono**: Reverb giù significa azioni utente in errore e azioni di massa applicate a metà.
3. **Catena dei PDF**: Imagick + Ghostscript + `policy.xml` patchata via `sed`. Un cambio dell'immagine base può romperla in silenzio.
4. **`deadline_reminder_sent_at` impostato prima dell'invio reale**: una mail fallita non viene ritentata il giorno dopo.
5. **Cancellare un utente** (anche da sé, da `/profile`) **cancella i `content_files`/`page_files` che ha caricato** (FK cascade).
6. **Log di Reverb non persistito** (il servizio non monta `storage_logs`).
7. **Seed su DB vuoto**: `setup.sh` su un server nuovo crea gli utenti demo con password `password`.
8. **`APP_TIMEZONE=UTC`**: orari dello scheduler e date in UI e log in UTC.
9. **Adminer** sempre esposto sulla porta 8081.
10. **`pagefiles:prune-orphaned`** guarda solo `page_files`: vedi il rischio in [08-job-e-scheduler.md](08-job-e-scheduler.md#pagefilesprune-orphaned---dry-run).
11. **Copie del PDF per ogni pagina**: consumo di disco proporzionale a dimensione × pagine.
12. **Nessun test browser** (niente Dusk): drag&drop, auto-scroll, menu «⋯» dei blocchi, focus/anelli di selezione e drop zone sono verificati solo a mano (dal 2026-10-27 con uno script CDP su Chrome headless, non nel repo: vedi HANDOFF.md, revisione 2 Fase 1). Dal 2026-10-26 anche l'animazione dei gruppi (MultiDrag di SortableJS, FLIP con la Web Animations API) e lo zoom S/M/L (`resources/js/timone-alpine.js`): i test PHP verificano solo markup e logica server (`nudgePage`, `PageReorderer::nudgeTarget()`).

## Caricamento multiplo e segnaposto (revisione 2, Fase 3, 2026-10-28)

- **Un file del lotto resta «✕ errore» con «Il file non è arrivato al server»**: l'upload temporaneo è stato rifiutato prima dell'applicazione. Cercare nel log la voce `upload_failed` con `component` = `App\Livewire\Timone\BulkUpload` (errori di validazione di Livewire o richiesta respinta) e controllare i limiti con `php artisan timone:diagnose-upload`.
- **«Impossibile leggere il numero di pagine di questo PDF»**: stesso controllo del modulo singolo (`PdfPageMeasurer::pageCount()`: Ghostscript, poi Imagick). In locale senza Ghostscript/Imagick OGNI PDF finisce così: provare con immagini o in Docker.
- **Contatore «Da assegnare» che non torna**: `php artisan timone:placeholder-sync --dry-run` elenca le pubblicità il cui `is_placeholder` non rispetta la regola (senza cliente e senza file); senza `--dry-run` le riallinea.
- **Toast «aggiornato da un altro utente» per una modifica propria** (solo col polling, senza Reverb): `Grid::checkForUpdates()` lo sopprime solo se in `activity_logs` ci sono voci dell'utente corrente e nessuna di altri dal poll precedente; un'azione che non scrive in cronologia lo mostra ancora.

## Permessi ed eliminazioni (revisione 2, Fase 4, 2026-10-28)

- **Un Commerciale riceve 403 spostando qualcosa**: è voluto se il contenuto è un articolo o se la pagina (o il suo gruppo collegato) contiene un articolo. Le regole sono in `App\Support\Permissions\TimoneAbilities`; `Grid::authorizeContent()`/`authorizePageMove()` dicono quale controllo scatta.
- **Nei test Livewire, dopo un `assertForbidden()` la chiamata successiva sullo stesso componente fallisce** («Invalid Livewire snapshot structure»): un 403 non restituisce lo stato. Usare un `Livewire::test()` nuovo per ogni chiamata rifiutata (vedi `CommercialeAdsOnlyTest.php`).
- **«Troppi tentativi con la password sbagliata»** nella conferma di eliminazione: rate limiter `dangerous-delete:{id admin}` sulla cache; si sblocca da solo dopo un minuto (o `php artisan tinker --execute "RateLimiter::clear('dangerous-delete:ID')"`).
- **File rimasti su disco dopo un'eliminazione**: cercare `stored_files_deleted` nel log del worker; se il job `DeleteStoredFiles` è in `failed_jobs`, `php artisan queue:retry <id>`.
- **Un utente con il numero aperto non viene riportato all'elenco**: con Reverb serve l'evento `IssueDeleted` (eliminazione dall'interfaccia, non da tinker); senza Reverb il poll. Il redirect lo fa `resources/js/deleted-issue-redirect.js` (asset da ricompilare: `npm run build`).

## Export PDF sfogliabile (revisione 2, Fase 5, 2026-10-28)

- **Fermo su «In coda»**: il worker non gira o è occupato (`docker compose logs worker`, `php artisan queue:work`). Dopo `TIMONE_EXPORT_TIMEOUT` + 5 minuti un nuovo click accoda di nuovo.
- **Fermo su «In generazione»**: il job è stato ucciso (memoria o timeout) senza passare da `failed()`; cercare `GenerateMagazinePdf` in `failed_jobs` e nel log del worker; alzare `TIMONE_EXPORT_MEMORY_LIMIT` o usare la bassa risoluzione. Dopo timeout + 5 min si può rilanciare dal pannello.
- **«Errore» con «Ghostscript non è riuscito…»**: solo la variante leggera; verificare `gs --version` nel container worker.
- **Molti «File mancante» inattesi**: il file del contenuto non è sul disco (`content_files.path` sul disco indicato) o è vuoto, oppure FPDI non lo apre neanche dopo la riscrittura con Ghostscript (PDF protetti/danneggiati). Il numero si esporta comunque; contarli in `magazine_exports.missing_files`.
- **Cartelle `exports/tmp-*` rimaste**: vengono rimosse a fine job anche in errore; ne resta una solo se il processo è stato ucciso. Si possono cancellare a mano.
- **Test**: il testo del PDF si verifica con `gs -sDEVICE=txtwrite`; i test che lo usano sono saltati senza Ghostscript (in locale) e girano nel container.

## Dropdown della navigazione che non si apre (2026-10-28)

- Alpine (che fa funzionare il menu in alto a destra) arriva SOLO con lo script di Livewire. Livewire lo inserisce da sé solo nelle pagine con un suo componente: le viste da controller (`/profile`, `/manuale`) ne erano senza. Ora `@livewireScripts` è in `layouts/app.blade.php` (test `tests/Feature/NavigationScriptsTest.php`: una sola copia su ogni pagina).
- **Causa reale in produzione (2026-10-28)**: script e Alpine c'erano, ma nel DOM il trigger del menu era `<div open="">` invece di `<div @click="open = ! open">` (l'HTML del server era corretto): qualcosa tra server e browser (estensione del browser — in console compariva `content.js … [Violation]` — o un proxy che riscrive l'HTML) toglieva gli attributi `@click`. Corretto usando `x-on:click`/`x-on:click.outside` in `components/dropdown.blade.php` e `layouts/navigation.blade.php`, come nel resto del progetto; un test verifica che nessuna vista usi `@click`. Per scoprire chi riscrive: confrontare «Visualizza sorgente» (HTML del server) con «Elementi» (DOM), e provare in finestra anonima senza estensioni.
- Se il menu non si apre su NESSUNA pagina e lo script manca, lo script non arriva: in Network cercare `livewire.js`/`livewire.min.js` (404 → il web server davanti all'app deve passare a `index.php` le richieste `.js` che non sono file, come `docker/nginx/default.conf`; bloccato → http/https misti; percorso sbagliato → app sotto un sottopercorso).

## Manuale: pagina non trovata, avviso giallo o test del manuale rossi (2026-10-29)

- **Avviso «Il manuale utente non è disponibile»**: manca la cartella `resources/manuale/` (o è vuota) sul server; il dettaglio è nel log (`report()` in `DocumentationController`).
- **404 su `/manuale/<slug>`**: lo slug non esiste (nome del file = slug). Un link vecchio `/manuale#…` lo gestisce lo script della pagina d'ingresso con `App\Support\Manual\LegacyAnchors`.
- **Una pagina dà errore 500**: front matter non valido o blocco `:::nome` non chiuso: l'eccezione dice il file e il motivo (`ManualPage::parse()`, `ManualBlocks::split()`); `tests/Feature/ManualPagesTest.php` lo trova prima.
- **Test «MANUALE_UTENTE.md is the file generated…» rosso**: hai cambiato una pagina senza rigenerare: `php artisan manuale:genera`.
- **`manuale:verifica` rosso**: un termine del vecchio manuale non c'è più; aggiungilo nella pagina giusta o, se è un bottone rinominato, mettilo in `ManualVerifier::EXCEPTIONS` con il nome nuovo.
- **La ricerca non trova una pagina**: prova la domanda con `node tests/js/manual-search-check.mjs <indice.json> <domande.json>` (indice da `ManualSearchIndex::build()`); aggiungi parole chiave o sinonimi alla pagina (front matter) o un gruppo in `resources/manuale/sinonimi.yml`, poi rilancia `tests/Feature/ManualSearchTest.php` (le 15 domande di riferimento devono restare al primo posto). L'indice in cache cambia da solo quando cambiano i file.
- **«La ricerca non è disponibile»**: `/manuale/indice.json` non risponde (manuale mancante o errore): l'indice e i riquadri restano utilizzabili.
- **Uno stile del manuale non si vede**: Tailwind tiene solo le classi scritte per intero nei file; in `ManualRenderer` non comporre i nomi delle classi (`'manual-x-'.$tipo`).

## Screenshot del manuale (2026-10-29)

- **Al posto dell'immagine c'è «[Screenshot da fare: …]»**: manca `public/manuale/img/<nome>.webp` (il nome è quello del blocco `:::screenshot nome`): `npm run manuale:screenshot --solo nome`.
- **`npm run manuale:screenshot` si ferma**: «Mancano gli asset compilati» → `npm run build`; «C'è public/hot» → ferma `npm run dev`; «Chrome/Chromium non trovato» → `CHROME_PATH=…`; «Serve node 22» → aggiorna node; «Tempo scaduto aspettando …» → un elemento non c'è più (un `data-testid` rinominato nelle view): correggi il selettore nello script. Gli errori del seeder sono stampati prima.
- **«N | x% | y% (nel manuale: …, fuori dal suo elemento)»**: l'interfaccia è cambiata e il numero indica un'altra cosa; copia la posizione proposta nel blocco della pagina (o spostalo a mano, entro 4 punti dall'elemento).
- **L'immagine non si aggiorna nel browser**: l'indirizzo ha `?v=<data del file>`; se il file è stato copiato senza cambiare data, toccalo (`touch`).

## Assistente «Chiedi al manuale»: risposta sbagliata, nessuna risposta, job bloccato (2026-10-29)

- **Risposta sbagliata a una domanda**: guarda «Cosa chiedono gli utenti» (`/manuale/amministrazione/domande`); se viene da una domanda imparata o da una correzione, «Disattiva». Altrimenti correggi la pagina giusta: «In breve», parole chiave o sinonimi nel front matter, o un gruppo in `resources/manuale/sinonimi.yml`; rilancia `tests/Feature/ManualAssistantTest.php`. `resources/manuale/domande.yml` non si tocca più (regola di manutenzione del manuale): le domande nuove le impara l'assistente dai voti.
- **Provare una domanda dal terminale**: `php artisan tinker --execute 'print_r(array_slice(App\Support\Manual\ManualAssistantFactory::fromConfig()->current()->rank("la domanda"), 0, 5));'` (punteggi; risposta da 60, «Forse cercavi» da 12). Sull'host senza Redis aggiungi `CACHE_STORE=array`; `php artisan tinker <file>` resta in attesa: usa `--execute`.
- **`manuale:apprendi` esce con errore**: il cancello ha bloccato i pesi nuovi; le domande che non passano sono nel log (`warning`) e nella pagina admin. I pesi di prima restano: nessuna azione urgente. Di solito la causa è una domanda di riferimento con un margine stretto: rinforza la pagina attesa (parole chiave, sinonimi), non domande.yml; se i voti dicono che la risposta attesa è sbagliata, è la pagina da correggere.
- **Voti che sembrano ignorati**: servono 5 persone diverse sulla stessa chiave e pagina; un voto su un «In breve» poi modificato vale 0, su una pagina modificata ½.
- **Il motore PHP e la ricerca del browser divergono**: il test di parità in `ManualSearchTest.php` lo dice; le regole sono in `SearchEngine` e `manual-search-core.js` e vanno cambiate insieme.

## Doppia pagina: una pagina trascinata non si sposta o finisce altrove (2026-10-29)

- **Il drag non parte**: le page-card devono essere figlie dirette di `.timone-spreads` (niente div attorno, Sortable lo verifica in silenzio).
- **Coppie scollate o pagine che «saltano» durante il drag**: il lato deve venire dalle colonne fisse di `app.css` (`:nth-child` → `grid-column`), non da margini (vedi `docs/02`). Le regole per N = 1…4 coppie per riga sono generate: aggiungendo uno zoom o una soglia vanno aggiunte tutte.
- **Prove automatiche con CDP**: il puntatore deve scorrere a piccoli passi e il bersaglio stare lontano dal bordo dello schermo; altrimenti lo scorrimento automatico (`drag-autoscroll.js`) sposta la pagina sotto il puntatore e la pagina finisce più avanti (succede uguale nella vista Griglia, non è un difetto).

## Commenti: il collega non vede il mio commento / non arriva la mail (2026-10-29)

- **Contatori fermi**: arrivano solo dal polling (niente broadcast). Controllare che l'elemento `wire:poll.1s` sia montato: `checkForCommentUpdates` con Reverb collegato, `checkForUpdates` senza (in `grid.blade.php`); si fermano durante un trascinamento. Il segnale è `issues.comments_updated_at`: se non cambia dopo un commento, la scrittura non è passata da `PageCommentWriter`.
- **Mail non arrivata**: va in coda (`PageCommentPosted` è `ShouldQueue`): serve il worker (`docker compose ps worker`, `php artisan queue:failed`). Con `MAIL_MAILER=log` la mail finisce in `storage/logs/laravel.log`, non parte. Variabili in [05-configurazione.md](05-configurazione.md#mail). Una mail non parte se nel frattempo il commento è stato eliminato (`shouldSend()`).
- **Menzione ignorata**: serve il nome completo come in «Utenti» (`@Mario Rossi` o `@MarioRossi`), e la persona deve vedere il numero (assegnata alla rivista, o admin). `CommentRecipients::mentioned()`.
- **Commento sparito dopo aver ridotto le pagine**: non è sparito, è nella scheda «Tutti» dei commenti del numero come «Pagina N, eliminata» (`page_id` NULL).

## Ctrl+Z non fa nulla (annulla/ripeti, 2026-10-28)

- **Focus in un campo** (input, textarea, select, contenteditable): lì resta l'annulla del browser (`isTimoneShortcutTarget()` in `grid.blade.php`). Cliccare fuori dal campo.
- **Modale aperta** (`[aria-modal=true]`, la modale «Modifica contenuto», o una modale di Breeze che mette `overflow-y-hidden` sul body): Ctrl+Z ignorato (`isModalOpen()`).
- **Pila vuota**: ↶ disattivato, tooltip «Niente da annullare». Si annullano solo le PROPRIE azioni su QUEL numero, delle ultime 24 ore (max 50).
- **Passo non annullabile**: tooltip «Non annullabile: …». Motivi: PDF sostituito in una modifica, oppure un conflitto trovato a un tentativo precedente (un collega ha cambiato le stesse righe dopo; oppure lo spazio è stato occupato). La pila si ferma lì apposta.
- **Raffica di tasti**: mentre un annulla è in corso gli altri Ctrl+Z vengono ignorati (flag `timelineBusy` nel browser + `Cache::lock("timeline:{issue}:{user}")` sul server).
- **«Ho annullato e si è disfatto il lavoro di un collega»**: non deve succedere. L'annulla sceglie solo i passi dell'utente collegato (`where user_id`, nessun id dal browser); i passi senza transazione (creazione/modifica/caricamento multiplo) tengono solo le righe del proprio contenuto (`TimelineDiff::onlyContents()`); se un collega ha cambiato dopo le stesse righe, l'annulla è rifiutato (conflitto). Test: `TimelineUndoRedoTest` («…does not undo what B did meanwhile», «…never pick another user's step»).
- **Azione che non compare in cronologia**: o non ha cambiato nulla (non si registra), o non passa da `Grid::recordStep()`/`TimelineActionService::run()` (vedi l'elenco in `MANUALE_UTENTE.md` §6quinquies). Dati: tabella `timone_azioni`.
- **Test**: un componente Livewire creato con un `Issue` già caricato ha una `reorder_version` vecchia dopo uno spostamento: usare `$issue->fresh()`.

## Contenuto non trascinabile dal pannello (2026-10-28)

- Si trascinano solo i contenuti **non ancora in pagina** e che l'utente può spostare: nella scheda «Tutti» quelli già in pagina non hanno `draggable`; per il Commerciale gli articoli hanno `data-not-movable` (cursore 🚫, tooltip). Markup: `partials/content-panel-item.blade.php`.
- Il rilascio su una pagina non fa nulla: la pagina è bloccata (nessun gestore di drop), oppure il contenuto è stato assegnato nel frattempo da un collega → errore «è già stato assegnato» nella fascia (`Grid::placeUnassignedContent()`).
- Errori di spazio/assegnazione: compaiono nella fascia in alto (`sticky-error-percentage`), anche a pannello chiuso.
- Pannello che copre l'inizio della pagina o colonna spostata di 16 px: il pannello è `fixed` con `top: var(--app-sticky-height)`; un margine ereditato (`space-y-*` del genitore) lo sposta — per questo ha `!mt-0`.
- Stato strano del pannello (aperto/larghezza): `localStorage.removeItem('timone.contentPanel')` nella console.

## Test in locale: memoria esaurita

Con oltre 850 test il PHP locale (MAMP, `memory_limit` 128M) si fermava con «Allowed memory size … exhausted» nella raccolta dei test. Dal 2026-10-27 `phpunit.xml` imposta `memory_limit` 512M per i test (`<ini>`), quindi `php artisan test` funziona di nuovo. Se ricapita: `php -d memory_limit=1G vendor/bin/pest`.

## Eseguire i test che richiedono Imagick (senza Imagick in locale)

I test che contano le pagine dei PDF (`ContentCreateMultipageUploadTest`, `ContentFreeFormPdfDistributionTest`, `ContentCreateAdPdfChoiceTest`, …) vengono saltati se `ext-imagick` manca (es. MAMP). Si possono eseguire in un container usa-e-getta dall'immagine `app` (che ha imagick, ghostscript e pdo_sqlite), montando il repo e il `vendor/` dell'host (con le dev-dependency):

```bash
docker run --rm --entrypoint sh -v "$PWD":/app -w /app \
  -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  timone-rivista-app:latest -c 'php vendor/bin/pest'
```

Non tocca i container in esecuzione. Il 2026-10-26: 701 test passati, 0 saltati.

## Test che falliscono con "Could not resolve host: reverb"

`phpunit.xml` non sovrascrive `BROADCAST_CONNECTION`, quindi i test ereditano `reverb` dal `.env`. Ogni test che esegue una mutazione deve usare `Event::fake([...])` sugli eventi broadcast. **Non** impostare `BROADCAST_CONNECTION=log`/`null` in `phpunit.xml`: `LogBroadcaster::auth()` non applica il 403 e romperebbe `PresenceChannelAuthTest` (`HANDOFF.md` §Decisioni).
