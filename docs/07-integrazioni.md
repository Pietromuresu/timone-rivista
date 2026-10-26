# 07 — Integrazioni e servizi esterni

[← Indice](README.md) · Vedi anche: [05-configurazione.md](05-configurazione.md), [10-debugging.md](10-debugging.md)

**Il progetto non chiama nessuna API di terze parti.** Non ci sono pagamenti, SMS, S3, ERP né analytics: un grep di `Http::`, `curl`, `Guzzle` e SDK di terzi in `app/` non dà risultati. Le "integrazioni" sono i servizi infrastrutturali sotto. L'unico servizio davvero **esterno** è l'SMTP per le mail, che oggi non è configurato.

## Mail (SMTP)

| | |
|---|---|
| **Scopo** | 1) Sollecito della scadenza di stampa al redattore di riferimento (`App\Mail\IssueDeadlineReminder`, view `resources/views/emails/issue-deadline-reminder.blade.php`). 2) Link di reset password (Breeze, `PasswordResetLinkController`) |
| **Credenziali** | `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` (vedi [05-configurazione.md](05-configurazione.md#mail)) |
| **Stato attuale** | `.env.example` ha `MAIL_MAILER=log`: le mail **non partono**, vengono scritte in `storage/logs/laravel.log`. ⚠️ DA VERIFICARE: provider SMTP, credenziali e mittente di produzione |
| **Come parte** | Il sollecito è **in coda** (`Mail::...->queue()` in `SendDeadlineReminders.php`): lo invia il `worker`. Il reset password è sincrono, nella richiesta HTTP |
| **Se è giù** | Sollecito: il job fallisce, viene ritentato 3 volte (`--tries=3 --backoff=3`) e poi finisce in `failed_jobs`. **`deadline_reminder_sent_at` è già stato impostato al momento dell'accodamento, quindi il comando NON ritenta il giorno dopo.** Reset password: l'utente vede un errore 500 |
| **Test in locale** | Lasciare `MAIL_MAILER=log` e guardare il log. In alternativa usare un catcher SMTP (es. Mailpit) con `MAIL_HOST`/`MAIL_PORT`: non è nel compose, va aggiunto a mano |

Rilancio manuale di un sollecito non partito: vedi [08-job-e-scheduler.md](08-job-e-scheduler.md#timonesend-deadline-reminders).

## Laravel Reverb (WebSocket, self-hosted)

| | |
|---|---|
| **Scopo** | Aggiornamenti in tempo reale del timone tra utenti, presence (utenti online), whisper "chi sta modificando cosa" |
| **Dove gira** | Servizio compose `reverb` (`php artisan reverb:start`, porta 8080), stessa immagine dell'app |
| **Credenziali** | `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`: generate localmente da `setup.sh`, nessun account esterno |
| **Due indirizzi diversi** | Server → Reverb: `REVERB_HOST`/`PORT`/`SCHEME` (rete Docker interna, `reverb:8080`). Browser → Reverb: `VITE_REVERB_HOST`/`PORT`/`SCHEME`, inseriti nel bundle JS al build |
| **Chiamata** | `broadcast(new Evento(...))->toOthers()`: 15 punti in `app/Livewire/Timone/Grid.php`, 5 in `ContentCreate.php`, 1 per ciascun job di miniatura (`GenerateContentFileThumbnail.php:97`, `GeneratePageFileThumbnail.php`). Eventi in `app/Events/*` (`ShouldBroadcastNow`, cioè chiamata HTTP sincrona a Reverb) |
| **Autorizzazione canale** | `POST /broadcasting/auth` → `routes/channels.php` (`issue.{issueId}` richiede `canAccessMagazine`) |
| **Se è giù** | **Lato server**: `broadcast()` lancia un'eccezione (es. `BroadcastException: Could not resolve host: reverb`) e **l'azione dell'utente fallisce con errore Livewire**. Le scritture già fatte restano: nelle azioni di massa lo stato resta applicato a metà (`HANDOFF.md` §Decisioni, ultima voce). **Lato client**: Echo non si connette, lo store `realtimeFallback.connected` diventa false e parte `wire:poll.1s="checkForUpdates"` (`grid.blade.php:95`) |
| **Test in locale** | È già nello stack Docker. Apri lo stesso numero in due browser con utenti diversi |

## Imagick + Ghostscript (librerie locali)

| | |
|---|---|
| **Scopo** | Conteggio pagine PDF, misura del formato in mm, miniature PNG |
| **Dove** | `App\Support\PdfPageMeasurer` (`new Imagick`), `spatie/pdf-to-image` nei job `GenerateContentFileThumbnail` / `GeneratePageFileThumbnail` |
| **Credenziali** | nessuna |
| **Se manca o è rotto** | Alla creazione del contenuto: `ContentCreator::pdfPageCount()` → `ValidationException` "PDF illeggibile", e il contenuto non viene creato. Nel job: `catch (Throwable)` → `report($e)` + `thumbnail_status=failed`, senza ritentare |
| **Test in locale** | Solo con Docker (vedi [01-setup-locale.md](01-setup-locale.md)) |

## MySQL e Redis

Servizi interni del compose, non esposti fuori dalla rete Docker (Redis non ha `ports:`; MySQL nemmeno, ma è raggiungibile tramite **Adminer su :8081**).

- **Redis giù**: cache e code non funzionano, quindi ogni richiesta che usa la cache va in errore e i job non vengono accodati. Le sessioni sono su DB, quindi il login di per sé non dipende da Redis.
- **MySQL giù**: l'app dà errore 500 ovunque. I servizi `app`, `worker`, `reverb` e `scheduler` dipendono da `mysql: service_healthy` solo **all'avvio**.

## Adminer

GUI web del database (`adminer:latest`), porta host **8081**, sempre avviata anche con `./setup.sh` in modalità produzione. Il login usa le credenziali MySQL. ⚠️ DA VERIFICARE: se in produzione la porta 8081 è filtrata dal firewall. Altrimenti espone una pagina di login al DB su internet (vedi [11-debito-tecnico.md](11-debito-tecnico.md)).

## Repository e registry Docker

- Codice: `https://its.git/pietro/timone-rivista`. Non ci sono pipeline CI.
- Immagini base scaricate da Docker Hub al build: `node:20-alpine`, `composer:2`, `php:8.2-fpm-alpine`, `nginx:alpine`, `mysql:8.4`, `redis:7-alpine`, `adminer:latest`. Il server deve poter raggiungere Docker Hub, oltre a npm e Packagist durante la build.
