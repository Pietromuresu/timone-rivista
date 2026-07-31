 # Prompt per Claude Code — Bugfix e nuove funzionalità Timone Elettronico

Sei incaricato di intervenire sul progetto esistente "Timone Elettronico" (Laravel + Livewire + Alpine.js + Tailwind + MySQL, con Reverb per il realtime). Prima di scrivere codice, esplora la struttura attuale del progetto (componenti Livewire della griglia timone, modelli `Page`, `Content`, `Issue`, `Publication`, migrazioni esistenti) per capire dove agganciare ogni modifica. Non riscrivere da zero componenti funzionanti: individua il bug o il punto di estensione preciso e intervieni in modo mirato.

Lavora per fasi, nell'ordine indicato sotto. Al termine di ogni fase mostrami il risultato (diff o riepilogo dei file toccati) prima di passare alla successiva. Aggiorna la documentazione di progetto (README/MANUALE_UTENTE/CHANGELOG, se già presenti) contestualmente al codice, non in un secondo momento.

---

## FASE 1 — Bugfix critici

### 1.1 Drag & drop delle singole pagine in modalità doppia pagina
**Bug**: quando la griglia è in modalità "doppia pagina" (visualizzazione affiancata sinistra/destra), non è possibile trascinare la singola pagina all'interno della coppia: il drag sembra agganciato solo alla coppia intera o non risponde.

**Comportamento atteso**: anche in modalità doppia pagina, ogni pagina singola deve restare un elemento trascinabile indipendente. L'utente deve poter:
- trascinare una singola pagina fuori da una coppia e inserirla altrove;
- trascinare una pagina in una posizione che spacca una coppia esistente, con ricalcolo automatico di lato (sinistra/destra) e posizione per tutte le pagine coinvolte.

Verifica l'implementazione Sortable.js: probabilmente il contenitore "gruppo doppia pagina" è stato reso l'elemento sortable invece delle singole card pagina al suo interno (contenitori annidati mal configurati, o `group`/`handle` di SortableJS puntato al div sbagliato). Correggi la struttura DOM/opzioni SortableJS in modo che l'unità trascinabile sia sempre la singola pagina, indipendentemente dalla modalità di visualizzazione.

### 1.2 Errore in scrittura sul campo numero totale di pagine
**Bug**: quando si modifica il numero totale di pagine dell'edizione, la scrittura sull'input genera un errore (probabilmente un errore Livewire ad ogni keystroke per via di `wire:model.live` che valida/ricalcola la griglia ad ogni carattere digitato, anche con valore intermedio non valido o vuoto).

**Comportamento atteso**: l'utente deve poter digitare liberamente nel campo (anche cancellare tutto e riscrivere) senza errori intermedi. La validazione e il ricalcolo effettivo della griglia pagine devono avvenire solo al blur/submit, non ad ogni carattere.

Correggi:
- passa da `wire:model.live` a `wire:model.blur` (o gestione esplicita con debounce) sul campo numero pagine;
- aggiungi validazione difensiva lato server (valore vuoto, non numerico, negativo, non gestito) che non lanci eccezioni ma restituisca un messaggio di errore controllato;
- mantieni la regola esistente sul multiplo di quattro come warning non bloccante, non come eccezione.

Scrivi un test Livewire che copra: digitazione progressiva, cancellazione totale del campo, invio di valore non numerico, invio di valore valido con pagine già popolate di contenuto (le pagine in eccesso non devono cancellare contenuto assegnato: vanno segnalate come pagine da riorganizzare, non eliminate in automatico).

---

## FASE 2 — Associazione obbligatoria PDF per pagina, con anteprima a pagine multiple

Ogni pagina del timone deve avere **sempre e solo** un PDF associato come contenuto (niente più upload generico di JPG per il contenuto pagina: il PDF è l'unico formato accettato per il materiale definitivo di pagina). Specifiche:

### 2.1 Upload e associazione
- Upload drag&drop o selezione da filesystem, validazione MIME reale (non solo estensione) e dimensione massima.
- Il PDF caricato può contenere **una o più pagine interne**. In base al numero di pagine del PDF:
  - se il PDF ha 1 pagina, viene associato solo alla pagina corrente;
  - se il PDF ha N pagine (N>1), il sistema deve occupare automaticamente le N pagine successive del timone a partire da quella corrente.
- **Non cancellare mai contenuto già presente** nelle pagine successive coinvolte. Se una o più di quelle pagine hanno già un PDF/contenuto assegnato:
  - blocca il posizionamento automatico su quelle pagine specifiche;
  - mostra un riepilogo chiaro all'utente ("Il PDF caricato occupa N pagine. Le pagine X e Y risultano già occupate da altro contenuto.") con scelte esplicite: spostare il contenuto esistente altrove, scegliere manualmente dove posizionare le pagine in conflitto, o annullare il caricamento;
  - non procedere con nessuna scrittura distruttiva finché l'utente non conferma una delle opzioni.
- Ogni pagina del timone deve sempre avere un PDF associato per poter essere considerata "pronta per la stampa": aggiungi questo controllo alla lista dei controlli automatici già esistenti (§10 della spec originale, "file definitivo mancante in prossimità della scadenza" va esteso a "PDF mancante" come stato bloccante per l'approvazione finale, non solo come warning temporale).

### 2.2 Miniatura sempre con anteprima reale del PDF, pagine separate
- La miniatura mostrata nella card della griglia **non è un'icona generica**: deve essere il render reale della pagina PDF corrispondente (già previsto nella spec via ImageMagick/Ghostscript, generazione asincrona in coda).
- Per PDF multipagina, genera una miniatura distinta per ciascuna pagina interna del PDF, e associa ciascuna miniatura alla rispettiva card pagina del timone (pagina 1 del PDF → miniatura sulla card timone corrispondente, pagina 2 del PDF → miniatura sulla card successiva, ecc.). Non generare un'unica miniatura collettiva.
- Mantieni l'indicatore "generazione anteprima in corso..." già previsto finché il job asincrono non ha completato tutte le pagine del PDF.
- Click sulla miniatura apre il PDF a schermo intero con PDF.js già previsto, posizionato sulla pagina interna corretta.

### 2.3 Validazione formato rispetto al listino pubblicitario
Ti allego (vedi immagine "LISTINO ADV") le misure ufficiali dei formati pubblicitari, in mm larghezza×altezza, file stampa finale in PDF alta risoluzione **+3mm di abbondanza per lato**:

| Formato | Misure (b×h mm) | Note |
|---|---|---|
| Battente copertina | 420×270 | doppia pagina copertina |
| Doppia pagina | 210×270 + 210×270 | due pagine affiancate |
| Copertina (2ª/3ª/4ª) | 152×194 | |
| 1 pagina intera | 210×270 | |
| 1/2 pagina orizzontale | 210×137 | |
| 1/2 pagina verticale | 103×270 | |
| Piedino | 210×88 | |
| 2/3 di pagina | 148×270 | |
| 1/3 di pagina | 58×270 | |
| 1/4 di pagina | 103×137 | |
| 1ª romana | — | usa misura "1 pagina intera" |
| Controsommario | — | usa misura "1 pagina intera" |
| Elenco inserzionisti | — | usa misura "1 pagina intera" |
| Controeditoriale | — | usa misura "1 pagina intera" |
| Pubbliredazionale | — | usa misura "1 pagina intera" |

Quando un PDF viene caricato su una pagina/contenuto marcato come pubblicitario con un formato specifico assegnato:
- estrai le dimensioni reali della pagina PDF (via Ghostscript/ImageMagick, già in uso per le miniature);
- confronta larghezza e altezza con quelle attese per il formato assegnato, tollerando la sovramisura di 3mm per lato di abbondanza (quindi il PDF atteso è formato nominale + 6mm su ciascuna dimensione, con una tolleranza aggiuntiva configurabile di 1-2mm per errori di export);
- se il formato non corrisponde, **non bloccare silenziosamente**: mostra un avviso ben visibile sulla card ("Formato non conforme: atteso 210×270+3mm, ricevuto ...") e marca la pagina con lo stato "da verificare", lasciando comunque all'utente la possibilità di forzare l'accettazione con conferma esplicita (per casi limite legittimi).
- Questo controllo deve essere solido e non deve mai bloccare l'intera applicazione in caso di PDF malformato o illeggibile: gestisci l'estrazione dimensioni con try/catch e fallback a stato "dimensioni non verificabili" senza eccezioni non gestite.

---

## FASE 3 — Contenuti pubblicitari prenotati in anticipo (senza materiale)

Aggiungi la possibilità di **riservare uno spazio per un cliente pubblicitario** in una specifica edizione, prima ancora che esista un contenuto/PDF reale:

- Nuova entità o stato per `Content`/riga pubblicitaria: "prenotato" (cliente, formato atteso, eventuale pagina/posizione preferita, note commerciali), distinto da "assegnato" (spazio nel timone ma senza materiale) e da "completo" (materiale PDF ricevuto e conforme).
- Un contenuto prenotato **occupa comunque il carico pubblicitario nel cruscotto percentuali** (già previsto: distingue prenotato/assegnato/materiale ricevuto — questa funzionalità va resa operativa e collegata a un vero flusso di inserimento, non solo al calcolo).
- **Regola di chiusura numero**: un'edizione non può essere marcata come "chiusa"/pronta per la stampa se esistono contenuti pubblicitari ancora in stato "prenotato" senza pagina assegnata o senza materiale, salvo eliminazione esplicita della prenotazione da parte dell'utente. Aggiungi questo controllo alla lista dei controlli automatici (§10) come blocco esplicito con messaggio chiaro ("Il cliente X ha uno spazio prenotato non ancora assegnato/completato").
- Interfaccia: una sezione dedicata "Pubblicità prenotate" nell'edizione, elencabile e modificabile indipendentemente dalla griglia timone, con possibilità di convertire una prenotazione in contenuto assegnato quando arriva il materiale.

---

## FASE 4 — Colori a colpo d'occhio per stato pagina/contenuto e per pubblicità

Sostituisci/ ­affianca le icone di stato con **colorazione della card stessa** (sfondo o bordo spesso, non solo pallino/icona piccola), in modo che lo stato sia riconoscibile senza dover leggere il testo:

- **Pagine pubblicitarie**: colore di sfondo card dedicato e distinto da qualsiasi altro stato, riconoscibile immediatamente rispetto alle pagine editoriali.
- **Stati contenuto/pagina** (mantieni coerenza con gli stati già previsti nella spec: da assegnare, assegnato, materiale ricevuto, approvato, pronto per stampa): assegna una palette fissa e distinta per ciascuno stato, applicata come colore dominante della card (non solo un'etichetta testuale piccola).
- Documenta la palette scelta in un'unica fonte (es. file di configurazione Tailwind o classe helper condivisa) così che sia coerente ovunque nell'interfaccia (griglia timone, dashboard, esportazioni PDF con legenda colori).
- Verifica leggibilità e contrasto testo/sfondo per ciascun colore scelto (accessibilità minima, non serve WCAG AAA ma testo sempre leggibile).

---

## FASE 5 — Drag & drop di selezione multipla come blocco unico

Attualmente il drag&drop sposta una pagina alla volta. Estendi il comportamento:

- L'utente deve poter selezionare più pagine contemporaneamente (click + modificatore, es. Ctrl/Cmd-click o Shift-click per range, già eventualmente presente per altre azioni: riusa lo stesso meccanismo di selezione se già esiste).
- Con più pagine selezionate, il drag&drop deve trascinare **l'intero blocco selezionato come unità unica** verso la posizione di destinazione.
- Al rilascio, il sistema deve:
  - inserire il blocco trascinato mantenendo l'ordine relativo interno delle pagine selezionate (non rimescolarle);
  - ricalcolare automaticamente la numerazione/posizione di tutte le pagine adiacenti coinvolte, sia quelle spostate sia quelle che si spostano di conseguenza per fare spazio, mantenendo sempre una sequenza continua e coerente di posizioni;
  - gestire correttamente il caso in cui il blocco selezionato non è contiguo in origine (pagine non adiacenti selezionate insieme): in tal caso, decidi in modo esplicito e documentato se il blocco viene "compattato" in un'unica sequenza contigua alla destinazione oppure se l'operazione viene rifiutata con messaggio esplicativo — non lasciare comportamento ambiguo o silenzioso.
  - propagare l'operazione via broadcasting Reverb agli altri utenti connessi come un singolo evento atomico (non N eventi separati), per evitare stati intermedi inconsistenti visti da altri utenti in tempo reale.
- Aggiungi test che coprano: selezione contigua trascinata, selezione non contigua trascinata, spostamento che supera i limiti dell'edizione (fine pagine), spostamento che coinvolge pagine bloccate (lucchetto) — in questo ultimo caso l'operazione deve essere rifiutata nel suo complesso con messaggio chiaro, non parzialmente eseguita.

---

## Requisiti trasversali (validi per tutte le fasi)

- Nessuna eccezione non gestita deve mai arrivare a rompere la UI: ogni operazione di scrittura (upload, riordino, cambio pagine, validazione formato) deve avere gestione esplicita degli errori con messaggio comprensibile all'utente editoriale, non uno stack trace.
- Ogni modifica strutturale (riordino, upload PDF multipagina, prenotazione pubblicitaria) deve generare un evento nello storico/cronologia già previsto (§9 della spec originale), con utente, data/ora e valore precedente/nuovo.
- Scrivi test Pest/PHPUnit per ciascun bugfix e ciascuna nuova funzionalità, con particolare attenzione ai due punti storicamente più delicati del progetto: riordino pagine e calcolo percentuali/carico pubblicitario.
- Mantieni gli stessi principi già stabiliti per il progetto: logica estratta in classi `App\Support`, niente accumulo di business logic nei componenti Livewire, pragmatismo senza over-engineering.
- Alla fine di ogni fase, aggiorna `MANUALE_UTENTE.md` (se presente) con la spiegazione in linguaggio semplice della nuova funzionalità per il team editoriale.

Procedi fase per fase, mostrandomi il risultato di ciascuna fase prima di passare alla successiva.
