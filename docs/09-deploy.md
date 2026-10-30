# 09 — Deploy

[← Indice](README.md) · Vedi anche: [05-configurazione.md](05-configurazione.md), [04-database.md](04-database.md#backup-e-restore), [10-debugging.md](10-debugging.md)

## Cosa c'è (e cosa non c'è) nel repo

**Presente**:
- `Dockerfile` multi-stage;
- `docker-compose.yml` (stack completo: app, nginx, worker, reverb, scheduler, mysql, redis, adminer, backup);
- `docker-compose.dev.yml`;
- `setup.sh`;
- `docker/nginx/default.conf` (solo HTTP);
- `docker/php/*.ini`;
- `docker/mysql/backup.sh`;
- `DOCKER.md` §"Aggiornare il codice".

**Assente**:
- CI/CD (nessuna `.github/`, GitLab CI, Envoy, Deployer);
- configurazione TLS/HTTPS;
- reverse proxy per Reverb;
- supervisor e crontab (non servono con Compose);
- script di deploy "vero";
- qualsiasi informazione sul server di produzione.

⚠️ DA VERIFICARE, prima di toccare la produzione:
- dove gira oggi (host, IP, provider, OS) e chi ha accesso SSH;
- come è stato fatto il deploy esistente (`./setup.sh` sul server? altro?) e se c'è un reverse proxy/TLS davanti;
- chi gestisce DNS e certificati.

Branch di produzione: **`master`**, l'unico esistente (`git branch -a`). Non c'è un flusso di release o di tag.

Il deploy descritto qui sotto si basa sull'unico meccanismo presente nel repo: **Docker Compose**. Le parti non presenti nel repo sono marcate `⚠️ TEMPLATE — verificare contro il server reale`.

---

## a) Requisiti server

| Requisito | Valore | Fonte |
|---|---|---|
| OS | Linux x86_64 con Docker Engine. Le immagini sono Alpine, quindi l'OS host è indifferente. ⚠️ DA VERIFICARE l'OS reale | — |
| Docker Engine + plugin Compose **v2** (`docker compose`) | versione recente; il compose usa `depends_on.condition: service_healthy` e `profiles` | `docker-compose.yml` |
| Accesso in uscita | Docker Hub, registry npm, Packagist (durante la build); `https://its.git/pietro/timone-rivista` (per `git clone`/`git pull`) | `Dockerfile` |
| Porte in ingresso | 80 (nginx), 8080 (Reverb WebSocket); 8081 (Adminer) **da NON esporre** | `docker-compose.yml` |
| RAM/CPU | Non documentato. Ogni conversione PDF usa fino a `memory_limit=256M` (`docker/php/uploads.ini`). ⚠️ DA VERIFICARE il dimensionamento reale | — |
| Disco | MySQL + file caricati (volume `storage_data`). **Ogni pagina di un PDF multipagina salva una copia intera del PDF** (vedi [11-debito-tecnico.md](11-debito-tecnico.md)): prevedere spazio abbondante e monitorarlo | `ContentCreator::storeFile` |
| Utente | Un utente non-root nel gruppo `docker`. Dentro i container PHP gira come `www-data` (`USER www-data` nel `Dockerfile`) | — |

## b) Primo deploy su server nuovo

> **Non usare `./setup.sh` così com'è su un DB di produzione vuoto**: se la tabella `users` è vuota, esegue `db:seed`, che crea gli utenti demo `admin@timone.test` / `redattore@timone.test` con password `password` e riviste finte. La procedura sotto replica i passi di `setup.sh` senza il seed.

### 1. Docker e codice

```bash
# ⚠️ TEMPLATE — verificare contro il server reale (esempio per Debian/Ubuntu)
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker <utente-deploy>
sudo systemctl enable --now docker        # i container hanno restart: unless-stopped → ripartono al boot

git clone https://its.git/pietro/timone-rivista /opt/timone-rivista   # percorso a scelta
cd /opt/timone-rivista
```

### 2. `.env` di produzione

```bash
cp .env.example .env
# segreti, stessi generatori di setup.sh:
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -hex 20)|" .env
sed -i "s|^MYSQL_ROOT_PASSWORD=.*|MYSQL_ROOT_PASSWORD=$(openssl rand -hex 20)|" .env
sed -i "s|^REVERB_APP_ID=.*|REVERB_APP_ID=$(openssl rand -hex 6)|" .env
sed -i "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=$(openssl rand -hex 10)|" .env
sed -i "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=$(openssl rand -hex 16)|" .env
chmod 600 .env
```

Poi modifica a mano i valori di produzione: vedi la [checklist in 05-configurazione.md](05-configurazione.md#checklist-env-di-produzione). Almeno `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `VITE_REVERB_HOST/PORT/SCHEME` e `MAIL_*`.

**Salva una copia del `.env` in un password manager.** `APP_KEY` e `DB_PASSWORD` non si possono rigenerare senza conseguenze.

### 3. Build, avvio, migrazioni

```bash
docker compose up -d --build
docker compose ps                                     # mysql/redis/app/reverb devono essere "healthy"
docker compose exec -T app php artisan migrate --force
```

### 4. Primo utente Admin (al posto del seed)

```bash
docker compose exec app php artisan tinker
>>> App\Models\User::create(['name' => 'Nome Cognome', 'email' => 'admin@dominio.it',
...   'password' => Illuminate\Support\Facades\Hash::make('<password-robusta>'), 'role' => 'admin']);
```

`role` è un `string` con cast all'enum `App\Enums\UserRole`. I valori ammessi sono `admin`, `redattore`, `commerciale`, `sola_lettura`. Gli altri utenti e le riviste si creano poi dall'interfaccia (`/utenti/nuovo`, `/riviste/nuova`). `name`, `email`, `password` e `role` sono in `$fillable` (`app/Models/User.php:23-28`).

### 5. HTTPS e WebSocket

Il repo serve solo HTTP sulla porta 80, mentre Reverb è in chiaro sulla 8080. Per l'HTTPS serve un reverse proxy davanti allo stack.

```nginx
# ⚠️ TEMPLATE — verificare contro il server reale
# nginx sull'HOST (fuori da Docker), certificato es. con certbot.
server {
    listen 443 ssl http2;
    server_name timone.example.org;                       # dominio reale: ⚠️ DA VERIFICARE
    ssl_certificate     /etc/letsencrypt/live/timone.example.org/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/timone.example.org/privkey.pem;
    client_max_body_size 120m;                            # allineato a docker/nginx/default.conf

    # WebSocket Reverb: il client Pusher/Echo si connette a /app/{key}; le API HTTP sono sotto /apps
    location ~ ^/(app|apps)/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 120s;
    }

    location / {
        proxy_pass http://127.0.0.1:80;                   # nginx del compose (cambiare la porta host se 80 è usata dal proxy)
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port 443;
        proxy_read_timeout 300s;                          # come fastcgi_read_timeout del compose
    }
}
server { listen 80; server_name timone.example.org; return 301 https://$host$request_uri; }
```

Con questo proxy:
- nel `.env` imposta `APP_URL=https://timone.example.org`, `VITE_REVERB_HOST=timone.example.org`, `VITE_REVERB_PORT=443`, `VITE_REVERB_SCHEME=https`, `SESSION_SECURE_COOKIE=true`, poi **ricostruisci** con `docker compose up -d --build` (le `VITE_*` sono fissate nel bundle);
- `bootstrap/app.php` ha già `trustProxies(at: '*')`, quindi gli header `X-Forwarded-*` vengono accettati;
- `AppServiceProvider` forza `https` se `APP_URL` inizia con `https://`.

Poiché il proxy occupa la porta 80 dell'host, la mappatura `ports: "80:80"` del servizio `nginx` va cambiata (es. `"127.0.0.1:8000:80"`) e le porte `8080`/`8081` andrebbero legate a `127.0.0.1`. Fallo con un `docker-compose.override.yml` **sul server**: Compose lo carica in automatico, quindi non serve modificare il file versionato.

```yaml
# ⚠️ TEMPLATE — docker-compose.override.yml sul server, verificare contro il server reale
services:
  nginx:
    ports: !override
      - "127.0.0.1:8000:80"
  reverb:
    ports: !override
      - "127.0.0.1:8080:8080"
  adminer:
    ports: !override
      - "127.0.0.1:8081:8080"     # raggiungibile solo con un tunnel SSH: ssh -L 8081:127.0.0.1:8081 server
```

Se usi questo override, nel proxy cambia `proxy_pass http://127.0.0.1:80` in `http://127.0.0.1:8000`. Il tag `!override` richiede Docker Compose ≥ 2.24: ⚠️ DA VERIFICARE la versione sul server.

### 6. Permessi `storage/` e `bootstrap/cache`

Con Docker non c'è nulla da fare a mano. Il `Dockerfile` esegue `chown -R www-data:www-data storage bootstrap/cache` e i volumi named `storage_data`/`storage_logs` vengono inizializzati dal contenuto dell'immagine.

Non serve `php artisan storage:link`: tutti i file stanno sul disco `local` (privato) e sono serviti dai controller.

### 6bis. Checklist: "in produzione non si caricano media" (Fase 1, 2026-10-26)

Prima cosa da fare, sul server: `docker compose exec app php artisan timone:diagnose-upload` (oppure `php artisan timone:diagnose-upload` senza Docker). Stampa ✅/⚠️/❌ per ognuno dei controlli sotto che è leggibile da PHP ed esce con codice 1 se qualcosa è fallito. Poi riprova un upload e cerca nel log la voce **`upload_failed`** (`docker compose exec app grep -A40 upload_failed storage/logs/laravel.log | tail -60`): contiene l'indirizzo con cui è arrivata la richiesta, `APP_URL`, `X-Forwarded-Proto`, i limiti PHP e i permessi dei dischi, scritti nel momento esatto del fallimento.

Come funziona l'upload (per orientarsi): il browser invia il file a `POST /livewire/upload-file?expires=…&signature=…` (URL firmato generato da Livewire, `WithFileUploads`); Livewire lo valida con `config/livewire.php` → `temporary_file_upload.rules` (`max:` = `config/timone.php` → `upload_max_kb`, mimes) e lo salva sul disco temporaneo (`temporary_file_upload.disk` = `null` → `FILESYSTEM_DISK`, cartella `livewire-tmp`). Al salvataggio del contenuto `ContentCreate`/`ContentEdit` lo rivalidano (`App\Support\ContentFormRules::fileRule()`, stesso limite `upload_max_kb`, 100 MB; fino al 2026-10-27 era 20 MB) e `App\Support\ContentCreator::storeFile()` lo copia sul disco **`local`** (`storage/app/private/contents/{id}`). Se l'upload temporaneo fallisce il browser chiama `ContentCreate::_uploadErrored()`, che scrive `upload_failed` nel log e mostra all'utente «Il file non è arrivato al server…».

Cause in ordine di probabilità:

| # | Causa | Controllo sul server | Cosa guardare / comando |
|---|---|---|---|
| 1 | **`APP_URL` diverso dall'indirizzo reale** (http/https, IP invece del nome, porta, `localhost`) | `grep APP_URL .env` e confronto con la barra del browser | Log `upload_failed`: `APP_URL … — richiesta arrivata su …`; nella console del browser il `POST /livewire/upload-file` risponde **401**. `AppServiceProvider` forza tutte le URL su `APP_URL`: dal 2026-10-28 il repository ha la versione di produzione di `AppServiceProvider`, che verifica la firma dell'upload ricostruendo l'URL da `APP_URL` (macro in `register()`): con `APP_URL` sbagliato l'upload fallisce, e si rompono anche altre URL (miniature, link). Correggi e `php artisan config:clear` |
| 2 | **Proxy HTTPS che non invia `X-Forwarded-Proto`** (o proxy non fidato) | Configurazione del reverse proxy sull'host | Log `upload_failed`: `x_forwarded_proto` vuoto e richiesta su `http://` mentre il browser è su `https://` → firma non valida (401) o contenuto misto bloccato. `bootstrap/app.php` fida già tutti i proxy (`trustProxies(at: '*')`): serve che il proxy mandi `proxy_set_header X-Forwarded-Proto $scheme;` (vedi §5 HTTPS sopra) |
| 3 | **`upload_max_filesize` / `post_max_size` di PHP** troppo bassi | `timone:diagnose-upload` → riga «Limiti PHP»; `docker compose exec app php -i \| grep -E 'upload_max\|post_max'` | Log PHP: `POST Content-Length of … exceeds the limit`. Valori in `docker/php/uploads.ini` (110M/120M dal 2026-10-27, vedi §6ter). Il riquadro di caricamento mostra il limite effettivo e rifiuta subito i file più grandi |
| 4 | **`client_max_body_size` di nginx** (container e/o reverse proxy sull'host) | `grep client_max_body_size docker/nginx/default.conf /etc/nginx -r` | Browser: risposta **413** al `POST /livewire/upload-file`; log nginx: `client intended to send too large body`. Deve essere ≥ `post_max_size` (oggi 120m nel container: va messo uguale anche nel proxy dell'host) |
| 5 | **Permessi su `storage/`** (in particolare `storage/app/private/livewire-tmp` e `storage/app/private/contents`) | `timone:diagnose-upload` → righe «Disco temporaneo Livewire» e «Disco finale dei file» | Log: `failed to open stream: Permission denied` / risposta 500. `docker compose exec -u root app chown -R www-data:www-data storage bootstrap/cache` |
| 6 | **Cartella temporanea di PHP non scrivibile** (`upload_tmp_dir`) | `timone:diagnose-upload` → «Cartella temporanea PHP» | Log PHP: `Missing a temporary folder` / `UPLOAD_ERR_NO_TMP_DIR` |
| 7 | **Disco diverso tra locale e produzione** (`FILESYSTEM_DISK` ≠ `local`, es. `public` o `s3`) | `grep FILESYSTEM_DISK .env` | `timone:diagnose-upload` segnala ⚠️: l'upload temporaneo finirebbe su un disco e i file definitivi sempre su `local` |
| 8 | **Regole `livewire.temporary_file_upload.rules`** più strette del file (dimensione o tipo) | `config/livewire.php` | Risposta **422** all'upload; l'utente vede il messaggio di validazione di Livewire (in italiano, `lang/it`); log `upload_failed` con `validation_errors` valorizzato |
| 9 | **Imagick/Ghostscript non leggono i PDF** (estensione mancante o `policy.xml` che vieta i PDF) | `timone:diagnose-upload` → «Lettura PDF (Imagick)» (prova davvero un PDF di una pagina) | L'utente vede «Impossibile leggere il numero di pagine di questo PDF». Vedi la `policy.xml` patchata nel `Dockerfile` |
| 10 | **`storage:link` mancante** | `ls -la public/storage` | **Non è una causa per questo progetto**: PDF e miniature sono serviti dalle route `content-files.*`/`page-files.*`, non da `/storage`. Elencato per escluderlo |
| 11 | **Upload più lento di `max_upload_time`** (30 minuti dal 2026-10-27, prima 10) o di `max_input_time` di PHP / timeout del proxy (§6ter) | `config/livewire.php` → `max_upload_time` | La firma dell'URL scade: 401 su linee molto lente |
| 12 | **Asset JS vecchi o non caricati** | Console del browser | Senza `app.js` aggiornato il riquadro non mostra il limite e gli errori; vedi [10-debugging.md](10-debugging.md#drag-drop-presence-o-aggiornamenti-realtime-non-funzionano-lato-browser) |

### 6ter. Valori server per file fino a 100 MB (revisione 2, Fase 1, 2026-10-27)

Il limite dell'applicazione è **uno solo**: `config/timone.php` → `upload_max_kb` (default 102400 KB = 100 MB, sovrascrivibile con la variabile d'ambiente facoltativa `TIMONE_UPLOAD_MAX_KB`). Lo usano la regola del modulo contenuti (`App\Support\ContentFormRules::fileRule()`), l'upload temporaneo di Livewire (`config/livewire.php`) e il limite mostrato nel riquadro di caricamento. Il server deve accettare **un po' di più** del limite dell'app, così a rifiutare un file troppo grande è sempre l'app, con un messaggio che dice il limite («Il file supera il limite di 100 MB…»), e non PHP o nginx con un errore muto.

Valori da impostare sul server (non leggibili tutti da PHP: quelli di nginx/apache/proxy vanno controllati a mano):

| Dove | Impostazione | Valore minimo | Nel Docker del repo |
|---|---|---|---|
| PHP (`php.ini` o `conf.d`) | `upload_max_filesize` | **110M** | `docker/php/uploads.ini`: 110M |
| PHP | `post_max_size` | **110M** (consigliato 120M: la richiesta multipart aggiunge qualche KB) | 120M |
| PHP | `max_input_time` | **300** s (o -1): tempo per *ricevere* 100 MB su una linea lenta | 300 |
| PHP | `max_execution_time` | **120** s (o 0): elaborazione nella richiesta (conteggio pagine, salvataggio) | 120 |
| PHP | `memory_limit` | 256M (miniature Imagick nel worker) | 256M |
| nginx (container e reverse proxy dell'host) | `client_max_body_size` | **110M** (≥ `post_max_size`; nel container 120m) | `docker/nginx/default.conf`: 120m |
| nginx | `fastcgi_read_timeout` / `proxy_read_timeout`, `client_body_timeout` | 300s / 300s / ≥ 60s | `fastcgi_read_timeout 300` |
| apache (se usato al posto di nginx) | `LimitRequestBody` | **115343360** (110 MB in byte) o 0 | — |
| Proxy/bilanciatore davanti (es. Cloudflare, HAProxy) | limite del corpo e timeout di lettura | ≥ 110 MB, ≥ 300 s | ⚠️ DA VERIFICARE sul server reale |
| Disco di `storage/app/private/livewire-tmp` | spazio libero | ≥ 2 GB (20 file da 100 MB in transito; Livewire li cancella dopo 24 h) | volume `storage_data` |

Verifica: `docker compose exec app php artisan timone:diagnose-upload` stampa i limiti effettivi di PHP e avvisa (⚠️) se `upload_max_filesize`/`post_max_size` sono sotto la soglia (limite app + 10%, cioè 110 MB), se `max_input_time`/`max_execution_time` sono sotto 300/120 s, e se lo spazio libero per `livewire-tmp` è sotto 2 GB. Attenzione: da riga di comando legge il `php.ini` della CLI; nel Docker del repo `uploads.ini` vale per CLI e PHP-FPM, su un server senza Docker controlla che i valori siano impostati anche per FPM (`php-fpm -i | grep upload_max`). Lo stesso riepilogo, letto da PHP-FPM, finisce nel log alla voce `upload_failed` quando un upload fallisce.

Le miniature dei PDF si generano in coda (job `GenerateContentFileThumbnail`, servizio `worker`), non nella richiesta di upload. Nella richiesta resta solo il conteggio delle pagine, fatto con Ghostscript senza rasterizzare (`App\Support\PdfPageMeasurer::pageCount()`, ~0,03 s anche per un PDF di stampa; Imagick solo come ripiego): serve `gs` installato sul server (nel `Dockerfile` c'è già).

### 7. Scheduler, code, backup

- Code e scheduler: già gestiti dai servizi `worker` e `scheduler`. **Niente supervisor né crontab per Laravel.**
- Backup: **non schedulato nel repo**. Template di crontab sull'host:

```cron
# ⚠️ TEMPLATE — verificare contro il server reale
# DB ogni notte alle 02:00 + file caricati + pulizia dei backup più vecchi di 30 giorni
0 2 * * *  cd /opt/timone-rivista && docker compose --profile tools run --rm backup >> /var/log/timone-backup.log 2>&1
15 2 * * * docker run --rm -v timone-rivista_storage_data:/data:ro -v /opt/timone-rivista/backups:/backup alpine tar czf /backup/storage_$(date +\%F).tar.gz -C /data . >> /var/log/timone-backup.log 2>&1
0 3 * * *  find /opt/timone-rivista/backups -type f -mtime +30 -delete
```

Copiare i backup **fuori dal server**: ⚠️ DA VERIFICARE la destinazione.

---

## c) Deploy di un aggiornamento

Ordine consigliato. `DOCKER.md` §"Aggiornare il codice" indica la versione minima: `git pull` → `up -d --build` → `migrate --force`.

```bash
cd /opt/timone-rivista

# 1. Backup prima di tutto
docker compose --profile tools run --rm backup

# 2. Annota il commit attuale (serve per il rollback)
git rev-parse HEAD > .last-deploy-commit

# 3. Codice
git fetch origin && git status          # nessuna modifica locale attesa (eccetto .env e override, non tracciati)
git pull --ff-only origin master

# 4. Controlla se ci sono migration bloccanti (vedi 04-database.md)
docker compose exec -T app php artisan timone:missing-occupancy

# 5. Build delle nuove immagini (composer install --no-dev e npm run build avvengono QUI, nel Dockerfile)
docker compose build

# 5bis. (una tantum, deploy della Fase 2 del 2026-10-26) anteprima della conversione delle
# copertine: per ogni numero mostra cosa diventa cosa, senza scrivere nulla.
# Va eseguito con la NUOVA immagine e PRIMA del migrate. Controllare gli avvisi
# (es. due pagine con lo stesso ruolo copertina). Vedi 04-database.md, cover_conversion_logs.
docker compose run --rm --no-deps app php artisan timone:covers-dry-run

# 5ter. (una tantum, deploy della Fase 3 del 2026-10-27) anteprima della conversione
# dell'occupazione da percentuali a dodicesimi di pagina, senza scrivere nulla.
# Con la NUOVA immagine e PRIMA del migrate. Controllare le righe ⚠️ (pagine che
# dopo l'arrotondamento superano la capienza: vanno sistemate a mano dopo il deploy).
docker compose run --rm --no-deps app php artisan timone:occupancy-dry-run

# 5quater. (una tantum, deploy della revisione 2, Fase 2, 2026-10-27) anteprima della
# posizione di ogni contenuto sulla griglia 6×6, senza scrivere nulla. Con la NUOVA
# immagine e PRIMA del migrate. Righe [NON ENTRA]: pagine oltre capienza da sistemare.
docker compose run --rm --no-deps app php artisan timone:grid-dry-run

# 6. Migrazioni con la nuova immagine, prima di sostituire i container
docker compose run --rm --no-deps app php artisan migrate --force

# 7. Sostituzione dei container (app, nginx, worker, reverb, scheduler vengono ricreati → il worker riparte con il codice nuovo)
docker compose up -d

# 7bis. (una tantum, revisione 2, Fase 2.4) proporzioni dei file già caricati, per il
# ritaglio delle anteprime. Facoltativo: senza, le anteprime vecchie restano intere.
docker compose exec app php artisan timone:file-ratios --dry-run
docker compose exec app php artisan timone:file-ratios

# 7ter. (una tantum, revisione 2, Fase 3.1, 2026-10-28) pubblicità senza cliente né
# file che la regola precedente considerava "vere" per il solo titolo: tornano
# segnaposto. Prima il dry-run (elenca numero e titolo), poi l'applicazione.
docker compose exec app php artisan timone:placeholder-sync --dry-run
docker compose exec app php artisan timone:placeholder-sync

# (revisione 2, Fase 5, 2026-10-28) nuove dipendenze composer (setasign/fpdi,
# tecnickcom/tcpdf): entrano con la ricostruzione dell'immagine (composer install
# dal composer.lock). Migration 2026_10_28_200000 = solo una tabella nuova.
# Il worker deve poter usare fino a TIMONE_EXPORT_MEMORY_LIMIT (default 1024M)
# di RAM durante un export: misurati 156 MB per 68 pagine da 72 MB di PDF.

# (revisione 2, Fase 4, 2026-10-28) la migration 2026_10_28_100000 cambia solo
# tre vincoli (uploaded_by/user_id → nullOnDelete), nessun dato: passa con il
# migrate del punto 6. Provata up/down/up su una copia del DB di sviluppo.

# 8. Verifica
docker compose ps
docker compose logs --tail=50 app worker reverb scheduler
```

Note:
- **`composer install --no-dev` e `npm run build` non si lanciano sul server**: li esegue il `Dockerfile`, stage `composer_builder` e `node_builder`.
- **Cache Laravel** (`config:cache`, `route:cache`, `view:cache`): il progetto **non** le genera, né nel Dockerfile né in `setup.sh`. Sono opzionali; se le usi, vanno eseguite in **ogni** container PHP (app, worker, scheduler, reverb), perché ognuno ha il proprio filesystem. Non sono necessarie.
- **OPcache** ha `validate_timestamps=0`: un nuovo codice richiede container nuovi, cosa che `up -d` fa già quando l'immagine cambia.
- **`docker compose restart` non basta** dopo un `git pull` in modalità prod: il codice è dentro l'immagine e serve `build` + `up -d`.
- Un cambio delle sole variabili `VITE_*` richiede `build`. Un cambio delle altre variabili del `.env` richiede solo `docker compose up -d`, che ricrea i container con il nuovo `env_file`.
- Dopo il deploy, i browser già aperti hanno ancora il vecchio bundle JS: basta un refresh.

## d) Rollback

```bash
cd /opt/timone-rivista
PREV=$(cat .last-deploy-commit)

# Se il deploy ha applicato migration che il codice vecchio non regge:
docker compose run --rm --no-deps app php artisan migrate:status    # individua le migration del deploy
# opzione A (preferibile se le migration hanno down() corretti):
docker compose run --rm --no-deps app php artisan migrate:rollback --step=<N> --force
# opzione B: restore del backup fatto al passo 1 (vedi 04-database.md#backup-e-restore) — perde i dati scritti dopo il backup

git checkout "$PREV"            # detached HEAD: ricordati di tornare su master al prossimo deploy
docker compose build && docker compose up -d
```

Le immagini **non sono taggate per versione**: compose le ricostruisce con il nome `timone-rivista-<servizio>:latest`. Il rollback passa quindi da una rebuild del commit precedente, che richiede accesso a Docker Hub, npm e Packagist. In alternativa, prima del passo 5 del deploy, salva le immagini correnti:

```bash
for s in app nginx worker reverb scheduler; do docker tag timone-rivista-$s:latest timone-rivista-$s:rollback; done
```

⚠️ DA VERIFICARE: i nomi esatti delle immagini con `docker images | grep timone-rivista` sul server.

La migration `2026_10_12_100001` ha un `down()` che rende di nuovo nullable `page_occupancy`: il rollback è sicuro.

La migration `2026_10_27_100000_convert_occupancy_to_twelfths` (Fase 3) ha un `down()` che riporta lo schema alle percentuali: le colonne vecchie conservano i valori originali, le righe create dopo il deploy ricevono valori ricavati dai dodicesimi (2 e 1/2 pagine diventa 3 pagine, il vecchio modello non le rappresenta). Le modifiche fatte dopo il deploy allo spazio delle assegnazioni **esistenti** non vengono riportate indietro (il rollback torna ai valori di prima del deploy).

## e) Checklist post-deploy

- [ ] `docker compose ps`: tutti i servizi `Up`; `mysql`, `redis`, `app` e `reverb` anche `healthy`.
- [ ] `curl -I https://<dominio>/up` → 200 (health check di Laravel, `bootstrap/app.php`).
- [ ] Login con un utente reale; `/riviste` si carica.
- [ ] Apertura di un numero: griglia visibile, **nessun errore nella console del browser**. In particolare niente `You must pass your app key` e niente errori WebSocket.
- [ ] Realtime: stesso numero aperto in due browser con utenti diversi. Lo spostamento di una pagina in uno compare nell'altro, e la barra "utenti online" mostra entrambi.
- [ ] Drag&drop pagine e drop di un contenuto su una pagina.
- [ ] Upload di un PDF di 2+ pagine → le miniature passano da "in elaborazione" a visibili (verifica worker, Imagick, Ghostscript e policy PDF).
- [ ] Export "Timone PDF" e "Carico pubblicitario CSV/PDF".
- [ ] Bottone «?» in basso a sinistra: la domanda «come sposto una pagina» mostra «Spostare le pagine». Il primo deploy con l'assistente richiede la migrazione `2026_10_29_300000` (`php artisan migrate`); `docker compose exec app php artisan manuale:apprendi` deve uscire con «Set di riferimento verde».
- [ ] `/manuale/legenda` mostra lo screenshot della card con i numeri (le immagini sono in `public/manuale/img/`, copiate nell'immagine nginx con `public/`).
- [ ] `/manuale` si apre, e una pagina come `/manuale/spostare-pagine` (le pagine di `resources/manuale/` sono nell'immagine: verificato con una build il 2026-10-29, 46 file).
- [ ] `docker compose exec app php artisan migrate:status`: nessuna migration *Pending*.
- [ ] `docker compose exec app php artisan queue:failed`: nessun job fallito nuovo.
- [ ] `docker compose exec app tail -n 50 storage/logs/laravel.log`: nessun errore nuovo.
- [ ] `docker compose logs scheduler`: lo scheduler gira.
- [ ] Se le mail sono configurate: `php artisan tinker` → `Mail::raw('test', fn($m) => $m->to('tu@dominio.it'));` e verifica la ricezione.

---

## Alternativa: deploy senza Docker (non presente nel repo)

Solo se il server non può usare Docker. **Nessuno di questi file esiste nel repo**: sono derivati dal `Dockerfile` e dal `docker-compose.yml`.

```bash
# ⚠️ TEMPLATE — verificare contro il server reale (Debian/Ubuntu, PHP 8.2 da repository ondrej/sury)
apt install nginx mysql-server redis-server ghostscript imagemagick \
  php8.2-fpm php8.2-mysql php8.2-bcmath php8.2-gd php8.2-imagick php8.2-redis php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip
# rimuovere/commentare la policy PDF di ImageMagick (/etc/ImageMagick-6/policy.xml o -7), come fa il Dockerfile
# php.ini: upload_max_filesize=110M, post_max_size=120M, max_input_time=300, max_execution_time=120, memory_limit=256M, display_errors=Off (come docker/php/uploads.ini, vedi §6ter)
composer install --no-dev --optimize-autoloader
npm ci && npm run build            # con VITE_REVERB_* nel .env
php artisan migrate --force
chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache
```

```ini
; ⚠️ TEMPLATE — /etc/supervisor/conf.d/timone.conf
[program:timone-worker]
command=php /var/www/timone/artisan queue:work redis --tries=3 --backoff=3 --sleep=1
user=www-data
autostart=true
autorestart=true
numprocs=1
stdout_logfile=/var/log/timone-worker.log

[program:timone-reverb]
command=php /var/www/timone/artisan reverb:start
user=www-data
autostart=true
autorestart=true
stdout_logfile=/var/log/timone-reverb.log
```

```cron
# ⚠️ TEMPLATE — crontab di www-data (al posto del servizio "scheduler")
* * * * * cd /var/www/timone && php artisan schedule:run >> /dev/null 2>&1
```

Per nginx usa `docker/nginx/default.conf`, sostituendo `fastcgi_pass app:9000` con il socket PHP-FPM locale (es. `unix:/run/php/php8.2-fpm.sock`) e `root` con il percorso reale. Aggiungi la sezione TLS/WebSocket del punto b.5.

Dopo ogni deploy: `php artisan queue:restart` e `supervisorctl restart timone-reverb`.
