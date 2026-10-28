# 06 — Dipendenze

[← Indice](README.md) · Vedi anche: [01-setup-locale.md](01-setup-locale.md), [09-deploy.md](09-deploy.md), [11-debito-tecnico.md](11-debito-tecnico.md)

Versioni rilevate il 2026-10-26 con `composer show -D`, `npm ls --depth=0`, `composer outdated --direct --locked`, `composer audit --locked` e `npm audit`.

## Dipendenze di sistema

Da `Dockerfile`, stage `app` (`php:8.2-fpm-alpine`):

| Pacchetto (apk) | Perché |
|---|---|
| `ghostscript` | Delegate PDF di ImageMagick: senza, nessuna miniatura né conteggio pagine. Dalla revisione 2, Fase 5 usato anche dall'export sfogliabile: riscrive in PDF 1.4 i sorgenti che FPDI non apre e crea la variante a bassa risoluzione (`App\Support\MagazineExport\Ghostscript`) |
| `imagemagick` (+ `imagemagick-dev` in build) | Libreria usata da `ext-imagick` |
| `libpng`, `libjpeg-turbo`, `freetype` (+ `-dev` in build) | Formati immagine per GD e Imagick |
| `mysql-client` | Disponibile nel container `app` (es. `mysql`/`mysqldump` manuali) |
| `$PHPIZE_DEPS`, `libtool` | Solo in build, per compilare le estensioni PECL; poi rimossi |

**Configurazione critica di ImageMagick**: il `Dockerfile` rimuove la riga `pattern="PDF"` da `/etc/ImageMagick-7/policy.xml`. Senza questa modifica ImageMagick rifiuta i PDF e ogni conversione fallisce (in silenzio, con `thumbnail_status=failed`). **Su un server senza Docker va fatto a mano.**

Altri componenti runtime: MySQL 8.4, Redis 7, Nginx (immagini Docker ufficiali, vedi [09-deploy.md](09-deploy.md)).

## Estensioni PHP

| Estensione | Installata nel Dockerfile | Perché serve |
|---|---|---|
| `imagick` 3.7.0 (PECL) | sì | `spatie/pdf-to-image` e `App\Support\PdfPageMeasurer` (`new Imagick`): miniature, conteggio pagine, misura in mm. In locale è "finta" con `config.platform.ext-imagick` in `composer.json` |
| `redis` (PECL) | sì | `REDIS_CLIENT=phpredis`: cache e code |
| `pdo_mysql` | sì | DB |
| `gd` (jpeg, freetype) | sì | dompdf (immagini nei PDF esportati) |
| `bcmath` | sì | aritmetica decimale (es. `brick/math` di Laravel) |
| `pcntl` | sì | **Obbligatoria per `php artisan reverb:start`** (gestione segnali); senza, Reverb crasha (`HANDOFF.md` §Decisioni) |
| `opcache` | sì | performance; `validate_timestamps=0` (`docker/php/opcache.ini`) |
| `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `dom`, `curl`, `pdo_sqlite` | incluse nell'immagine ufficiale `php:8.2-fpm-alpine` | Requisiti standard di Laravel. `fileinfo` serve alla validazione `mimes:` sul contenuto reale del file |

`composer.json` non dichiara `ext-*` in `require` (solo il `platform` fittizio di imagick). Le estensioni sopra sono dedotte da Dockerfile e codice.

## Pacchetti Composer — `require` (produzione)

| Pacchetto | Versione installata | Scopo nel progetto | Dove è usato |
|---|---|---|---|
| `laravel/framework` | 12.64.0 (ultima 12.70.1) | Framework | ovunque |
| `livewire/livewire` | 3.8.2 (vincolo `^3.6`; v4 disponibile ma esclusa) | Tutte le schermate | `app/Livewire/*`, `config/livewire.php` |
| `laravel/reverb` | 1.11.0 (ultima 1.12.0) | Server WebSocket self-hosted | servizio compose `reverb`, `config/reverb.php`, `config/broadcasting.php` |
| `barryvdh/laravel-dompdf` | 3.1.2 | Export PDF del timone e del carico pubblicitario | `TimoneExportController.php:84`, `AdDashboardExportController.php:67` (`Pdf::loadView`) |
| `setasign/fpdi` | 2.6.9 (revisione 2, Fase 5, scelta dell'utente) | Importa le pagine dei PDF caricati come oggetti vettoriali nel PDF sfogliabile. Versione gratuita: non legge i PDF con xref compresse, che vengono prima riscritti in 1.4 con Ghostscript | `app/Support/MagazineExport/MagazinePdfComposer.php` |
| `tecnickcom/tcpdf` | 6.11.4 (revisione 2, Fase 5) | Composizione del PDF sfogliabile: ritaglio, layout `TwoPageRight`, segnalibri, font `dejavusans`. LGPL-3.0, ~25 MB (font) | `MagazinePdfComposer` (via `setasign\Fpdi\Tcpdf\Fpdi`) |
| `spatie/pdf-to-image` | 3.3.0 (ultima 3.5.0) | Rendering PNG di una pagina PDF (wrapper su Imagick) | `app/Jobs/GenerateContentFileThumbnail.php:68`, `GeneratePageFileThumbnail.php:46` |
| `laravel/tinker` | 2.11.1 (v3 disponibile) | REPL; **usato da `setup.sh`** per contare gli utenti | `setup.sh` passo 5 |
| `fakerphp/faker` | 1.24.1 | Dati demo del seeder. È in `require` di proposito: deve funzionare anche nell'immagine `--no-dev` | `database/factories/*`, `DatabaseSeeder` |

Transitive rilevanti: `league/commonmark` (via Laravel, usato da `Str::markdown()` in `App\Support\ManualRenderer`), `league/flysystem` (storage), `dompdf/dompdf`, `pusher/pusher-php-server` (client server-side di Reverb).

## Pacchetti Composer — `require-dev`

| Pacchetto | Versione | Scopo / uso |
|---|---|---|
| `pestphp/pest` + `pestphp/pest-plugin-laravel` | 3.8.7 / 3.2.0 | Test (`tests/`) |
| `mockery/mockery` | 1.6.12 | Mock nei test |
| `nunomaduro/collision` | 8.9.5 | Output errori CLI |
| `laravel/pail` | 1.2.7 | Tail dei log, usato da `composer dev` |
| `laravel/pint` | 1.29.3 | Formatter. Nessun `pint.json`: usa il preset di default |
| `laravel/breeze` | 2.4.2 | Solo scaffolding già generato (Auth controller e view); nessun riferimento runtime. Si può rimuovere |
| `laravel/sail` | 1.64.0 | **Non usato**: il progetto ha un proprio Docker. Si può rimuovere |

## Pacchetti npm

| Pacchetto | Versione | Tipo | Scopo | Dove |
|---|---|---|---|---|
| `laravel-echo` | 1.19.0 | dependency | Client WebSocket | `resources/js/echo.js` |
| `pusher-js` | 8.6.0 | dependency | Protocollo Pusher, usato da Echo verso Reverb | `resources/js/echo.js` |
| `sortablejs` | 1.15.7 | dev | Drag&drop riordino pagine | `resources/js/app.js` (direttiva `x-sortable`) |
| `axios` | 1.18.1 | dev | Esposto su `window.axios`, ma **nessuna chiamata** nel codice (le mutazioni passano da `$wire`) | `resources/js/bootstrap.js` |
| `vite` | 6.4.3 | dev | Build e dev server | `vite.config.js` |
| `laravel-vite-plugin` | 1.3.0 | dev | Integrazione `@vite()` / `public/hot` | `vite.config.js` |
| `tailwindcss` | 3.4.19 | dev | CSS. `content` include anche `app/` (le classi stanno negli enum) | `tailwind.config.js` |
| `@tailwindcss/forms` | 0.5.11 | dev | Stili dei form | `tailwind.config.js:33` |
| `postcss`, `autoprefixer` | 8.5.22 / 10.5.4 | dev | Pipeline CSS | `postcss.config.js` |
| `concurrently` | 9.2.4 | dev | Solo per `composer dev` | `composer.json` `scripts.dev` |

Alpine.js **non** è una dipendenza npm: lo fornisce Livewire. Non aggiungerlo (vedi `resources/js/app.js:5-19`).

**Asset compilati**: `public/build` è **gitignored**. Viene generato da `npm run build` nello stage `node_builder` del Dockerfile e copiato sia nell'immagine `app` sia in `nginx`.

## Vulnerabilità note (al 2026-10-26)

### `composer audit --locked`: 19 advisory su 6 pacchetti

| Pacchetto | Advisory | Rilevanza per questo progetto |
|---|---|---|
| `livewire/livewire` 3.8.2 | CVE-2026-81887: DOM-based XSS nella gestione dello stato client (versioni affette `<=3.8.2`) | **Alta**: tutta l'app è Livewire. Da aggiornare per primo, restando su 3.x |
| `laravel/framework` 12.64.0 | CVE-2026-114509 (bypass TokenGuard con credenziali non stringa), CVE-2026-114508 (relationship inclusion → distruzione dati non autorizzata), CVE-2026-102279 (XSS nella pagina di debug) | TokenGuard non è usato (niente API token); la XSS di debug conta solo con `APP_DEBUG=true`; la 114508 va valutata. Aggiornare a 12.70.x |
| `league/commonmark` | 11 advisory (DoS, bypass del filtro raw HTML/link) | Usato solo per renderizzare `MANUALE_UTENTE.md`, che è un file fidato del repo: rischio basso. Si risolve aggiornando le dipendenze |
| `league/flysystem` | CVE-2026-102601: bypass del controllo dei caratteri di controllo nei path | I path sono generati con UUID dal server: rischio basso |
| `symfony/http-foundation` | CVE-2026-108082: ReDoS sull'header `Cache-Control` | Raggiungibile da qualunque richiesta HTTP: aggiornare |
| `symfony/yaml` | CVE-2026-109413: ricorsione illimitata | Nessun parsing di YAML utente nel progetto: rischio basso |

### `npm audit`: 11 vulnerabilità (2 critical, 5 high, 4 moderate)

Pacchetti coinvolti: `shell-quote` (critical, transitiva di `concurrently`), `axios`, `follow-redirects`, `nanoid`, `source-map-js`, `vite` (high), `postcss`, `postcss-selector-parser` (moderate).

Quasi tutte riguardano **tooling di build e sviluppo**, che non finisce in produzione. Eccezione: `axios` è nel bundle client, ma non viene mai chiamato. `npm audit` indica `fix available via npm audit fix`.

## Dipendenze a rischio / da tenere d'occhio

- **Livewire 3.x vs 4.x**: il progetto è fissato su 3. La migrazione alla v4 è un progetto a sé: semantica di `wire:`, directive `x-sortable` custom, listener `echo-presence`.
- **`spatie/pdf-to-image` + Imagick + Ghostscript + policy.xml**: catena fragile. Un aggiornamento dell'immagine base Alpine può cambiare il percorso di `policy.xml` (oggi `/etc/ImageMagick-7/`). Se il `sed` non trova il file, non fallisce (`if [ -f ... ]`) e il problema si vede solo a runtime.
- **`imagick-3.7.0` fissato in PECL** e nel `platform` di `composer.json`: vanno aggiornati insieme.
- **Immagini Docker non fissate**: `nginx:alpine`, `redis:7-alpine`, `adminer:latest`, `composer:2`, `php:8.2-fpm-alpine` sono tag mobili, quindi un rebuild può cambiare versione senza modifiche al repo.
- **PHP 8.2**: le patch di sicurezza di PHP 8.2 terminano a fine 2026 (dato generale del ciclo di vita PHP, non nel repo). ⚠️ DA VERIFICARE: pianificare il passaggio a 8.3/8.4 (`Dockerfile` + `composer.json`).
- **Abbandonate**: nessuna delle dipendenze dirette risulta abbandonata (`composer outdated` non ne segnala).

## Come aggiornare in sicurezza

1. **Branch dedicato**: `git checkout -b chore/update-deps`.
2. **PHP (patch e minor)**:
   ```bash
   composer update livewire/livewire laravel/framework laravel/reverb spatie/pdf-to-image --with-all-dependencies
   composer audit
   ```
   Il `config.platform.ext-imagick` permette di farlo anche su un host senza Imagick. Non serve `--ignore-platform-reqs`.
3. **npm**:
   ```bash
   npm audit fix          # non --force: eviterebbe salti di major (vite 7, tailwind 4)
   npm run build
   ```
4. **Test completi con Imagick** (vedi [01-setup-locale.md](01-setup-locale.md#7-test)):
   ```bash
   composer install
   ./setup.sh dev   # ricostruisce l'immagine con le nuove dipendenze
   docker run --rm --network none -v "$PWD":/var/www/html -w /var/www/html --entrypoint php timone-rivista-app:latest vendor/bin/pest
   ```
5. **Verifica manuale** (non ci sono test browser): drag&drop delle pagine, upload di un PDF multipagina con miniature, presenza di due utenti su browser diversi, export PDF. La checklist è in [09-deploy.md](09-deploy.md#e-checklist-post-deploy).
6. Commit di `composer.lock` e `package-lock.json`, poi deploy (sezione c di [09-deploy.md](09-deploy.md)).

Da **non** fare senza un piano dedicato: Livewire 4, Tailwind 4, Vite 7, Laravel 13, `laravel-echo` 2.x (cambia l'API client).
