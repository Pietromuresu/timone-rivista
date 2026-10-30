# Istruzioni permanenti per questo progetto

Queste regole valgono per **ogni sessione di lavoro** su questo progetto, in aggiunta a quanto richiesto nel compito specifico assegnato di volta in volta. Introdotte il 2026-07-27 (vedi `PROMPT_DI_PARTENZA.md`, "Prompt 2").

## 1. Ogni azione va documentata, non solo il codice

Per ogni modifica — nuova funzionalità, fix, modifica di configurazione, decisione architetturale, scelta consapevole di non implementare qualcosa — aggiorna la documentazione pertinente **nella stessa sessione in cui fai la modifica**, non "alla fine" o in una sessione dedicata dopo:

- **`HANDOFF.md`** (cronologia delle fasi completate): aggiungi una voce per ogni fase/modifica completata, con lo stesso livello di dettaglio già usato finora — cosa è stato fatto, perché, quali file coinvolti, quali test aggiunti, quali limiti/scelte consapevoli. Non riscrivere la cronologia esistente, aggiungici in coda.
- **`README.md` e il manuale utente**: se la modifica cambia qualcosa che l'utente finale vede o fa (nuova schermata, nuovo comando di avvio, nuovo comportamento), aggiorna subito la parte pertinente. Se non sei sicuro se una modifica è "visibile all'utente", aggiornali comunque: è più economico un aggiornamento in più che una discrepanza silenziosa.
  - Dal 2026-10-29 il manuale è diviso in pagine: **`resources/manuale/<slug>.md`**, una per argomento. Aggiorna la pagina giusta, non `MANUALE_UTENTE.md`, che è generato: dopo ogni modifica esegui `php artisan manuale:genera` (un test fallisce se te ne dimentichi).
  - Aggiorna sempre il blocco `:::in-breve` della pagina: è la risposta breve che mostra l'assistente. Date e storico («dal 2026-…», «prima era…») vanno **solo** nella pagina `novita`; dettagli tecnici e di installazione in `note-amministratore`.
  - Assistente «Chiedi al manuale» (dal 2026-10-29): la risposta è sempre l'«In breve» della pagina, quindi ogni nuova funzionalità aggiorna l'«In breve» della pagina giusta. `resources/manuale/domande.yml` non va più toccato (le domande nuove le impara l'assistente dai voti); non toccare nemmeno `resources/manuale/domande-di-controllo.yml` per far passare un test.
  - Usa i nomi dei bottoni esattamente come nelle view. Se rinomini uno slug, aggiorna `App\Support\Manual\LegacyAnchors` e i link `manuale:slug` (i test falliscono se lo dimentichi); lo stesso vale per le piccole «?» nelle view (`<x-manual-help slug="…" anchor="…">`, slug e anchor scritti come testo fisso). Se cambia l'interfaccia, rilancia gli screenshot: `npm run build && npm run manuale:screenshot` (Chrome sull'host, node ≥ 22), poi `php artisan manuale:genera`; lo script avvisa se un numero delle annotazioni non indica più il suo elemento. `php artisan manuale:verifica` e i test del manuale girano con la suite normale.
- **Commenti nel codice**: ogni classe, metodo pubblico o blocco di logica non ovvia deve avere un commento che spiega il **perché**, non il **cosa** (il codice stesso dice cosa fa; il commento serve per le decisioni non ovvie — es. "usiamo `ShouldBroadcastNow` invece di `ShouldBroadcast` perché..."). Segui lo stile già presente nel progetto — i commenti/decisioni già documentati in `HANDOFF.md` sono un buon riferimento del livello di dettaglio atteso.
- **Bug, limiti, o scorciatoie consapevoli scoperti strada facendo**: documentali subito dove si trova già la documentazione di quel tipo di informazione in questo progetto (es. sezione "Decisioni architetturali da ricordare" di `HANDOFF.md`), anche se il compito assegnato non lo richiedeva esplicitamente. Non lasciare che restino solo nella risposta in chat: se non finiscono in un file, sono perse alla sessione successiva.

Prima di dire che un compito è concluso, fai una verifica esplicita: "ho aggiornato tutta la documentazione pertinente?" — e se la risposta è no, completala prima di chiudere, non rimandarla.

## 2. Segui i principi SOLID nel codice PHP/Laravel

Applica concretamente, non solo a parole, i cinque principi SOLID a ogni nuova classe o refactoring:

- **Single Responsibility**: ogni classe ha una sola ragione per cambiare. Continua il pattern già in uso nel progetto di estrarre la logica pura in classi dedicate sotto `App\Support` (come già fatto per il calcolo del carico pubblicitario, il riordino pagine, l'allocazione percentuali, il ridimensionamento pagine) invece di accumulare logica dentro i componenti Livewire — i componenti Livewire restano un livello sottile di orchestrazione (input utente → chiamata al servizio → aggiornamento stato), non la logica di business stessa.
- **Open/Closed**: quando aggiungi varianti di comportamento (nuovi tipi di contenuto, nuovi formati pubblicitari, nuovi stati pagina), preferisci estendere tramite enum/configurazione o nuove implementazioni di un'interfaccia esistente piuttosto che aggiungere `if`/`match` sparsi in più punti del codice che vanno modificati ogni volta che si aggiunge un caso.
- **Liskov Substitution**: se introduci gerarchie o interfacce (es. tipi di contenuto, strategie di riordino), verifica che ogni implementazione concreta rispetti davvero il contratto atteso dall'interfaccia, senza sorprese per chi la usa.
- **Interface Segregation**: se definisci interfacce/contratti tra classi, tienile piccole e specifiche per il consumatore, invece di un'unica interfaccia grande che pochi usano per intero.
- **Dependency Inversion**: le classi di alto livello (componenti Livewire, controller) dipendono da astrazioni (interfacce, o classi `App\Support` con una responsabilità chiara) e non costruiscono direttamente dipendenze concrete complesse al loro interno — favorisci l'iniezione via container Laravel dove sensato, senza però ingegnerizzare eccessivamente parti semplici del progetto (SOLID è una guida, non un obbligo di creare un'interfaccia per ogni classe: applicalo dove riduce davvero accoppiamento e migliora la testabilità, come già fatto con le classi `App\Support` esistenti).

Se in una sessione noti che del codice già scritto viola uno di questi principi in modo che comporterebbe rischi concreti (non solo teorici) — es. una classe che sta accumulando troppe responsabilità e sta diventando difficile da testare — segnalalo esplicitamente nella risposta e proponi un refactoring, invece di continuare ad aggiungere codice sopra una struttura già problematica.

## 3. Non sacrificare la velocità di consegna delle fasi per un'aderenza formale a SOLID

Queste regole si aggiungono al modo di lavorare già stabilito (procedere a fasi, mostrare il risultato prima di passare alla successiva), non lo sostituiscono. Se applicare SOLID alla lettera in un punto specifico richiederebbe una complessità sproporzionata rispetto al beneficio per un progetto di queste dimensioni, dillo esplicitamente e motiva la scelta pragmatica presa — quella motivazione va comunque documentata secondo la regola 1 sopra (tipicamente in "Decisioni architetturali da ricordare" in `HANDOFF.md`).

## 4. Non modificare `app/Providers/AppServiceProvider.php` né la configurazione di URL/proxy/dischi/sessione

La produzione dipende da questi file. Introdotta il 2026-10-27 dal batch "revisione con gli utenti 2" (`CLAUDE_PROMPTS/BATCH_REVISIONE_UTENTI_2.md`, regola 1). Non aggiungere, togliere o spostare nulla, neanche per "pulizia" o riformattazione (attenzione a Pint/formatter lanciati su intere cartelle). File protetti:

- `app/Providers/AppServiceProvider.php`
- `bootstrap/app.php` (trusted proxies, middleware globali)
- `config/app.php` (URL, schema)
- `config/session.php`
- `config/filesystems.php` (disco di default e dischi)
- la configurazione dei trusted proxies (`TrustProxies`, oggi in `bootstrap/app.php`)

Se una funzionalità sembra richiedere una modifica lì, fermati e proponi un'alternativa (service provider dedicato, config locale al componente o in `config/timone.php`). Prima di ogni consegna esegui `git diff --stat` e conferma esplicitamente che questi file non sono cambiati.

## 5. Backup: ogni nuovo dato va coperto

Introdotta il 2026-10-30 con il backup automatico (vedi `docs/OPERATIONS.md`). Ogni **nuova tabella** finisce da sola nel dump, ma se contiene dati importanti va aggiunta ai conteggi della prova di ripristino. Ogni **nuova cartella di file caricati** fuori da `storage/app` (o dentro, ma nelle cartelle escluse `livewire-tmp`/`exports`) va aggiunta al backup (`docker/backup/timone-backup.sh`, `step_files`) e alla prova di ripristino. In entrambi i casi aggiorna «Cosa viene salvato e cosa no» in `docs/OPERATIONS.md`. Le nuove variabili del backup vanno in `backup.env.example` (mai in `.env.example`), con commento e valore finto. Regole di sicurezza da non allentare: copia remota solo rest-server append-only o S3 con Object Lock (mai cartelle/NAS montati), nessun segreto o indirizzo di destinazione nei log, nei file di stato o nella pagina admin (ogni messaggio passa da `redact`), password del repository mai nel backup né in `backup.env`.

## Riferimenti rapidi

- Stato e cronologia completa del progetto: `HANDOFF.md`.
- Spec originale (per confronto, non da seguire ciecamente se il codice reale ha già preso strade diverse e documentate): `PROMPT_DI_PARTENZA.md`.
- Backup e ripristino: `docs/OPERATIONS.md`.
- Avvio Docker: `DOCKER.md`. Uso funzionale per la redazione: `MANUALE_UTENTE.md`. `README.md` è il punto d'ingresso per l'utente non tecnico.
