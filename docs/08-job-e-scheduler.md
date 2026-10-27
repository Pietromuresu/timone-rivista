# 08 — Job, comandi artisan e scheduler

[← Indice](README.md) · Vedi anche: [07-integrazioni.md](07-integrazioni.md), [10-debugging.md](10-debugging.md)

## Infrastruttura asincrona

| Componente | Configurazione | Dove |
|---|---|---|
| Coda | Driver `redis` (`QUEUE_CONNECTION=redis`), coda `default`, `retry_after` 90 s (default `config/queue.php:70`) | `.env.example`, `config/queue.php` |
| Worker | `php artisan queue:work redis --tries=3 --backoff=3 --sleep=1` | servizio `worker` in `docker-compose.yml` |
| Job falliti | tabella `failed_jobs` (driver di default) | `config/queue.php` sezione `failed` |
| Scheduler | `php artisan schedule:work` (loop in foreground, nessun crontab) | servizio `scheduler` in `docker-compose.yml` |
| Broadcast | **sincrono**: eventi `ShouldBroadcastNow`, non passano dalla coda | `app/Events/*` |
| Horizon / Telescope | **non installati** | — |

Nessun job definisce `$tries`, `$timeout`, `$backoff` o `failed()`: valgono i parametri del comando worker. Il timeout di default di `queue:work` è 60 s.

⚠️ DA VERIFICARE: con un PDF molto pesante, il rendering a 150 dpi potrebbe superare i 60 s di timeout del worker. In quel caso il processo worker viene ucciso e il job ritentato. Non è documentato se sia mai successo.

## Job

### `App\Jobs\GenerateContentFileThumbnail`

- **Cosa fa** (`app/Jobs/GenerateContentFileThumbnail.php`):
  1. `thumbnail_status=processing`;
  2. per un'immagine, copia il file come miniatura; per un PDF, rende la pagina `pdf_page_number` in PNG (150 dpi, larghezza 400) in `storage/app/private/contents/{content_id}/thumbnails/`;
  3. `thumbnail_status=ready`;
  4. per le pubblicità in PDF, **controllo del formato** (`checkFormat`), che scrive `format_check_status` e le misure in mm;
  5. broadcast `ContentFileUploaded` (`:97`).
- **Quando parte**: una volta per **ogni pagina** del PDF caricato, accodato da `App\Support\ContentCreator::storeFile()` (`app/Support/ContentCreator.php:226`).
- **Se fallisce il rendering**: `catch (Throwable)` → `report($e)` (in `laravel.log` del worker) + `thumbnail_status=failed`. **Nessun rilancio** (scelta documentata nel codice: un PDF corrotto non diventa leggibile ritentando). In UI la card mostra lo stato fallito.
- **Se fallisce il broadcast finale** (Reverb giù): l'eccezione fa fallire il job, che viene ritentato fino a 3 volte (rifacendo la miniatura) e poi finisce in `failed_jobs`. La miniatura però è già `ready`, quindi l'utente la vede al refresh o con il polling.
- **Rilancio manuale** per un `ContentFile` con miniatura fallita:
  ```bash
  docker compose exec app php artisan tinker --execute='App\Jobs\GenerateContentFileThumbnail::dispatch(App\Models\ContentFile::findOrFail(ID));'
  ```
  In UI non esiste un bottone "riprova" (`HANDOFF.md`, "Debito noto": l'unico modo lato utente è creare un nuovo contenuto con un nuovo file).

### `App\Jobs\GeneratePageFileThumbnail` (legacy)

Stessa logica, per i `PageFile` caricati direttamente su una pagina prima del 2026-09-02. **Nessun punto di `app/` lo accoda più**: resta per i test (`tests/Feature/Jobs/GeneratePageFileThumbnailTest.php`) e per un eventuale rilancio manuale sui dati storici.

### Mail in coda: `App\Mail\IssueDeadlineReminder`

Accodata da `SendDeadlineReminders` con `Mail::to(...)->queue(...)` ed eseguita dal worker come `SendQueuedMailable`. Fallimento: 3 tentativi, poi `failed_jobs`. Vedi [07-integrazioni.md](07-integrazioni.md#mail-smtp).

### Gestione dei job falliti

```bash
docker compose exec app php artisan queue:failed            # elenco
docker compose exec app php artisan queue:retry <uuid>|all  # rilancio
docker compose exec app php artisan queue:forget <uuid>     # elimina
docker compose exec app php artisan queue:flush             # elimina tutti i falliti
docker compose restart worker                               # dopo ogni deploy: il worker tiene il codice in memoria
```

## Scheduler

Definito in `routes/console.php`:

| Task | Quando | Cosa fa |
|---|---|---|
| `timone:send-deadline-reminders` | `dailyAt('07:30')`, nel fuso `APP_TIMEZONE` (default **UTC**, cioè 08:30 o 09:30 in Italia secondo l'ora legale) | Vedi sotto |

Il servizio compose `scheduler` è l'unico "cron". Se il container è fermo, nessun sollecito parte; il comando recupera le scadenze già passate al giro successivo (`DeadlineReminderChecker::isDue` usa `<=`).

Elenco dei task: `docker compose exec app php artisan schedule:list`. Sull'host senza `ext-redis` fallisce con `Class "Redis" not found`.

⚠️ DA VERIFICARE: `HANDOFF.md` ("Debito noto" del batch 2026-08-31) segnala che lo scheduler Docker è stato validato solo a livello di sintassi, senza osservare un giro reale. Controlla nei log del container `scheduler` che il task giri davvero (`docker compose logs scheduler`).

## Comandi artisan custom

### `timone:send-deadline-reminders`

- **File**: `app/Console/Commands/SendDeadlineReminders.php`.
- **Cosa fa**: per le issue con `print_deadline_at` impostato e `deadline_reminder_sent_at` NULL, se `DeadlineReminderChecker::isDue()` (non `chiuso`, rivista con `redattore_id`, giorni alla scadenza `<= TIMONE_DEADLINE_REMINDER_DAYS_BEFORE`), accoda la mail e imposta subito `deadline_reminder_sent_at=now()`.
- **Idempotente**: sì, grazie al flag.
- **Se fallisce l'invio**: il flag è già impostato, quindi il comando non riprova. Per forzare un nuovo invio:
  ```bash
  docker compose exec app php artisan tinker --execute='App\Models\Issue::findOrFail(ID)->update(["deadline_reminder_sent_at" => null]);'
  docker compose exec app php artisan timone:send-deadline-reminders
  ```
  In alternativa, cambia la scadenza da UI (`Grid::updatePrintDeadline` azzera il flag).

### `timone:missing-occupancy`

- **File**: `app/Console/Commands/ListMissingOccupancy.php`. **Sola lettura.**
- Conta ed elenca i `contents` con `page_occupancy` NULL. È il prerequisito della migration NOT NULL (vedi [04-database.md](04-database.md#migration-bloccante-page_occupancy)).
- Dal 2026-10-27, su un database già convertito in dodicesimi (colonna `contents.occupancy_twelfths` presente), risponde "Comando non più necessario" e non elenca nulla.

### `timone:occupancy-dry-run {--issue=}`

- **File**: `app/Console/Commands/OccupancyDryRun.php`. **Sola lettura.**
- Mostra, numero per numero, cosa farà la migration `2026_10_27_100000_convert_occupancy_to_twelfths`: occupazione di ogni contenuto (vecchio valore → dodicesimi) e spazio di ogni assegnazione (vecchia percentuale → frazione, con "(arrotondato)" se non era esatta); segnala con ⚠️ le pagine che dopo l'arrotondamento superano 12/12. Stesso pianificatore della migration (`App\Support\Occupancy\OccupancyConversionPlanner`). Funziona prima e dopo la migration.

### `timone:migrate-legacy-page-files {--dry-run}`

- **File**: `app/Console/Commands/MigrateLegacyPageFilesToContents.php`.
- **Cosa fa**: per ogni pagina la cui anteprima viene ancora da un `PageFile` legacy, crea un `Content` di tipo articolo ("Materiale pagina N" / "Materiale pagine N-M"), un `ContentFile` che punta **allo stesso file fisico** (nessuna copia) e il pivot al 100%. Raggruppa con un'euristica (stesso `original_name`, `pdf_page_number` consecutivi, posizioni contigue) e collega i gruppi con `PageGroupLinker`. Non tocca né cancella i `PageFile`.
- **Stato**: testato (`tests/Feature/Console/MigrateLegacyPageFilesToContentsTest.php`), **mai eseguito su dati reali** (`HANDOFF.md`, sessione 2026-09-02).
- ⚠️ DA VERIFICARE: se è stato eseguito in produzione e se esistono ancora `page_files` legacy.
- **Uso**: prima con `--dry-run`, poi senza. Fai un backup del DB prima.

### `pagefiles:prune-orphaned {--dry-run}`

- **File**: `app/Console/Commands/PruneOrphanedPageFiles.php`.
- **Cosa fa**: elimina dal disco `local` i PDF e le miniature sotto `pages/` che non hanno più una riga `page_files` (es. dopo la cancellazione di pagine o issue).
- **Limite**: **non copre i `content_files`** orfani sotto `contents/` (`HANDOFF.md`, "Debito noto").
- **⚠️ Rischio, dedotto dal codice e non verificato con un test**: considera "referenziati" solo i path presenti in `page_files` (`PruneOrphanedPageFiles.php:30-37`). Dopo `timone:migrate-legacy-page-files`, alcuni `content_files` puntano a file sotto `pages/`. Se poi la riga `page_files` sparisce (es. la pagina viene eliminata riducendo il numero di pagine, con cascade), il prune **cancellerebbe un file ancora usato da un `ContentFile`**. Prima di lanciarlo senza `--dry-run`, controlla che nessun path elencato compaia in `content_files.path`/`thumbnail_path`.
- **Non schedulato**: va lanciato a mano.
  ```bash
  docker compose exec app php artisan pagefiles:prune-orphaned --dry-run
  docker compose exec app php artisan pagefiles:prune-orphaned
  ```

### `timone:diagnose-upload {--no-pdf}`

Aggiunto il 2026-10-26 (Fase 1, bug 9). Sola lettura: stampa ✅/⚠️/❌/ℹ️ per APP_URL, limiti PHP vs regole di upload, disco temporaneo Livewire e disco finale (`local`) scrivibili, cartella temporanea di PHP, lettura di un PDF di prova con Imagick (saltata con `--no-pdf`), coda delle miniature, `storage:link` (solo informativo). Esce con codice 1 se un controllo è fallito. Logica in `App\Support\Uploads\UploadEnvironmentInspector`, la stessa usata per il contesto della voce di log `upload_failed`. Uso: `docker compose exec app php artisan timone:diagnose-upload`. Checklist: [09-deploy.md §6bis](09-deploy.md#6bis-checklist-in-produzione-non-si-caricano-media-fase-1-2026-10-26).

### `inspire`

Comando di esempio dello scaffold Laravel (`routes/console.php`). Irrilevante.

## Event, listener, observer

- **Observer**: `App\Observers\IssueObserver::created`, registrato in `AppServiceProvider::boot()`. Crea le pagine e assegna i ruoli di copertina. Né `Issues\Create::save()` né l'observer usano `DB::transaction`. Se l'inserimento delle pagine fallisce, la riga `issues` è già scritta e resta un numero senza pagine; l'utente vede un errore. Il recupero va fatto via tinker (cancellare e ricreare l'issue). Non ho verificato se il ridimensionamento da UI (`Grid::resizePages`) ricostruisca le pagine mancanti: lavora sulla differenza rispetto a `total_pages`.
- **Eventi broadcast** (`app/Events/`): `PageMoved`, `PagesBlockMoved`, `PagesSwapped`, `PageStatusUpdated`, `PageLocked`, `PageUnlocked`, `PageGroupsChanged`, `IssuePageCountUpdated`, `ContentCreated`, `ContentAssigned`, `ContentUnassigned`, `ContentListChanged` (dal 2026-10-26: contenuto non assegnato archiviato/ripristinato/eliminato), `ContentFileUploaded`, `PageFileUploaded`. Tutti `ShouldBroadcastNow` su `PresenceChannel('issue.{id}')`.
- **Listener**: nessuna classe listener server-side. I "listener" sono i metodi `#[On('echo-presence:issue.{issue.id},.Evento')]` di `App\Livewire\Timone\Grid` (es. `Grid.php:612`, `:750`, `:911`).
- **Eventi Livewire interni** (non broadcast): `thumbnail-work-started`, `remote-change`, `open-modal`/`close-modal`.
