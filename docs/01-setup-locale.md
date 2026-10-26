# 01 — Setup locale

[← Indice](README.md) · Vedi anche: [05-configurazione.md](05-configurazione.md), [10-debugging.md](10-debugging.md)

Ci sono due strade:

- **A. Docker (consigliata, è l'ambiente "ufficiale" del progetto).** È l'unico ambiente in cui funziona tutto, comprese miniature PDF, conteggio pagine PDF e controllo formato, perché solo l'immagine Docker ha `ext-imagick` e Ghostscript. Dal 2026-10-12, fuori da Docker **nessun PDF può essere allegato a un contenuto**: viene rifiutato come illeggibile (`HANDOFF.md`, sezione "Sessione 2026-10-12 (continuazione)", "Conseguenza da sapere").
- **B. PHP/Node sull'host.** Va bene per lavorare su UI e logica e per lanciare i test più velocemente, con le limitazioni descritte sotto.

---

## Prerequisiti

| Strumento | Versione | Per quale strada | Note |
|---|---|---|---|
| Docker Desktop (o Docker Engine + plugin Compose v2) | non fissata nel repo; serve `docker compose` (v2, non `docker-compose`) | A | `setup.sh` controlla `docker info` |
| `openssl` | qualsiasi | A | `setup.sh` lo usa per generare password e chiavi |
| Git | qualsiasi | A, B | |
| PHP | **8.2.x** (`composer.json`: `^8.2`; l'immagine Docker usa 8.2) | B (e A, se vuoi lanciare i test) | Estensioni: vedi [06-dipendenze.md](06-dipendenze.md#estensioni-php) |
| Composer | 2.x | B (e A per i test) | |
| Node.js | **20** (stessa major dell'immagine `node:20-alpine`) | B | Nessun `.nvmrc` nel repo |
| npm | quello incluso in Node 20 | B | Il lockfile è `package-lock.json` |

---

## A. Setup con Docker (consigliato)

### 1. Clona e avvia

```bash
git clone https://its.git/pietro/timone-rivista
cd timone-rivista
./setup.sh dev     # modalità sviluppo: codice montato dall'host + Vite con HMR
# oppure
./setup.sh         # modalità "produzione": codice e asset congelati nell'immagine
```

Cosa fa `setup.sh`, in ordine (vedi il file stesso, `setup.sh`):

1. Controlla che Docker sia installato e acceso.
2. Se manca `.env`, lo copia da `.env.example`.
3. Genera con `openssl` le variabili obbligatorie ancora vuote: `APP_KEY`, `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`. Non sovrascrive mai valori già presenti.
4. Esegue `docker compose ... up -d --build`, con fino a 3 tentativi: al primissimo avvio Docker Desktop può dare un errore temporaneo `mkdir ... file exists` sui volumi condivisi.
5. Esegue `php artisan migrate --force` nel container `app`.
6. Esegue `php artisan db:seed --force` **solo se** la tabella `users` è vuota. Il seed non è idempotente: ricreerebbe le stesse email e violerebbe il vincolo di unicità.
7. Stampa URL e credenziali demo.

Lo script si può rilanciare in sicurezza.

### 2. Apri l'app

| Servizio | URL |
|---|---|
| App | http://localhost |
| Adminer (GUI database) | http://localhost:8081, server `mysql`, utente e password in `.env` (`DB_USERNAME`/`DB_PASSWORD`) |
| Reverb (WebSocket) | `ws://localhost:8080` (lo usa il browser da solo) |
| Vite HMR (solo `dev`) | http://localhost:5173 (non va aperto direttamente) |

### 3. Utenti di test (creati da `database/seeders/DatabaseSeeder.php`)

| Email | Ruolo | Accesso |
|---|---|---|
| `admin@timone.test` | Admin | tutte le riviste |
| `redattore@timone.test` | Redattore | solo alcune riviste demo |

La password di entrambi è la stringa letterale `password`, valore della `UserFactory` e del seeder. Sono credenziali **solo demo**: non vanno mai create in produzione (vedi [09-deploy.md](09-deploy.md)).

Il dataset demo contiene 3 riviste e alcuni numeri con contenuti già assegnati (`HANDOFF.md`, sezione "Come avviare in locale").

### 4. Code e scheduler in locale

Con Docker non serve fare nulla: `docker-compose.yml` avvia già
- `worker`: `php artisan queue:work redis --tries=3 --backoff=3 --sleep=1`
- `scheduler`: `php artisan schedule:work`
- `reverb`: `php artisan reverb:start`

### 5. Differenze tra `./setup.sh` e `./setup.sh dev`

| | `./setup.sh` (prod) | `./setup.sh dev` |
|---|---|---|
| File compose | solo `docker-compose.yml` | `docker-compose.yml` + `docker-compose.dev.yml` |
| Codice PHP | copiato nell'immagine | bind mount `./:/var/www/html` |
| Asset JS/CSS | buildati nell'immagine (`npm run build`) | servizio `vite` con HMR su :5173 (esegue `npm ci` a ogni avvio) |
| `vendor/` | nell'immagine, **senza dev-dependency** | volume anonimo, comunque dall'immagine, **senza dev-dependency** |

⚠️ DA VERIFICARE: l'immagine imposta `opcache.validate_timestamps=0` (`docker/php/opcache.ini`) e `docker-compose.dev.yml` non lo sovrascrive. In teoria PHP-FPM potrebbe quindi **non vedere** le modifiche ai file PHP finché non si riavvia il container (`docker compose -f docker-compose.yml -f docker-compose.dev.yml restart app`). Il commento in `opcache.ini` lo segnala proprio per il caso bind mount; non ho trovato una verifica in `HANDOFF.md`. Se una modifica PHP "non si vede", riavvia `app`.

### 6. Comandi utili

```bash
DC="docker compose -f docker-compose.yml -f docker-compose.dev.yml"   # in modalità dev
$DC ps
$DC logs -f app worker reverb scheduler
$DC exec app php artisan tinker
$DC exec app php artisan migrate --force
$DC down          # ferma (i dati restano nei volumi)
$DC down -v       # ferma e CANCELLA database, file caricati e log (reset totale)
```

### 7. Test

Nell'immagine Docker manca Pest (`composer install --no-dev`), mentre sull'host manca Imagick. Per lanciare **tutta** la suite, compresi i test che richiedono Imagick, `HANDOFF.md` (sezione "Sessione 2026-10-12 — Fase 0") usa un container usa-e-getta che monta il `vendor/` dell'host:

```bash
composer install                     # sull'host: installa anche le dev-dependency (Pest)
docker run --rm --network none -v "$PWD":/var/www/html -w /var/www/html \
  --entrypoint php timone-rivista-app:latest vendor/bin/pest
```

Il nome immagine `timone-rivista-app` deriva da `name: timone-rivista` in `docker-compose.yml` più il servizio `app`. Esiste solo dopo aver eseguito `setup.sh` almeno una volta.

Solo sull'host, senza Imagick: `php vendor/bin/pest` (oppure `php artisan test`). Circa 33 test vengono saltati.

L'ultimo esito documentato (`HANDOFF.md`, 2026-10-12) è: **569 passed** in Docker; **536 passed, 33 skipped** in locale. Non l'ho rieseguito per questa documentazione.

`phpunit.xml` forza `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `MAIL_MAILER=array`. **Non** sovrascrive `BROADCAST_CONNECTION`, di proposito: vedi [10-debugging.md](10-debugging.md#test-che-falliscono-con-could-not-resolve-host-reverb).

---

## B. Setup senza Docker (PHP/Node sull'host)

> Questa strada è stata usata nelle prime fasi (`HANDOFF.md` §"Come avviare in locale (senza Docker...)"), con SQLite. Il `.env.example` attuale invece è scritto per Docker: host `mysql`, `redis`, `reverb`. Vanno adattati a mano.

```bash
git clone https://its.git/pietro/timone-rivista && cd timone-rivista
composer install          # funziona anche senza ext-imagick grazie a config.platform in composer.json
npm ci
cp .env.example .env
php artisan key:generate
```

Modifica `.env` (nomi variabili in [05-configurazione.md](05-configurazione.md)):

```dotenv
DB_CONNECTION=sqlite            # e rimuovi/commenta DB_HOST, DB_DATABASE ecc.
                                # (Laravel userà database/database.sqlite)
QUEUE_CONNECTION=database       # oppure redis se hai Redis + ext-redis
CACHE_STORE=database            # oppure redis se hai Redis + ext-redis
REVERB_HOST=localhost
REVERB_APP_ID=...               # valori qualsiasi non vuoti, coerenti tra loro
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
APP_URL=http://localhost:8000   # deve coincidere con l'URL che apri: AppServiceProvider forza la root URL da APP_URL
```

Poi:

```bash
touch database/database.sqlite
php artisan migrate --seed
composer dev      # avvia in parallelo: artisan serve, queue:listen --tries=1, pail (log), vite
# in un altro terminale, se vuoi il realtime:
php artisan reverb:start         # se la porta 8080 è occupata: --port=8095 e allinea REVERB_PORT/VITE_REVERB_PORT
php artisan schedule:work        # solo se ti serve testare i solleciti
```

`composer dev` è definito in `composer.json` (`scripts.dev`) e usa `npx concurrently`.

Limiti noti di questa strada:
- senza `ext-imagick`: nessun PDF allegabile, niente miniature né controllo formato;
- senza `ext-redis`: tutto ciò che usa Redis fallisce con `Class "Redis" not found` (es. `php artisan schedule:list` con `CACHE_STORE=redis`, verificato il 2026-10-26 sulla macchina di sviluppo);
- in PHP 8.2 senza `pcntl`, `reverb:start` può fallire (`HANDOFF.md` §Decisioni: "`docker-php-ext-install` deve includere `pcntl`").

---

## Errori comuni in fase di setup

| Errore / sintomo | Causa | Soluzione |
|---|---|---|
| `Docker è installato ma non è in esecuzione` | Il demone Docker è spento | Avvia Docker Desktop e rilancia `./setup.sh` |
| `mkdir ... file exists` al primo `up` | Race di Docker Desktop sui volumi condivisi (`setup.sh`, commento al passo 4) | `setup.sh` ritenta da solo 3 volte; altrimenti rilancia |
| `required variable ... DB_PASSWORD is missing a value` / `manca DB_PASSWORD in .env` | `.env` senza le variabili generate | Esegui `./setup.sh`, non `docker compose up` "nudo" la prima volta |
| `Bind for 0.0.0.0:80 / :8080 / :8081 failed: port is already allocated` | Porta occupata sull'host. Sulla macchina di sviluppo originale la 8080 era occupata da Docker Desktop stesso (`HANDOFF.md`, "Note ambiente") | Libera la porta o cambia la mappatura `ports:` in `docker-compose.yml`. Per Reverb allinea anche `VITE_REVERB_PORT` e ricostruisci |
| `migrate` si ferma con un messaggio su `page_occupancy` | La migration `2026_10_12_100001_make_page_occupancy_not_null_on_contents_table.php` rifiuta di partire se esistono contenuti senza occupazione | `php artisan timone:missing-occupancy` per vederli, valorizzarli, poi rilanciare. Vedi [04-database.md](04-database.md#migration-bloccante-page_occupancy) |
| `Duplicate entry 'admin@timone.test'` | Seed rieseguito su un DB non vuoto | Non rilanciare `db:seed`. `setup.sh` lo evita già |
| 502 Bad Gateway dopo un secondo `./setup.sh dev` | nginx tiene il vecchio IP del container `app` (`HANDOFF.md:1173`) | `docker compose -f docker-compose.yml -f docker-compose.dev.yml restart nginx` |
| In console del browser `You must pass your app key`, niente drag&drop | `VITE_REVERB_APP_KEY` vuota al momento della build degli asset (commento in `Dockerfile`, stage 1) | Controlla che `REVERB_APP_KEY` sia in `.env` e ricostruisci: `docker compose up -d --build` |
| Gli asset puntano a `http://localhost:5173` anche se Vite non gira | File `public/hot` rimasto da un `npm run dev` precedente (gitignored, ma presente sul disco) | Cancella `public/hot` oppure avvia Vite |
| `Class "Laravel\Pail\PailServiceProvider" not found` | `bootstrap/cache/*.php` generato con le dev-dependency finito in un ambiente `--no-dev` | `php artisan package:discover` (il `Dockerfile` lo fa già al build) |
| Upload PDF "PDF illeggibile" in locale | Manca `ext-imagick`/Ghostscript sull'host | Usa Docker |
| `Class "Redis" not found` | PHP host senza `ext-redis` e `.env` con driver redis | Installa `ext-redis` oppure usa i driver `database` (vedi B) |
