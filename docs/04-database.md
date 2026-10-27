# 04 — Database

[← Indice](README.md) · Vedi anche: [03-dominio-e-flussi.md](03-dominio-e-flussi.md), [09-deploy.md](09-deploy.md), [10-debugging.md](10-debugging.md)

- **Motore**: MySQL 8.4 (`docker-compose.yml`, servizio `mysql`, volume `mysql_data`). Nei test si usa SQLite in-memory (`phpunit.xml`).
- **Schema**: 35 migration in `database/migrations/`, tutte Laravel standard. **Nessuna query raw, nessuna stored procedure, view o trigger** (grep di `DB::raw|whereRaw|selectRaw` vuoto, confermato dall'audit in `HANDOFF.md` §Fase 6.2). L'unico uso di `DB::table` è dentro la migration `2026_10_12_100001`.
- **Charset/collation**: default di Laravel (`config/database.php`, `DB_CHARSET`/`DB_COLLATION` non impostati in `.env.example`).

## Schema ER testuale

```text
users ─┬─< magazine_user >─ magazines ─┬─< sections
       │                               ├─< issues ─┬─< pages ─┬─< page_files (legacy)
       │  magazines.redattore_id ──────┘           │          ├─< page_content >─ contents
       │                                           │          │        └── content_file_id → content_files
       │                                           │          └── page_group_id → page_groups
       │                                           ├─< contents ─┬── articles (1:1)
       │                                           │             ├── advertisements (1:1)
       │                                           │             └─< content_files
       │                                           ├─< page_groups
       │                                           ├─< page_reorder_logs
       │                                           └─< activity_logs
       ├── pages.locked_by
       ├── page_files.uploaded_by / content_files.uploaded_by     (CASCADE!)
       ├── page_reorder_logs.user_id                               (CASCADE!)
       └── activity_logs.user_id                                   (SET NULL)
```

## Tabelle di dominio

Colonne `id` e `timestamps` omesse. FK = foreign key. "Scrive" e "Legge" indicano i punti principali del codice.

### `users`
- **Scopo**: account.
- **Colonne**: `name`, `email` (unique), `password` (bcrypt, `BCRYPT_ROUNDS`), `role` (string, default `redattore`; enum `App\Enums\UserRole`), `email_verified_at`, `remember_token`.
- **Relazioni**: `magazines()` (belongsToMany), `pageFiles()`, `pageReorderLogs()` (`app/Models/User.php`).
- **Scrive**: `Users\Create`/`Edit` (Admin), `ProfileController` (profilo, **cancellazione del proprio account**). **Legge**: auth, policy.

### `magazines`
- **Scopo**: rivista.
- **Colonne**: `name`, `slug` (unique, usato nelle URL), `periodicity` (`mensile`/`settimanale`/`altro`), `color` (hex), `ad_threshold_percentage` (decimal 5,2, nullable: soglia di allarme del carico pubblicitario), `battente_counted_in_total` (bool), `notes`, `redattore_id` (FK users, nullOnDelete: destinatario dei solleciti).
- **Scrive**: `Magazines\Create`/`Edit`, `Grid::updateAdThreshold`.

### Parametri del peso su `magazines` e `issues` (migration `2026_10_26_*`)

Aggiunti da due migration:
- `database/migrations/2026_10_26_100000_add_weight_defaults_to_magazines_table.php` (default sulla rivista);
- `database/migrations/2026_10_26_100001_add_weight_params_to_issues_table.php` (copia sul numero).

Il loro `down()` elimina con `dropColumn` esattamente le colonne aggiunte, quindi i valori inseriti vanno persi. Up, down e di nuovo up sono stati verificati su SQLite il 2026-10-26.

Logica e flusso: [03-dominio-e-flussi.md](03-dominio-e-flussi.md#calcolo-peso-rivista).

Colonne presenti **con lo stesso nome** su entrambe le tabelle (`App\Support\Weight\WeightParameters::FIELDS`). Sono tutte nullable: un valore vuoto produce in UI "Parametri peso da configurare".

| Colonna | Tipo | Unità | Default DB |
|---|---|---|---|
| `formato_base_mm` | unsignedSmallInteger | mm interi (210 = 21 cm) | NULL |
| `formato_altezza_mm` | unsignedSmallInteger | mm interi | NULL |
| `grammatura_interno` | decimal(6,2) | g/m² | NULL |
| `grammatura_copertina` | decimal(6,2) | g/m² | NULL |
| `pagine_copertina` | unsignedTinyInteger | pagine | 4 |
| `maggiorazione_percentuale` | decimal(5,2) | % | 2.00 |
| `peso_bancale_kg` | decimal(6,2) | kg | 20.00 |
| `tolleranza_grammatura_perc` | decimal(4,2) | ± % | 4.00 |
| `tolleranza_formato_mm` | decimal(4,2) | ± mm | 1.00 |
| `tolleranza_maggiorazione_perc` | decimal(4,2) | ± punti % | 1.00 |

Solo su `issues` (`WeightParameters::ISSUE_ONLY_FIELDS`):

| Colonna | Tipo | Unità | Default DB |
|---|---|---|---|
| `n_copie` | unsignedInteger | copie (tiratura) | NULL |
| `n_bancali` | unsignedSmallInteger | bancali | NULL |
| `peso_reale_g` | decimal(8,2) | g, peso misurato a stampa avvenuta | NULL |

Chi le scrive:

| Colonne | Scritte da |
|---|---|
| Default su `magazines` | `App\Livewire\Magazines\Edit::save()`; `maggiorazione_percentuale` anche da `App\Livewire\Timone\WeightPanel::applySuggestedSurcharge()` (calibrazione, solo Admin) |
| Parametri su `issues` | `App\Livewire\Issues\Create::save()` (copia dei default della rivista) e `WeightPanel::saveParams()` |

Le pagine interne **non** sono una colonna: sono `issues.total_pages`. `pagine_copertina` sul numero è scritto da `App\Support\IssueCovers::syncPrintedPages()` (4, o 6 con la bandella) e non è più modificabile a mano (Fase 2, 2026-10-26); sulla rivista resta il default che decide se un nuovo numero nasce con la bandella (≥ 6).

Cast: solo gli interi (`formato_*_mm`, `pagine_copertina`, `n_copie`, `n_bancali`). I decimal restano grezzi, senza cast `decimal:2`, per evitare la deprecation di `brick/math` (vedi [11-debito-tecnico.md](11-debito-tecnico.md)); li converte a float `MagazineWeightInput::fromArray()`.

Numeri e riviste **già esistenti** al momento della migration:
- ricevono i default di colonna (pagine copertina 4, maggiorazione 2, tolleranze, peso bancale);
- formato e grammature restano **NULL**: nessun valore viene indovinato per le riviste esistenti;
- finché non vengono impostati, il pannello mostra "Parametri peso da configurare".

### `magazine_user`
- **Scopo**: pivot di accesso utente ↔ rivista.
- **Vincoli**: `unique(magazine_id, user_id)`, cascade su entrambe le FK.
- **Scrive**: `Users\Create`/`Edit`. **Legge**: `User::canAccessMagazine()`.

### `sections`
- **Scopo**: rubriche della rivista.
- **Vincoli**: `unique(magazine_id, name)`; `contents.section_id` è nullOnDelete.
- **Scrive**: `ContentCreate::saveSection`.

### `issues`
- **Scopo**: numero della rivista.
- **Colonne**: `title`, `issue_date`, `print_deadline_at` (date), `deadline_reminder_sent_at`, `status` (`bozza`/`in_lavorazione`/`chiuso`), `total_pages` (fonte di verità del numero di pagine), `reorder_version` (lock ottimistico), `pages_updated_at` (usata dal polling di fallback), `notes`.
- **Indici**: `(magazine_id, status)`.
- **Scrive**: `Issues\Create`, `Grid` (resize, deadline, riordino), `AdReservations::closeIssue`, `SendDeadlineReminders`. **Observer**: `IssueObserver::created` crea le pagine.

### `pages`
- **Scopo**: pagina fisica del numero.
- **Colonne**: `position` (1..N per le pagine interne, **NULL per le copertine** dal 2026-10-26), `cover_slot` (`I`/`II`/`III`/`IV`/`bandella`, NULL per le pagine interne — `App\Enums\CoverSlot`), `content_type` (`editoriale`/`pubblicita`/`mista`/`bianca`, derivato dai contenuti), `status` (`da_assegnare`…`ok_stampa`), `role` (**non più letto dal codice** dalla Fase 2: resta come traccia e per il rollback della conversione), `locked_at`/`locked_by`, `page_group_id` (FK page_groups), `notes`.
- **Vincoli**: **`unique(issue_id, position)`** (più NULL ammessi: le copertine): il riordino usa un offset temporaneo (vedi [02-architettura.md](02-architettura.md)); **`unique(issue_id, cover_slot)`**.
- **Scrive**: quasi tutti i metodi di `Grid`, `IssueObserver`, `PageInserter`, `App\Support\IssueCovers` (unico punto che scrive `cover_slot`).

### `cover_conversion_logs` (Fase 2, 2026-10-26)
- **Scopo**: registro, riga per riga, di ciò che ha fatto la migration `2026_10_26_200002_convert_cover_pages_to_slots` (pagina diventata copertina, rinumerata, copertina creata, gruppo sciolto, totali del numero prima della conversione). Rende la migration reversibile: `down()` ripristina posizioni, ruoli, gruppi e totali da qui, e riporta al vecchio modello anche i numeri creati dopo la conversione.
- **Colonne**: `issue_id`, `page_id`, `action` (`to_cover`/`renumbered`/`created_cover`/`ungrouped`/`issue_totals`), `old_position`, `new_position`, `old_role`, `cover_slot`, `old_page_group_id`, `old_total_pages`, `old_pagine_copertina`.
- **Da non cancellare** finché si vuole poter annullare la conversione. Il rollback è pensato per l'immediato dopo-deploy: dopo nuove modifiche al timone le posizioni ripristinate possono collidere.
- **Prima del deploy**: `php artisan timone:covers-dry-run` mostra per ogni numero cosa diventa cosa, senza scrivere nulla (funziona anche sullo schema vecchio).

### `contents`
- **Scopo**: articolo o pubblicità del numero, assegnato o no.
- **Colonne**: `type` (`articolo`/`pubblicita`), `title`, `section_id`, `page_occupancy` (decimal 5,2: frazioni `0.25/0.33/0.50/0.67` oppure numero intero di pagine; vedi `App\Support\PageOccupancy`), `archived_at`.
- **Indici**: `(issue_id, type)`.
- **Scrive**: `ContentCreator::create`, `Grid::archiveContent`/`deleteUnassignedContent`.

### `articles`
- 1:1 con `contents`.
- **Colonne**: `author`, `editorial_status` (`da_scrivere`…`pronto`), `expected_length`.

### `advertisements`
- 1:1 con `contents`.
- **Colonne**: `client`, `agency`, `format` (enum `AdFormat`, 16 formati di listino), `occupied_percentage_override`, `confirmation_status` (`in_trattativa`/`confermata`/`annullata`), `preferred_position`, `commercial_notes`.

### `page_content`
- **Scopo**: pivot pagina ↔ contenuto.
- **Colonne**: `occupied_percentage` (decimal, default 100; **nessun vincolo DB** su range o somma: la validazione è in `PagePercentageAllocator`), `content_file_id` (FK content_files, nullOnDelete: quale pagina del PDF mostrare).
- **Vincoli**: `unique(page_id, content_id)`.
- Ha un modello dedicato: `App\Models\PageContent`.

### `content_files`
- **Scopo**: file allegati a un contenuto, **una riga per pagina del PDF**, ognuna con la propria copia del file.
- **Colonne**: `disk` (default `local`), `path`, `thumbnail_path`, `original_name`, `mime_type`, `size`, `thumbnail_status` (`pending`/`processing`/`ready`/`failed`), `pdf_page_number`, `format_check_status`, `measured_width_mm`/`measured_height_mm`, `format_override_confirmed_by`/`_at`, `uploaded_by` (FK users, **cascadeOnDelete**).
- **Scrive**: `ContentCreator`, job `GenerateContentFileThumbnail`, `Grid::confirmFormatOverride`.

### `page_files`
- **LEGACY**: PDF caricati direttamente sulla pagina, prima del 2026-09-02. Nessun codice applicativo ne crea più. Restano per i dati storici e come fallback in `Page::previewFile()`. Si migrano con `timone:migrate-legacy-page-files` (vedi [08-job-e-scheduler.md](08-job-e-scheduler.md)).
- **Colonne** come `content_files`, senza `mime_type`. `disk` ha default `public`.
- `uploaded_by` è **cascadeOnDelete**.

### `page_groups`
- **Scopo**: gruppo di pagine collegate.
- **Colonne**: `issue_id`, `color`.
- **Scrive**: `PageGroupLinker`.

### `page_reorder_logs`
- **Scopo**: storico degli spostamenti di pagina.
- **Colonne**: `issue_id`, `page_id`, `user_id` (**cascade**), `from_position`, `to_position`, `created_at`.
- **Indici**: `(issue_id, created_at)`.

### `activity_logs`
- **Scopo**: cronologia generale (audit applicativo).
- **Colonne**: `issue_id` (nullOnDelete), `user_id` (nullOnDelete), `entity_type`, `entity_id`, `action` (es. `page.moved`, `content.assigned`), `description`, `old_values`/`new_values` (JSON), `ip_address`, `created_at`.
- **Scrive**: `App\Support\ActivityLogger::log()`. **Legge**: `Timone\ActivityLogPanel`.

## Tabelle di framework (senza modello applicativo)

| Tabella | Uso nel progetto |
|---|---|
| `sessions` | **Usata**: `SESSION_DRIVER=database` |
| `password_reset_tokens` | Reset password (Breeze) |
| `cache`, `cache_locks` | Non usate con `CACHE_STORE=redis` (default nell'`.env.example`); create dalla migration standard |
| `jobs`, `job_batches` | Non usate con `QUEUE_CONNECTION=redis` (la coda sta in Redis) |
| `failed_jobs` | **Usata**: driver di default per i job falliti (`config/queue.php`, sezione `failed`) |
| `migrations` | Tabella interna di Laravel |

## Dati che NON vanno mai cancellati

- **`activity_logs`, `page_reorder_logs`**: storico e audit della redazione. Non c'è nessuna pulizia automatica.
- **`content_files` / `page_files` e i file fisici in `storage/app/private/`** (volume Docker `storage_data`): sono il materiale di stampa. Il DB senza i file (o viceversa) non è un backup completo.
- **`users`**: per le cascade sopra, **eliminare un utente elimina le righe dei file che ha caricato e i suoi log di riordino**. I file fisici restano orfani su disco. Ogni utente può farlo su se stesso da `/profile` (`ProfileController::destroy`, `app/Http/Controllers/ProfileController.php:43`). Per disattivare un utente conviene togliergli l'accesso alle riviste o cambiare password, **non** cancellarlo. Vedi [11-debito-tecnico.md](11-debito-tecnico.md).
- **`issues`**: cancellare un numero cancella a cascata pagine, contenuti, file (righe), gruppi e log di riordino. Nella UI non c'è nessun punto che cancelli un'issue o una rivista: in `app/` gli unici `->delete()` sono contenuto (`Grid.php:1626`, `AdReservations.php:68`), pagine in eccesso nel ridimensionamento (`Grid.php:309`) e utente su se stesso (`ProfileController.php:53`). Una cancellazione può quindi avvenire solo da DB o da tinker.
- **Riduzione delle pagine totali** (`Grid.php:309`): cancella le pagine oltre il nuovo totale e, a cascata, i relativi `page_content` e `page_files`. È preceduta da una conferma se ci sono contenuti.
- **Volume `mysql_data`**: `docker compose down -v` lo distrugge. **Non usarlo mai in produzione.**

## Migration bloccante: `page_occupancy`

`database/migrations/2026_10_12_100001_make_page_occupancy_not_null_on_contents_table.php` lancia `RuntimeException` (e quindi ferma `migrate` e `setup.sh`) se esistono righe di `contents` con `page_occupancy` NULL. È voluto: nessun backfill viene inventato (`HANDOFF.md`, "⚠️ Checkpoint Fase 1 ancora aperto").

Procedura:

```bash
docker compose exec app php artisan timone:missing-occupancy     # sola lettura: conta ed elenca
# valorizza page_occupancy per quei contenuti (da UI non è possibile modificarla su un contenuto esistente:
# va fatto via tinker/SQL con i valori decisi dalla redazione — vedi App\Support\PageOccupancy per i valori ammessi)
docker compose exec app php artisan migrate --force
```

Sul DB di sviluppo, al 2026-10-12, risultavano 17 contenuti senza occupazione.

⚠️ DA VERIFICARE: se in produzione questa migration è già stata applicata o è ancora *Pending*. Si controlla con `php artisan migrate:status` sul server. Va anche deciso chi definisce i valori di backfill.

## Seeder e factory

- `database/seeders/DatabaseSeeder.php`: dataset **demo** (utenti `admin@timone.test` e `redattore@timone.test` con password `password`, 3 riviste, numeri e contenuti). **Non va mai eseguito in produzione.** `setup.sh` lo lancia solo se `users` è vuota: su un server di produzione nuovo creerebbe quindi gli utenti demo (vedi [09-deploy.md](09-deploy.md)).
- `database/factories/*`: una factory per ogni modello di dominio, usate dai test e dal seeder.

## Backup e restore

### Backup (presente nel repo)

```bash
docker compose --profile tools run --rm backup
```

- Servizio `backup` in `docker-compose.yml` (profilo `tools`, non parte con `up`) → `docker/mysql/backup.sh`.
- Esegue `mysqldump --single-transaction --quick --routines --no-tablespaces` con l'utente applicativo, compresso con gzip, in `./backups/timone_YYYY-MM-DD_HHMMSS.sql.gz` (cartella gitignored).
- **Non** fa backup dei file caricati (volume `storage_data`), **non** è schedulato e **non** ruota i file vecchi.

### Backup dei file caricati (non presente nel repo, comando proposto)

```bash
# ⚠️ TEMPLATE — verificare contro il server reale (nome del volume = <progetto>_storage_data)
docker run --rm -v timone-rivista_storage_data:/data:ro -v "$PWD/backups":/backup alpine \
  tar czf /backup/storage_$(date +%Y-%m-%d_%H%M%S).tar.gz -C /data .
```

### Restore del database (da `DOCKER.md` §"Ripristinare un backup")

Sostituisce **completamente** il database attuale. Fai prima un backup nuovo.

```bash
gunzip -c ./backups/timone_YYYY-MM-DD_HHMMSS.sql.gz | docker compose exec -T mysql \
  mysql -u root -p"$(grep '^MYSQL_ROOT_PASSWORD=' .env | cut -d'=' -f2-)" timone
```

`timone` è il valore di default di `DB_DATABASE`: se in produzione è diverso, sostituiscilo.

⚠️ DA VERIFICARE: se in produzione esistono backup schedulati (cron sul server?), dove vengono copiati (off-site?) e con quale retention. Nel repo non c'è nulla al riguardo.

### Restore dei file

```bash
# ⚠️ TEMPLATE — verificare contro il server reale
docker run --rm -v timone-rivista_storage_data:/data -v "$PWD/backups":/backup alpine \
  sh -c 'cd /data && tar xzf /backup/storage_<timestamp>.tar.gz'
```

Dopo il restore dei file, conviene lanciare `php artisan pagefiles:prune-orphaned --dry-run` per vedere le incoerenze tra DB e disco (copre solo `page_files`, vedi [08-job-e-scheduler.md](08-job-e-scheduler.md)).

## Query e indici rilevanti per le prestazioni

- `Grid::render()` (`app/Livewire/Timone/Grid.php:2003`) carica **tutte** le pagine dell'issue con molti eager load (contenuti, file, gruppi, lock) a **ogni** render. Con il polling di fallback attivo questo avviene per ogni utente, ma `checkForUpdates()` (`:1135`) prima confronta solo `pages_updated_at`.
- Indici presenti: `pages(issue_id, position)` unique, `contents(issue_id, type)`, `issues(magazine_id, status)`, `page_files(page_id, created_at)`, `content_files(content_id, created_at)`, `activity_logs(issue_id, created_at)`, `page_reorder_logs(issue_id, created_at)`, `sessions(user_id)`, `sessions(last_activity)`.
- `page_content` ha solo `unique(page_id, content_id)` più gli indici delle FK.
