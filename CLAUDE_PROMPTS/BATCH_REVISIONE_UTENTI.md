# Batch "revisione con gli utenti" — file di ripartenza

Ultimo aggiornamento: 2026-10-27. **Fasi 1–5 completate e approvate (migration della Fase 5 applicata). Fase 6 approvata. Fase 7: approccio B; 7a approvato; **7b (ordine con ↑/↓) implementato, in attesa di ok** — migration `2026_10_27_400000` non ancora applicata sul DB di sviluppo (controllare `migrate:status`: l'utente può aver ricostruito lo stack); 7c (drag&drop) solo se l'utente lo chiede dopo aver provato 7b.**

Questo file serve a riprendere il lavoro in una sessione nuova senza ricostruire il contesto. Contiene:
1. come ripartire (testo da incollare);
2. lo stato di ogni fase, le decisioni prese e i punti aperti;
3. note operative sull'ambiente;
4. il prompt originale completo, così come è stato dato (in fondo).

Il dettaglio tecnico completo di ogni fase è in `HANDOFF.md` (sezioni "Sessione 2026-10-26 (continuazione) — Batch "revisione con gli utenti", Fase …" e successive), la cronologia sintetica in `docs/CHANGELOG.md`.

---

## 1. Come ripartire

Incollare in una nuova sessione:

> Leggi `CLAUDE.md`, poi `CLAUDE_PROMPTS/BATCH_REVISIONE_UTENTI.md` (stato del batch e prompt originale) e le ultime sezioni di `HANDOFF.md`. Le Fasi 1 e 2 sono completate e approvate; la Fase 3 è implementata (vedi stato sotto: se approvata, applicare la migration e passare alla **Fase 4 — Gestione contenuti**), seguendo le regole generali del prompt originale (fasi con ok esplicito, migration nuove e reversibili con dry-run mostrato prima di eseguirla, una sola fonte di verità per i calcoli, test di regressione, documentazione aggiornata a fine fase incluso il file Nextcloud).

Fase 7 (opzionale, solo dopo ok): **proporre prima l'approccio** (impilamento verticale proporzionale vs griglia libera, orientamento orizzontale/verticale per i formati mezza/terzo, riordino dentro la pagina), partendo da `partials/page-content-entry.blade.php`, `page-card.blade.php`, pivot `page_content` (servirebbe una colonna d'ordine) e `PageCapacity`. Note storiche — prima della Fase 4: leggere `App\Livewire\Timone\ContentCreate` (+ `partials/occupancy-fields.blade.php`), `App\Support\ContentCreator`, `App\Support\PageOccupancy` (`distribution()`), `partials/page-content-entry.blade.php` (punto naturale per la rinomina inline) e i debiti #55, #56, #59 di `docs/11-debito-tecnico.md` (riguardano proprio la modifica di un contenuto).

---

## 2. Stato per fase

| Fase | Stato | Dove è documentata |
|---|---|---|
| 1 — Bug (9 punti) | ✅ completata e approvata (bug 8 implementato dopo l'ok sul flusso) | `HANDOFF.md` "Fase 1: bug" + "Fase 1 approvata; bug 8 implementato" |
| 2 — Copertine separate | ✅ completata e approvata; migration applicata sul DB di sviluppo | `HANDOFF.md` "Fase 2: copertine separate dalle pagine" + "Fase 2 approvata: copertine in un'unica fascia" |
| 3 — Occupazione in dodicesimi | ✅ completata e approvata; migration applicata sul DB di sviluppo (2026-10-27) | `HANDOFF.md` "Sessione 2026-10-27 — … Fase 3" |
| 4 — Gestione contenuti (modifica, rinomina inline; miniatura tolta su richiesta) | ✅ completata e approvata (senza miniatura) | `HANDOFF.md` "Sessione 2026-10-27 (continuazione) — … Fase 4" |
| 5 — Pubblicità previste alla creazione (segnaposto, `AdPlacementService`) | ✅ completata e approvata; migration applicata | `HANDOFF.md` "Sessione 2026-10-27 (continuazione) — … Fase 5" |
| 6 — Riepilogo stile dashboard | ✅ completata e approvata (nessuna migration) | `HANDOFF.md` "Sessione 2026-10-27 (continuazione) — … Fase 6" |
| 7 — Disposizione dei contenuti nella pagina (opzionale) | 🟡 approccio B; 7a approvato; 7b implementato, in attesa di ok; 7c da decidere | `HANDOFF.md` "… Fase 7a" |

### Fase 1 — cosa è stato fatto (sintesi)
1. **Header fisso**: `layouts/app.blade.php`, wrapper `sticky top-0 z-40`; auto-scroll Sortable da 120px.
2. **Input numerici vuoti**: causa doppia in Livewire (unset della proprietà `int`; null non riassegnato in idratazione). `Issues\Create::$total_pages` `?int` default null + trait `App\Livewire\Concerns\NormalizesEmptyNumericInput`. Regola per i nuovi campi numerici documentata in `docs/10-debugging.md`.
3. **Contenuto creato che non compariva**: evento Livewire locale `content-created`/`content-list-changed` (Grid, AdReservations); broadcast `App\Events\ContentListChanged` per archivia/ripristina/elimina.
4. **Animazione gruppi**: plugin MultiDrag di SortableJS (stesso pacchetto), FLIP con la Web Animations API, `Grid::nudgePage()` + `PageReorderer::nudgeTarget()` (bug frecce sui gruppi), posizione di drop dall'ordine reale del DOM (bug latente degli spacer in vista doppia).
5. **`$store.pagePresence` undefined**: store registrati nel corpo di `app.js`, interrotti da un'eccezione di `new Echo()` durante gli import. Ora in `resources/js/timone-alpine.js`, importato per primo; `echo.js` non lancia più.
6. **Testo bianco negli input**: regola `@layer base` in `resources/css/app.css`.
7. **Pagine più grandi**: zoom S/M/L (store Alpine `timoneZoom`, localStorage), proporzioni dal formato del numero.
8. **PDF multipagina su pubblicità**: `App\Support\AdPdfPagePlan` (usa tutte le N pagine / solo da X a Y), `ContentFile.pdf_page_number` = pagina originale, file associati alle pagine per posizione.
9. **Upload in produzione**: causa più probabile trovata nel codice (URL firmato dell'upload generato su `APP_URL` forzato ma verificato sull'indirizzo reale → 401) e corretta con `App\Support\Uploads\RequestRootSignedUploadUrl`; log `upload_failed`, limite reale nel riquadro, comando `php artisan timone:diagnose-upload`, checklist in `docs/09-deploy.md` §6bis.

### Fase 2 — cosa è stato fatto (sintesi)
- Copertine = righe di `pages` con `cover_slot` (`App\Enums\CoverSlot`: I, II, III, IV, bandella) e `position` NULL. `Issue::pages()` = sole interne, `covers()`, `allPages()`. `total_pages` = pagine interne, numerate da 1.
- I–IV create sempre (`App\Support\IssueCovers::ensure()` dall'observer; prima nascevano solo I e IV). Bandella facoltativa = 2 pagine di copertina; `issues.pagine_copertina` derivato (4/6), in sola lettura nella modale del Peso.
- Tutte le copertine in un'unica fascia in cima al timone (richiesta all'ok). Scambio tra copertine con drag nativo (`Grid::swapCovers()`), mai tra copertine e pagine interne. Contenuti su più pagine da una copertina: II → II + pagine 1…; III → ultime interne + III; I/IV/bandella una pagina.
- Rimossi `PageRole`, `PageRoleAssigner`, `PageNumberer`, `Timone\CoverSection`; colonne `pages.role` e `magazines.battente_counted_in_total` lasciate (non più lette).
- Migration `2026_10_26_200000/200001/200002` (schema, registro `cover_conversion_logs`, conversione dati) reversibili; dry-run `php artisan timone:covers-dry-run`.

### Fase 3 — cosa è stato fatto (sintesi)
- Dodicesimi interi: `App\Support\Twelfths` (unità + formattazione), `PageCapacity` (sostituisce `PagePercentageAllocator`), `PageFillStatus`, `PageOccupancy` riscritto (`fromParts`, `distribution` 12/12/6), `AdFormat::twelfths()` (sostituisce `defaultPercentage`).
- `contents.occupancy_twelfths`, `page_content.occupied_twelfths`; colonne vecchie nullable e non più usate.
- Modulo: "Pagine intere" + "Frazione", anteprima «Occupa …»; il formato propone, modificabile; via la "Percentuale manuale".
- Card: select 1/12…intera per pagina (`Grid::updateContentOccupancy`), barra + etichetta «Vuota / Libero: 1/3 / Piena».
- Migration `2026_10_27_100000_convert_occupancy_to_twelfths` + dry-run `php artisan timone:occupancy-dry-run`; conversione = dodicesimo più vicino (non "12 su ogni pagina": avrebbe creato pagine a 24/12 sui dati reali).

### Fase 4 — cosa è stato fatto (sintesi)
- Modale `Timone\ContentEdit` + `App\Support\ContentUpdater` (campi, cambio tipo, ridistribuzione se cambia l'occupazione, sostituzione PDF); regole condivise `ContentFormRules`, trait `HasOccupancyFields`.
- Miniatura separata (punto 4.2 della specifica): implementata e poi **tolta su richiesta dell'utente** («il PDF principale è già la miniatura»). Non riproporla.
- Rinomina inline: doppio click sul nome in pagina → `Grid::renameContent()` (articolo: titolo; pubblicità: cliente).

### Fase 5 — cosa è stato fatto (sintesi)
- `PlannedAdKind`, `AdPlacement\AdPlacementService` (puro, config `timone.ad_placement`), `PlaceholderAdCreator` (solo da `Issues\Create::save()`), `PlaceholderAdCounter`.
- `contents.is_placeholder`, `advertisements.client` nullable; modulo "Nuovo numero" con anteprima live ed errore bloccante; segnaposto tratteggiati «Da assegnare»; diventano veri con cliente/titolo/PDF.

### Fase 6 — cosa è stato fatto (sintesi)
- `App\Support\IssueSummary` + partial `summary-bar` sticky sotto l'header (`--app-header-height`); evidenziazione bianche/da assegnare (`Grid::toggleSummaryHighlight()`); soglia di allarme tolta ovunque (colonna lasciata); bug "la ricerca cambiava la % pubblicitaria" corretto; `PlaceholderAdCounter` rimosso.

### Decisioni prese con una scelta di default (da tenere presenti)
- Bandella = vecchio "battente", slot facoltativo; un nuovo numero la riceve se la rivista ha 6 pagine di copertina nei parametri del peso.
- Copertine fuori da selezione multipla, modalità scambio, frecce, gruppi collegati, cruscotto pubblicitario e conteggi delle pagine interne (conteggio a parte nell'intestazione).
- Bug 8: scelta "usa tutte / da X a Y" solo per le pubblicità (non per gli articoli); un PDF più corto del formato non chiede nulla; se la scelta supera le pagine del formato la percentuale per pagina diventa 100 (da riassorbire nei dodicesimi in Fase 3).
- In vista doppia la II non è affiancata alla pagina 1 (fascia separata).
- Fase 6: "occupate" = piene (le quattro voci sommano al totale); evidenziare invece di filtrare; riepilogo come partial di Grid e non componente a sé (eviterebbe di duplicare tutti i listener realtime).
- Fase 5: mezza segnaposto orizzontale; «in trattativa»; solo intera/2/3/1/2/1/3/1/4; creazione bloccata se non ci stanno; contatore segnaposto su tutto il numero (DV-32).
- Fase 4: rinomina di una pubblicità = cliente; ridistribuzione sempre dalla prima pagina su pagine consecutive; in modifica niente scelta "usa tutte / da X a Y" del PDF (il PDF deve coincidere con l'occupazione); ✏️ = modale completa, doppio click = rinomina.
- Fase 3: conversione al dodicesimo più vicino invece di "12 su ogni pagina"; copertina 2ª/3ª/4ª e battente = 12, piedino = 4, 2/3 = 8, 1/4 = vetrina (DV-28); pivot con default 12; bug 8 "usa tutte" = N pagine intere a prescindere dai campi; estendere a una pagina in più = pagina intera (o tutto il contenuto se < 1 pagina).

### Punti aperti / da verificare
- `docs/DA-VERIFICARE.md` DV-23 (causa reale degli upload in produzione: eseguire `timone:diagnose-upload` sul server), DV-24 (verifiche nel browser della Fase 1: nessun Dusk), DV-25 (regole copertina su più pagine da confermare con la redazione), DV-26 (dry-run delle copertine sul server prima del `migrate` in produzione).
- Checklist di verifica manuale nel browser: `HANDOFF.md`, sessione "Fase 1: bug", paragrafo "Da verificare a mano nel browser". Da aggiungere per la Fase 2: fascia copertine, scambio trascinando l'etichetta blu, bandella.
- `docs/11-debito-tecnico.md` #45–#53: rischi noti introdotti o emersi in Fase 1–2 (override di metodi interni di Livewire, `Grid.php` che cresce → proposta di estrarre il riepilogo in un componente nella Fase 6, rollback della conversione affidabile solo subito dopo il deploy).
- **Fase 6 / regole**: soglie e colori di allarme del carico pubblicitario tolti nella Fase 6 (2026-10-27). Nota storica: prima esistevano (`magazines.ad_threshold_percentage`, input "Soglia allarme" nel cruscotto, export).

### Fase 7 — proposta presentata il 2026-10-27 — **scelta dell'utente: B con le scelte consigliate** (anteprima per blocco, ↑/↓ per l'ordine, articoli sempre orizzontali)
- **Approccio consigliato (B)**: impilamento proporzionale con orientamento, calcolato al volo da classe pura `App\Support\PageLayoutPlanner` (rettangoli in % da dodicesimi + orientamento): i contenuti "verticali" (mezza verticale, 1/3 verticale, 2/3: alti quanto la pagina nel listino) diventano colonne a tutta altezza larghe dodicesimi/12; gli altri si impilano in righe nella larghezza rimasta, alti dodicesimi/(12 × larghezza rimasta). Le aree sono esatte e ci stanno sempre (somma ≤ 12). Spazio libero = rettangolo vuoto in fondo. Orientamento da `AdFormat::orientation()` (derivato da `dimensionsMm()`); articoli orizzontali di default.
- Alternative scartate: (A) solo righe impilate — semplice ma una mezza verticale sembra orizzontale; (C) griglia libera 12 celle con posizionamento manuale — 1/2 verticale e 1/3 orizzontale non sono rettangoli di celle intere su una griglia 3×4, serve un editor di posizionamento, sproporzionato.
- Ogni blocco mostra l'anteprima del PROPRIO file (pivot `content_file_id` o file più recente) al posto dell'immagine unica a tutta card; controlli (spazio, ✏️, ↗, ✕) su hover/focus del blocco; vista Lista invariata; export PDF invariato (eventuale estensione dopo).
- Passi: **7a** layout in sola lettura (nessuna migration); **7b** ordine dei contenuti nella pagina: colonna `page_content.position` (migration nuova e reversibile, default = ordine di assegnazione), bottoni ↑/↓ + evento realtime; **7c** (solo se 7b regge) drag&drop dentro la card: rischio di conflitto con il Sortable delle pagine (MultiDrag, handle) e con il morph di Livewire.
- Limiti dichiarati: è una rappresentazione, non l'impaginato reale; con zoom S e 4+ contenuti i nomi si troncano (tooltip); le pagine «oltre capienza» si scalano in proporzione con l'avviso rosso; orientamento degli articoli fisso finché non si aggiunge una scelta per pagina.
- Risposte: B; anteprima per blocco; ↑/↓ (7c solo se 7b regge); articoli sempre orizzontali.
- **7a fatto** (2026-10-27): `PageLayoutPlanner`, `ContentOrientation`, `AdFormat::orientation()`, `Page::layout()`/`previewFileFor()`, blocchi nella card, comandi compatti; tolta la barra laterale di riempimento. **7b fatto** (2026-10-27): `page_content.position`, `PageContentOrder`, `Grid::moveContentInPage()`, `PageContentsReordered`, frecce ↑/↓ nei comandi del blocco. **7c**: da proporre/decidere dopo che l'utente ha provato 7b (rischi nel debito #77).

---

## 3. Note operative sull'ambiente (macchina di sviluppo)

- **Fase 5: migration `2026_10_27_300000_add_placeholder_ads` applicata** (batch 5). Attenzione: l'utente ricostruisce anche da sé lo stack, quindi le migration possono risultare applicate prima del previsto — controllare sempre `migrate:status` (il 2026-10-27 la migration della miniatura, poi tolta, era stata applicata così: colonne e riga di `migrations` rimosse a mano, vedi HANDOFF).
- **Fase 3: migration `2026_10_27_100000` applicata** (batch 3, 2026-10-27, con `docker compose up -d --build`). Lo stack esegue `migrate` all'avvio: ogni rebuild applica le migration pendenti.
- **DB di sviluppo** (Docker, MySQL `timone`): le migration della Fase 2 sono **applicate** (batch 2, alle 18:07 del 2026-10-26, all'avvio dello stack ricostruito). Numeri presenti: #1/#2 (62 interne), #3 (210 interne; la bandella creata dalla conversione è stata poi tolta dall'interfaccia, quindi il rollback non sarebbe più esatto per questo numero), #4 (210), #5 «TTOT» (30, creato poco prima della conversione).
- **Test** (ultimo stato, Fase 7b: 747 + 40 skipped in locale, 787/787 con imagick): `php artisan test` in locale (MAMP, senza imagick: 38 test saltati). Suite completa con imagick, senza toccare i container in esecuzione:
  ```bash
  docker run --rm --entrypoint sh -v "$PWD":/app -w /app \
    -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
    timone-rivista-app:latest -c 'php vendor/bin/pest'
  ```
- Comandi artisan contro il DB di sviluppo con il codice del repo (es. un dry-run), senza ricostruire: stesso `docker run` con `--network timone-rivista_timone` e senza le variabili `DB_*` (usa `.env`).
- Asset: `npm run build` dopo modifiche a JS/CSS/classi Tailwind (in locale esiste `public/hot`, che punta al Vite dev server).
- I test che fanno broadcast vanno scritti con `Event::fake([...])` (vedi `docs/10-debugging.md`, "Could not resolve host: reverb"). Gli helper `reorderableIssue()`/`editorFor()` stanno in `tests/Feature/Livewire/TimoneGridReorderTest.php`.
- Fasi 1–2 committate dall'utente (`14184e3`, `0340c72`). Fasi 3–7b nel working tree, non committate (rimossi con `git rm`: `app/Support/PagePercentageAllocator.php`, `tests/Unit/PagePercentageAllocatorTest.php`).

---

## 4. Prompt originale (2026-10-26), testo completo

# Contesto
Timone elettronico (flat plan) riviste: Laravel + Livewire + Alpine + MySQL, aggiornamento in
tempo reale via polling, calcolo peso già implementato. Dopo una revisione con gli utenti ci sono
bug da correggere e nuove funzionalità. Lavori per FASI: alla fine di ogni fase lanci tutta la
suite di test, mi mostri cosa hai fatto e **aspetti il mio ok** prima della fase successiva.

# Regole generali
- Prima di ogni fase leggi il codice coinvolto e usa i nomi reali di model, componenti e campi.
- Migration solo nuove e reversibili. Per ogni cambio di modello dati serve una migration di dati
  che converte i numeri esistenti senza perdere assegnazioni; mostrami cosa cambia prima di eseguirla.
- Una sola fonte di verità per ogni calcolo (conteggi pagine, occupazione, percentuale pubblicità,
  peso): un servizio, chiamato da tutti i componenti. Niente ricalcoli duplicati in Blade/JS.
- Ogni modifica deve propagarsi agli altri utenti collegati con il polling esistente.
- Nessuna dipendenza nuova senza chiedermelo.
- Ogni bug corretto ha un test di regressione, quando è testabile lato PHP.

# FASE 1 — Bug

1. **Header fisso in alto**: `sticky top-0` (o `fixed` con compensazione del contenuto), z-index sopra
   le pagine del timone e sotto modali/dropdown. Verifica che il drag&drop non finisca sotto l'header
   e che l'auto-scroll durante il trascinamento funzioni ancora.

2. **Errore quando si svuota l'input pagine nella creazione rivista/numero**: probabilmente una
   proprietà Livewire tipizzata `int` che riceve `''`. Rendi le proprietà numeriche `?int`, gestisci
   la stringa vuota come null, niente ricalcoli finché il valore non è valido, errore di validazione
   inline al posto dell'eccezione. Controlla TUTTI gli input numerici del progetto con lo stesso
   pattern (pagine, copie, bancali, grammature) e correggili insieme.

3. **Contenuto creato che non appare senza ricaricare**: dopo la creazione, dispatcha un evento
   Livewire (es. `content-created`) ascoltato dalla sezione contenuti, oppure aggiorna la collezione
   nel componente padre. Stesso controllo per modifica ed eliminazione.

4. **Animazione dello spostamento di un gruppo di pagine**: quando si sposta un contenuto o gruppo su
   più pagine, l'animazione (ghost/placeholder del drag e transizione finale) deve mostrare TUTTE le
   pagine del gruppo che si muovono insieme, non solo quella afferrata. Stesso comportamento per lo
   spostamento da tastiera.

5. **Errore Alpine `$store.pagePresence` undefined (`editorFor`)**: lo store viene letto prima di
   essere registrato, oppure non è registrato in alcune pagine o dopo una navigazione Livewire.
   Registra lo store una sola volta dentro `document.addEventListener('alpine:init', ...)` in uno
   script caricato in tutte le pagine che lo usano; se si usa `wire:navigate` verifica che non venga
   perso o ri-registrato. Aggiungi anche una protezione nelle espressioni
   (`$store.pagePresence?.editorFor(id)`), ma la correzione vera è l'ordine di registrazione.
   Spiegami la causa che hai trovato. Console senza errori durante creazione e modifica.

6. **Testo bianco negli input della pagina**: correggi il colore (probabile ereditarietà da un
   contenitore scuro o dark mode). Controlla tutti gli input, select e textarea dentro le pagine del
   timone e nelle modali. Contrasto leggibile.

7. **Pagine del timone più grandi**: aumenta la dimensione delle miniature mantenendo le proporzioni
   del formato reale della rivista (base/altezza dal numero), più spazio tra le pagine. Rendi la
   dimensione un controllo di zoom (es. 3 livelli: S/M/L) ricordato per utente in localStorage, con
   try/catch. Il contenuto della pagina non deve più essere schiacciato.

8. **PDF multipagina per una pubblicità** (errore attuale: "Il PDF ha 3 pagine, ma il formato
   «Copertina (2ª/3ª/4ª)» ne occupa 1"): non deve essere bloccante. Comportamento:
   - una pubblicità può occupare più pagine; il numero di pagine del PDF è un suggerimento
   - se le pagine del PDF non corrispondono all'occupazione del formato, mostra una scelta:
     "usa tutte le N pagine (la pubblicità occuperà N pagine)" oppure "usa solo le pagine da X a Y"
   - l'anteprima di ogni pagina del timone mostra la pagina del PDF corrispondente
   - il formato copertina occupa una sola copertina: se il PDF ha più pagine, proponi di usare la
     prima per la copertina e le altre come pagine interne successive, oppure di sceglierne una
   Mostrami il flusso proposto prima di implementarlo.

9. **In produzione non si possono caricare media nei contenuti**: non hai accesso alla produzione,
   quindi:
   - individua nel codice come avviene l'upload (Livewire `WithFileUploads`, disco temporaneo,
     disco finale, validazioni di dimensione/MIME)
   - elenca le cause probabili in ordine di probabilità, con il controllo da fare sul server per
     ognuna e il comando o la riga di log da guardare. Includi almeno: `APP_URL` diverso dall'URL
     reale o http/https (gli URL firmati dell'upload temporaneo Livewire falliscono con 401),
     trusted proxies, `upload_max_filesize`/`post_max_size` PHP, `client_max_body_size` nginx,
     permessi su `storage/` e `livewire-tmp`, `storage:link` mancante, disco configurato diverso
     tra locale e produzione, limiti `livewire.temporary_file_upload.rules`
   - correggi nel codice quello che è correggibile lato applicazione (messaggi d'errore chiari
     all'utente invece di fallimenti silenziosi, log dell'errore con contesto)
   - aggiungi questa checklist a `docs/10-debugging.md` e `docs/09-deploy.md`

# FASE 2 — Copertine separate dalle pagine

Oggi pagine e copertine sono mischiate nel conteggio e bisogna fare addizioni e sottrazioni a mano.
Non deve più succedere.

## Modello
- Le copertine NON sono pagine: sono elementi separati del numero, con posizione fissa
  `I`, `II`, `III`, `IV` (prima, seconda, terza, quarta di copertina).
- Alla creazione di un numero vengono SEMPRE create tutte e quattro le copertine (oggi vengono create
  solo I e IV: è un bug).
- Le pagine interne partono da 1 e arrivano al totale indicato. Il numero di pagine inserito nel
  form è SOLO quello delle pagine interne.
- Il peso continua a usare `pagine_copertina` per il calcolo: deve derivare dalle copertine esistenti
  (4, o 6 se c'è una bandella e il progetto lo prevede) e non da sottrazioni sul totale.
- Migration di dati: converti i numeri esistenti spostando le copertine attuali nei nuovi slot e
  rinumerando le pagine interne da 1, senza perdere contenuti assegnati. Fammi vedere prima un
  dry-run (cosa diventa cosa per ogni numero).

## Interfaccia
- Le copertine stanno in una fascia visivamente separata: I e II prima della pagina 1, III e IV dopo
  l'ultima pagina, con stile diverso dalle pagine (bordo, etichetta "I di copertina", ecc.).
- Su una copertina si possono assegnare contenuti e pubblicità come sulle pagine, ma non si può
  trascinarla tra le pagine interne né trascinare una pagina interna dentro una copertina.
  Spostamenti tra copertine sono consentiti.
- Tutti i conteggi (totale pagine, percentuale pubblicità, pagine bianche) sono sulle pagine interne;
  le copertine hanno un conteggio separato. Ovunque compaiano numeri di pagina, nessuno deve fare
  calcoli a mente.

# FASE 3 — Occupazione a frazioni di pagina

## Modello
Un contenuto (editoriale o pubblicitario) non occupa sempre pagine intere: per esempio 2,5 pagine.
- Salva l'occupazione come intero in **dodicesimi di pagina** (12 = pagina intera; 1/4 = 3,
  1/3 = 4, 1/2 = 6, 2/3 = 8, 3/4 = 9). Niente float: somme e confronti esatti.
- Un contenuto può distribuirsi su più pagine: tabella pivot contenuto–pagina con l'occupazione su
  ciascuna pagina in dodicesimi. Default alla creazione: pagine intere in sequenza e la frazione
  sull'ultima (2,5 pagine → 12, 12, 6). L'occupazione per pagina resta modificabile.
- Una pagina ha capacità 12. Una pagina può contenere più contenuti separati; la somma delle loro
  occupazioni non può superare 12 (errore bloccante, con messaggio che dice quanto spazio resta).
- Formati pubblicitari mappati sull'occupazione: pagina intera 12, 2/3 8, 1/2 6, piedino 1/3 4,
  vetrina 1/4 3. Usa la tabella formati esistente aggiungendo il campo `occupazione_dodicesimi` e il
  formato 2/3 se manca. Verifica i formati che esistono già (mezza orizzontale/verticale, 1/3
  verticale...) e dimmi come li mappi.
- Migration dati: i contenuti esistenti occupano 12 su ogni pagina assegnata.

## Interfaccia di creazione/modifica contenuto
- Togli la select del numero di pagine. Metti due campi: "Pagine intere" (intero ≥ 0) e
  "Frazione" (select: nessuna, 1/4, 1/3, 1/2, 2/3, 3/4). Totale mostrato sotto: "Occupa 2,5 pagine".
  Almeno uno dei due deve essere > 0.
- Per le pubblicità la frazione si deduce dal formato scelto, ma resta modificabile.

## Riempimento delle pagine
- Ogni pagina mostra a colpo d'occhio quanto è piena: barra o riempimento verticale in proporzione,
  più etichetta "Libero: 1/3" o "Piena".
- Le pagine al 100% hanno uno stato visivo evidente e diverso (bordo/colore + icona), riconoscibile
  anche senza colore (accessibilità).
- Pagine vuote e pagine parziali distinguibili tra loro.

# FASE 4 — Gestione contenuti

1. **Modifica di un contenuto esistente**: modale di modifica con tutti i campi della creazione
   (titolo, tipo, occupazione, formato, PDF, miniatura). Le modifiche si applicano a tutte le pagine
   occupate dal contenuto.
2. **Miniatura**: in creazione e modifica si può caricare un'immagine miniatura (jpg/png/webp), con
   anteprima, sostituzione e rimozione. Se c'è un PDF e nessuna miniatura, usa l'anteprima del PDF
   come oggi. Stesso disco e stesse regole di upload sistemate nella Fase 1, punto 9.
3. **Rinomina inline**: doppio click (o icona matita) sul nome del contenuto dentro la pagina →
   input inline, Invio salva, Esc annulla. Il nome è del contenuto, non della pagina: un contenuto su
   più pagine si aggiorna ovunque compaia, anche per gli altri utenti collegati.

# FASE 5 — Pubblicità previste alla creazione del numero

## Form di creazione numero
Oltre al totale pagine interne, campi per le pubblicità previste:
- n. pubblicità pagina intera (100%)
- n. pubblicità 1/2 pagina
- n. pubblicità 2/3 pagina
- n. vetrine (1/4)
- n. piedini (1/3)
Mostra in tempo reale le pagine pubblicitarie equivalenti totali, la percentuale sulle pagine
interne e un errore se le pubblicità non ci stanno nello spazio disponibile.

## Preinserimento automatico
Alla creazione del numero, le pubblicità previste vengono create come **pubblicità segnaposto**
(campo `is_placeholder` o stato `da_assegnare`): senza inserzionista, senza PDF, con titolo
automatico ("Pubblicità 1/2 — da assegnare"), e vengono già posizionate nel timone, non nella
sezione "assegna contenuti".
Regola di posizionamento (mettila in un servizio dedicato e testabile, `AdPlacementService`):
1. Le prime 4 pubblicità **a pagina intera** vanno sulle copertine, in quest'ordine: II, III, IV, I.
   (La I di copertina di solito non è pubblicità: lasciala come ultima scelta e segnalamela;
   l'ordine va in config.)
2. Le restanti a pagina intera si alternano a una pagina sì e una no, partendo da pagina 2
   (2, 4, 6...). Pagina iniziale e passo vanno in config.
3. Le pubblicità frazionarie (2/3, 1/2, 1/3, 1/4) continuano la stessa sequenza alternata, una per
   pagina, lasciando libero il resto della pagina per l'editoriale.
4. Se le posizioni alternate finiscono, riempi le pagine libere rimaste partendo dal fondo; se non
   c'è più spazio, la pubblicità resta da assegnare e lo segnali.
5. Il posizionamento avviene SOLO alla creazione del numero. Dopo è tutto modificabile a mano e non
   viene mai ricalcolato automaticamente.

## Segnaposto → pubblicità reale
- Un segnaposto si modifica come qualsiasi contenuto (Fase 4): quando gli si assegna nome, PDF o
  inserzionista, smette di essere segnaposto.
- Segnaposto visivamente distinti nel timone (es. tratteggiato, etichetta "Da assegnare").
- Contano nella percentuale di pubblicità ma anche nel contatore "pubblicità da assegnare".

# FASE 6 — Riepilogo stile dashboard
Barra di riepilogo sempre visibile sotto l'header (sticky insieme o compatibile con l'header):
- pagine interne totali / occupate / **bianche** (occupazione 0) / parziali
- spazio libero totale in pagine (somma dei dodicesimi liberi, es. "4 e 1/3 pagine libere")
- **pubblicità da assegnare** (segnaposto) suddivise per formato
- percentuale pubblicità sulle pagine interne, calcolata in dodicesimi. **Togli le soglie di allarme
  e i colori di avviso del carico pubblicitario**: mostra solo la percentuale.
- copertine: assegnate/libere
- peso copia e scaglione (dal calcolo esistente)
Click su "pagine bianche" o "da assegnare" evidenzia o filtra quelle pagine nel timone.

# FASE 7 (opzionale, solo dopo il mio ok) — Disposizione dei contenuti nella pagina
Nella miniatura della pagina ogni contenuto occupa uno spazio proporzionale alla sua occupazione
reale (1/2 = metà pagina, 1/3 = un terzo...), così si vedono tutti i contenuti di una pagina.
- Prima versione: impilamento verticale automatico in proporzione, con orientamento orizzontale/
  verticale per i formati che lo prevedono (mezza orizzontale vs verticale).
- Poi, se semplice: riordino dei contenuti dentro la pagina con drag&drop, salvando l'ordine.
Prima di implementarla proponimi l'approccio e i limiti (layout libero a griglia vs impilamento).

# Test (in ogni fase)
- Fase 1: proprietà numeriche vuote non generano eccezioni; evento di refresh dopo la creazione
  di un contenuto; upload PDF multipagina accettato con la scelta delle pagine.
- Fase 2: un nuovo numero ha 4 copertine I–IV; le pagine interne partono da 1; il totale pagine non
  include le copertine; migration dati su un numero di esempio con copertine e contenuti assegnati.
- Fase 3: 2 intere + 1/2 = 30 dodicesimi distribuiti come 12/12/6; pagina con 6+4+3 = 13 → errore;
  pagina con 6+3+3 = 12 → piena; percentuale pubblicità esatta in dodicesimi.
- Fase 4: rinomina di un contenuto su 3 pagine aggiorna tutte e 3; modifica occupazione ridistribuisce.
- Fase 5: casi di posizionamento: 6 intere + 2 mezze su 32 pagine → II, III, IV, I sulle copertine,
  poi pagine 2, 4, poi le mezze su 6, 8; caso con più pubblicità che spazio; il posizionamento non si
  ripete modificando il numero.
- Fase 6: conteggi pagine bianche/parziali/da assegnare su un numero costruito ad hoc.

# Documentazione (alla fine di ogni fase, per quello che la fase tocca)
Aggiorna `docs/` (02-architettura, 03-dominio-e-flussi, 04-database, 05-configurazione, 09-deploy,
10-debugging, 11-debito-tecnico, 12-glossario, DA-VERIFICARE, CHANGELOG) e il file Nextcloud:
- copertine come elementi separati, numerazione da pagina 1
- occupazione in dodicesimi: perché, conversioni, capacità della pagina
- pubblicità segnaposto e regola di posizionamento con le chiavi di config
- glossario: I/II/III/IV di copertina, vetrina, piedino, segnaposto, occupazione, pagina bianca
- debugging: errore Alpine store, upload in produzione (checklist), input numerici vuoti
- Nextcloud, per utenti non tecnici: come leggere riempimento pagine e dashboard, come trasformare un
  segnaposto in pubblicità vera, come si inseriscono le frazioni
Ogni percorso, campo e comando citato deve esistere nel codice.

# Consegna di ogni fase
File modificati (codice, migration, test, docs separati), output dei test, migration di dati
eseguite e cosa hanno cambiato, punti ambigui risolti con una scelta di default (elencali), diff del
file Nextcloud.

---

### Modifiche alla specifica concordate durante il lavoro
- Fase 5, regola 1: **le prime 4 pubblicità di qualsiasi formato** (prima le intere, poi 2/3, 1/2, 1/3, 1/4) riempiono tutte le copertine II, III, IV, I; poi pagine interne una sì e una no. La specifica diceva "prime 4 a pagina intera" (precisazione dell'utente del 2026-10-27, avviso sulla I mantenuto).
- Fase 4, punto 2 (miniatura): **tolto** su richiesta dell'utente (2026-10-27): il PDF principale è già la miniatura.
- Fase 2, interfaccia: **tutte le copertine (I, bandella, II, III, IV) in un'unica fascia in cima al timone**, prima della pagina 1 — non più III e IV dopo l'ultima pagina (richiesta dell'utente all'ok della Fase 2).
- Fase 1, bug 8: "organizzati in base alla richiesta e fai come credi" — ramo copertina implementato con le regole II/III descritte sopra, scelta pagine solo per le pubblicità.
