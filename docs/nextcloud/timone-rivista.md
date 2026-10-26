# Timone Elettronico (timone-rivista)

Strumento interno della redazione per gestire il timone (flat plan) delle riviste: pagine, articoli, pubblicità, PDF di stampa e collaborazione in tempo reale tra più utenti. Non ha area pubblica: tutto è dietro login.

## Scheda rapida

| Voce | Valore |
|---|---|
| Nome | Timone Elettronico (repo `timone-rivista`) |
| URL produzione | ⚠️ DA VERIFICARE (non presente nel repo) |
| URL staging | ⚠️ DA VERIFICARE (nessuna traccia di staging) |
| URL locale | http://localhost (app), http://localhost:8081 (Adminer) |
| Repository | https://its.git/pietro/timone-rivista |
| Branch di produzione | master (unico branch) |
| Stack | PHP 8.2, Laravel 12, Livewire 3, Laravel Reverb (WebSocket), Tailwind 3 + Vite 6, MySQL 8.4, Redis 7 |
| Infrastruttura | Docker Compose: app (PHP-FPM), nginx, worker, reverb, scheduler, mysql, redis, adminer |
| Server | ⚠️ DA VERIFICARE (host, IP, OS, provider) |
| Referente | ⚠️ DA VERIFICARE |
| CI/CD | Nessuna |

## Accessi necessari

| Accesso | Dove si trova |
|---|---|
| Repository (https://its.git/pietro/timone-rivista) | Chiedere l'accesso all'amministratore del repo (⚠️ DA VERIFICARE chi è) |
| SSH al server di produzione | ⚠️ DA VERIFICARE |
| File .env di produzione (APP_KEY, DB_PASSWORD, MYSQL_ROOT_PASSWORD, REVERB_APP_ID/KEY/SECRET, MAIL_*) | Sul server, nella cartella del progetto. Va conservata una copia nel password manager aziendale. Mai nel repo |
| Credenziali SMTP | ⚠️ DA VERIFICARE (oggi le mail sono solo scritte nel log: MAIL_MAILER=log) |
| Primo utente Admin dell'app | Creato a mano con tinker al primo deploy (vedi docs/09-deploy.md nel repo) |
| Database | Adminer sulla porta 8081 del server, con DB_USERNAME e DB_PASSWORD del .env |

## Setup locale in breve

Serve solo Docker Desktop.

```bash
git clone https://its.git/pietro/timone-rivista
cd timone-rivista
./setup.sh dev
```

Lo script crea il .env, genera le password, avvia i container, esegue le migration e carica i dati demo. Login demo: admin@timone.test oppure redattore@timone.test, password "password" (solo in locale).

I PDF funzionano solo dentro Docker (servono Imagick e Ghostscript). Dettagli in docs/01-setup-locale.md nel repo.

## Deploy in breve

Aggiornamento di un server già installato:

```bash
cd <cartella-progetto>
docker compose --profile tools run --rm backup
git pull --ff-only origin master
docker compose exec -T app php artisan timone:missing-occupancy
docker compose build
docker compose run --rm --no-deps app php artisan migrate --force
docker compose up -d
docker compose ps
```

Punti chiave:

- Composer e npm girano dentro la build Docker, non sul server.
- Su un server nuovo NON lanciare ./setup.sh su DB vuoto: crea gli utenti demo con password nota. Seguire la procedura in docs/09-deploy.md.
- HTTPS e WebSocket sicuro non sono nel repo: serve un reverse proxy (template in docs/09-deploy.md).
- Se si cambiano le variabili VITE_REVERB_* serve la rebuild (docker compose up -d --build).

Rollback: git checkout del commit precedente, docker compose build, docker compose up -d. Se le migration lo richiedono, migrate:rollback oppure restore del backup.

## Servizi esterni

| Servizio | Uso | Configurazione | Se è giù |
|---|---|---|---|
| SMTP | Mail di sollecito scadenza stampa, reset password | MAIL_* nel .env | Solleciti persi (vengono marcati come inviati già all'accodamento); reset password in errore |
| Reverb (interno, container reverb) | Aggiornamenti in tempo reale tra utenti | REVERB_*, VITE_REVERB_* | Le azioni nel timone vanno in errore; i client passano al polling ogni secondo |
| Imagick + Ghostscript (dentro l'immagine) | Miniature, conteggio pagine e controllo formato dei PDF | nessuna | PDF rifiutati o miniature "fallite" |

Non ci sono API di terze parti (pagamenti, S3, ecc.).

## Job e cron

| Cosa | Dove gira | Quando |
|---|---|---|
| Miniature PDF (GenerateContentFileThumbnail) | Container worker (coda Redis, 3 tentativi) | A ogni upload, un job per pagina |
| Mail di sollecito (timone:send-deadline-reminders) | Container scheduler | Ogni giorno alle 07:30 UTC |
| Backup DB | Manuale: docker compose --profile tools run --rm backup | Non schedulato (⚠️ DA VERIFICARE cron sul server) |
| Pulizia PDF orfani (pagefiles:prune-orphaned) | Manuale | Su richiesta, sempre prima con --dry-run |

Non servono supervisor né crontab per Laravel: worker e scheduler sono container con restart automatico.

## Dove guardare quando si rompe

```bash
docker compose ps
docker compose logs -f app worker reverb scheduler nginx
docker compose exec app tail -n 100 storage/logs/laravel.log
docker compose exec app php artisan queue:failed
docker compose exec app php artisan migrate:status
```

| Sintomo | Prima cosa da controllare |
|---|---|
| Errore 500 | storage/logs/laravel.log |
| Errore 502 | docker compose restart nginx (tiene il vecchio IP del container app) |
| Azioni nel timone che falliscono | Container reverb attivo? (log: "Could not resolve host: reverb") |
| Drag&drop morto, console "You must pass your app key" | REVERB_APP_KEY nel .env, poi rebuild |
| Miniature ferme | Container worker attivo? thumbnail_status in tabella content_files |
| Mail non arrivate | MAIL_MAILER (log = non inviate), queue:failed, redattore di riferimento impostato sulla rivista |
| migrate si ferma su page_occupancy | php artisan timone:missing-occupancy, poi valorizzare i contenuti elencati |
| Modifiche al codice non visibili | Immagini non ricostruite: docker compose build e docker compose up -d |

## Punti di attenzione

- Vulnerabilità note: Livewire 3.8.2 (XSS) e Laravel 12.64 vanno aggiornati (composer audit: 19 advisory).
- La migration che rende obbligatorio page_occupancy blocca migrate finché esistono contenuti senza occupazione.
- Cancellare un utente (anche dal suo profilo) cancella a cascata i file che ha caricato.
- Backup non schedulati e file caricati (volume storage_data) non inclusi nel backup.
- Adminer è esposto sulla porta 8081; il WebSocket è su ws:// in chiaro sulla 8080.
- Ogni pagina di un PDF multipagina salva una copia intera del PDF: tenere d'occhio il disco.
- Mai lanciare docker compose down -v in produzione: cancella database e file.

## Documentazione completa nel repo

- docs/README.md (indice)
- docs/01-setup-locale.md
- docs/09-deploy.md
- docs/10-debugging.md
- docs/11-debito-tecnico.md
- docs/DA-VERIFICARE.md
- HANDOFF.md (cronologia completa dello sviluppo), MANUALE_UTENTE.md (manuale per la redazione, anche nell'app su /manuale)
