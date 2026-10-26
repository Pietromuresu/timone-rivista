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
| `VITE_REVERB_APP_KEY` vuota nel bundle | Console: `You must pass your app key`. **Tutto** `app.js` si interrompe: niente Alpine store, niente Sortable | Verifica `REVERB_APP_KEY` nel `.env`, poi `docker compose up -d --build` |
| `VITE_REVERB_HOST/PORT/SCHEME` sbagliati per l'URL pubblico | Network → WS: connessione fallita verso `localhost:8080`, oppure `ws://` bloccato su una pagina `https://` (mixed content) | Allinea le `VITE_REVERB_*` al dominio/proxy reale e ricostruisci (vedi [09-deploy.md](09-deploy.md#5-https-e-websocket)) |
| `/livewire/livewire.js` restituisce 404 | Network | La location statica `.js` di nginx deve avere `try_files ... /index.php` (già presente in `docker/nginx/default.conf`; bug del 2026-07-29) |
| Due istanze di Alpine | Console: `Detected multiple instances of Alpine running`, `$wire is not defined` | Non importare `alpinejs` in `app.js` (vedi commento `resources/js/app.js:5-19`) |
| Asset da un Vite dev server non attivo | Gli `<script>` puntano a `localhost:5173` | Cancella `public/hot` (solo in locale) |
| Asset vecchi dopo un deploy | Comportamento "vecchio" solo per qualche utente | Refresh forzato. Gli asset hanno nomi hashati, ma la cache nginx è di 7 giorni sugli statici |
| WebSocket giù | Avviso giallo nel timone "⚠️ Aggiornamento in tempo reale non disponibile" (`grid.blade.php:618-622`); parte il polling ogni 1 s (`grid.blade.php:95`) | L'app continua a funzionare con il polling: risolvi Reverb |

### Upload PDF bloccato / "si blocca al caricamento"

| Causa | Dove guardare | Soluzione |
|---|---|---|
| File > 100 MB (`upload_max_filesize`) o richiesta > 110 MB (`post_max_size`) | `docker compose logs app`: `POST Content-Length ... exceeds the limit` | Limiti in `docker/php/uploads.ini`, `config/livewire.php:84` (`max:102400`) e `client_max_body_size 120m` (nginx, e nel reverse proxy se presente): **vanno alzati insieme** |
| 413 da nginx o dal proxy | log di nginx | `client_max_body_size` |
| Upload lento oltre 10 minuti | `max_upload_time` in `config/livewire.php:92` | Alzalo |
| "PDF illeggibile" | Imagick o Ghostscript assenti, policy PDF attiva, PDF corrotto | `php -m`, `Imagick::queryFormats("PDF")`; prova il PDF in locale con `gs` |
| Pubblicità con PDF di più pagine del formato | Messaggio di validazione | È voluto (`HANDOFF.md`, "Limite noto": il formato è l'occupazione) |
| Occupazione non scelta | Errore sotto il menu "Occupazione" | È voluto: l'occupazione è obbligatoria (`MANUALE_UTENTE.md` §5bis) |

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
12. **Nessun test browser** (niente Dusk): drag&drop, focus/anelli di selezione e drop zone sono verificati solo a mano.

## Test che falliscono con "Could not resolve host: reverb"

`phpunit.xml` non sovrascrive `BROADCAST_CONNECTION`, quindi i test ereditano `reverb` dal `.env`. Ogni test che esegue una mutazione deve usare `Event::fake([...])` sugli eventi broadcast. **Non** impostare `BROADCAST_CONNECTION=log`/`null` in `phpunit.xml`: `LogBroadcaster::auth()` non applica il 403 e romperebbe `PresenceChannelAuthTest` (`HANDOFF.md` §Decisioni).
