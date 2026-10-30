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
- **Colonne**: `name`, `slug` (unique, usato nelle URL), `periodicity` (`mensile`/`settimanale`/`altro`), `color` (hex), `ad_threshold_percentage` (decimal 5,2, nullable: soglia di allarme del carico pubblicitario — **non più usata dal 2026-10-27**, Fase 6: soglie e colori di allarme tolti; colonna lasciata con i suoi valori), `battente_counted_in_total` (bool), `notes`, `redattore_id` (FK users, nullOnDelete: destinatario dei solleciti).
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
- **Colonne**: `type` (`articolo`/`pubblicita`), `title`, `section_id`, `occupancy_twelfths` (smallint unsigned, NOT NULL, dal 2026-10-27: occupazione totale in dodicesimi di pagina, 30 = 2 pagine e mezza; vedi `App\Support\PageOccupancy`), `archived_at`.
- `is_placeholder` (boolean, default false, Fase 5 — migration `2026_10_27_300000_add_placeholder_ads`): pubblicità segnaposto. Dalla revisione 2, Fase 3.1 (2026-10-28) è il valore memorizzato della regola «pubblicità senza cliente e senza file» (`App\Support\AdPlaceholder`, unico che la calcola); vale anche per le pubblicità create dal modulo col cliente vuoto. Riallineamento dei dati scritti con la regola precedente: `php artisan timone:placeholder-sync [--dry-run]`. Nessuna migration nella Fase 3: `advertisements.client` è nullable dalla stessa migration della Fase 5.
- `file_fit` (string(16), nullable — revisione 2, Fase 2.4, migration `2026_10_27_610000_add_file_fit_columns`): scelta manuale del ritaglio dell'anteprima, `full_page` / `format` (`App\Enums\FileFit`); null = automatico dalle proporzioni del file.
- **Colonna non più usata**: `page_occupancy` (decimal 5,2, il vecchio valore in pagine: `0.25/0.33/0.50/0.67` o N). Resa nullable dalla migration della Fase 3, non più letta né scritta; tiene i valori originali per il rollback.
- **Indici**: `(issue_id, type)`.
- **Scrive**: `ContentCreator::create`, `ContentUpdater::update` (Fase 4), `Grid::renameContent`/`archiveContent`/`deleteUnassignedContent`.

### `articles`
- 1:1 con `contents`.
- **Colonne**: `author`, `editorial_status` (`da_scrivere`…`pronto`), `expected_length`.

### `advertisements`
- 1:1 con `contents`.
- **Colonne**: `client` (nullable dalla Fase 5: un segnaposto non ha inserzionista; il rollback rimette `''` e NOT NULL), `agency`, `format` (enum `AdFormat`, 16 formati di listino; propone l'occupazione con `AdFormat::twelfths()`), `confirmation_status` (`in_trattativa`/`confermata`/`annullata`), `preferred_position`, `commercial_notes`.
- **Colonna non più usata**: `occupied_percentage_override` (dal 2026-10-27 l'occupazione della pubblicità sta nel `Content`, modificabile come per un articolo).

### `page_content`
- **Scopo**: pivot pagina ↔ contenuto.
- **Colonne**: `occupied_twelfths` (tinyint unsigned 1–12, default 12, dal 2026-10-27; **nessun vincolo DB** sulla somma per pagina: la validazione è in `App\Support\PageCapacity`), `content_file_id` (FK content_files, nullOnDelete: quale pagina del PDF mostrare).
- **Vincoli**: `unique(page_id, content_id)`.
- `position` (smallint unsigned, nullable, Fase 7b — migration `2026_10_27_400000_add_position_to_page_content_table`): ordine del contenuto nella pagina, 1…n. La migration numera le righe esistenti per pagina nell'ordine di id (= l'ordine già mostrato); nullable perché un `attach()` senza posizione resta valido (va in coda). Rollback: colonna eliminata. Verificata su copia MySQL dei dati di sviluppo.
- `grid_x`, `grid_y`, `grid_w`, `grid_h` (tinyint unsigned, nullable — revisione 2, Fase 2.3, migration `2026_10_27_600000_add_grid_cells_to_page_content_table`): rettangolo del contenuto sulla **griglia 6×6** della pagina (angolo in alto a sinistra 0–5, larghezza e altezza in celle; 1 dodicesimo = 3 celle). Null per i contenuti **a L** (7/12, 3/4, 11/12: non sono rettangoli, riempiono le celle libere) e per quelli che non ci stanno (pagine oltre capienza): si ricalcolano a ogni disegno. Unico punto che le scrive: `App\Support\PageGrid\PageGridPositions` (assegnazione, cambio di spazio, frecce, rotazione, cambio di formato). La migration le valorizza con la posizione in cui la Fase 7a disegnava ogni contenuto (`GridConversion`); dry-run: `php artisan timone:grid-dry-run`. In sviluppo: 33 assegnazioni identiche, 3 vetrine 1/4 da riga a tutta larghezza a 3×3, nessuna a L o fuori pagina. Rollback: colonne eliminate. Verificata up/down/up su copia MySQL.
- Da allora `position` decide solo chi viene sistemato prima quando un contenuto non ha ancora un rettangolo.
- **Colonna non più usata**: `occupied_percentage` (decimal, la vecchia percentuale; nullable dal 2026-10-27, tenuta per il rollback).
- Ha un modello dedicato: `App\Models\PageContent`.

### Conversione in dodicesimi (Fase 3, 2026-10-27)
- Migration `2026_10_27_100000_convert_occupancy_to_twelfths`: aggiunge `contents.occupancy_twelfths` e `page_content.occupied_twelfths`, li calcola con `App\Support\Occupancy\OccupancyConversion` (regole in `OccupancyConversionPlanner`, vedi [03-dominio-e-flussi.md](03-dominio-e-flussi.md#occupazione-in-dodicesimi-fase-3-2026-10-27)), li rende NOT NULL e rende nullable le colonne vecchie.
- **Prima del deploy**: `php artisan timone:occupancy-dry-run [--issue=ID]` mostra per ogni numero cosa diventa cosa e segnala le pagine che dopo l'arrotondamento superano 12/12. Nessuna scrittura; funziona prima e dopo la migration.
- **Rollback** (`down()`): le colonne vecchie conservano i valori originali; per le righe nate dopo la conversione li ricava dai dodicesimi (percentuale = dodicesimi × 100 / 12; occupazione = frazione più vicina sotto la pagina, altrimenti pagine attraversate: 2 e 1/2 → 3). Verificato su una copia MySQL dei dati di sviluppo (migrate → rollback → migrate, 15/15 assegnazioni, valori originali identici).
- `$withinTransaction = false`: su SQLite `->change()` ricostruisce le tabelle e solo fuori transazione Laravel riesce a spegnere le foreign key (altrimenti la ricostruzione di `contents` cancellerebbe in cascata il pivot). Su MySQL il DDL non è comunque transazionale.

### `content_files`
- **Scopo**: file allegati a un contenuto, **una riga per pagina del PDF**, ognuna con la propria copia del file.
- **Colonne**: `disk` (default `local`), `path`, `thumbnail_path`, `original_name`, `mime_type`, `size`, `thumbnail_status` (`pending`/`processing`/`ready`/`failed`), `pdf_page_number`, `format_check_status`, `measured_width_mm`/`measured_height_mm`, `format_override_confirmed_by`/`_at`, `uploaded_by` (FK users, **nullOnDelete** dalla migration `2026_10_28_100000_keep_user_work_on_user_delete`, prima cascadeOnDelete: eliminare un utente cancellava i suoi file).
- `aspect_ratio` (decimal 8,5, nullable — revisione 2, Fase 2.4): larghezza/altezza della pagina del file, misurata dalla miniatura quando viene generata (`App\Support\ContentFileAspectRatio`). Confrontata con il formato del numero (±5%) decide se il file è «a pagina intera» (si mostra solo la porzione del rettangolo) o «nel formato» (intero). Per i file caricati prima: `php artisan timone:file-ratios [--dry-run]`; finché è null l'anteprima resta intera, senza ritaglio.
- **Scrive**: `ContentCreator`, job `GenerateContentFileThumbnail`, `Grid::confirmFormatOverride`.

### `page_files`
- **LEGACY**: PDF caricati direttamente sulla pagina, prima del 2026-09-02. Nessun codice applicativo ne crea più. Restano per i dati storici e come fallback in `Page::previewFile()`. Si migrano con `timone:migrate-legacy-page-files` (vedi [08-job-e-scheduler.md](08-job-e-scheduler.md)).
- **Colonne** come `content_files`, senza `mime_type`. `disk` ha default `public`.
- `uploaded_by` è **nullOnDelete** (nullable) dal 2026-10-28, come in `content_files`.

### `page_groups`
- **Scopo**: gruppo di pagine collegate.
- **Colonne**: `issue_id`, `color`.
- **Scrive**: `PageGroupLinker`.

### `page_reorder_logs`
- **Scopo**: storico degli spostamenti di pagina.
- **Colonne**: `issue_id`, `page_id`, `user_id` (**nullOnDelete**, nullable dal 2026-10-28; prima cascade), `from_position`, `to_position`, `created_at`.
- **Indici**: `(issue_id, created_at)`.

### `magazine_exports` (revisione 2, Fase 5, migration `2026_10_28_200000_create_magazine_exports_table`)
- **Scopo**: stato e file del PDF sfogliabile di un numero, una riga per (numero, variante `full`/`preview`, unique): rigenerare aggiorna la riga e sostituisce il file.
- **Colonne**: `issue_id` (cascade), `variant`, `status` (`queued`/`processing`/`ready`/`failed`, `App\Enums\MagazineExportStatus`), `disk`, `path` (`exports/issue-{id}/{variant}.pdf`), `size`, `page_count`, `missing_files` (contenuti disegnati «File mancante»), `error`, `requested_by` (nullOnDelete), `started_at`, `finished_at`.
- **Scrive**: `App\Support\MagazineExport\MagazineExportRequester` (richiesta), `App\Jobs\GenerateMagazinePdf` (avanzamento). Eliminando un numero il file va nell'elenco di `DeleteStoredFiles` (`StoredFiles::forIssues()`).

### `magazines.signature_pages`, `magazines.signature_includes_covers` (segnature, 2026-10-29, migration `2026_10_29_200000_add_signature_settings_to_magazines`)
- `signature_pages` (default 32; il modulo ammette 8, 16, 32, 64), `signature_includes_covers` (default false). Lette a ogni render dai numeri della rivista (non copiate sul numero). Salvarle tocca `pages_updated_at` dei numeri della rivista, così i timoni aperti si aggiornano al poll.

### `page_comments`, `page_comment_reads`, `issues.comments_updated_at` (commenti, 2026-10-29, migration `2026_10_29_100000_create_page_comments_tables`)
- **`page_comments`**: `issue_id` (cascade col numero), `page_id` (**nullable, nullOnDelete**: i commenti seguono la pagina come entità; se la pagina viene eliminata restano), `user_id` (nullable, nullOnDelete: restano anche se l'utente viene eliminato), `body`, `page_label` (nome della pagina al momento del commento, riscritto con il nome di quel momento da `DeletedPageComments::preserve()` prima di ogni eliminazione di pagine), `page_deleted_at`, `edited_at`, timestamp, `deleted_at` (soft delete = commento eliminato dall'autore o da un admin).
- **`page_comment_reads`**: (`page_comment_id`, `user_id`) chiave primaria, `read_at`. Non letto = commento di un altro senza riga per me.
- **`issues.comments_updated_at`** (timestamp con microsecondi): segnale del polling solo per i commenti, separato da `pages_updated_at`.
- Eliminazione di pagine: `Grid::resizePages()`, `IssueCovers::removeBandella()`, `CoverConversion::revert()` chiamano `DeletedPageComments::preserve()` prima della delete. Un percorso nuovo che elimina pagine deve fare lo stesso (altrimenti la FK mette comunque `page_id` a NULL, ma resta il nome di quando il commento è stato scritto).

### `timone_azioni` (annulla/ripeti, 2026-10-28, migration `2026_10_28_310000_create_timone_azioni_table`)
- **Scopo**: cronologia annulla/ripeti per utente e per numero (`App\Models\TimelineAction`, scritta solo da `App\Support\Timeline\TimelineActionService`).
- **Colonne**: `issue_id`, `user_id` (cascade), `sequence` (progressivo per utente e numero), `type` (es. `page.move`, `content.assign`), `label` (testo leggibile), `changes` (JSON: righe e campi cambiati, `{t, id, b, a}`), `status` (`eseguita`/`annullata`), `blocked_reason` (non annullabile: PDF sostituito, conflitto), `batch_key` (caricamento multiplo), `undone_at`, timestamp.
- **Retention**: massimo 50 passi per utente e numero (il servizio toglie i più vecchi); `php artisan timone:history-prune` (ogni ora) toglie i passi più vecchi di 24 ore.

### `contents.deleted_at` (soft delete, 2026-10-28, migration `2026_10_28_300000_add_soft_deletes_to_contents`)
- Eliminare un contenuto (o annullarne la creazione) lo mette nel cestino: sparisce dall'app (scope di `SoftDeletes`), righe figlie e file restano. Dopo 24 ore `timone:history-prune` lo elimina davvero (cascate) e cancella i file da disco. `StoredFiles` (eliminazione di numeri) conta anche i contenuti nel cestino.

### `activity_logs`
- **Scopo**: cronologia generale (audit applicativo).
- **Colonne**: `issue_id` (nullOnDelete), `user_id` (nullOnDelete), `entity_type`, `entity_id`, `action` (es. `page.moved`, `content.assigned`), `description` (**TEXT** dal 2026-10-27, migration `2026_10_27_500000_change_activity_logs_description_to_text`; prima VARCHAR(255): la creazione di un numero con decine di pubblicità previste falliva con «Text too long»), `old_values`/`new_values` (JSON: qui vanno i dati strutturati, es. l'elenco delle posizioni dei segnaposto), `ip_address`, `created_at`.
- **Regola**: la descrizione è una frase breve per l'elenco in UI; elenchi lunghi e dati completi vanno in `new_values`. Il `down()` della migration accorcia a 255 caratteri le descrizioni più lunghe prima di tornare a VARCHAR.
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
- **`users`**: dal 2026-10-28 (revisione 2, Fase 4.3) eliminare un utente **non** cancella il suo lavoro: `content_files.uploaded_by`, `page_files.uploaded_by`, `page_reorder_logs.user_id` diventano NULL («Utente eliminato» in UI); spariscono solo i suoi accessi (`magazine_user`) e le sue sessioni. Lo fa solo un admin (`App\Support\Deletion\UserDeletion`, `UserPolicy::delete`: mai sé stesso, mai l'ultimo admin); l'auto-eliminazione da `/profile` è stata tolta.
- **`issues` / `magazines`**: cancellare un numero cancella a cascata pagine, contenuti, file (righe), gruppi e log di riordino; una rivista cancella i suoi numeri, rubriche e accessi. Dal 2026-10-28 (revisione 2, Fase 4.2) un admin può farlo dall'interfaccia (`App\Support\Deletion\IssueDeletion`/`MagazineDeletion`, conferma con password + nome): voce `issue.deleted`/`magazine.deleted` in `activity_logs` (con `issue_id` NULL) e nel log applicativo, file fisici cancellati dal job `DeleteStoredFiles` dopo il commit. **Definitiva**: nessun SoftDeletes, quindi l'unico recupero è il backup.
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

Dal 2026-10-27 (Fase 3) l'occupazione è in `contents.occupancy_twelfths` (NOT NULL): su un database già convertito `timone:missing-occupancy` risponde che non serve più. Resta utile solo su un database fermo a prima di questa migration del 2026-10-12, che va applicata prima di quella della Fase 3 (l'ordine delle migration lo garantisce).

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

## Assistente del manuale (2026-10-29, `2026_10_29_300000_create_manual_assistant_tables`)

Nessuna FK verso le altre tabelle (voluto: i voti non dicono chi ha votato).

| Tabella | Contenuto |
|---|---|
| `manual_feedback` | Un voto: `risposta_id`, `chiave`, `domanda`, `slug_mostrato` (null in «Forse cercavi»), `slug_scelto` (alternativa scelta), `su_alternativa`, `posizione` 1–3, `voto` ±1, `contesto`, `hash_versione`/`hash_in_breve` della pagina votata, `utente` (HMAC con `APP_KEY`), `created_at`. Unico `(risposta_id, su_alternativa)`. Cancellato oltre 12 mesi |
| `manual_learned_boosts` | Correzioni per (chiave, pagina), con voti, persone, quota positiva, `sospetta`. Riscritta da zero a ogni giro pubblicato |
| `manual_learned_questions` | Domande imparate (chiave unica, testo, pagina, voti, persone). Riscritta come sopra |
| `manual_unanswered` | Domande senza risposta sicura per chiave: ultimo testo, `volte`, `ultima_volta`. Senza utente; cancellate oltre 12 mesi |
| `manual_learning_runs` | Un giro del job: pubblicato sì/no, conteggi, `fallimenti` (JSON) se bloccato |
| `manual_disabled` | Voci spente da un admin: `tipo` domanda/correzione, chiave, pagina |

## Query e indici rilevanti per le prestazioni

- `Grid::render()` (`app/Livewire/Timone/Grid.php:2003`) carica **tutte** le pagine dell'issue con molti eager load (contenuti, file, gruppi, lock) a **ogni** render. Con il polling di fallback attivo questo avviene per ogni utente, ma `checkForUpdates()` (`:1135`) prima confronta solo `pages_updated_at`.
- Indici presenti: `pages(issue_id, position)` unique, `contents(issue_id, type)`, `issues(magazine_id, status)`, `page_files(page_id, created_at)`, `content_files(content_id, created_at)`, `activity_logs(issue_id, created_at)`, `page_reorder_logs(issue_id, created_at)`, `sessions(user_id)`, `sessions(last_activity)`.
- `page_content` ha solo `unique(page_id, content_id)` più gli indici delle FK.
