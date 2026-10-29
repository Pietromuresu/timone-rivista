# 11 — Debito tecnico e rischi

[← Indice](README.md) · Vedi anche: [06-dipendenze.md](06-dipendenze.md), [10-debugging.md](10-debugging.md)

Ordinati per priorità: P1 = da affrontare prima di (o subito dopo) il passaggio di consegne; P2 = presto; P3 = quando capita. Dove la fonte è `HANDOFF.md` la segnalo; il resto è dedotto dal codice in questa analisi.

Nel codice **non ci sono `TODO`/`FIXME`/`HACK`/`XXX`** (grep su `app/`, `resources/`, `routes/`, `config/`, `database/`). Il debito è tracciato in prosa in `HANDOFF.md`.

## P1 — Rischi concreti

| # | Problema | Dove | Impatto | Intervento proposto |
|---|---|---|---|---|
| 1 | **Vulnerabilità note nelle dipendenze**: Livewire 3.8.2 (XSS DOM, CVE-2026-81887), Laravel 12.64 (3 advisory), symfony/http-foundation (ReDoS), più altre. 19 advisory composer e 11 npm in totale | `composer.lock`, `package-lock.json` | XSS e DoS su un'app esposta in rete | `composer update` su patch/minor (Livewire resta su 3.x), `npm audit fix`. Procedura in [06-dipendenze.md](06-dipendenze.md#come-aggiornare-in-sicurezza) |
| 2 | **Migration NOT NULL `page_occupancy` in attesa di backfill** | `database/migrations/2026_10_12_100001_*` | Il prossimo `migrate` in un ambiente con contenuti vecchi **si ferma** | Decidere i valori con la redazione, valorizzarli, poi migrare (`HANDOFF.md`, "Checkpoint Fase 1 ancora aperto") |
| 3 | **Cancellazione utente → cancellazione a cascata dei file caricati** (`content_files.uploaded_by`, `page_files.uploaded_by`, `page_reorder_logs.user_id` sono `cascadeOnDelete`). Ogni utente può cancellarsi da `/profile` | migration `2026_08_31_110000`, `2026_07_23_155110`, `2026_07_23_155111`; `ProfileController::destroy` (`:43`); `resources/views/profile/partials/delete-user-form.blade.php` | Un redattore che chiude il proprio account fa sparire dal timone il materiale di stampa che ha caricato | Rimuovere l'auto-cancellazione da `/profile` e/o passare le FK a `nullOnDelete` / `restrictOnDelete`, o usare la disattivazione al posto della cancellazione |
| 4 | **Nessun backup schedulato né backup dei file** | `docker-compose.yml` (servizio `backup` manuale), `docker/mysql/backup.sh` | Perdita dati in caso di guasto del server | Cron + copia off-site + backup del volume `storage_data` (template in [09-deploy.md](09-deploy.md#7-scheduler-code-backup)) |
| 5 | **Nessun HTTPS nel repo; Reverb `ws://` in chiaro; Adminer esposto sulla porta 8081 anche in "produzione"** | `docker/nginx/default.conf`, `docker-compose.yml` | Credenziali in chiaro in rete; pagina di login al DB raggiungibile | Reverse proxy TLS e porte su `127.0.0.1` (template in [09-deploy.md](09-deploy.md#5-https-e-websocket)); togliere Adminer dal compose di produzione o metterlo sotto un profilo |
| 6 | **`setup.sh` in modalità prod esegue il seed demo** su DB vuoto (utenti con password `password`) | `setup.sh` passo 5 | Un primo deploy fatto "come dice il README" crea account noti | Procedura manuale in [09-deploy.md](09-deploy.md#b-primo-deploy-su-server-nuovo); valutare di non fare seed se `APP_ENV=production` |
| 7 | **Broadcast sincrono non protetto**: se Reverb è giù, ogni mutazione va in errore dopo aver scritto; le azioni di massa restano a metà | `app/Events/*` (`ShouldBroadcastNow`), `Grid.php` (15 `broadcast()`) | Stato incoerente percepito dall'utente | `try/catch` + `report()` intorno a `broadcast()`, oppure `ShouldBroadcast` in coda (`HANDOFF.md` §Decisioni, ultima voce) |

## P2 — Sicurezza e robustezza (audit interno, `HANDOFF.md` §"Fase 6.2", mai corretti)

| # | Problema | Dove | Note |
|---|---|---|---|
| 8 | Estensione del file salvato presa dal nome fornito dal client (`getClientOriginalExtension()`) | `app/Support/ContentCreator.php:200` (era `ContentCreate.php:216` all'epoca dell'audit); `GenerateContentFileThumbnail` eredita l'estensione | Oggi innocuo (storage fuori dal docroot, `Content-Type` dal MIME salvato). Diventerebbe RCE se un domani lo storage fosse servito come statico. Fix: derivare l'estensione dal MIME reale |
| 9 | `Grid`, `ContentCreate` e `AdReservations` non fanno `authorize('view')` nel `mount()` (`CoverSection` rimosso nella Fase 2) | `app/Livewire/Timone/*.php` | Protetti da `Issues\Show::mount()`. Se un domani vengono esposti come rotta diretta, perdono la protezione. Fix: `authorize` ridondante |
| 10 | `updateContentPercentage()`: `->first()->pivot` su un contenuto rimosso nel frattempo da un altro utente → errore fatale | `app/Livewire/Timone/Grid.php:1829` | Solo robustezza (500 Livewire) |
| 11 | `redattore_id` accetta qualunque utente, non solo i redattori | `app/Livewire/Magazines/Edit.php` (`'redattore_id' => 'nullable|exists:users,id'`) | Integrità dati; solo l'Admin può modificarlo |

## P2 — Operatività

| # | Problema | Dove | Note |
|---|---|---|---|
| 12 | **Una copia intera del PDF per ogni pagina** del PDF | `ContentCreator::create()` → `storeFile()` per ogni pagina (`app/Support/ContentCreator.php:185`, `:198-201`) | Un PDF di 64 pagine da 100 MB occupa circa 6,4 GB. Fix: un file fisico condiviso tra le righe `content_files` (lo fa già la migrazione legacy) |
| 13 | **Sollecito marcato "inviato" all'accodamento** | `SendDeadlineReminders.php` | Se SMTP fallisce, il sollecito si perde. Scelta consapevole contro i doppioni, ma senza un recupero |
| 14 | **`pagefiles:prune-orphaned` non conosce `content_files`** (né quelli orfani, né quelli che puntano sotto `pages/` dopo la migrazione legacy) | `app/Console/Commands/PruneOrphanedPageFiles.php:30-37` | Rischio di cancellare file in uso (vedi [08-job-e-scheduler.md](08-job-e-scheduler.md#pagefilesprune-orphaned---dry-run)) |
| 15 | **Migrazione dei `page_files` legacy mai eseguita su dati reali** | `timone:migrate-legacy-page-files` | Due modelli paralleli (`PageFile` e `ContentFile`) in `Page::previewFile()`, nel job legacy e nei controller |
| 16 | **Log**: `laravel.log` singolo e senza rotazione; log di Reverb non persistito; nessun monitoraggio o alert | `.env.example` (`LOG_STACK=single`), `docker-compose.yml` | `LOG_STACK=daily`; volume `storage_logs` anche su `reverb`; Sentry o uptime check |
| 17 | **Immagini Docker con tag mobili** (`nginx:alpine`, `adminer:latest`, `redis:7-alpine`, ...) e nessun tag di versione sulle immagini buildate | `Dockerfile`, `docker-compose.yml` | Build non riproducibili, rollback lento |
| 18 | **Nessuna CI**: i test (circa 562) girano solo a mano; quelli con Imagick solo con un comando Docker ad hoc | — | Aggiungere una pipeline che esegua Pest nell'immagine con Imagick |
| 19 | **Nessun test browser** (Dusk o Playwright): drag&drop, selezione, drop zone, presence | `HANDOFF.md`, sezioni "Da verificare manualmente" | Gli ultimi fix (2026-10-12) hanno una lista di verifiche manuali **ancora da fare** (`HANDOFF.md:1166-1171`) |
| 20 | **`opcache.validate_timestamps=0` anche in modalità dev** (bind mount) | `docker/php/opcache.ini`, `docker-compose.dev.yml` | Possibile "modifica non visibile" in sviluppo. ⚠️ DA VERIFICARE |
| 21 | **PHP 8.2 a fine supporto di sicurezza** (fine 2026, dato generale del ciclo di vita PHP) | `Dockerfile`, `composer.json` | Pianificare 8.3/8.4 |

## P3 — Qualità del codice e codice morto

| # | Problema | Dove | Note |
|---|---|---|---|
| 22 | `Grid.php` di 2151 righe con 50 metodi pubblici (SRP) | `app/Livewire/Timone/Grid.php` | Scelta consapevole e motivata (`HANDOFF.md` §Decisioni, prima voce). Da spezzare solo con un piano di refactoring dei test |
| 23 | `ContentCreate.php` di 808 righe | `app/Livewire/Timone/ContentCreate.php` | Come sopra, in misura minore |
| 24 | `PageCountResizer` ignora i gruppi di pagine (una riduzione può lasciare un gruppo con una sola pagina) | `app/Support/PageCountResizer.php` | `HANDOFF.md`, "Debito noto" batch 2026-08-31 |
| 25 | Nessun "riprova" per una miniatura fallita (la modifica di un contenuto esistente, file e occupazione inclusi, esiste dalla Fase 4, 2026-10-27) | `ContentCreate` | `HANDOFF.md`, sessione 2026-09-02 |
| 26 | Stato issue `in_lavorazione` mai impostato dal codice; un numero `chiuso` resta modificabile | `app/Enums/IssueStatus.php`, `IssuePolicy::update` | Forse voluto: va chiarito con la redazione |
| 27 | Ruolo `commerciale` identico a `sola_lettura` | `app/Policies/*`, `MANUALE_UTENTE.md` §14 | Ruolo predisposto, mai sviluppato |
| 28 | Codice morto o residuo: `setup-old.sh`; job `GeneratePageFileThumbnail` (non più accodato); comando `inspire`; `tests/*/ExampleTest.php`; `laravel/sail` e `laravel/breeze` in require-dev; `axios` caricato ma mai chiamato; variabili `.env.example` inutilizzate (`MEMCACHED_HOST`, `AWS_*`, `VITE_APP_NAME`); rotte `verify-email` (il modello `User` non implementa `MustVerifyEmail`) | vari | Pulizia a basso rischio |
| 29 | `TIMONE_DEADLINE_REMINDER_DAYS_BEFORE` assente da `.env.example` | `config/timone.php` | Documentarla |
| 30 | Warning di deprecazione `brick/math` ("Passing floats to BigNumber::of()") sui cast `decimal` | cast `decimal` rimasti (es. parametri peso); dal 2026-10-27 non più sull'occupazione, che è intera | Non funzionale (`HANDOFF.md` §Decisioni) |
| 31 | Nessun vincolo DB sulla somma dei dodicesimi per pagina | `page_content` | Validazione solo applicativa (`App\Support\PageCapacity`, dal 2026-10-27); un CHECK sulla somma richiederebbe un trigger |
| 32 | `HANDOFF.md` ha la riga "Ultimo aggiornamento" ferma al 2026-09-01 e cita "Laravel 11" nello "Stato generale" | `HANDOFF.md` righe 3 e 7 | Il progetto è su Laravel 12 dal 2026-07-27 (stesso file, sezione dedicata) |

## Calcolo peso rivista — assunzioni non verificate e limiti (aggiunto il 2026-10-26)

| # | Assunzione o limite | Dove | Note |
|---|---|---|---|
| 33 | **Significato del +2 % di maggiorazione** (inchiostro? colla/legatura? cellophane?) non documentato: riprodotto dall'Excel com'è | default di `maggiorazione_percentuale` nelle migration `2026_10_26_*` | Da confermare con la tipografia ([DA-VERIFICARE.md](DA-VERIFICARE.md), DV-18). La calibrazione col peso reale serve a correggerlo nel tempo |
| 34 | **Tolleranze di default** 4 % sulla grammatura, 1 mm sul formato, ±1 punto sulla maggiorazione: valori del prompt di sviluppo, non presi da schede tecniche | default di colonna, `tolleranza_*` | Il margine ± g dipende interamente da questi valori (DV-19) |
| 35 | **Peso del bancale** 20 kg fisso per tutti i bancali | `peso_bancale_kg` | Dall'Excel; da verificare con chi spedisce (DV-20) |
| 36 | **Scaglioni** 250/350/500/1000 g con limite **inclusivo**: non verificati contro il listino postale in vigore | `config/peso_rivista.php` | DV-21 |
| 37 | **Pagine interne = `total_pages − pagine_copertina`**: presuppone che nel timone le copertine occupino posizioni dentro `total_pages` (oggi è così, vedi Flusso 4 di [03-dominio-e-flussi.md](03-dominio-e-flussi.md)) | `Issue::weightInput()` | Se la redazione inserisse nel timone solo le pagine interne, il peso sarebbe sottostimato di `pagine_copertina` pagine (DV-22) |
| 38 | **Riviste esistenti senza parametri**: non è stato possibile riconoscere con certezza NM, GL e F&C (nel repo ci sono solo le riviste demo del seeder) | migration `2026_10_26_*` | Formato e grammature vanno inseriti a mano in "Modifica rivista" e in ogni numero esistente |
| 39 | **Nessun aggiornamento realtime della modifica dei parametri**: `WeightPanel::saveParams()` non emette eventi broadcast, quindi gli altri utenti vedono i nuovi parametri solo al prossimo render (ricarica o cambio pagine) | `app/Livewire/Timone/WeightPanel.php` | Scelta per non aggiungere un evento e un listener in `Grid` per un dato che cambia di rado |
| 40 | **Simulazione a passi di 2 pagine** (un foglio), non di segnatura | `MagazineWeightCalculator::addablePages()` | In stampa si aggiungono di solito 4/8/16 pagine: il dato "pagine aggiungibili" è un massimo teorico |
| 41 | Le modifiche ai parametri di peso **sono** nella cronologia (`activity_logs`, azioni `issue.weight_params_changed` e `magazine.weight_defaults_changed`), ma le modifiche **agli altri campi** della rivista in `Magazines\Edit` (nome, colore, soglia, redattore) continuano a non essere registrate | `app/Livewire/Magazines/Edit.php` | Preesistente, non esteso per restare nel perimetro della funzionalità |
| 42 | Decimal dei parametri di peso senza cast `decimal:2` | `app/Models/Magazine.php`, `app/Models/Issue.php` | Voluto: evita la deprecation di `brick/math` (#30). La conversione a float è centralizzata in `MagazineWeightInput::fromArray()` |
| 43 | ~~Formato senza intervallo di validazione~~ **Risolto il 2026-10-26**: base e altezza ammesse tra 50 e 1000 mm (`config/peso_rivista.php` → `formato`), nei form e nel calcolo. Resta da correggere a mano il **numero 3 "Novembre 2026" del DB locale** (21 × 27): ora mostra l'errore invece di 5,1 g | `WeightLimits`, `WeightParameters::rules()` | Diagnosi in [10-debugging.md](10-debugging.md#peso-rivista) |
| 44 | **Limiti opzionali nel calculator**: `MagazineWeightCalculator::calculate()` controlla grammature (30–400 g/m²) e formato (50–1000 mm) solo se riceve un `WeightLimits` | `app/Support/Weight/MagazineWeightCalculator.php` | Voluto per tenerlo puro (vedi `HANDOFF.md`, Decisioni). Ogni nuovo punto che calcola il peso deve passare `WeightLimits::fromConfig()` |

## Fase 1 del batch "revisione con gli utenti" (aggiunto il 2026-10-26)

| # | Problema | Dove | Note |
|---|---|---|---|
| 45 | `ContentCreate::_uploadErrored()` sovrascrive un metodo **interno** di Livewire (`WithFileUploads::_uploadErrored`, nome con underscore) per loggare e tradurre l'errore | `app/Livewire/Timone/ContentCreate.php` | Da ricontrollare a ogni aggiornamento di Livewire: se cambia firma o nome, il log `upload_failed` smette di essere scritto (gli upload continuano a funzionare) |
| 46 | `RequestRootSignedUploadUrl` estende una classe interna di Livewire (`GenerateSignedUploadUrl`); dal 2026-10-28 non è più collegata (binding commentato nella versione di produzione di `AppServiceProvider`), resta usata solo dalla diagnostica | `app/Support/Uploads/RequestRootSignedUploadUrl.php` | Da togliere quando la diagnostica non la usa più |
| 46b | Le macro di produzione in `AppServiceProvider::register()` sostituiscono `Request::hasValidSignature()` per TUTTA l'app e usano l'alias globale `\\URL` (l'IDE lo segnala come tipo non definito, a runtime funziona); la macro `alternateHasCorrectSignature` ritorna `null` invece di `false` se nessuna chiave corrisponde (equivale a falso) | `app/Providers/AppServiceProvider.php` (protetto, CLAUDE.md §4) | Solo su richiesta esplicita dell'utente |
| 47 | Le proporzioni delle miniature (`formato_base_mm`/`formato_altezza_mm`) sono lette dal modello `Issue` di `Grid`: dopo una modifica del formato nel pannello Peso servono un ricaricamento o un aggiornamento del timone | `Grid::render()` | Cosmetico; il formato cambia raramente |
| 48 | MultiDrag/FLIP/zoom non hanno test automatici (niente Dusk) | `resources/js/timone-alpine.js` | Vedi la checklist manuale in `HANDOFF.md`, sessione 2026-10-26 "Fase 1" |
| 49 | Le proprie modifiche fatte in `Grid` (spostamenti, stato, ecc.) non riallineano `lastKnownPagesUpdatedAt`: in modalità polling il client può mostrarsi il toast "aggiornato da un altro utente" per una modifica propria | `Grid.php` (tutte le chiamate a `markPagesUpdated()`) | Preesistente; corretto solo per i nuovi percorsi dei contenuti (`broadcastContentListChanged()`, `onLocalContentListChanged()`) |

## Fase 2 — copertine separate (aggiunto il 2026-10-26)

| # | Problema | Dove | Note |
|---|---|---|---|
| 50 | Colonna `pages.role` e `magazines.battente_counted_in_total` non più usate dal codice | migration originali | Tenute di proposito: la prima serve al rollback della conversione, cancellarle sarebbe una migration distruttiva. Da togliere quando la conversione è consolidata |
| 51 | Rollback della conversione affidabile solo subito dopo il deploy | `App\Support\Covers\CoverConversion::revertAll()` | Se nel frattempo si aggiungono pagine interne, le posizioni ripristinate possono collidere su `unique(issue_id, position)`: il rollback si ferma con un errore del database (transazione per numero, niente stati parziali) |
| 52 | `Grid.php` cresce ancora (+ `swapCovers`, `addBandella`, `removeBandella`, guardie copertina) | `app/Livewire/Timone/Grid.php` | La logica è in `App\Support\IssueCovers`/`ContentPlacer`; in Grid solo orchestrazione. Proposta per la Fase 6: estrarre cruscotto/riepilogo in un componente dedicato |
| 53 | In vista doppia pagina la II di copertina non è affiancata alla pagina 1 (sta nella fascia copertine sopra) | `grid.blade.php`, `cover-band.blade.php` | Scelta: la richiesta chiede una fascia visivamente separata. Se la redazione vuole vedere l'apertura II + 1, va aggiunta come anteprima nella vista doppia |

## Fase 3 — occupazione in dodicesimi (aggiunto il 2026-10-27)

| # | Problema | Dove | Note |
|---|---|---|---|
| 54 | Colonne non più usate: `contents.page_occupancy`, `page_content.occupied_percentage`, `advertisements.occupied_percentage_override` | migration `2026_10_27_100000_*` | Lasciate (nullable) per il rollback, come `pages.role` nella Fase 2. Da eliminare con una migration dedicata quando il rollback non servirà più |
| 55 | Pubblicità con PDF più lungo del formato e scelta "usa tutte le N pagine": l'occupazione salvata è N pagine intere anche se i campi "Pagine intere"/"Frazione" dicono altro | `ContentCreator::resolveOccupancy()`, `content-create.blade.php` | Il riquadro della scelta lo dice ("la pubblicità occuperà N pagine intere"), ma l'anteprima "Occupa …" sotto i campi mostra i campi. Da allineare nella Fase 4 (modale di modifica) |
| 56 | Occupazione totale del contenuto e somma dei suoi pivot possono divergere (modifica per pagina, "↗ Estendi", rimozione da una pagina) | `Grid::updateContentOccupancy()`, `extendToPage()` | Voluto: la specifica rende modificabile lo spazio per pagina. Il cruscotto usa i pivot per le assegnate. Dalla Fase 4 cambiare l'occupazione dalla modale ridistribuisce (e azzera le regolazioni manuali); lasciarla invariata non tocca le pagine |
| 57 | Barra di riempimento e select dei dodicesimi sulla card verificate solo sul markup (nessun Dusk) | `partials/page-fill.blade.php`, `partials/page-content-entry.blade.php` | Vedi la checklist manuale in `HANDOFF.md` (sessione 2026-10-27) |
| 58 | La migration della Fase 3 gira fuori transazione (`$withinTransaction = false`) | `2026_10_27_100000_*` | Necessario su SQLite (foreign key spente durante `->change()`); su MySQL il DDL non era comunque transazionale. Un errore a metà richiede di rilanciare la migration (è idempotente: ricalcola tutto dalle colonne vecchie) |
| 59 | `ContentCreate.php` cresce ancora (+ `proposeOccupancyFromPdf`) | `app/Livewire/Timone/ContentCreate.php` | Nella Fase 4 la modifica è un componente a sé (`ContentEdit`) e ciò che è comune è condiviso (`ContentFormRules`, trait `HasOccupancyFields`, partial dei campi) invece di un form object Livewire: spostare i campi in `$form.*` avrebbe cambiato ~40 test e ogni `wire:model` del modulo di creazione senza ridurre la logica |

## Fase 4 — gestione contenuti (aggiunto il 2026-10-27)

| # | Problema | Dove | Note |
|---|---|---|---|
| 60 | La modale di modifica non ha la drop zone né il messaggio dettagliato sugli upload falliti (`_uploadErrored`) della creazione | `ContentEdit`, `content-edit.blade.php` | Input file classico; un upload rifiutato dal server mostra il messaggio generico di Livewire. Estendere se serve |
| 61 | Cambiare l'occupazione ridistribuisce sempre dalla PRIMA pagina occupata, anche se il contenuto era stato sparso a mano su pagine non consecutive ("↗ Estendi") | `ContentUpdater::redistribute()` | Scelta di default: la distribuzione di default è sempre su pagine consecutive. Il messaggio d'errore lo dice se non ci sta |
| 62 | ~~Miniatura separata non ridimensionata~~ — la miniatura separata è stata tolta su richiesta dell'utente (2026-10-27): il PDF è già la miniatura | — | Numero lasciato per non rinumerare |
| 63 | Rinomina inline e modale verificate solo su markup e Livewire::test() (nessun Dusk) | `page-content-entry.blade.php`, `content-edit.blade.php` | Checklist manuale in `HANDOFF.md` (sessione Fase 4) |

## Fase 5 — pubblicità previste (aggiunto il 2026-10-27)

| # | Problema | Dove | Note |
|---|---|---|---|
| 64 | Una pubblicità prevista occupa sempre UNA pagina (o una copertina): la doppia pagina e il battente non sono tra i tipi previsti | `App\Enums\PlannedAdKind` | La specifica elenca solo intera, 2/3, 1/2, 1/3, 1/4. Un nuovo tipo = un nuovo case, ma la doppia richiederebbe due posizioni contigue nella regola |
| 65 | La mezza pagina segnaposto nasce sempre orizzontale | `PlannedAdKind::format()` | Orientamento ignoto finché non c'è l'inserzionista; si cambia dalla modale |
| 66 | Il posizionamento ignora i tipi di pagina copiati da "Duplica struttura" | `PlaceholderAdCreator` | Le pagine "pubblicità" del numero d'origine non attirano i segnaposto: la regola è quella della specifica, uguale per ogni numero |
| 67 | ~~Il contatore dei segnaposto fa una query a ogni render del timone~~ — risolto nella Fase 6: conteggio in `IssueSummary` sulle collezioni già caricate | ~~`PlaceholderAdCounter`~~ (rimosso) | Una query leggera (whereHas su contents per numero). Da accorpare nel riepilogo della Fase 6 |

## Fase 6 — riepilogo stile dashboard (aggiunto il 2026-10-27)

| # | Problema | Dove | Note |
|---|---|---|---|
| 68 | Colonna `magazines.ad_threshold_percentage` non più usata | migration originale | Soglie tolte su richiesta della specifica; colonna lasciata coi valori. Da eliminare con una migration dedicata se la soglia non tornerà |
| 69 | Il riepilogo ricalcola il peso a ogni render del timone (anche col pannello Peso che lo calcola già) | `Grid::render()`, `WeightPanel::render()` | Calcolo puro e veloce, nessuna query in più (parametri sull'issue). Due componenti separati: condividerlo richiederebbe uno stato comune non giustificato |
| 70 | Barra sticky misurata con un ResizeObserver nel layout | `layouts/app.blade.php` | Senza JS (o prima dell'init) la barra si ferma a `top: 0` e scorre sotto l'header per un istante. Verifica nel browser in DV-34 |
| 71 | "Occupate" nel riepilogo = pagine **piene**; le parziali sono a parte | `IssueSummary` | Interpretazione della specifica ("totali / occupate / bianche / parziali"): le quattro voci si sommano al totale |

## Fase 7a — disposizione dei contenuti (aggiunto il 2026-10-27)

| # | Problema | Dove | Note |
|---|---|---|---|
| 72 | Gli articoli sono sempre disegnati orizzontali | `Page::layout()` | Scelta confermata dall'utente; una scelta per pagina richiederebbe un campo sul pivot |
| 73 | Con zoom S e molti contenuti i blocchi sono piccoli: testo troncato, comandi stretti | `page-card.blade.php` | Nome completo nel tooltip del blocco; comandi visibili solo su hover/focus |
| 74 | ~~L'export PDF del timone non mostra la disposizione~~ — risolto il 2026-10-27 (`App\Support\ExportPageLayout`) | `resources/views/exports/timone.blade.php` | — |
| 75 | I blocchi sono focalizzabili (Tab) e bloccano le frecce sinistra/destra per non spostare la pagina mentre si usa un blocco | `page-card.blade.php` | Per spostare la pagina da tastiera il focus va sulla card, non sul blocco |
| 76 | Nelle query su `$content->pages()` la colonna `position` è ora ambigua (c'è anche `page_content.position`): va scritta `pages.position` | relazioni `Content::pages()` | Scoperto dai test (SQLite "ambiguous column name"); nel codice dell'app nessun caso, nei test corretti. `Issue::pages()` non è toccata (nessun pivot) |
| 77 | Il passo 7c (drag&drop dei blocchi dentro la card) non è fatto | `page-card.blade.php`, `resources/js/timone-alpine.js` | Da decidere con l'utente dopo aver provato ↑/↓: rischio di conflitto con il Sortable delle pagine (MultiDrag, maniglia) e con il morph di Livewire |
| 78 | ~~Nell'export PDF una pagina mostrava UNA sola miniatura~~ — risolto il 2026-10-27 su segnalazione dell'utente: un blocco con la propria miniatura per contenuto | `exports/timone.blade.php` | — |
| 79 | Miniature WEBP assenti dall'export dove la GD non supporta WEBP (es. container di sviluppo) | `App\Support\ExportThumbnail` | Convertirle richiederebbe Imagick/GD con WEBP; per i PDF la miniatura è sempre PNG |

## Revisione 2, Fase 1 — bug (aggiunto il 2026-10-27)

- **Verifiche nel browser fuori dal repo**: focus del campo pagine, menu «⋯» a tutti gli zoom e auto-scroll sono stati verificati con uno script CDP su Chrome headless, tenuto nella cartella temporanea della sessione e non nel repo. Il progetto resta senza test browser (niente Dusk, regola «nessuna dipendenza nuova»); i test PHP coprono markup e logica server. Se i bug del browser tornano spesso, valutare Dusk o Playwright (dipendenza da approvare).
- **Auto-scroll con eventi sintetici**: il gestore `drag-autoscroll.js` è verificato con `DragEvent` sintetici (stesso codice degli eventi veri); il drag nativo reale con il mouse non è automatizzabile in Chrome headless senza intercettare il drag. Da riprovare a mano su Chrome, Firefox e Safari.
- **Avvisi solo al blur** solo nel modulo di creazione numero (e nel componente `x-weight-field`, quindi anche modale Peso e impostazioni rivista). Altri moduli con anteprime live (`ContentCreate` pagine del PDF, occupazione) usano ancora `debounce.300ms` e mostrano gli avvisi durante la digitazione: non segnalato dagli utenti.
- **Proprietà `?int` con `wire:model.live`** ancora presenti: `HasOccupancyFields::$whole_pages`, `ContentCreate::$pdfPageFrom/$pdfPageTo` (con debounce 300 ms). Hanno lo stesso rischio teorico di cifre perse scrivendo numeri a più cifre molto velocemente; non toccate perché fuori dal perimetro del bug (form del numero). Da convertire a `string` + parsing se gli utenti lo segnalano.
- **Conteggio pagine nella richiesta di upload**: ora con Ghostscript (~0,03 s), ma resta sincrono. Se `gs` manca sul server il ripiego Imagick può impiegare decine di secondi su PDF di stampa (avviso in `timone:diagnose-upload` solo indiretto, tramite «Lettura PDF (Imagick)»).
- **`TIMONE_UPLOAD_MAX_KB` assente da `.env.example`** (regola del batch: niente modifiche a `.env*` senza dirlo). Facoltativa con default 100 MB.

## Revisione 2, Fase 2 — interazione con pagine e contenuti (aggiunto il 2026-10-27)

- **`PageLayoutPlanner` (Fase 7a) resta solo per la conversione** (`GridConversion`, dry-run `timone:grid-dry-run`): da togliere quando tutte le installazioni avranno fatto la migration `2026_10_27_600000`.
- **Posizione di partenza solo come preferenza nella ridisposizione**: quando i rettangoli salvati lasciano lo spazio nella forma sbagliata (es. due vetrine in diagonale + una mezza pagina) il planner ridispone tutta la pagina; un contenuto spostato a mano può quindi cambiare posto. Raro, documentato in `PageGridPlanner::resolve()`.
- **Forma a L in ordine di lettura**: un 3/4 prende le celle libere riga per riga; con altri contenuti in posizioni strane la L può avere più di due pezzi. Accettabile per una rappresentazione dello spazio.
- **Rotazione del piedino**: piedino → 1/3 verticale → 1/3 orizzontale (non torna piedino: il piedino è un 1/3 orizzontale ancorato in fondo, la rotazione non lo sa).
- **Ritaglio non applicato nell'export PDF del timone** (dompdf): lì le miniature restano adattate al blocco. La Fase 5 rifà l'esportazione della rivista con il ritaglio.
- **File caricati prima della Fase 2.4**: senza `aspect_ratio` l'anteprima resta intera finché non si esegue `timone:file-ratios`.
- **Frecce solo col mouse/Tab nel menu**: nessuna scorciatoia da tastiera dedicata (la Fase 4 parla di «tastiera» per i permessi del commerciale).
- **Fascia sticky non sticky sotto i 640 px**: su un telefono occuperebbe un terzo dello schermo.
- **`placement` controllato prima di creare, scritto dopo**: tra il controllo e la scrittura un collega può occupare la pagina; in quel caso il contenuto viene creato e resta da assegnare con un errore (stesso comportamento del vecchio percorso «📎 Carica materiale»). Una transazione unica non era possibile: `ContentCreator` salva i file e accoda i job delle miniature.

## Revisione 2, Fase 3 — segnaposto dal modulo e caricamento multiplo (aggiunto il 2026-10-28)

- **`is_placeholder` è un valore memorizzato**: ogni nuovo percorso che cambia cliente o file di una pubblicità deve chiamare `AdPlaceholder::sync()` (oggi lo fanno creazione, modifica e rinomina). Un percorso dimenticato lascia un contatore «Da assegnare» sbagliato finché `timone:placeholder-sync` non lo riallinea.
- **Caricamento multiplo: la coda vive nella pagina**: chiudendo o ricaricando la scheda i file non ancora partiti si perdono (quelli già creati restano). Una coda lato server (job) servirebbe solo per lotti molto grandi.
- **Pubblicità del caricamento multiplo come «Pagina intera»**: formato da correggere a mano; nessuna deduzione del formato dalle misure del PDF.
- **Duplicati confrontati in PHP** su tutti i titoli del numero (una query per file): trascurabile con centinaia di contenuti, da rivedere se un numero ne avesse decine di migliaia.
- **Toast del polling e cronologia**: `Grid::onlyOwnChangesSince()` si fida di `activity_logs`; un'azione che chiama `markPagesUpdated()` senza scrivere in cronologia continua a mostrare il toast anche quando è dell'utente stesso (comportamento di prima).

## Revisione 2, Fase 4 — ruoli ed eliminazioni (aggiunto il 2026-10-28)

- **`movePage` controlla le pagine che si spostano, non quelle che scorrono**: spostando una pagina di sole pubblicità, le pagine intermedie (anche con articoli) cambiano numero. Interpretazione della specifica («spostamenti di pagine … che contengono contenuti editoriali»); se la redazione la vuole più stretta, il punto è `TimoneAbilities::canMovePages()`.
- **Commerciale e PDF multipagina ancorato a una pagina**: «sovrascrivi le pagine in conflitto» può collegare in un gruppo pagine che contengono anche articoli (non li sposta né li toglie). «Sposta le pagine dopo» è invece riservato alla redazione.
- **«Ultimo admin» in `UserPolicy::delete`**: con dati coerenti è già escluso da «mai sé stessi»; resta come difesa per richieste partite prima di un cambio di ruolo.
- **Sessioni dell'utente eliminato**: cancellate subito solo con `SESSION_DRIVER=database`; con altri driver l'utente è comunque fuori alla richiesta successiva (non esiste più).
- **Nessun cestino**: un numero o una rivista eliminati si recuperano solo dal backup (DB + volume dei file).
- **Comandi della barra ancora visibili al Commerciale**: «Selezione multipla» e «Modalità scambio» restano (servono a spostare pagine di sole pubblicità); le azioni di massa su stato, blocco e gruppi compaiono solo alla redazione, «Scollega selezionate» resta visibile e risponde 403.

## Revisione 2, Fase 5 — PDF sfogliabile (aggiunto il 2026-10-28)

- **FPDI gratuito e PDF compressi**: i sorgenti 1.5+ con xref compresse vengono riscritti in 1.4 con Ghostscript (`-dPDFSETTINGS=/prepress`) prima dell'import: tempo in più e una possibile, piccola differenza rispetto al file originale. L'alternativa senza riscrittura è il parser commerciale di Setasign.
- **TCPDF tiene il PDF in RAM**: con numeri di molti GB di sorgenti serve più memoria del default (1024M); il job ha un solo tentativo.
- **Disco `local` obbligatorio per i sorgenti**: `SourceFiles::path()` usa `Storage::disk()->path()`, che c'è solo per i dischi locali (oggi tutti). Un disco S3 richiederebbe di scaricare i file in una cartella temporanea.
- **Variante leggera lenta**: Ghostscript ricomprime tutto (80 s per 68 pagine da 72 MB nella prova).
- **Ordine della bandella**: dopo la I di copertina (ordine di sfoglio di `CoverSlot`); la specifica non la cita.
- **Nessuna notifica a fine export**: lo stato si vede nel pannello (che si aggiorna da solo mentre è aperto), non arriva un avviso altrove.

## Annulla/ripeti (aggiunto il 2026-10-28)

- **Fotografia di tutto il timone del numero a ogni azione** (due volte): poche centinaia di righe, ma rende la suite di test più lenta e ogni azione un po' più pesante; con numeri molto grandi andrebbe ristretta alle righe coinvolte.
- **Lock della riga del numero** durante ogni azione annullabile: le scritture sullo stesso numero passano una alla volta (con MySQL; SQLite lo ignora).
- **Creazione/modifica con file senza transazione**: tra le due fotografie un collega può scrivere sullo stesso numero. Il conflitto all'annulla NON lo fermerebbe (il valore attuale è proprio il «dopo» del passo), quindi dal 2026-10-29 il passo tiene solo le righe del contenuto creato/modificato, delle pagine su cui sta e dei loro gruppi (`TimelineDiff::onlyContents()`, parametro `$scope` di `run()`). Resta un caso limite: un collega che cambia proprio una di quelle pagine nello stesso istante finisce nel passo.
- **Azioni in transazione**: la fotografia «dopo» non vede le scritture dei colleghi perché MySQL usa REPEATABLE READ (stessa vista per tutta la transazione). Con un database in READ COMMITTED (es. PostgreSQL di default) servirebbe lo stesso filtro anche lì.
- **Un passo non annullabile blocca la pila**: scelta prudente (annullare i passi più vecchi saltandolo produrrebbe stati mai visti).
- **Sostituzione del PDF non annullabile** (scelta dell'utente): il file vecchio si cancella subito.
- **Autore del conflitto dal registro attività**: è l'ultima voce di un altro utente sul numero dopo la mia azione, non necessariamente quella sulla stessa riga.

## Cose che sembrano sbagliate ma hanno un motivo (non "correggerle")

| Cosa | Perché è così |
|---|---|
| `trustProxies(at: '*')` | Intranet interna dietro proxy (`bootstrap/app.php`, commento). Da rivalutare **solo** se l'app viene esposta direttamente su internet senza proxy |
| `fakerphp/faker` in `require` e non in `require-dev` | Serve al seeder anche nell'immagine `--no-dev` |
| `config.platform.ext-imagick` "finto" in `composer.json` | Permette `composer install` dove Imagick manca (host di sviluppo, stage `composer:2`) |
| Livewire fissato a 3.x nonostante la v4 | Semantica richiesta e refactoring non banale |
| Eventi `ShouldBroadcastNow` invece che in coda | Realtime anche senza worker (con il costo descritto al punto 7) |
| `display_errors=Off` | Un warning PHP rompeva il JSON degli upload Livewire |
| Doppio UPDATE con offset nel riordino | Vincolo `unique(issue_id, position)` |
| Nessun fallback "1 pagina" quando il PDF è illeggibile o l'occupazione manca | Il fallback silenzioso era la causa di un bug reale |
| `phpunit.xml` non imposta `BROADCAST_CONNECTION` | Con `log`/`null` il test di autorizzazione del canale presence perderebbe di significato |
| Classi `App\Support` statiche e senza interfacce | Scelta pragmatica dichiarata (`CLAUDE.md`, regola 3) |
| Nessun `@livewireScripts` esplicito e nessun import di Alpine | Livewire 3 inietta tutto da solo; un secondo Alpine rompeva `$wire` |
