# 05 — Configurazione (variabili d'ambiente)

[← Indice](README.md) · Vedi anche: [01-setup-locale.md](01-setup-locale.md), [09-deploy.md](09-deploy.md)

## Come funziona la configurazione in questo progetto

- Un unico file **`.env`** nella root, letto da:
  - Laravel (container `app`, `worker`, `reverb`, `scheduler` tramite `env_file: .env` in `docker-compose.yml`);
  - **Docker Compose stesso**, per l'interpolazione: `MYSQL_DATABASE`/`MYSQL_USER`/`MYSQL_PASSWORD` del container `mysql` sono **le stesse** `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`, più `MYSQL_ROOT_PASSWORD`;
  - i build arg `VITE_REVERB_*` delle immagini.
- **`.env.example`** è commentato riga per riga ed è la base del `.env`. `setup.sh` genera da solo le 6 variabili segrete se sono vuote.
- `config/timone.php` contiene i parametri specifici del progetto (tolleranza formato PDF, preavviso scadenza, posizionamento delle pubblicità previste).
- **Variabili `VITE_*`**: vengono inserite nel bundle JS **al momento della build**. In Docker passano come `build.args` (il `.env` è escluso dal contesto di build da `.dockerignore`). Se cambiano, serve **ricostruire le immagini** (`docker compose up -d --build`); non basta un restart.
- Dopo un `php artisan config:cache` le `env()` fuori da `config/` non vengono più lette. Il progetto non usa `env()` fuori da `config/` (verificato con grep su `app/`, `routes/`, `database/`).

> Regola: **nessun valore segreto in questo documento.** Dove serve un segreto, è indicato come ottenerlo.

## Tabella completa

Legenda colonna *Obbl.*: **Sì** = senza, l'app o lo stack non partono o una funzione chiave si rompe; *Prod* = obbligatoria da cambiare/impostare in produzione; No = opzionale.

### Applicazione

| Variabile | Obbl. | Default (codice) | Esempio formato | Usata in | Dove si ottiene il valore |
|---|---|---|---|---|---|
| `APP_NAME` | No | `Laravel` | `"Timone Rivista"` | `config/app.php:16`, mail `from.name`, prefissi cache/sessione | Scelta libera (`.env.example`: `Timone Rivista`) |
| `APP_ENV` | Prod | `production` | `local` / `production` | `config/app.php:36` | `production` sul server |
| `APP_KEY` | **Sì** | nessuno | `base64:<44 caratteri>` | `config/app.php:107` (cifratura sessioni/cookie, firma degli snapshot Livewire) | Generata da `setup.sh` (`openssl rand -base64 32`) oppure `php artisan key:generate`. **Non va mai cambiata in produzione**: invaliderebbe sessioni e dati cifrati |
| `APP_DEBUG` | Prod | `false` | `true` / `false` | `config/app.php:49` | **`false` in produzione** (`.env.example` ha `true`) |
| `APP_URL` | **Sì** | `http://localhost` | `https://timone.example.org` | `config/app.php:62`; `AppServiceProvider::boot()` forza root URL e schema https (tranne l'URL firmato dell'upload temporaneo Livewire, firmato sull'indirizzo reale della richiesta dal 2026-10-26: `App\Support\Uploads\RequestRootSignedUploadUrl`); `{{APP_URL}}` nel manuale | URL pubblico reale, **identico** a quello nella barra del browser (schema, nome, porta): `php artisan timone:diagnose-upload` e la voce di log `upload_failed` mostrano le differenze. ⚠️ DA VERIFICARE: URL di produzione |
| `APP_TIMEZONE` | No | `UTC` | `Europe/Rome` | `config/app.php:75`; **orario dello scheduler** (07:30) | Decisione di business: oggi i solleciti partono alle 07:30 UTC |
| `APP_LOCALE` | No | `en` | `it` | `config/app.php:88` | `.env.example`: `it` (traduzioni in `lang/it/`) |
| `APP_FALLBACK_LOCALE` | No | `en` | `en` | `config/app.php:90` | — |
| `APP_FAKER_LOCALE` | No | `en_US` | `it_IT` | factory/seeder | — |
| `APP_PREVIOUS_KEYS` | No | vuoto | `base64:...,base64:...` | `config/app.php:111` | Solo per una rotazione di `APP_KEY` |
| `APP_MAINTENANCE_DRIVER` | No | `file` | `file` | `config/app.php:129` | — |
| `PHP_CLI_SERVER_WORKERS` | No | — | `4` | solo `php artisan serve` | Irrilevante in Docker |
| `BCRYPT_ROUNDS` | No | `12` | `12` | hashing password | — |

### Log

| Variabile | Obbl. | Default | Esempio | Usata in | Dove si ottiene |
|---|---|---|---|---|---|
| `LOG_CHANNEL` | No | `stack` | `stack` | `config/logging.php:21` | — |
| `LOG_STACK` | No | `single` | `single` o `daily` | canali dello stack | Consigliato `daily` in produzione (vedi [10-debugging.md](10-debugging.md)) |
| `LOG_LEVEL` | No | `debug` | `warning` | `config/logging.php` | `warning`/`error` in produzione |
| `LOG_DAILY_DAYS` | No | `14` | `30` | canale `daily` | Non presente in `.env.example` |
| `LOG_DEPRECATIONS_CHANNEL` | No | `null` | `null` | — | — |

### Database

| Variabile | Obbl. | Default | Esempio | Usata in | Dove si ottiene |
|---|---|---|---|---|---|
| `DB_CONNECTION` | **Sì** | `sqlite` | `mysql` | `config/database.php:19` | `mysql` con Docker |
| `DB_HOST` | **Sì** | `127.0.0.1` | `mysql` | idem | Nome del servizio compose `mysql` |
| `DB_PORT` | No | `3306` | `3306` | idem | — |
| `DB_DATABASE` | **Sì** | `laravel` (Laravel); il compose usa `timone` | `timone` | Laravel + `MYSQL_DATABASE` del container + `backup` | `.env.example`: `timone` |
| `DB_USERNAME` | **Sì** | `root` (Laravel); il compose usa `timone` | `timone` | Laravel + `MYSQL_USER` | `.env.example`: `timone` |
| `DB_PASSWORD` | **Sì** | nessuno (compose fallisce con errore esplicito) | 40 caratteri hex | Laravel + `MYSQL_PASSWORD` + `backup` | Generata da `setup.sh` (`openssl rand -hex 20`). Su un server nuovo: generane una tu. **Se il volume `mysql_data` esiste già, cambiarla nel `.env` NON cambia la password in MySQL** |
| `MYSQL_ROOT_PASSWORD` | **Sì** (solo compose) | nessuno (compose fallisce) | 40 caratteri hex | `docker-compose.yml` (container mysql, healthcheck), restore in `DOCKER.md` | Generata da `setup.sh` |

### Sessione, cache, code, Redis

| Variabile | Obbl. | Default | Esempio | Usata in | Dove si ottiene |
|---|---|---|---|---|---|
| `SESSION_DRIVER` | No | `database` | `database` | `config/session.php:21` | — |
| `SESSION_LIFETIME` | No | `120` (minuti) | `120` | `config/session.php:35` | — |
| `SESSION_ENCRYPT` | No | `false` | `false` | — | — |
| `SESSION_PATH` / `SESSION_DOMAIN` | No | `/` / `null` | — | — | — |
| `SESSION_SECURE_COOKIE` | Prod se HTTPS | `null` | `true` | `config/session.php:172` | Non in `.env.example`: impostare `true` se il sito è servito in HTTPS |
| `CACHE_STORE` | No | `database` | `redis` | `config/cache.php:18` | `.env.example`: `redis` |
| `CACHE_PREFIX` | No | slug di `APP_NAME` + `_cache_` | vuoto | `config/cache.php:106` | `.env.example` lo lascia vuoto |
| `QUEUE_CONNECTION` | **Sì** | `database` | `redis` | `config/queue.php:16` | Deve coincidere con il comando del `worker` (`queue:work redis`) |
| `BROADCAST_CONNECTION` | **Sì** | `null` | `reverb` | `config/broadcasting.php:18` | `reverb` (con `null` il realtime tace, ma non va usato nei test di presence, vedi [10-debugging.md](10-debugging.md)) |
| `FILESYSTEM_DISK` | No | `local` | `local` | `config/filesystems.php:16` | Il codice usa comunque `'local'` esplicito |
| `REDIS_CLIENT` | No | `phpredis` | `phpredis` | `config/database.php` | Richiede `ext-redis` (presente nell'immagine) |
| `REDIS_HOST` | **Sì** | `127.0.0.1` | `redis` | idem | Nome del servizio compose |
| `REDIS_PASSWORD` | No | `null` | — | idem | Redis nel compose non ha password (rete interna, porta non esposta) |
| `REDIS_PORT` | No | `6379` | `6379` | idem | — |
| `MEMCACHED_HOST` | No | — | — | non usata | Residuo dello scaffold |

### Reverb (WebSocket)

| Variabile | Obbl. | Default | Esempio | Usata in | Dove si ottiene |
|---|---|---|---|---|---|
| `REVERB_APP_ID` | **Sì** | nessuno | 12 caratteri hex | `config/broadcasting.php:37`, `config/reverb.php` | Generata da `setup.sh` |
| `REVERB_APP_KEY` | **Sì** | nessuno | 20 caratteri hex | server + **`VITE_REVERB_APP_KEY`** (il compose la copia nel build arg) | Generata da `setup.sh` |
| `REVERB_APP_SECRET` | **Sì** | nessuno | 32 caratteri hex | firma server ↔ Reverb | Generata da `setup.sh` |
| `REVERB_HOST` | **Sì** | nessuno | `reverb` | dove **il server PHP** pubblica gli eventi | Nome del servizio compose (rete interna) |
| `REVERB_PORT` | No | `443` | `8080` | idem | `.env.example`: 8080 |
| `REVERB_SCHEME` | No | `https` | `http` | idem | `http` sulla rete interna Docker |
| `REVERB_SERVER_HOST` | No | `0.0.0.0` | `0.0.0.0` | bind del processo `reverb:start` | — |
| `REVERB_SERVER_PORT` | No | `8080` | `8080` | idem; deve coincidere con `ports` e healthcheck del compose | — |
| `VITE_REVERB_APP_KEY` | **Sì** (per il realtime) | — | — | `resources/js/echo.js` (build) | Nel compose è **sovrascritta** dal build arg `${REVERB_APP_KEY}`: il valore nel `.env` serve solo fuori Docker. Se manca al build, dal 2026-10-26 il timone resta usabile in polling (avviso in console `[timone] VITE_REVERB_APP_KEY non impostata…`); prima interrompeva tutto `app.js` |
| `VITE_REVERB_HOST` | **Sì** | `localhost` (compose) | `timone.example.org` | `echo.js`, host a cui **il browser** si connette | Hostname pubblico. ⚠️ DA VERIFICARE in produzione |
| `VITE_REVERB_PORT` | No | `8080` (compose) | `443` | `echo.js` | Porta pubblica del WebSocket |
| `VITE_REVERB_SCHEME` | No | `http` (compose); `https` se non impostata in `echo.js` | `https` | `echo.js` (`forceTLS`) | `https` se il sito è in HTTPS (altrimenti il browser blocca un `ws://` da una pagina `https://`) |
| `VITE_APP_NAME` | No | — | `"${APP_NAME}"` | nessun uso trovato in `resources/js` | Residuo dello scaffold |

### Mail

| Variabile | Obbl. | Default | Esempio | Usata in | Dove si ottiene |
|---|---|---|---|---|---|
| `MAIL_MAILER` | Prod | `log` | `smtp` | `config/mail.php:17` | Con `log` **nessuna mail viene inviata** (finisce in `laravel.log`). ⚠️ DA VERIFICARE: provider SMTP di produzione |
| `MAIL_SCHEME` | No | `null` | `smtps` / `null` | `config/mail.php` | Dal provider SMTP |
| `MAIL_HOST` | Prod | `127.0.0.1` | `smtp.provider.tld` | idem | Dal provider SMTP |
| `MAIL_PORT` | Prod | `2525` | `587` | idem | Dal provider SMTP |
| `MAIL_USERNAME` | Prod | `null` | — | idem | Credenziali del provider SMTP |
| `MAIL_PASSWORD` | Prod | `null` | — | idem | Credenziali del provider SMTP (segreto) |
| `MAIL_FROM_ADDRESS` | Prod | `hello@example.com` | `timone@dominio.it` | `config/mail.php:112` | ⚠️ DA VERIFICARE: indirizzo mittente reale |
| `MAIL_FROM_NAME` | No | `Example` | `"${APP_NAME}"` | `config/mail.php:113` | — |

### Storage remoto (non usato)

| Variabile | Obbl. | Note |
|---|---|---|
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT` | No | Il disco `s3` non è usato da nessun punto del codice (tutti i file usano il disco `local`). Lasciare vuote |

### Specifiche del progetto (`config/timone.php`)

| Variabile | Obbl. | Default | Esempio | Usata in | Dove si ottiene |
|---|---|---|---|---|---|
| `TIMONE_PDF_FORMAT_TOLERANCE_MM` | No | `1.5` | `1.5` | `config/timone.php` → `GenerateContentFileThumbnail`/`GeneratePageFileThumbnail::checkFormat()` | Tolleranza in mm oltre l'abbondanza di 3 mm per lato. Decisione della redazione |
| `TIMONE_DEADLINE_REMINDER_DAYS_BEFORE` | No | `3` | `3` | `config/timone.php` → `SendDeadlineReminders` | **Assente da `.env.example`** (vedi sotto). Decisione della redazione |
| `TIMONE_EXPORT_MEMORY_LIMIT` | No | `1024M` | — (non in `.env.example`) | `config/timone.php` `magazine_export.memory_limit` → `App\Jobs\GenerateMagazinePdf` (`ini_set` solo nel job) | Memoria del job del PDF sfogliabile (revisione 2, Fase 5). Misurato: 68 pagine da 20 PDF reali (72 MB) → picco 156 MB |
| `TIMONE_EXPORT_TIMEOUT` | No | `1800` | — | `magazine_export.timeout` → timeout del job e di Ghostscript; oltre timeout + 5 min un export «in coda/in generazione» si può rilanciare | La variante leggera di quel numero ha richiesto 80 s |
| `TIMONE_AD_PLACEMENT_START_PAGE` | No | `2` | `2` | `config/timone.php` `ad_placement.start_page` → `App\Support\AdPlacement\AdPlacementService` | Prima pagina interna della sequenza alternata delle pubblicità previste (Fase 5). Decisione della redazione |
| `TIMONE_AD_PLACEMENT_STEP` | No | `2` | `2` | `config/timone.php` `ad_placement.step` → `AdPlacementService` | Passo della sequenza (2 = una pagina sì e una no). Decisione della redazione |
| `TIMONE_UPLOAD_MAX_KB` | No | `102400` | `102400` | `config/timone.php` `upload_max_kb` → `App\Support\ContentFormRules::maxKilobytes()`, `config/livewire.php` (`temporary_file_upload.rules`), limite mostrato nel riquadro di caricamento (`UploadLimits`) | Dimensione massima di un file caricato, in KB (revisione 2, Fase 1, 2026-10-27; prima il modulo era fermo a 20 MB). **Assente da `.env.example`**: il default va bene. Se lo alzi, alza anche PHP e nginx ([09-deploy.md §6ter](09-deploy.md#6ter-valori-server-per-file-fino-a-100-mb-revisione-2-fase-1-2026-10-27)) |

`ad_placement.cover_order` (`['II', 'III', 'IV', 'I']`, senza variabile d'ambiente: è una lista) è l'ordine in cui le prime pubblicità previste (di qualsiasi formato, prima le pagine intere) occupano tutte le copertine; la I è l'ultima scelta e il sistema segnala quando la usa. Si cambia in `config/timone.php`.

### `config/peso_rivista.php` (calcolo peso rivista)

Non legge variabili d'ambiente: è un file di configurazione versionato. Si cambia con un commit e un deploy, non dal `.env`.

| Chiave | Valore attuale | A cosa serve | Letta da |
|---|---|---|---|
| `scaglioni` | `[250, 350, 500, 1000]` | Limiti superiori in grammi degli scaglioni di peso, in ordine crescente. Una copia ricade nel primo scaglione il cui limite è ≥ al suo peso; oltre l'ultimo limite è "Fuori scaglione" | `Issue::weight()`, `Issues\Create::weightPreview()`, `Magazines\Edit::weightPreview()`, `Timone\WeightPanel::render()`, che la passano a `MagazineWeightCalculator::calculate()` (direttamente o via `WeightPreview::fromForm()`) |
| `grammatura.min` / `grammatura.max` | `30` / `400` (g/m², limiti inclusi) | Intervallo ammesso per grammatura interno e copertina. Sotto il minimo la validazione dice di inserire la grammatura (es. 80) e non il peso di una pagina; sopra il massimo di controllare l'unità di misura. Una grammatura fuori intervallo già nel DB rende il calcolo **incompleto** invece di dare un peso sbagliato | `App\Support\Weight\WeightLimits::fromConfig()`, usato da `WeightParameters::rules()` (form) e da `Issue::weight()`, `WeightPanel::render()` e dalle anteprime (calcolo) |
| `formato.min` / `formato.max` | `50` / `1000` (mm, limiti inclusi) | Intervallo ammesso per base e altezza. Sotto 50 mm il valore è quasi certamente in centimetri (21 invece di 210): la validazione chiede i millimetri e un formato fuori intervallo già nel DB rende il calcolo **incompleto** | Come `grammatura` (`WeightLimits::fromConfig()`) |
| `pagine_copertina.min` / `pagine_copertina.max` | `2` / `12` | Pagine di copertina ammesse nei form (numero pari, `multiple_of:2`). Dalla Fase 2 (2026-10-26) il valore conta solo come default della rivista e alla creazione del numero (≥ 6 = il numero nasce con la bandella); per un numero esistente è derivato dalle copertine (4 o 6) | `WeightParameters::rules()` / `messages()`, `IssueObserver`, `App\Support\IssueCovers` |

**Come aggiungere o cambiare uno scaglione**: modifica l'array (es. `[250, 350, 500, 750, 1000]`), poi esegui il deploy (vedi [09-deploy.md](09-deploy.md#c-deploy-di-un-aggiornamento)).
- L'ordine nel file non è vincolante: il calcolatore ordina i limiti prima di usarli.
- Se in produzione si usa `php artisan config:cache` (oggi non lo si fa), serve anche `php artisan config:clear` o di nuovo `config:cache` in ogni container PHP.

**Cambiare i limiti di grammature o formato**: se si alzano o abbassano `grammatura.*` o `formato.*`, i numeri con valori ora fuori intervallo mostrano subito l'errore nel pannello Peso (nessun peso) finché non si correggono dalla modale del numero. La query per trovarli è in [10-debugging.md](10-debugging.md#peso-rivista).

**Cosa succede ai numeri esistenti**: lo scaglione **non è salvato** su nessun numero, viene ricalcolato a ogni visualizzazione. Cambiare gli scaglioni cambia subito scaglione, badge e avviso "a rischio" di **tutti** i numeri, anche quelli già usciti. Il loro **peso** invece non cambia, perché dipende solo dai parametri salvati sul numero.

I valori di default dei parametri di peso (maggiorazione 2 %, peso bancale 20 kg, tolleranze, 4 pagine di copertina) **non** sono in config. Sono default di colonna delle migration `2026_10_26_*` e poi valori modificabili per rivista e per numero (vedi [04-database.md](04-database.md#parametri-del-peso-su-magazines-e-issues-migration-2026_10_26_)).

### Variabili non-`.env` rilevanti

| Chiave | Dove | Note |
|---|---|---|
| `app.manual_path` | `config/app.php:23` | `base_path('MANUALE_UTENTE.md')`; modificabile solo nei test |
| `livewire.temporary_file_upload.rules` | `config/livewire.php:88` | `mimes:pdf,jpg,jpeg,png,webp`, `max:` letto da `config/timone.php` → `upload_max_kb` (100 MB). PHP e nginx devono accettare almeno il 10% in più: `docker/php/uploads.ini` (110M/120M) e `client_max_body_size 120m` in nginx |
| `livewire.temporary_file_upload.max_upload_time` | `config/livewire.php:98` | 30 (minuti; era 10) |

## Discrepanze tra `.env.example` e il codice

**Usate nel codice, assenti in `.env.example`:**
- `TIMONE_DEADLINE_REMINDER_DAYS_BEFORE` (`config/timone.php`). Funziona con il default 3, ma non è documentata dove un sistemista la cercherebbe.
- `TIMONE_UPLOAD_MAX_KB` (`config/timone.php`, revisione 2 Fase 1, 2026-10-27). Facoltativa, default 102400 (100 MB). Non aggiunta a `.env.example` per la regola del batch «non modificare `.env`/`.env.example` senza dirlo»: segnalata nella consegna.
- `SESSION_SECURE_COOKIE`, `LOG_DAILY_DAYS`: default di Laravel, rilevanti in produzione.
- Tutte le altre variabili lette in `config/*.php` e assenti dall'esempio (`PUSHER_*`, `ABLY_KEY`, `POSTMARK_*`, `RESEND_KEY`, `SQS_*`, `DYNAMODB_*`, `SLACK_*`, `PAPERTRAIL_*`, `REVERB_SCALING_*`, ecc.) sono default dello scaffold Laravel per driver non usati da questo progetto.

**In `.env.example` ma inutili per il codice:** `MEMCACHED_HOST`, `AWS_*`, `VITE_APP_NAME`, `PHP_CLI_SERVER_WORKERS` (solo `artisan serve`). `MYSQL_ROOT_PASSWORD` è usata solo da Docker Compose, non da Laravel.

## Checklist `.env` di produzione

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<dominio-pubblico>              # ⚠️ DA VERIFICARE
APP_TIMEZONE=UTC                                # o Europe/Rome, decisione esplicita
LOG_LEVEL=warning
LOG_STACK=daily                                 # opzionale, evita un laravel.log infinito
SESSION_SECURE_COOKIE=true                      # se HTTPS
MAIL_MAILER=smtp                                # + MAIL_HOST/PORT/USERNAME/PASSWORD/FROM_ADDRESS
VITE_REVERB_HOST=<dominio-pubblico>
VITE_REVERB_PORT=443                            # se il WS passa da un reverse proxy TLS
VITE_REVERB_SCHEME=https
# APP_KEY, DB_PASSWORD, MYSQL_ROOT_PASSWORD, REVERB_APP_ID/KEY/SECRET: generate una volta, mai committate
```
