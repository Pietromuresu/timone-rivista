# Da verificare prima del passaggio di consegne

[← Indice](README.md)

Elenco unico delle informazioni **non deducibili dal repository**, o deducibili solo in parte. Ogni voce indica dove compare nella documentazione. Sono le domande da fare a chi passa le consegne **prima** che se ne vada. Le voci sono raggruppate per tema e ordinate per urgenza.

Totale: **22 domande**: 17 sul progetto (35 marcature `⚠️ DA VERIFICARE` nei file) e 5 sul calcolo del peso rivista (sezione dedicata).

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
| DV-23 | **Upload in produzione** (Fase 1, 2026-10-26): qual è la causa reale? Senza accesso al server è stata individuata la più probabile (`APP_URL` diverso dall'indirizzo reale → firma dell'upload non valida, corretta lato app) ma non confermata | [09-deploy.md §6bis](09-deploy.md#6bis-checklist-in-produzione-non-si-caricano-media-fase-1-2026-10-26) | Sul server: `php artisan timone:diagnose-upload`; poi un upload di prova e `grep -A40 upload_failed storage/logs/laravel.log` |
| DV-25 | ~~Bug 8, formato copertina su più pagine~~ **Deciso nella Fase 2 (2026-10-26)**: dalla II di copertina si continua sulle pagine 1, 2…; dalla III si occupano le ultime pagine interne e poi la III; I, IV e bandella una sola pagina. Da confermare con la redazione che corrisponda all'uso reale (es. una pubblicità «II + pagina 1») | `App\Support\ContentPlacer::coverTargets()` | Prova con la redazione |
| DV-26 | Conversione copertine in produzione (Fase 2): eseguire `php artisan timone:covers-dry-run` sul server prima del `migrate` e controllare gli avvisi; in sviluppo il numero #3 aveva due pagine con ruolo «copertina2» e due con «battente» (le seconde restano interne) | [09-deploy.md](09-deploy.md#c-deploy-di-un-aggiornamento), [04-database.md](04-database.md) | Output del dry-run sul server |
| DV-24 | Animazione dei gruppi durante il drag (MultiDrag), transizione finale e frecce, zoom S/M/L, header fisso con auto-scroll: verificati solo nel codice e con test sul markup | `resources/js/timone-alpine.js` | Prova manuale nel browser (checklist in `HANDOFF.md`, sessione 2026-10-26 "Fase 1"), almeno Chrome e Safari |
| DV-27 | Conversione dell'occupazione in produzione (Fase 3, 2026-10-27): eseguire `php artisan timone:occupancy-dry-run` sul server prima del `migrate` e controllare le righe ⚠️ (pagine oltre capienza dopo l'arrotondamento) e "(arrotondato)". In sviluppo: nessuna pagina oltre capienza, 3 assegnazioni arrotondate (99,7% → intera, 33% → 1/3, 49,8% → 1/2) | [09-deploy.md](09-deploy.md), [04-database.md](04-database.md#conversione-in-dodicesimi-fase-3-2026-10-27) | Output del dry-run sul server |
| DV-28 | Mappatura dei formati senza frazione esatta, scelta di default della Fase 3: **Copertina 2ª/3ª/4ª** (52% di listino) = pagina intera; **Battente** = una pagina (la bandella è un solo riquadro); **Piedino** (32,6%) = 1/3; **2/3** (70,5%) = 2/3; «Un quarto pagina» = vetrina. Da confermare con la redazione/commerciale | `App\Enums\AdFormat::twelfths()` | Listino e uso reale |
| DV-29 | Barra di riempimento, etichette «Libero: …/Piena» e menu dei dodicesimi sulla card: verificati solo sul markup (nessun Dusk) | `partials/page-fill.blade.php`, `partials/page-content-entry.blade.php` | Prova manuale nel browser (checklist in `HANDOFF.md`, sessione 2026-10-27) |
| DV-30 | Fase 4: rinomina inline (doppio click, Invio/Esc, anche sopra una miniatura), modale di modifica (scroll, Esc, clic fuori), sostituzione del PDF: verificati solo con Livewire::test() e sul markup | `page-content-entry.blade.php`, `content-edit.blade.php` | Prova manuale nel browser (checklist in `HANDOFF.md`, sessione Fase 4) |
| DV-31 | Rinomina di una pubblicità: si cambia il **cliente** (il nome mostrato sulla pagina), non il titolo dell'inserzione. Scelta di default, da confermare con la redazione | `Grid::renameContent()` | Uso reale |

## Calcolo peso rivista — da confermare con chi gestisce stampa e spedizioni

| # | Domanda | Dove compare | A chi chiedere |
|---|---|---|---|
| DV-18 | Cosa rappresenta esattamente il **+2 % di maggiorazione** dell'Excel (inchiostro, colla/legatura, cellophane, altro)? È lo stesso per tutte le testate? | [03-dominio-e-flussi.md](03-dominio-e-flussi.md#calcolo-peso-rivista), [11-debito-tecnico.md](11-debito-tecnico.md) (#33) | Tipografia / chi ha creato il foglio Excel |
| DV-19 | **Tolleranza della grammatura** reale: quanto scosta la carta usata (scheda tecnica della cartiera)? Il default del 4 % e quello di 1 mm sul formato sono corretti? | [11-debito-tecnico.md](11-debito-tecnico.md) (#34) | Ufficio acquisti carta / tipografia |
| DV-20 | **Peso reale dei bancali**: 20 kg vale per tutti? Legno o plastica, con o senza imballo? | [11-debito-tecnico.md](11-debito-tecnico.md) (#35) | Chi organizza le spedizioni |
| DV-21 | Gli **scaglioni** 250/350/500/1000 g sono quelli **postali in vigore** per il contratto di spedizione? Il limite è **inclusivo** (500,0 g → scaglione 500)? | [05-configurazione.md](05-configurazione.md#configpeso_rivistaphp-calcolo-peso-rivista), [11-debito-tecnico.md](11-debito-tecnico.md) (#36) | Ufficio spedizioni / contratto con il vettore |
| DV-22 | Nel timone, il **numero totale di pagine comprende le copertine**? Es. NM: 208 interne + 6 di copertina = 214 nel timone. Il calcolo assume di sì | [03-dominio-e-flussi.md](03-dominio-e-flussi.md#calcolo-peso-rivista), [11-debito-tecnico.md](11-debito-tecnico.md) (#37) | Redazione |

## Altre cose da chiedere (non marcate nei file, ma utili)

- Esistono **dati reali** in produzione, quanti utenti e quante riviste? È mai stato usato in esercizio?
- La lista "Da verificare manualmente" dei fix del 2026-10-12 (`HANDOFF.md:1166-1171`) è stata eseguita?
- Il numero chiuso deve restare modificabile? Il ruolo `commerciale` deve avere funzioni dedicate? (vedi [11-debito-tecnico.md](11-debito-tecnico.md), #26-27)
- Fuso orario voluto per i solleciti: 07:30 **UTC** è intenzionale?
