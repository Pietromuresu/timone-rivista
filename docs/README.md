# Timone Elettronico (`timone-rivista`) — Documentazione tecnica di passaggio di consegne

> Documentazione scritta il 2026-10-26 sullo stato del branch `master` al commit `bd156ff`.
> I punti marcati `⚠️ DA VERIFICARE` sono informazioni **non deducibili dal repository**: l'elenco completo è in [DA-VERIFICARE.md](DA-VERIFICARE.md).

## Panoramica in 10 righe

1. **Cos'è**: un "timone elettronico" (flat plan) per riviste cartacee. Sostituisce il timone su carta (foto di riferimento: `timone_cartaceo_riferimento.jpg` nella root del repo).
2. **Cosa fa**: gestisce riviste → numeri → pagine. Ogni pagina può ricevere articoli e pubblicità, avere uno stato redazionale e un PDF. Le pagine si riordinano con il drag&drop.
3. **Chi lo usa**: la redazione interna (ruoli Admin, Redattore, Commerciale, Sola lettura; vedi [03-dominio-e-flussi.md](03-dominio-e-flussi.md#ruoli-e-permessi)). Non ha area pubblica: `/` porta al login (`routes/web.php:26`).
4. **Collaborativo in tempo reale**: più utenti sullo stesso numero vedono le modifiche degli altri via WebSocket (Laravel Reverb). Se il WebSocket cade, il client passa al polling.
5. **Pubblicità**: riepilogo sempre visibile (riempimento, spazio libero, % pubblicità, segnaposto, copertine, peso), cruscotto del carico pubblicitario (senza soglie dal 2026-10-27), export CSV/PDF, controllo del formato dei PDF rispetto al listino.
6. **PDF**: upload di PDF anche multipagina, generazione di miniature in coda (Imagick + Ghostscript), export PDF dell'intero timone (dompdf).
7. **Scadenze**: mail di sollecito al redattore di riferimento prima della scadenza di stampa (scheduler giornaliero).
8. **Stack**: Laravel 12 + Livewire 3 + Reverb + Tailwind, MySQL 8.4, Redis 7. Tutto containerizzato con Docker Compose.
9. **Deploy**: non c'è CI/CD. L'unico meccanismo presente nel repo è `./setup.sh` / `docker compose` (vedi [09-deploy.md](09-deploy.md)).
10. **Stato**: sviluppato a fasi dal 2026-07-23 al 2026-10-12. La cronologia dettagliata di ogni sessione è in `HANDOFF.md` nella root, circa 1200 righe e molto ricco: è la fonte primaria per il "perché" delle scelte.

## Ambienti

| Ambiente | URL | Note |
|---|---|---|
| Locale (Docker) | `http://localhost` (app), `http://localhost:8081` (Adminer), `ws://localhost:8080` (Reverb), `http://localhost:5173` (Vite, solo modalità dev) | Stampato da `setup.sh` a fine avvio |
| Produzione | ⚠️ DA VERIFICARE: nessun URL di produzione nel repo (`.env.example` ha `APP_URL=http://localhost`). Chiedere a chi passa le consegne. | — |
| Staging | ⚠️ DA VERIFICARE: nessuna traccia di un ambiente di staging nel repo. | — |

- **Repository**: `https://its.git/pietro/timone-rivista` (nota: nel clone analizzato il remote `origin` punta ancora a un vecchio indirizzo GitHub; aggiornarlo con `git remote set-url origin https://its.git/pietro/timone-rivista`). Unico branch: `master`.
- **Referente / proprietario del progetto**: ⚠️ DA VERIFICARE. I commit sono tutti di un unico autore; chi amministra il repository e il server non risulta dal codice.

## Stack

| Componente | Versione | Fonte |
|---|---|---|
| PHP | 8.2 (`^8.2`), immagine `php:8.2-fpm-alpine` | `composer.json`, `Dockerfile` |
| Laravel | 12.64.0 | `php artisan about` |
| Livewire | 3.8.2 (vincolo `^3.6`, v4 esclusa di proposito) | `composer.lock`, `HANDOFF.md` §Decisioni |
| Laravel Reverb (WebSocket) | 1.11.0 | `composer show` |
| Alpine.js | quello incluso in Livewire (non installato a parte) | `resources/js/app.js:5-19` |
| Tailwind CSS | 3.4.19 | `npm ls` |
| Vite | 6.4.3 | `npm ls` |
| Node.js (build) | 20 (`node:20-alpine`) | `Dockerfile`, `docker-compose.dev.yml` |
| MySQL | 8.4 | `docker-compose.yml` |
| Redis | 7 (alpine) | `docker-compose.yml` |
| Nginx | `nginx:alpine` (versione non fissata) | `Dockerfile` stage `nginx_runtime` |
| Test | Pest 3.8 | `composer.json` |

## Dove trovare cosa

| Documento | Contenuto |
|---|---|
| [01-setup-locale.md](01-setup-locale.md) | Da macchina vuota a progetto funzionante |
| [02-architettura.md](02-architettura.md) | Cartelle, pattern, flusso di una richiesta, decisioni non ovvie |
| [03-dominio-e-flussi.md](03-dominio-e-flussi.md) | Concetti di dominio, ruoli, flussi di business passo passo |
| [04-database.md](04-database.md) | Tabelle, relazioni, dati da non cancellare, backup/restore |
| [05-configurazione.md](05-configurazione.md) | Tutte le variabili d'ambiente |
| [06-dipendenze.md](06-dipendenze.md) | Dipendenze di sistema, PHP, npm, come aggiornarle, vulnerabilità note |
| [07-integrazioni.md](07-integrazioni.md) | Servizi esterni/collaterali (Reverb, mail, Imagick/Ghostscript, ...) |
| [08-job-e-scheduler.md](08-job-e-scheduler.md) | Job in coda, comandi artisan custom, scheduler |
| [09-deploy.md](09-deploy.md) | Requisiti server, primo deploy, aggiornamento, rollback, checklist |
| [OPERATIONS.md](OPERATIONS.md) | **Backup e ripristino** (dal 2026-10-30): cosa si salva, attivazione, copia remota, registro |
| [10-debugging.md](10-debugging.md) | **Il più importante**: log, strumenti, sintomo → causa → soluzione |
| [11-debito-tecnico.md](11-debito-tecnico.md) | Debito tecnico e rischi ordinati per priorità |
| [12-glossario.md](12-glossario.md) | Termini di dominio e valori degli enum |
| [DA-VERIFICARE.md](DA-VERIFICARE.md) | Domande aperte da fare prima del passaggio di consegne |
| [CHANGELOG.md](CHANGELOG.md) | Modifiche visibili e tecniche, per fase, dal batch "revisione con gli utenti" (2026-10-26) in poi |
| [nextcloud/timone-rivista.md](nextcloud/timone-rivista.md) | Scheda sintetica per Nextcloud |

Documenti preesistenti nella root del repo, ancora validi e complementari a questi:

- `HANDOFF.md`: cronologia di ogni fase, decisioni architetturali, bug trovati e corretti.
- `MANUALE_UTENTE.md`: manuale per la redazione, mostrato anche nell'app su `/manuale`.
- `DOCKER.md`: avvio Docker per un utente non tecnico.
- `README.md`: punto d'ingresso per un utente non tecnico.
- `CLAUDE.md`: regole di lavoro permanenti per le sessioni di sviluppo assistito (documentare ogni modifica in `HANDOFF.md`, SOLID pragmatico).
- `CLAUDE_PROMPTS/PROMPT_DI_PARTENZA.md`: specifica funzionale originale.
