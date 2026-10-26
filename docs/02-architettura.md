# 02 — Architettura

[← Indice](README.md) · Vedi anche: [03-dominio-e-flussi.md](03-dominio-e-flussi.md), [04-database.md](04-database.md)

## Struttura cartelle (commentata)

```text
app/
├── Console/Commands/      4 comandi artisan custom (vedi 08-job-e-scheduler.md)
├── Enums/                 13 enum PHP nativi backed-string: stati, ruoli, formati ADV (vedi 12-glossario.md)
├── Events/                13 eventi realtime, tutti ShouldBroadcastNow su PresenceChannel issue.{id}
├── Http/
│   ├── Controllers/       Controller "classici": export PDF/CSV, stream file, manuale, profilo
│   │   └── Auth/          Scaffold Laravel Breeze (login, reset password, verifica email). Registrazione rimossa
│   └── Requests/          LoginRequest, ProfileUpdateRequest (Breeze)
├── Jobs/                  Generazione miniature PDF/immagini (in coda)
├── Livewire/              Le schermate dell'app (componenti Livewire 3)
│   ├── Magazines/         Index (dashboard), Show, Create, Edit
│   ├── Issues/            Show (contenitore del timone), Create
│   ├── Timone/            Grid (il timone vero e proprio), ContentCreate, CoverSection,
│   │                      AdReservations, ActivityLogPanel, PageFileHistory
│   └── Users/             Index, Create, Edit (solo Admin)
├── Mail/                  IssueDeadlineReminder (mail di sollecito scadenza stampa)
├── Models/                14 modelli Eloquent (vedi 04-database.md)
├── Observers/             IssueObserver: crea le pagine bianche alla creazione di un numero
├── Policies/              MagazinePolicy, IssuePolicy, UserPolicy (auto-discovery)
├── Providers/             AppServiceProvider: registra l'observer, forza root URL da APP_URL
├── Support/               23 classi di logica di dominio "pura" (statiche, testabili senza Livewire)
└── View/Components/       AppLayout, GuestLayout (Breeze)
bootstrap/app.php          Routing, trustProxies('*'), health check /up
config/timone.php          Configurazione specifica del progetto (2 chiavi)
database/migrations/       33 migration (vedi 04-database.md)
database/seeders/          DatabaseSeeder: dataset demo (NON usare in produzione)
database/factories/        Factory per tutti i modelli di dominio
docker/                    nginx/default.conf, php/{opcache,uploads}.ini, mysql/backup.sh
lang/it/                   Traduzioni italiane di auth/passwords/validation
resources/views/           Blade: layouts, livewire/*, exports/* (template PDF), emails/*, documentation/*
resources/js/              app.js (direttive Alpine, Sortable, presence), echo.js (client Reverb), bootstrap.js (axios)
resources/css/app.css      Tailwind
routes/web.php             Tutte le rotte applicative (non c'è routes/api.php)
routes/auth.php            Rotte di autenticazione (Breeze, senza /register)
routes/channels.php        Autorizzazione dei canali broadcast
routes/console.php         Scheduler
tests/                     Pest: Unit/ (logica App\Support), Feature/ (HTTP, Livewire, Jobs, Console)
setup.sh                   Avvio a un comando via Docker (vedi 01-setup-locale.md)
setup-old.sh               Versione precedente di setup.sh, non referenziata da nulla (codice morto)
CLAUDE_PROMPTS/            Specifica originale e prompt di lavoro usati durante lo sviluppo
```

## Pattern usati

1. **Livewire come livello di orchestrazione.** Ogni schermata è un componente Livewire (`routes/web.php` mappa le rotte direttamente su classi Livewire full-page). Il componente valida l'input, chiama `authorize()` e poi una classe `App\Support`, salva, registra l'attività e invia il broadcast. Non ci sono Service container, Repository o Action.
2. **`App\Support`: logica pura statica.** Calcoli e regole di dominio stanno in classi con metodi `static`, senza stato. Molte non toccano Eloquent e sono unit-testate in `tests/Unit/`. Esempi:
   - `PageReorderer` (nuove posizioni dopo un drag)
   - `PagePercentageAllocator` (spazio libero su una pagina)
   - `AdLoadCalculator` (carico pubblicitario)
   - `PageOccupancy` (value object dell'occupazione)
   - `ContentCreator` / `ContentPlacer` (creazione e posizionamento di un contenuto)

   La scelta "statico, nessuna interfaccia" è voluta e motivata in `CLAUDE.md` (regola 3) e in `HANDOFF.md`.
3. **Enum come fonte di verità per stati e varianti.** Etichette, colori e classi Tailwind stanno negli enum (es. `PageStatus::colorClasses()`, `AdFormat`).
4. **Policy di Laravel per l'autorizzazione** (`app/Policies`, auto-discovery), chiamate con `$this->authorize(...)` nei componenti e con `Gate::authorize(...)` nei controller.
5. **Eventi broadcast + listener Livewire.** Le mutazioni sul timone emettono un evento `ShouldBroadcastNow` sul canale presence `issue.{id}`. Gli altri client lo ricevono con `#[On('echo-presence:issue.{issue.id},.NomeEvento')]` in `app/Livewire/Timone/Grid.php` e si ri-renderizzano.
6. **Activity log applicativo.** `App\Support\ActivityLogger::log()` scrive su `activity_logs`. Non usa pacchetti esterni (niente spatie/activitylog).

## Flusso di una richiesta tipica

### Caricamento pagina (es. apertura del timone di un numero)

```text
Browser GET /riviste/{slug}/numeri/{issue}
 └─ nginx (docker/nginx/default.conf)  → fastcgi app:9000 → public/index.php
     └─ bootstrap/app.php (middleware web + trustProxies)
         └─ routes/web.php: middleware auth + verified, scopeBindings (issue deve appartenere al magazine)
             └─ App\Livewire\Issues\Show::mount()  → $this->authorize('view', $issue)  (Show.php:25)
                 └─ view livewire/issues/show.blade.php monta i componenti annidati:
                    Timone\Grid, Timone\ContentCreate, Timone\CoverSection, Timone\AdReservations, ...
                     └─ Grid::render() (Grid.php:2003): carica tutte le pagine con eager loading,
                        calcola spread, carico pubblicitario, avvisi automatici
```

### Interazione (es. drag&drop di una pagina)

```text
Browser (Sortable.js, direttiva x-sortable in resources/js/app.js)
 └─ $wire.movePage(pageId, toPosition)  → POST /livewire/update (CSRF gestito da Livewire)
     └─ Grid::movePage() (Grid.php:476)
         ├─ authorize('update', issue)
         ├─ controlli: pagina dell'issue? bloccata? gruppo collegato?
         ├─ DB::transaction + lockForUpdate sull'issue + confronto reorder_version (lock ottimistico)
         ├─ App\Support\PageReorderer::move() calcola le nuove posizioni
         ├─ doppio UPDATE (offset temporaneo) per rispettare unique(issue_id, position)
         ├─ PageReorderLog::create, increment reorder_version
         ├─ PageRoleAssigner::syncCoverRoles, ActivityLogger::log
         ├─ broadcast(new PageMoved(...))->toOthers()   ← sincrono verso Reverb
         └─ Issue::markPagesUpdated()  (aggiorna pages_updated_at per il polling di fallback)
 └─ Gli altri browser: Reverb → Echo → Livewire listener onPageMoved() → re-render
```

## Diagramma dei componenti

```text
                    ┌──────────────┐
  Browser ──HTTP──▶ │ nginx :80    │──fastcgi──▶ ┌──────────────┐      ┌───────────┐
     │              └──────────────┘             │ app (FPM)    │─────▶│ mysql 8.4 │
     │                                           │ Laravel 12   │      └───────────┘
     │                                           │ Livewire 3   │─────▶┌───────────┐
     │                                           └──────┬───────┘      │ redis 7   │ cache + queue
     │                                                  │ broadcast()  └─────▲─────┘
     │  WebSocket (ws://host:8080)               ┌──────▼───────┐            │
     └─────────────────────────────────────────▶ │ reverb :8080 │            │
                                                 └──────────────┘            │
                     ┌──────────────┐   queue:work redis                     │
                     │ worker       │────────────────────────────────────────┘
                     │ miniature,   │   Imagick + Ghostscript → storage/app (volume storage_data)
                     │ mail         │   SMTP (MAIL_*) — di default "log"
                     └──────────────┘
                     ┌──────────────┐
                     │ scheduler    │ schedule:work → timone:send-deadline-reminders (07:30 UTC)
                     └──────────────┘
                     ┌──────────────┐
                     │ adminer :8081│ GUI DB (sempre avviato, anche in "produzione")
                     └──────────────┘
```

`app`, `worker`, `reverb` e `scheduler` usano **la stessa immagine** (stage `app` del `Dockerfile`) con comandi diversi. `nginx` è uno stage separato che contiene solo `public/` con gli asset buildati.

## Decisioni architetturali non ovvie (e perché)

Fonte principale: `HANDOFF.md`, sezione "Decisioni architetturali da ricordare" e voci di sessione.

| Decisione | Perché | Dove |
|---|---|---|
| Eventi `ShouldBroadcastNow` (sincroni) invece di `ShouldBroadcast` (in coda) | Il realtime funziona senza un worker. **Effetto collaterale**: se Reverb non è raggiungibile, l'azione dell'utente fallisce con eccezione e nelle azioni di massa resta applicata a metà | `app/Events/*`, `HANDOFF.md` §Decisioni (ultima voce) |
| Canale presence registrato come `issue.{issueId}`, senza prefisso `presence-` | È Echo ad aggiungere il prefisso: registrarlo con `presence-` bloccava tutti | `routes/channels.php` |
| Fallback a polling `wire:poll.1s="checkForUpdates"` solo quando il WebSocket è disconnesso e nessun drag è in corso | Resilienza se Reverb è giù; evita morph a metà drag | `resources/views/livewire/timone/grid.blade.php:94-96`, `Grid::checkForUpdates()` (`Grid.php:1135`) |
| Lock ottimistico con `issues.reorder_version` + `lockForUpdate` | Due utenti che riordinano in contemporanea: chi arriva secondo riceve un messaggio, la sua modifica non viene applicata | `Grid::movePage()` |
| Doppio UPDATE con offset temporaneo nel riordino | Vincolo `unique(issue_id, position)` su `pages` | `Grid.php` dentro `movePage` |
| `Grid.php` lasciato monolitico (2151 righe) | Split giudicato troppo costoso: rompe le firme usate da centinaia di test e moltiplica le query di `render()`. La logica pura è comunque estratta in `App\Support` | `HANDOFF.md` §Decisioni (prima voce) |
| Livewire fissato a 3.x | Lo stack richiesto usa la semantica di Livewire 3 | `composer.json` |
| Non si importa `alpinejs` a parte | Livewire 3 include già Alpine: due istanze rompevano `$wire` e il drag&drop | `resources/js/app.js:5-19` |
| `URL::forceRootUrl(APP_URL)` | Le URL generate (miniature) usavano l'header Host sbagliato dietro proxy | `app/Providers/AppServiceProvider.php` |
| `trustProxies(at: '*')` | "Intranet interna" | `bootstrap/app.php` |
| File su disco `local` (`storage/app/private`), serviti solo tramite controller con `Gate::authorize('view', issue)` | I PDF non devono essere pubblici | `PageFileController`, `ContentFileController` |
| `VITE_REVERB_*` passate come build arg Docker | `.env` è escluso dal contesto di build: senza i build arg il bundle JS avrebbe `undefined` | `Dockerfile` stage 1, `docker-compose.yml` |
| `bootstrap/cache/*.php` escluso dall'immagine e rigenerato con `package:discover` | Il manifest generato con le dev-dependency rompe l'immagine `--no-dev` | `.dockerignore`, `Dockerfile` |
| Ogni servizio compose ha `build.target` esplicito | L'ultimo stage del Dockerfile è nginx: senza target, `app` diventava nginx | `docker-compose.yml`, `HANDOFF.md` §Decisioni |
| `display_errors=Off` in PHP | Un warning di `post_max_size` stampato in HTML corrompeva il JSON di Livewire durante l'upload | `docker/php/uploads.ini` |
| Occupazione di pagina obbligatoria; nessun fallback "1 pagina" | Il fallback silenzioso era la causa del bug "PDF multipagina su una sola pagina" | `App\Support\ContentCreator`, `ContentPlacer`, migration `2026_10_12_*` |
| `fakerphp/faker` in `require` (non dev) | Il seeder demo deve funzionare anche nell'immagine `--no-dev` | `composer.json`, `HANDOFF.md` §Decisioni |
| Ruoli come colonna `users.role` (enum), non un pacchetto permessi | Semplicità: 4 ruoli fissi | `app/Enums/UserRole.php` |

## Autenticazione e autorizzazione

- **Guard**: `web` (sessione), driver sessione `database` (`.env.example`: `SESSION_DRIVER=database`). Non c'è API, non c'è Sanctum né token.
- **Rotte**: tutto il gruppo applicativo richiede `auth` + `verified` (`routes/web.php:30`). In pratica `verified` **non blocca nessuno**: `User` non implementa `MustVerifyEmail` (l'import è commentato in `app/Models/User.php:5`), e il middleware controlla la verifica solo per i modelli che lo implementano. Le rotte `verify-email` di Breeze esistono ma non sono necessarie.
- **Registrazione pubblica disattivata** (`routes/auth.php`, commento in testa): gli account si creano solo da `/utenti/nuovo` (Admin).
- **Policy** (`app/Policies`):
  - `MagazinePolicy`: `view` se `canAccessMagazine()`; `create`, `update` e `delete` solo Admin.
  - `IssuePolicy`: `view` se ha accesso alla rivista; `create` se Admin o Redattore; `update` se ha accesso alla rivista **e** (Admin o Redattore); `delete` solo Admin.
  - `UserPolicy`: tutto solo Admin.
- **Helper centrali**: `User::isAdmin()` e `User::canAccessMagazine()` (`app/Models/User.php:72-80`). L'Admin vede tutto; gli altri solo le riviste collegate in `magazine_user`.
- **Canale broadcast** `issue.{issueId}`: autorizzato se `canAccessMagazine()` (`routes/channels.php`).
- **Componenti annidati** (`Grid`, `ContentCreate`, `CoverSection`): il loro `mount()` **non** chiama `authorize('view')` e si affida a `Issues\Show::mount()`. È un finding "media" dell'audit interno, non corretto (vedi [11-debito-tecnico.md](11-debito-tecnico.md)).
