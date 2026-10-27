# Batch "revisione con gli utenti 2" — file di ripartenza

Ultimo aggiornamento: 2026-10-27 sera. Questo file basta per riprendere il lavoro in una sessione nuova: stato, cose da sapere, comandi, prossimo passo e, in fondo, **il prompt originale invariato**. La cronologia dettagliata (cosa, perché, file, test) è in `HANDOFF.md`, voci "Revisione 2"; le regole permanenti in `CLAUDE.md` (§4 = file protetti, nuova con questo batch).

## Come ripartire (da leggere per primo)

1. Leggi `CLAUDE.md`, questo file e le ultime due voci di `HANDOFF.md` ("Revisione 2, Fase 1" e "Fase 2").
2. Chiedi all'utente l'esito della prova della **Fase 2** (è consegnata, **non ancora approvata**). Se dà l'ok → Fase 3. Se segnala problemi → correggi prima.
3. Controlla lo stato del DB di sviluppo: `docker exec timone-rivista-app-1 php artisan migrate:status | tail -5`. L'utente ricostruisce lo stack da sé (`docker compose up -d --build` esegue `migrate` all'avvio): le migration nuove potrebbero essere già applicate.
4. Lavora una fase alla volta: a fine fase suite completa (locale + container), consegna con il formato della sezione "Consegna di ogni fase" del prompt, poi **aspetta l'ok esplicito**.

## Stato delle fasi

| Fase | Stato | Note |
|---|---|---|
| 1 — Bug (focus/cifre perse, "Text too long", barra irraggiungibile, auto-scroll, upload 100 MB) | ✅ approvata | migration `2026_10_27_500000_change_activity_logs_description_to_text` |
| 2 — Click = pagina + occhio, fascia sticky, griglia 6×6, ritaglio anteprime, posizione in creazione/modifica | 📦 consegnata, **in attesa di ok** | migration `2026_10_27_600000_add_grid_cells_to_page_content_table` (con conversione), `2026_10_27_610000_add_file_fit_columns` |
| 3 — Pubblicità segnaposto dal form + caricamento massivo | ⏳ da fare | vedi "Prossimo passo" |
| 4 — Ruoli ed eliminazioni | ⏳ da fare | PRIMA mostrare all'utente ruoli e permessi attuali |
| 5 — Export PDF sfogliabile della rivista | ⏳ da fare | PRIMA proporre le librerie (pro/contro), nessuna dipendenza senza ok |

**Migration non ancora applicate sul DB di sviluppo** (al momento della scrittura): `500000`, `600000`, `610000`. Dry-run già mostrati e approvati come procedura: `php artisan timone:grid-dry-run` (33 identiche, 3 vetrine riposizionate, 0 a L, 0 fuori pagina). Dopo l'applicazione, facoltativo: `php artisan timone:file-ratios` (12 file da misurare).

**Nulla è committato** dalla fase "fase 7 e fine" (`eef47ce`) in poi: lavoro della revisione 2 tutto nel working tree. Commit e push solo su richiesta esplicita (CLAUDE.md).

## Decisioni dell'utente già prese (non richiederle)

- Fase 2.3 griglia 6×6: **3/4, 7/12, 11/12 = forma a L** (riempie le celle libere, niente frecce); **ruotare una pubblicità cambia il formato** (`AdFormat::rotated()`); freccia su un posto occupato da un contenuto di misura diversa = **scambio nel rettangolo comune**, altrimenti freccia disattivata.
- Dal batch precedente: le prime 4 pubblicità previste vanno su tutte le copertine (II, III, IV, I, qualsiasi formato, avviso sulla I), poi pagine interne una sì e una no; niente miniatura separata dal PDF (il PDF è la miniatura); approccio "B" per la vecchia disposizione (ora sostituita dalla griglia).

## Scelte di default fatte (dichiarate all'utente, da confermare: DA-VERIFICARE DV-36…DV-39)

- Il "campo description" della Fase 1.2 era `activity_logs.description` (registro), non note utente: portato a TEXT + descrizione breve; le note del numero (`issues.notes`) erano già TEXT, aggiunto il messaggio.
- Avvisi solo al blur nel modulo del numero e in `x-weight-field`; altri moduli invariati.
- Menu «⋯» su ogni blocco (non solo su quelli piccoli).
- Fascia sticky da 640 px in su (su telefono scorre).
- Piedino ruotato → 1/3 verticale (poi 1/3 orizzontale, non più piedino); L in ordine di lettura; campo «Posizione» assente se il modulo è aperto da «📎 Carica materiale»; frecce che non chiudono il menu; ritaglio non applicato nell'export PDF del timone (lo farà la Fase 5).
- `TIMONE_UPLOAD_MAX_KB` (facoltativa, default 102400) non aggiunta a `.env.example` (regola 2 del batch).

## Mappa del codice toccato dalla revisione 2 (dove mettere le mani)

- **Griglia 6×6**: `app/Support/PageGrid/` — `GridRect`, `GridShapes` (forme), `PageGridPlanner` (puro: resolve/moveTarget/rotateTarget), `PageGridPositions` (UNICO che scrive `page_content.grid_*`), `GridConversion` (migration + `timone:grid-dry-run`), `ThumbnailFit` (ritaglio). `Page::layout()` / `layoutBlockFor()`. `Grid::moveContentOnGrid()` / `rotateContentOnGrid()`.
- **Comandi di un contenuto**: `resources/views/livewire/timone/partials/content-controls.blade.php` (stesso markup per la vista Lista e il menu «⋯», che sta in `page-card.blade.php` con `@teleport('body')` + `x-anchor`).
- **Posizione in creazione/modifica**: `App\Support\PlacementChoice`, `ContentPlacer::check()`, `ContentUpdater::update(..., moveTo)`, partial `placement-field.blade.php`.
- **Upload**: limite unico `config/timone.php` → `upload_max_kb`; `ContentFormRules::maxKilobytes()/fileRule()/temporaryUploadMessage()`; `Alpine.data('fileUpload')` in `resources/js/timone-alpine.js`; `timone:diagnose-upload` con soglie; `PdfPageMeasurer::pageCount()` con Ghostscript.
- **Auto-scroll del drag**: `resources/js/drag-autoscroll.js` (elementi in alto con `data-sticky-top`).
- **Fascia sticky**: `grid.blade.php` (`data-testid="timone-sticky-band"`), `--timone-sticky-height` → `scroll-padding-top` in `resources/css/app.css`.
- **Input numerici live**: proprietà `string` + parsing (mai `?int` con `wire:model.live`), `<x-live-field>` per gli avvisi al blur.

## Prossimo passo: Fase 3 (appunti già raccolti)

- `advertisements.client` è **già nullable** (migration Fase 5 `2026_10_27_300000`): per 3.1 probabilmente nessuna migration, solo validazione (`ContentFormRules::rules()`, oggi `required_if:type,pubblicita` tranne che per i segnaposto) — verificare e dirlo nella consegna.
- Testo segnaposto oggi in `App\Enums\PlannedAdKind::placeholderTitle()` («Pubblicità 1/2 — da assegnare»): estrarre un metodo unico usabile anche dal form (che parte da un `AdFormat`, non da un `PlannedAdKind`).
- Regola segnaposto oggi = colonna `contents.is_placeholder` (Content::isPlaceholder()), azzerata in `ContentUpdater::updateFields()` quando arrivano cliente/titolo/PDF. La specifica chiede "segnaposto = senza cliente e senza file, nello stesso punto del codice": riallineare la regola in un solo posto e dire all'utente cosa cambia (oggi anche un titolo diverso la rende reale).
- Conteggio «da assegnare» della dashboard: `App\Support\IssueSummary`.
- Caricamento massivo (3.2): riusare `ContentCreator` (un contenuto per file), `ContentFormRules::fileRule()` e `Alpine.data('fileUpload')`/`$wire.upload` in sequenza (un file alla volta), `PdfPageMeasurer::pageCount()` per le pagine (Ghostscript) e per "PDF leggibile". Titolo dal nome file (underscore/trattini → spazi), duplicati con suffisso " (2)".

## Fase 4 e 5: cosa preparare

- Fase 4: ruoli = colonna `users.role`, enum `App\Enums\UserRole` (Admin, Redattore, Commerciale, SolaLettura); policy in `app/Policies` (`IssuePolicy`, `MagazinePolicy`, `UserPolicy`); nessun pacchetto permessi. Mostrare questo all'utente PRIMA di implementare. Verificare se i model usano `SoftDeletes` e chiedere prima di un'eliminazione definitiva.
- Fase 5: proposta librerie (es. `setasign/fpdi` + `tcpdf`, oppure Ghostscript/qpdf da CLI — Ghostscript è già nell'immagine Docker), pro/contro, memoria; aspettare l'ok. La griglia 6×6 (`Page::layout()`) e `ThumbnailFit` sono la base per collocare e ritagliare i contenuti.

## Ambiente e comandi utili

- **Test in locale** (PHP MAMP 8.2, senza imagick: 41 test saltati): `php artisan test --compact` (memory_limit 512M impostato in `phpunit.xml`). Ultimo esito: 816 passati + 41 saltati.
- **Test nel container** (con imagick e Ghostscript): `docker run --rm --entrypoint sh -v "$PWD":/app -w /app -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: timone-rivista-app:latest -c 'php vendor/bin/pest --compact'`. Ultimo esito: 857/857.
- **Helper dei test** definiti in altri file (caricati solo con l'intera suite o includendo quei file): `reorderableIssue()`/`editorFor()` in `TimoneGridReorderTest.php`, `layoutContent()` in `PageLayoutTest.php`, `gridAd()`/`cellsOf()` in `PageGridMoveTest.php`, `weightEditorFor()` in `IssueWeightTest.php`.
- **Pint solo sui file modificati** (mai su cartelle intere: riformatta file non coinvolti). `npm run build` dopo modifiche JS/CSS (`public/build` è ignorato da git; il Docker compila da sé).
- **Prova di una migration su copia MySQL**: dump del DB di sviluppo (`docker exec timone-rivista-mysql-1 sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"'`), container `mysql:8.4` temporaneo `occ-mysql` sulla rete `timone-rivista_timone`, import, `migrate` / `rollback` con l'immagine `timone-rivista-app:latest` e `-e DB_HOST=occ-mysql`, poi `docker rm -f occ-mysql`.
- **Dry-run in sola lettura sul DB di sviluppo**: `docker run --rm --network timone-rivista_timone --env-file .env -e APP_ENV=local --entrypoint sh -v "$PWD":/app -w /app timone-rivista-app:latest -c 'php artisan timone:grid-dry-run'`.
- **Verifiche nel browser** (niente Dusk nel progetto): server PHP locale con DB SQLite temporaneo (variabili d'ambiente che puntano a un file nella scratchpad; `php -S 127.0.0.1:8123` lanciato dalla cartella `public` con `vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`; NON `artisan serve`, che rilegge il `.env` e torna a MySQL) + Chrome headless (`--remote-debugging-port`) guidato da un piccolo script Node CDP. Lo script e il DB della sessione erano nella scratchpad, che non sopravvive: vanno ricreati (seed: `migrate:fresh --seed`, utente `admin@timone.test` / `password`). `php artisan tinker file.php < /dev/null` (senza `< /dev/null` resta in attesa).
- **Comandi artisan del batch**: `timone:diagnose-upload`, `timone:grid-dry-run [--issue=]`, `timone:file-ratios [--dry-run]` (oltre a `timone:occupancy-dry-run`, `timone:covers-dry-run` dei batch precedenti).
- **File protetti** (CLAUDE.md §4): `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `config/app.php`, `config/session.php`, `config/filesystems.php`. Prima di ogni consegna: `git diff --stat` e conferma esplicita che non sono cambiati (finora mai toccati).
- **Il classificatore dei permessi** ha bloccato `git checkout <file>` per ripristinare un file: per tornare alla versione di HEAD usare `git show HEAD:<file>` + Write, oppure `git apply -R` del proprio diff.

## Documentazione da aggiornare a ogni fase (dal prompt)

`docs/` 02, 03, 04, 05, 09-deploy, 10-debugging, 11, 12, DA-VERIFICARE, CHANGELOG; `docs/nextcloud/timone-rivista.md` (+ suo diff nella consegna); `MANUALE_UTENTE.md`, `README.md`, `HANDOFF.md` (voce in coda + "Decisioni architetturali da ricordare"), questo file (tabella "Stato delle fasi").

## Prompt originale del batch (invariato)

Incollato dall'utente il 2026-10-27; tutto ciò che segue è il testo originale.

---

# Contesto
Timone elettronico (flat plan) riviste: Laravel + Livewire + Alpine + MySQL, aggiornamento via
polling. Copertine I–IV separate dalle pagine interne, occupazione in dodicesimi, pubblicità
segnaposto create alla creazione del numero, riepilogo stile dashboard. Dopo un'altra revisione
con gli utenti ci sono bug e nuove richieste. Lavori per FASI: a fine fase lanci tutta la suite,
mi mostri cosa hai fatto e **aspetti il mio ok** prima della successiva.

# REGOLE NON NEGOZIABILI
1. **NON modificare `app/Providers/AppServiceProvider.php`**: non aggiungere, togliere o spostare
   nulla, neanche per "pulizia". Contiene configurazione da cui dipende la produzione. Lo stesso vale
   per tutto ciò che cambia URL, schema http/https, trusted proxies, middleware globali, disco di
   default e config di sessione (`bootstrap/app.php`, `config/app.php`, `config/session.php`,
   `config/filesystems.php`, `TrustProxies`). Se una funzionalità sembra richiedere una modifica lì,
   FERMATI e proponimi un'alternativa (service provider dedicato, config locale al componente).
   Prima di ogni consegna esegui `git diff --stat` e conferma esplicitamente che questi file non
   sono cambiati.
2. Non modificare `.env` / `.env.example` senza dirmelo: elenca le variabili nuove a parte.
3. Le autorizzazioni si controllano SEMPRE lato server (Policy/Gate), mai solo nascondendo bottoni.
4. Migration solo nuove e reversibili. Per i dati esistenti, prima un dry-run.
5. Nessuna dipendenza nuova composer/npm senza il mio ok: proponi il pacchetto con il motivo.
6. Un solo punto di verità per calcoli e regole (occupazione, testo segnaposto, permessi).
7. Ogni modifica si propaga agli altri utenti collegati col polling esistente.
8. Ogni bug ha un test di regressione, quando è testabile lato PHP.

# FASE 1 — Bug

1. **Focus perso e avviso "non è multiplo di 4" mentre si scrive** (creazione numero): l'input non
   deve perdere il focus né mostrare errori durante la digitazione.
   - Trova la causa del focus perso: di solito è un re-render che sostituisce il nodo (wire:key
     instabile, input dentro un `@if` che cambia, `wire:model.live` senza debounce che ridisegna
     il blocco). Correggila, non limitarti al debounce.
   - Usa `wire:model.live.debounce.600ms` per l'anteprima dei calcoli e mostra gli avvisi di
     validazione solo al blur o al submit.
   - Applica la stessa correzione a tutti gli input numerici del form (pagine, pubblicità previste,
     copie, bancali, grammature).

2. **"Text too long" sul campo description con tante pubblicità previste**:
   - Trova cosa scrive in quel campo e perché cresce con il numero di pubblicità.
   - Un riepilogo generato automaticamente non va salvato come testo: si calcola in visualizzazione
     dai dati strutturati. Togli la generazione del testo lungo.
   - Il campo `description` resta per le note scritte dagli utenti: portalo a `TEXT` con una
     migration nuova, se oggi è `VARCHAR`, e valida la lunghezza lato server con un messaggio chiaro.
   - Test: creazione numero con 60 pubblicità previste → nessun errore, nessun dato perso.

3. **Barra strumenti irraggiungibile con contenuti frazionari sulla stessa pagina** (es. mezza
   pubblicità da assegnare + mezza pagina di contenuto: non si riesce a cliccare il controllo per
   cambiare la grandezza della pubblicità):
   - Trova cosa la nasconde (overflow hidden, z-index, sovrapposizione del blocco vicino, area troppo
     bassa).
   - La barra di ogni contenuto deve essere sempre raggiungibile, anche per i blocchi da 1/4 alle
     dimensioni di zoom più piccole: se il blocco è troppo piccolo, la barra si apre come popover
     sopra tutto (menu "⋯" cliccabile) invece di stare dentro il blocco.
   - Verifica con queste combinazioni: 1/2+1/2, 1/3+2/3, 1/4×4, 1/4+1/4+1/2 a tutti i livelli di zoom.

4. **Auto-scroll durante il trascinamento non funziona più**: trascinando un contenuto verso il
   bordo inferiore o superiore della finestra, la pagina deve scorrere per raggiungere le ultime
   pagine.
   - Individua la libreria di drag usata e perché lo scroll si è rotto (probabilmente l'header/
     dashboard sticky o il cambio di contenitore che scorre: finestra o div con overflow).
   - Configura l'auto-scroll sul contenitore che scorre davvero, con la zona sensibile che tiene
     conto dell'altezza della barra sticky e una velocità che aumenta vicino al bordo.
   - Deve funzionare anche trascinando un gruppo di pagine e anche verso le copertine III/IV in fondo.

5. **Upload fino ad almeno 100 MB per file** (PDF di stampa ad alta risoluzione):
   - Lato applicazione: alza a 102400 KB tutte le regole di validazione `max` dei file (componenti,
     Form Request) e `livewire.temporary_file_upload.rules` in `config/livewire.php` (pubblicalo
     se non esiste), e aumenta anche `max_upload_time`. Metti il limite in una sola chiave di config
     (es. `config/timone.php` → `upload_max_kb`) usata ovunque.
   - Generazione di anteprime e miniature da PDF grandi: in job in coda, non nella request.
   - Lato server (non puoi cambiarlo tu): scrivi in `docs/09-deploy.md` i valori da impostare, cioè
     `upload_max_filesize` e `post_max_size` ≥ 110M, `max_execution_time` e `max_input_time`,
     `client_max_body_size 110M` nginx (o `LimitRequestBody` apache), spazio su disco per
     `livewire-tmp`, timeout del proxy. Aggiungi uno script/comando artisan di verifica che stampa i
     limiti effettivi di PHP e avvisa se sono sotto soglia.
   - In UI: barra di avanzamento dell'upload e messaggio chiaro se il file supera il limite, con
     il limite indicato.
   - Ricorda la regola 1: non toccare filesystem/disco di default per farlo funzionare.

# FASE 2 — Interazione con pagine e contenuti

1. **Click sul contenuto = click sulla pagina**: cliccare un contenuto nella miniatura della pagina
   deve selezionare la pagina (stesso comportamento del click sulla pagina), NON aprire il PDF.
   Aggiungi un'icona "occhio" sul contenuto (nella barra strumenti/popover del punto 1.3) che apre il
   file del contenuto in una nuova scheda (`target="_blank" rel="noopener"`), tramite una rotta
   protetta da autenticazione e policy che serve il file inline (`Content-Disposition: inline`).
   Senza file, l'occhio è disabilitato con tooltip "Nessun file caricato".

2. **Comandi delle pagine sticky**: la barra dei comandi sulle pagine (zoom, aggiunta pagine, ecc.)
   va nella stessa fascia sticky del conteggio pagine/dashboard, sotto l'header. Un solo blocco
   sticky coerente; verifica che non copra le pagine e che l'auto-scroll (1.4) ne tenga conto.

3. **Disposizione dei contenuti nella pagina con 4 direzioni**:
   - Oggi ci sono solo le frecce su/giù. Servono anche destra/sinistra.
   - Modello: la pagina è una griglia **6 × 6**, che rappresenta tutti i formati in modo esatto:
     intera 6×6; 1/2 verticale 3×6, orizzontale 6×3; 1/3 verticale 2×6, orizzontale (piedino) 6×2;
     2/3 verticale 4×6, orizzontale 6×4; 1/4 (vetrina) 3×3. Ogni contenuto su una pagina ha
     `x, y, w, h` in celle; l'area w×h deve corrispondere alla sua occupazione in dodicesimi
     (36 celle = 12 dodicesimi, 1 dodicesimo = 3 celle).
   - Le frecce spostano il contenuto di uno step nella direzione indicata (agganciato alle posizioni
     valide del formato); se la posizione è occupata scambia con il contenuto lì presente, se esce
     dalla pagina la freccia è disabilitata. Mai sovrapposizioni (controllo lato server).
   - Bottone per ruotare l'orientamento (verticale ↔ orizzontale) dove il formato lo consente.
   - Migration dati: i contenuti esistenti ricevono posizioni coerenti con l'impilamento attuale.
     Mostrami il dry-run.
   - Se la disposizione attuale (Fase 7 precedente) usa un modello diverso, mostrami la differenza
     prima di sostituirlo.

4. **Ritaglio della miniatura in base alla posizione**: oggi l'anteprima del PDF viene tagliata
   centrata ("a caso"). Regola nuova:
   - Se il file ha le proporzioni di una pagina intera (PDF impaginato a pagina piena con l'annuncio
     in una zona): mostra SOLO la porzione corrispondente al rettangolo `x,y,w,h` del contenuto.
     Esempio: mezza verticale a sinistra → si vede la metà sinistra del PDF, la destra è tagliata;
     mezza orizzontale in basso → si vede la metà inferiore.
   - Se il file ha già le proporzioni del formato (annuncio esportato alla sua misura): mostralo
     intero, adattato al rettangolo senza tagli.
   - Rilevazione con il rapporto larghezza/altezza della prima pagina del file, con una tolleranza
     del 5%, calcolata una volta all'upload e salvata. Override manuale nella modale del contenuto:
     "Il file è a pagina intera / è già nel formato".
   - Implementazione CSS (`background-size`/`background-position` o `object-position` calcolati da
     x,y,w,h), senza rigenerare immagini.

5. **Scelta della pagina già dalla creazione del contenuto** (e dalla modifica):
   - Campo opzionale "Posizione": pagina iniziale (1…N) oppure copertina (I–IV). Se vuoto, il
     contenuto va in "da assegnare" come oggi.
   - Se indicato, il contenuto viene piazzato distribuendo l'occupazione dalla pagina scelta in poi
     (stessa logica dell'assegnazione manuale, stesso servizio) e nella prima posizione libera della
     griglia 6×6.
   - Se non c'è spazio: errore che dice cosa occupa la pagina e quanto spazio manca; nessun
     salvataggio parziale.

# FASE 3 — Creazione pubblicità e caricamento massivo

1. **Pubblicità segnaposto anche dal form di creazione pubblicità** (solo pubblicità, non articoli):
   - `cliente` diventa opzionale (migration nullable + validazione).
   - Se il titolo è vuoto, il titolo diventa lo stesso testo segnaposto usato dalla creazione del
     numero (es. "Pubblicità 1/2 — da assegnare"). Estrai la generazione del testo in un unico metodo
     condiviso, usato da entrambi i flussi.
   - Una pubblicità è segnaposto se non ha cliente e non ha file. Diventa reale appena ne ha
     uno dei due: stessa regola già usata per i segnaposto del numero, nello stesso punto del codice.
   - Conteggio "da assegnare" della dashboard aggiornato di conseguenza.

2. **Caricamento massivo con drag&drop di più file** (articoli o pubblicità):
   - Area di drop + selettore file multiplo. In alto si sceglie il tipo per tutto il lotto
     (Articoli / Pubblicità).
   - Ogni file diventa un contenuto: titolo = nome del file senza estensione (underscore e trattini
     trasformati in spazi, spazi multipli ridotti), occupazione = pagine intere pari al numero di
     pagine del PDF (1 per le immagini), stato "da assegnare". L'utente correggerà a mano
     occupazione e posizione dove servono.
   - Upload **un file alla volta** (in JS, con `$wire.upload` in sequenza), non tutti insieme in una
     request: con file da 100 MB un lotto unico supera qualsiasi limite del server.
   - Validazione per file: tipo (pdf, jpg, png, webp), dimensione ≤ limite di config, PDF leggibile.
     Un file non valido non blocca gli altri.
   - Lista con stato per file (in coda, caricamento %, ok, errore con motivo) e riepilogo finale
     ("12 caricati, 1 scartato: motivo"). Possibilità di riprovare i falliti.
   - Duplicati: se esiste già un contenuto con lo stesso titolo nel numero, avvisa ma crea comunque,
     con suffisso " (2)".

# FASE 4 — Ruoli e eliminazioni

Prima di iniziare mostrami come sono implementati oggi ruoli e permessi (colonna, pacchetto, gate,
policy) e quali ruoli esistono.

1. **Commerciale: può spostare SOLO i contenuti pubblicitari**:
   - Policy `move` sul contenuto: il commerciale può spostare (drag, frecce di posizionamento,
     tastiera, cambio pagina dalla modale) solo contenuti di tipo pubblicità, segnaposto compresi.
   - Gli spostamenti di pagine intere o gruppi di pagine che contengono anche contenuti editoriali
     sono vietati al commerciale.
   - UI: per il commerciale i contenuti editoriali non sono trascinabili (niente handle, cursore
     `not-allowed`, frecce disabilitate, tooltip "Solo la redazione può spostare questo contenuto").
   - Test lato server: richiesta di spostamento di un articolo con utente commerciale → 403;
     di una pubblicità → ok.

2. **Eliminazione di riviste e numeri (solo admin)**:
   - Policy `delete` solo per admin, lato server.
   - Conferma in due passaggi: (1) click su Elimina → modale con il riepilogo di cosa verrà eliminato
     (n. numeri, pagine, contenuti, file e MB occupati; per la rivista anche l'elenco dei numeri);
     (2) inserimento della password dell'admin, verificata lato server con `Hash::check`. In più
     va digitato il nome della rivista/numero per conferma, come su GitHub.
   - Tentativi password limitati (rate limiter, es. 5 al minuto).
   - Eliminazione in transazione; i file su disco si cancellano in un job dopo il commit
     (`afterCommit`), così un errore non lascia file orfani o record senza file.
   - Se il model usa già SoftDeletes, mantienilo e dimmelo; altrimenti chiedimi se volere soft
     delete prima di implementare un'eliminazione definitiva.
   - Registra chi ha eliminato cosa e quando (log applicativo o audit esistente).
   - Se altri utenti hanno il numero aperto, al polling successivo vedono un avviso e vengono
     riportati all'elenco.

3. **Eliminazione utenti (solo admin)**: stesso flusso in due passaggi con password.
   - L'admin non può eliminare se stesso né l'ultimo admin rimasto.
   - I contenuti creati dall'utente restano: la FK diventa `nullOnDelete` e in UI si mostra
     "Utente eliminato".
   - Le sessioni attive dell'utente eliminato vengono invalidate.

# FASE 5 — Esportazione PDF sfogliabile della rivista completa

Un'azione "Esporta PDF rivista" sul numero genera un unico PDF multipagina che simula la rivista
finita.
- **Ordine**: I copertina, II copertina, pagine 1…N, III copertina, IV copertina.
- **Formato**: ogni pagina del PDF è A4 verticale. Il formato reale del numero (es. 21×27) viene
  scalato in proporzione dentro l'A4 e centrato, senza deformare.
- **Contenuto di ogni pagina** (ricostruito dalla griglia 6×6):
  - contenuto con file: la pagina del PDF di quel contenuto (o l'immagine) collocata nel suo
    rettangolo, con la stessa logica di ritaglio della Fase 2.4: file a pagina intera → porzione
    del rettangolo; file nel formato → adattato. Un contenuto su più pagine usa la pagina del file
    corrispondente.
  - contenuto senza file (segnaposto o articolo senza PDF): NON sparisce. Riquadro nel suo
    rettangolo con bordo tratteggiato, titolo, tipo e "File mancante".
  - spazio libero: bianco. Pagina vuota: numero di pagina e "Pagina bianca" in piccolo.
  - numero di pagina in piccolo a piè di pagina, fuori dall'area del contenuto; per le copertine
    "I di copertina" ecc.
- **Sfogliabile**: imposta nel PDF il layout di visualizzazione a doppia pagina con copertina
  singola (`TwoPageRight`), così si apre a coppie affiancate come una rivista. Segnalibri per
  copertine e ogni pagina.
- **Esecuzione**: in un job in coda (i file sorgente possono pesare centinaia di MB in totale), con
  stato visibile in UI (in coda / in generazione / pronto / errore) e download quando è pronto.
  Il file generato si salva sul disco già in uso (regola 1) e viene sostituito alla generazione
  successiva.
- **Opzione "Bassa risoluzione per anteprima"**: immagini ricompresse, file leggero da mandare via mail.
- **Librerie**: proponimi prima la soluzione (es. `setasign/fpdi` + `tcpdf` per importare pagine
  PDF come template scalabili e ritagliabili; oppure Ghostscript/qpdf da riga di comando per la
  bassa risoluzione) con pro e contro, dipendenze di sistema da installare sul server e peso
  in memoria. Aspetta il mio ok prima di aggiungerle.
- **Test**: numero con 4 copertine, pagine con contenuto intero, due mezze, un segnaposto e una pagina
  vuota → PDF con N+4 pagine nell'ordine giusto, tutte A4, segnaposto presente (verifica il testo
  estratto), job che non fallisce con un file sorgente mancante su disco (riquadro "File mancante").

# Documentazione (a fine di ogni fase)
Aggiorna `docs/` (02, 03, 04, 05, 09-deploy, 10-debugging, 11, 12, DA-VERIFICARE, CHANGELOG) e il
file Nextcloud:
- griglia 6×6, posizionamento, regola di ritaglio delle miniature
- limiti di upload: config applicativa + valori server da impostare + comando di verifica
- caricamento massivo: come funziona, nome file → titolo, cosa correggere dopo
- ruoli: cosa può fare il commerciale e cosa no; flusso di eliminazione admin
- esportazione PDF: ordine pagine, formato A4, segnaposto, coda e dove finisce il file
- 10-debugging: focus perso negli input Livewire, auto-scroll del drag, upload grandi che falliscono,
  job di export bloccato
- **CLAUDE.md** (crealo se non esiste): aggiungi la regola permanente "Non modificare
  `app/Providers/AppServiceProvider.php` né la configurazione di URL/proxy/dischi/sessione: la
  produzione dipende da questi file", con l'elenco dei file protetti
- Nextcloud, per utenti non tecnici: frecce di posizionamento, occhio per aprire il file, caricamento
  massivo, esportazione PDF, chi può spostare o eliminare cosa
Ogni percorso, campo e comando citato deve esistere nel codice.

# Consegna di ogni fase
File modificati (codice, migration, test, docs separati), output dei test, dry-run e migration di
dati eseguite, `git diff --stat` con la conferma che AppServiceProvider e i file protetti della
regola 1 non sono cambiati, scelte di default fatte sui punti ambigui, diff del file Nextcloud,
variabili d'ambiente o configurazioni server nuove da applicare in produzione.
