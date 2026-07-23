# Handoff — Timone Elettronico

Ultimo aggiornamento: 2026-07-23. Scritto per riprendere il lavoro in una nuova sessione senza perdere contesto.

## Stato generale

Progetto Laravel 11 + Livewire 3 + Reverb + Tailwind, avviato da zero in questa stessa cartella (prima conteneva solo `timone_cartaceo_riferimento.jpg`, la foto del timone cartaceo usata come riferimento per il flat plan). Si procede **a fasi**, mostrando il risultato prima di passare alla successiva — approccio richiesto esplicitamente dall'utente, da mantenere anche nelle prossime sessioni.

**Nessun commit git è stato ancora creato.** Tutto è working tree non tracciato (`git log` non ha commit). Prima di qualsiasi operazione distruttiva, controllare `git status`.

## Fasi completate finora

1. **Schema dati e migrazioni** — tutte le tabelle (magazines, magazine_user, sections, issues, pages, contents, articles, advertisements, page_content, page_files, page_reorder_logs) + modelli Eloquent con relazioni complete + enum PHP nativi in `app/Enums`.
2. **Reverb + Echo + canale presence** — `routes/channels.php` ha il canale `issue.{issueId}` (presence). `resources/js/echo.js` configurato per Reverb.
3. **Componenti Livewire, punto per punto**:
   - **Punto 0 — selezione rivista/numero**: `Magazines\Index` (`/riviste`, `/dashboard`), `Magazines\Show` (`/riviste/{slug}`), `Issues\Show` (`/riviste/{slug}/numeri/{issue}`, con barra "utenti online" via Alpine+Echo già funzionante).
   - **Punto 1 — vista timone**: `Timone\Grid`, montato dentro `Issues\Show`. Tre modalità (griglia / doppia pagina / lista) commutabili a runtime. Card con colore per tipo, badge stato, contenuto assegnato, placeholder PDF/note.

## Decisioni architetturali da ricordare

- **Livewire fissato a `^3.6`**, non la v4 (che composer prenderebbe di default oggi) — lo stack richiesto dall'utente è esplicitamente su Livewire 3.x semantics (wire:sortable, ecc.).
- **Canale presence**: va registrato in `routes/channels.php` **senza** il prefisso `presence-` (es. `Broadcast::channel('issue.{issueId}', ...)`). È Echo lato client (`Echo.join(...)`) ad aggiungere il prefisso. Un test l'ha scoperto: registrarlo con `presence-` bloccava *tutti* gli utenti, anche quelli autorizzati.
- **`pages` ha un vincolo `unique(issue_id, position)`** — garantisce l'integrità dei numeri pagina ma richiede attenzione nella futura logica di riordino: aggiornare le posizioni con uno swap diretto causerebbe collisioni transitorie. Strategia da usare quando si implementa il riordino (punto 2, non ancora fatto): all'interno di una transazione, spostare prima le pagine coinvolte su posizioni temporanee (es. `+ total_pages`), poi assegnare le posizioni finali.
- **`IssueObserver::created()`** genera automaticamente le `Page` bianche numerate quando un'`Issue` viene creata con `total_pages > 0`. Gestisce solo la creazione iniziale — la logica di ridimensionamento a numero avviato (aggiunta pagine in coda se aumenta, conferma con modale se si riducono pagine con contenuti assegnati) **non è ancora implementata**: sarà il componente "gestione pagine totali dell'Issue" elencato nella consegna originale.
- **`Content::displayLabel()`** restituisce il titolo per gli articoli o `advertisement->client` per le pubblicità — usato ovunque serva mostrare "titolo articolo o cliente pubblicità" (richiesta esplicita dello spec).
- **`App\Support\PageSpreadBuilder`**: la logica di raggruppamento pagine in "aperture" (copertina sola + coppie) per la vista doppia è stata estratta in una classe pura, testata a sé, non dentro il componente Livewire — pattern da replicare per la futura logica di calcolo percentuali pubblicità/editoriale (punto 5), che deve restare unit-testabile in isolamento.
- **Autorizzazione**: `MagazinePolicy` e `IssuePolicy` in `app/Policies`, auto-discovered da Laravel (nessuna registrazione manuale necessaria). `User::canAccessMagazine()` e `User::isAdmin()` sono gli helper centrali.
- I ruoli utente (`App\Enums\UserRole`: admin, redattore, commerciale, sola_lettura) esistono nello schema e nelle policy, ma **nessuna UI/route sfrutta ancora il ruolo Commerciale o Sola lettura** in modo specifico — da considerare quando si costruiranno i componenti di assegnazione pubblicità/stato.

## Come avviare in locale (senza Docker, per sviluppo rapido)

```bash
cd /Users/pietromuresu/Desktop/Projects/timone-rivista
php artisan migrate:fresh --seed   # sqlite locale, vedi .env (DB_CONNECTION=sqlite)
npm run build                      # o `npm run dev` per hot reload
php artisan serve --port=8123
```

Utenti demo (password per tutti: `password`):
- `admin@timone.test` — ruolo Admin, vede tutte le riviste.
- `redattore@timone.test` — ruolo Redattore, abilitato solo su "Motori Elettrici" e "Casa & Giardino" (non su "Sport Weekly", usato apposta per testare lo scoping).

Dataset seed: 3 riviste, 3 numeri. Il numero "Novembre 2026" di Motori Elettrici (issue id tipicamente 1, 64 pagine) ha contenuti già assegnati (articolo a pagina 5, pubblicità pagina intera "Fuchs" a pagina 10, pagina mista pubblicità "Neoparts" + articolo a pagina 12) e 2 contenuti non assegnati — utile per testare da subito il pannello "contenuti non assegnati" quando verrà costruito.

**Docker non è stato ancora creato** (docker-compose.yml, Dockerfile con Imagick/Ghostscript) — è un deliverable esplicito dello spec originale, rimandato a fine sviluppo funzionale.

## Note ambiente (macchina di sviluppo di Pietro)

- Il Node.js locale era rotto (libreria icu4c disallineata via Homebrew) — **risolto** con `brew reinstall node` durante questa sessione. Ora Node 26 funziona.
- Il PHP locale (8.2.0) **non ha l'estensione Imagick** — necessaria in produzione/Docker per `spatie/pdf-to-image` (thumbnail PDF). `composer.json` ha `"config": {"platform": {"ext-imagick": "3.7.0"}}` per permettere a Composer di risolvere le dipendenze in locale senza l'estensione reale (verrà davvero installata nell'immagine Docker).
- La porta 8080 in locale è occupata da Docker Desktop stesso — Reverb (che di default vuole la 8080) va testato su un'altra porta in locale (es. `php artisan reverb:start --port=8095`) finché non si lavora dentro docker-compose, dove non ci sarà conflitto.

## Roadmap rimanente (dallo spec originale)

Nell'ordine suggerito ma non vincolante — l'ultima domanda fatta all'utente (senza risposta) era se procedere prima con:
- **Punto 2 — riordino pagine** (drag&drop con Sortable.js + Alpine, riordino da tastiera, log spostamenti, broadcasting `PageMoved`), oppure
- **Punto 3 — assegnazione contenuti** (drag&drop da pannello "contenuti non assegnati", divisione pagina in percentuali, eventi `ContentAssigned`/`ContentUnassigned`)

Ragionamento emerso in conversazione: il punto 3 è propedeutico al punto 2 (serve poter assegnare contenuti prima che il riordino abbia davvero senso da mostrare). **Probabile prossimo passo: punto 3.**

Dopo questi due, restano dallo spec:
- Punto 1bis — upload PDF + thumbnail (Imagick/Ghostscript, coda Redis, pdf.js per la visualizzazione, storico versioni, evento `PageFileUploaded`).
- Punto 4 — collaborazione realtime completa: gli eventi `PageMoved`, `ContentAssigned/Unassigned`, `PageStatusUpdated`, `AdPercentageRecalculated` con `ShouldBroadcast`; badge "chi sta modificando cosa"; lock ottimistico su riordino; fallback polling se i websocket non sono disponibili.
- Punto 5 — dashboard percentuale pubblicità/editoriale con soglia di allarme configurabile, report esportabile CSV/PDF.
- Punto 6 — gestione riviste/numeri (creazione, duplicazione struttura da numero precedente, archivio) e componente "modifica pagine totali dell'Issue" con la modale di conferma per le rimozioni.
- Autenticazione/permessi — già presente lo scheletro (Breeze + policy), ma nessuna UI di gestione utenti/ruoli per rivista ancora costruita.
- Docker — `docker-compose.yml`, `Dockerfile` (PHP-FPM + Imagick/Ghostscript), servizio `worker` per la coda, `nginx`, `mysql`, `redis`, `reverb`; `.env.example` da rifinire con i valori Docker-specifici finali; script di backup MySQL.
- Test — man mano che si aggiungono le feature vanno aggiunti test Pest (pattern già stabilito: unit test per logica pura come `PageSpreadBuilder`, feature test con `Livewire::test()` per i componenti, test di integrazione per verificare che gli eventi broadcasting vengano davvero emessi una volta creati).

## Suite di test attuale

54 test, tutti verdi (`php artisan test`). Copertura attuale: relazioni Eloquent, autorizzazione canale presence, navigazione/policy riviste-numeri, auto-generazione pagine, componente griglia timone, spread builder. Nessun test ancora sul riordino, l'assegnazione contenuti, gli eventi broadcasting applicativi (solo l'autenticazione del canale presence è testata) o le thumbnail PDF — normale, perché quelle feature non esistono ancora.
