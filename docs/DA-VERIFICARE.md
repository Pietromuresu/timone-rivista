# Da verificare prima del passaggio di consegne

[← Indice](README.md)

Elenco unico delle informazioni **non deducibili dal repository**, o deducibili solo in parte. Ogni voce indica dove compare nella documentazione. Sono le domande da fare a chi passa le consegne **prima** che se ne vada. Le voci sono raggruppate per tema e ordinate per urgenza.

Totale: **17 domande** (35 marcature `⚠️ DA VERIFICARE` nei file).

## Ambienti e accessi (bloccanti per operare)

| # | Domanda | Dove compare | Dove cercare la risposta |
|---|---|---|---|
| DV-01 | Qual è l'**URL di produzione** (dominio di `APP_URL` e `VITE_REVERB_HOST`)? | [README.md](README.md#ambienti), [05-configurazione.md](05-configurazione.md#applicazione) (righe `APP_URL`, `VITE_REVERB_HOST`, checklist), [09-deploy.md](09-deploy.md#5-https-e-websocket), [nextcloud](nextcloud/timone-rivista.md) | `.env` sul server; DNS |
| DV-02 | Esiste un ambiente di **staging**? | [README.md](README.md#ambienti), [nextcloud](nextcloud/timone-rivista.md) | Chi passa le consegne |
| DV-03 | Chi è il **referente/proprietario** del progetto e chi **amministra il repository** `https://its.git/pietro/timone-rivista`? | [README.md](README.md#ambienti), [nextcloud](nextcloud/timone-rivista.md) | Impostazioni del repository su its.git |
| DV-04 | **Server di produzione**: host/IP, provider, OS, chi ha accesso SSH, percorso del progetto. **Come è stato fatto il deploy esistente** (`./setup.sh`? altro?), c'è un **reverse proxy/TLS** davanti, chi gestisce **DNS e certificati**? | [09-deploy.md](09-deploy.md#cosa-cè-e-cosa-non-cè-nel-repo), [09-deploy.md §a](09-deploy.md#a-requisiti-server) (OS), [nextcloud](nextcloud/timone-rivista.md) | Accesso al server, provider di hosting, registrar |
| DV-05 | **Mail**: provider SMTP, credenziali, indirizzo mittente (`MAIL_FROM_ADDRESS`) di produzione. Oggi le mail partono davvero o sono su `log`? | [07-integrazioni.md](07-integrazioni.md#mail-smtp), [05-configurazione.md](05-configurazione.md#mail), [nextcloud](nextcloud/timone-rivista.md) | `.env` sul server; account del provider |

## Dati e continuità

| # | Domanda | Dove compare | Dove cercare la risposta |
|---|---|---|---|
| DV-06 | **Backup in produzione**: esistono backup schedulati (cron)? Dove vengono copiati (off-site)? Con che retention? Sono inclusi i **file caricati** (volume `storage_data`)? È mai stato provato un restore? | [04-database.md](04-database.md#backup-e-restore), [09-deploy.md](09-deploy.md#7-scheduler-code-backup), [nextcloud](nextcloud/timone-rivista.md) | `crontab -l` sul server, cartella `backups/` |
| DV-07 | La migration **`2026_10_12_100001` (NOT NULL `page_occupancy`)** è già applicata in produzione o è *Pending*? Chi decide i valori di backfill per i contenuti senza occupazione? | [04-database.md](04-database.md#migration-bloccante-page_occupancy) | `php artisan migrate:status` e `php artisan timone:missing-occupancy` sul server; redazione |
| DV-08 | Il comando **`timone:migrate-legacy-page-files`** è stato eseguito in produzione? Esistono ancora `page_files` legacy? | [08-job-e-scheduler.md](08-job-e-scheduler.md#timonemigrate-legacy-page-files---dry-run) | `SELECT COUNT(*) FROM page_files`, `--dry-run` sul server |

## Sicurezza e infrastruttura

| # | Domanda | Dove compare | Dove cercare la risposta |
|---|---|---|---|
| DV-09 | La porta **8081 (Adminer)**, e la 8080 di Reverb, sono filtrate dal firewall in produzione? | [07-integrazioni.md](07-integrazioni.md#adminer) | Firewall/security group del server |
| DV-10 | Esiste **monitoraggio** (uptime, alert, raccolta errori) lato server? | [10-debugging.md](10-debugging.md#dove-sono-i-log) | Chi passa le consegne |
| DV-11 | **Dimensionamento** del server (RAM/CPU/disco) e spazio libero attuale (i PDF sono duplicati per ogni pagina) | [09-deploy.md](09-deploy.md#a-requisiti-server) | `free -h`, `df -h`, `docker system df -v` |
| DV-12 | Versione di **Docker Compose** sul server (il template di override usa `!override`, che richiede ≥ 2.24) | [09-deploy.md](09-deploy.md#5-https-e-websocket) | `docker compose version` |
| DV-13 | **Nomi delle immagini** buildate sul server (per il tag di rollback) | [09-deploy.md](09-deploy.md#d-rollback) | `docker images \| grep timone-rivista` |
| DV-14 | Pianificazione dell'aggiornamento da **PHP 8.2** (fine del supporto di sicurezza a fine 2026) | [06-dipendenze.md](06-dipendenze.md#dipendenze-a-rischio--da-tenere-docchio) | Decisione tecnica |

## Comportamenti da confermare con una prova

| # | Domanda | Dove compare | Come verificarlo |
|---|---|---|---|
| DV-15 | In modalità `./setup.sh dev`, le modifiche PHP sono visibili senza riavviare `app`? (OPcache `validate_timestamps=0` non è sovrascritto in dev) | [01-setup-locale.md](01-setup-locale.md#5-differenze-tra-setupsh-e-setupsh-dev), [10-debugging.md](10-debugging.md#cache--config-stale), [11-debito-tecnico.md](11-debito-tecnico.md) (#20) | Modifica un testo in una view o un controller e ricarica; `docker compose exec app php -i \| grep validate_timestamps` |
| DV-16 | Il container **`scheduler`** esegue davvero `timone:send-deadline-reminders` ogni giorno? (`HANDOFF.md`: validato solo a livello di sintassi) | [08-job-e-scheduler.md](08-job-e-scheduler.md#scheduler) | `docker compose logs scheduler` sul server; `issues.deadline_reminder_sent_at` valorizzati |
| DV-17 | Un PDF molto pesante può superare il **timeout di 60 s** del worker durante il rendering delle miniature? | [08-job-e-scheduler.md](08-job-e-scheduler.md#infrastruttura-asincrona) | Log del worker (`run too long`), `failed_jobs` |

## Altre cose da chiedere (non marcate nei file, ma utili)

- Esistono **dati reali** in produzione, quanti utenti e quante riviste? È mai stato usato in esercizio?
- La lista "Da verificare manualmente" dei fix del 2026-10-12 (`HANDOFF.md:1166-1171`) è stata eseguita?
- Il numero chiuso deve restare modificabile? Il ruolo `commerciale` deve avere funzioni dedicate? (vedi [11-debito-tecnico.md](11-debito-tecnico.md), #26-27)
- Fuso orario voluto per i solleciti: 07:30 **UTC** è intenzionale?
