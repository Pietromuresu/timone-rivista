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
| 9 | `Grid`, `ContentCreate` e `CoverSection` non fanno `authorize('view')` nel `mount()` | `app/Livewire/Timone/*.php` | Protetti da `Issues\Show::mount()`. Se un domani vengono esposti come rotta diretta, perdono la protezione. Fix: `authorize` ridondante |
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
| 25 | Nessun "riprova" per una miniatura fallita e nessuna modifica di un contenuto esistente (incluso il suo file e l'occupazione) | `ContentCreate` | `HANDOFF.md`, sessione 2026-09-02 |
| 26 | Stato issue `in_lavorazione` mai impostato dal codice; un numero `chiuso` resta modificabile | `app/Enums/IssueStatus.php`, `IssuePolicy::update` | Forse voluto: va chiarito con la redazione |
| 27 | Ruolo `commerciale` identico a `sola_lettura` | `app/Policies/*`, `MANUALE_UTENTE.md` §14 | Ruolo predisposto, mai sviluppato |
| 28 | Codice morto o residuo: `setup-old.sh`; job `GeneratePageFileThumbnail` (non più accodato); comando `inspire`; `tests/*/ExampleTest.php`; `laravel/sail` e `laravel/breeze` in require-dev; `axios` caricato ma mai chiamato; variabili `.env.example` inutilizzate (`MEMCACHED_HOST`, `AWS_*`, `VITE_APP_NAME`); rotte `verify-email` (il modello `User` non implementa `MustVerifyEmail`) | vari | Pulizia a basso rischio |
| 29 | `TIMONE_DEADLINE_REMINDER_DAYS_BEFORE` assente da `.env.example` | `config/timone.php` | Documentarla |
| 30 | Warning di deprecazione `brick/math` ("Passing floats to BigNumber::of()") sui cast `decimal` | `PageContent::occupied_percentage`, ecc. | Non funzionale (`HANDOFF.md` §Decisioni) |
| 31 | Nessun vincolo DB sulla somma delle percentuali per pagina | `page_content` | Validazione solo applicativa (`PagePercentageAllocator`) |
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
| 46 | `RequestRootSignedUploadUrl` estende una classe interna di Livewire (`GenerateSignedUploadUrl`) | `app/Support/Uploads/RequestRootSignedUploadUrl.php` | Stesso rischio di #45. Coperto da `tests/Feature/UploadDiagnosticsTest.php` |
| 47 | Le proporzioni delle miniature (`formato_base_mm`/`formato_altezza_mm`) sono lette dal modello `Issue` di `Grid`: dopo una modifica del formato nel pannello Peso servono un ricaricamento o un aggiornamento del timone | `Grid::render()` | Cosmetico; il formato cambia raramente |
| 48 | MultiDrag/FLIP/zoom non hanno test automatici (niente Dusk) | `resources/js/timone-alpine.js` | Vedi la checklist manuale in `HANDOFF.md`, sessione 2026-10-26 "Fase 1" |
| 49 | Le proprie modifiche fatte in `Grid` (spostamenti, stato, ecc.) non riallineano `lastKnownPagesUpdatedAt`: in modalità polling il client può mostrarsi il toast "aggiornato da un altro utente" per una modifica propria | `Grid.php` (tutte le chiamate a `markPagesUpdated()`) | Preesistente; corretto solo per i nuovi percorsi dei contenuti (`broadcastContentListChanged()`, `onLocalContentListChanged()`) |

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
