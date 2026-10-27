# Manuale utente — Timone Elettronico

Guida per chi lavora sul timone ogni giorno: redattori, commerciali, grafici. Organizzata per "voglio fare X → ecco come": cerca la sezione che ti serve, non serve leggerlo tutto di fila.

**Dove trovare questo manuale**: è consultabile anche **dentro l'applicazione**, senza uscire dal browser — voce **"📖 Manuale"** nel menu in alto (indirizzo `/manuale`; il vecchio `/documentazione` ti porta comunque lì), visibile a qualunque utente loggato (nessun ruolo specifico richiesto — è materiale di aiuto, non un'azione che modifica dati). A sinistra trovi un **indice cliccabile** con tutte le sezioni: un clic ti porta direttamente alla sezione che ti serve. Se al posto del manuale compare un avviso giallo "Il manuale utente non è disponibile", avvisa chi gestisce l'installazione.

Per avviare l'applicazione la prima volta vedi invece [README.md](README.md).

---

## 1. Prima di iniziare

La struttura è sempre: **rivista → numero → timone**.

- Una **rivista** (es. "Motori Elettrici") è la testata.
- Un **numero** (es. "Novembre 2026") è una singola uscita di quella rivista, con un numero di pagine deciso.
- Il **timone** è la griglia delle pagine di un numero specifico: qui si fa il lavoro vero e proprio.

Accedi da <{{APP_URL}}> (l'indirizzo della tua installazione: nella pagina "📖 Manuale" dell'applicazione qui compare quello vero) con l'email e la password che ti sono state assegnate. Se non hai ancora un account, chiedi a un Admin di crearlo (vedi [§13](#13-gestire-gli-utenti-solo-admin)).

Quello che vedi dipende dal tuo **ruolo** — riepilogo completo in [§14](#14-cosa-può-fare-ciascun-ruolo).

---

## 2. Scegliere rivista e numero

Dopo il login vedi **"Le tue riviste"**: solo quelle a cui hai accesso (un Admin le vede tutte). Ogni riquadro mostra il numero attualmente in lavorazione, se c'è.

Cliccando su una rivista vedi:
- **Numeri attivi** (bozza o in lavorazione);
- **Archivio** (numeri chiusi), in fondo alla pagina.

Clicca su un numero per aprirne il timone.

---

## 3. Leggere la griglia del timone

La schermata del timone ha tre modalità, selezionabili in alto a destra:

- **Griglia** — una card per pagina, la vista principale per lavorare.
- **Doppia pagina** — le pagine affiancate come si vedrebbero sfogliando la rivista stampata (copertina da sola, poi coppie). La pagina 1 è sempre allineata a destra (nessuna pagina sinistra la precede) e, se il numero ha un totale pagine pari, l'ultima resta da sola allineata a sinistra — coerente con la convenzione editoriale di stampa. Puoi assegnare/rimuovere contenuti, cambiare lo stato pagina e trascinare per riordinare esattamente come in Griglia: ogni pagina resta trascinabile singolarmente anche dentro una coppia, sia per spostarla fuori sia per inserirne un'altra al suo posto (la coppia si ricompone da sola in base al nuovo ordine).
- **Lista** — una riga per pagina, utile per scorrere velocemente un numero lungo. Ogni riga mostra anche un'etichetta **DX**/**SX**: pagina destra o sinistra secondo la convenzione di stampa (pagina 1 sempre a destra, poi alternanza) — la stessa usata per affiancare le pagine in Doppia pagina.

**Dimensione delle pagine (S / M / L)**: in Griglia e Doppia pagina, accanto ai bottoni delle modalità, tre bottoni **S**, **M**, **L** ingrandiscono o rimpiccioliscono le pagine (M è il valore iniziale). La scelta resta memorizzata **su questo browser** per te (non cambia la vista dei colleghi) e la ritrovi alla prossima apertura. Le pagine hanno le **proporzioni del formato reale del numero** (base × altezza impostate nel pannello Peso, [§9ter](#9ter-peso-della-rivista)); se il formato non è ancora impostato si usa una proporzione standard 3:4.

**La barra in alto** (logo, Riviste, Utenti, Manuale, il tuo nome) resta sempre visibile mentre scorri il timone: non serve tornare in cima per cambiare schermata.

**Ogni card/riga mostra:**

| Elemento | Significato |
|---|---|
| Colore di sfondo | Tipo pagina: blu = editoriale, ambra = pubblicità, viola = mista (metà e metà), grigio = bianca (ancora vuota) — pubblicità sempre riconoscibile a colpo d'occhio |
| Bordo colorato spesso (griglia/doppia) o striscia colorata a sinistra (lista) | Stato della pagina: grigio = da assegnare, azzurro = assegnata, giallo = in bozza, arancione = revisionata, verde = ok stampa — riconoscibile senza dover leggere l'etichetta |
| Numero con ⠿ | Numero della pagina interna, da 1 in su (le copertine sono a parte, vedi [§9bis](#9bis-le-copertine-i-ii-iii-iv-e-la-bandella)). È anche la "maniglia" per trascinare la pagina |
| 📘 «I di copertina» su sfondo blu | È una copertina, non una pagina interna: sta nella fascia azzurra «Copertine» in cima al timone, prima della pagina 1 ([§9bis](#9bis-le-copertine-i-ii-iii-iv-e-la-bandella)) |
| Etichetta colorata (es. "In bozza") | Stato della pagina, stessa palette del bordo — cliccabile per cambiarlo, vedi [§6](#6-cambiare-lo-stato-di-una-pagina) |
| 📄 / 📢 | Contenuto assegnato: 📄 articolo, 📢 pubblicità, seguito dal titolo/cliente |
| Pallino verde con iniziali, animato | Un collega ha in questo momento il focus su quella pagina (sta cambiando qualcosa) |
| 📤 | Nessun PDF caricato ancora — clic per caricarne uno |
| ⏳ PDF | PDF caricato, anteprima in elaborazione |
| Miniatura a piena card (griglia/doppia) | PDF pronto — riempie il centro della card, è l'elemento visivo principale; clic per aprirlo. Sotto, in sovrimpressione, restano titolo/percentuale/pulsanti del contenuto assegnato, invariati. In Lista resta una piccola icona, come prima. |
| ⚠️ PDF (rosso) | Generazione dell'anteprima fallita — clic per ricaricare il file |
| 🕐 | Apre lo storico di tutti i PDF caricati su questa pagina (compare solo se ce n'è almeno uno) |
| 📝 | La pagina ha una nota (passa il mouse per leggerla) |
| 🔓 / 🔒 | Blocca/sblocca la pagina — vedi [§4bis](#4bis-bloccare-una-pagina) |
| Casella di spunta (solo con "☑️ Selezione multipla" attivo) | Seleziona la pagina per un'azione di massa — vedi [§6bis](#6bis-selezione-multipla-e-azioni-di-massa) |

Tutto si aggiorna da solo in tempo reale se un collega è collegato sullo stesso numero — non serve mai ricaricare la pagina. Quando arriva un cambiamento fatto da qualcun altro, compare per pochi secondi un piccolo avviso in basso a destra (es. "Una pagina è stata spostata da un altro utente.") — un promemoria discreto che qualcosa è cambiato, non un blocco: la griglia si è già aggiornata da sola. Se per qualche minuto non arrivano aggiornamenti (rete che taglia fuori i websocket), compare invece un avviso giallo permanente: la pagina continua comunque a controllare da sola se c'è qualcosa di nuovo (circa una volta al secondo) e si aggiorna nello stesso identico modo, avviso "qualcosa è cambiato" compreso — l'unica differenza percepibile è un ritardo massimo di un secondo invece dell'istante.

**Per stampare o condividere il timone**: bottone **"📄 Esporta PDF"** nella barra in alto — un foglio con tutte le pagine del numero, colori e stati come nell'interfaccia, con una **legenda colori** in cima (tipo pagina e stato) per chi lo guarda senza avere sotto mano lo schermo. Prima di scaricare puoi scegliere di includere le miniature dei PDF caricati, mostrare solo le pagine con pubblicità, o solo quelle non ancora approvate (non in stato "Revisionata"/"Ok stampa").

---

## 4. Spostare le pagine

**Con il mouse (tutte e tre le modalità):** trascina una pagina prendendola dalla maniglia ⠿ (il numero di posizione) e rilasciala nel punto desiderato. Le altre pagine si spostano di conseguenza — tutte le pagine intermedie slittano per fare spazio. Con "☑️ Selezione multipla" attiva e più pagine selezionate, trascinarne una sposta **l'intera selezione insieme** come blocco unico — vedi [§6bis](#6bis-selezione-multipla-e-azioni-di-massa). Se una pagina fa parte di un **gruppo collegato** (🔗), trascinarla sposta automaticamente tutto il gruppo insieme, anche senza selezione multipla — vedi [§6ter](#6ter-collegare-pagine-in-un-gruppo-permanente).

Mentre trascini un **gruppo collegato** o una **selezione multipla**, tutte le pagine coinvolte si "raccolgono" sotto quella che hai afferrato e si muovono insieme (hanno un bordo tratteggiato blu); l'immagine che segue il mouse le mostra tutte affiancate. Al rilascio le pagine scorrono fino alla loro posizione finale.

**Da tastiera (tutte e tre le modalità):** clicca una volta sulla card/riga per selezionarla (o raggiungila con Tab), poi:
- in **Griglia**/**Doppia pagina**: freccia sinistra/destra;
- in **Lista**: freccia su/giù.

La pagina si sposta di un posto e scorre visibilmente nella nuova posizione, restando selezionata: puoi premere la freccia più volte di fila. Se fa parte di un **gruppo collegato**, si sposta di un posto **l'intero gruppo**, qualunque pagina del gruppo tu abbia selezionato.

**Modalità scambio (bottone "🔀 Modalità scambio" in barra, funziona in tutte e tre le modalità):** attivala, poi clicca una pagina (si evidenzia con un bordo colorato) e clicca una seconda pagina — si scambiano di posto direttamente, senza far slittare nient'altro. Un secondo click sulla stessa pagina annulla la selezione senza fare nulla. Utile per scambiare due pagine lontane tra loro senza dover trascinare attraverso tutto lo schermo. Contenuti, stato e PDF caricati restano legati alla pagina, non alla posizione: si "spostano" insieme ad essa.

Se nel frattempo un collega ha spostato o scambiato delle pagine e la tua vista non si è ancora aggiornata, l'operazione viene rifiutata con un avviso invece di mescolare le posizioni in modo imprevedibile — la vista si allinea subito da sola e puoi riprovare.

---

## 4bis. Bloccare una pagina

Bottone **🔓** su ogni card/riga (accanto allo stato) — clic per bloccarla, diventa **🔒**. Una pagina bloccata:

- **non può essere spostata o scambiata di posto** (drag&drop, tastiera, modalità scambio: tutti rifiutati con un avviso);
- **non può essere modificata**: niente cambio di stato, assegnazione/rimozione contenuti, modifica percentuale, caricamento di un nuovo PDF — tutti i controlli diventano di sola visualizzazione, in tutte e tre le modalità di vista;
- **non può essere eliminata** riducendo il numero totale di pagine — se provi a ridurre e una delle pagine che verrebbero tolte è bloccata, l'operazione viene rifiutata (il bottone di conferma nel pannello "⚙️ Pagine totali" si disabilita da solo se questo succede).

Per sbloccarla, clic sulla stessa icona (ora 🔒): torna 🔓 e la pagina è di nuovo modificabile normalmente. Utile per esempio per una pagina già mandata in stampa, che non deve più essere toccata per errore.

**Limite da sapere**: il blocco impedisce di toccare *direttamente* la pagina bloccata, ma non impedisce che la sua *posizione* cambi come conseguenza indiretta se un'altra pagina viene spostata attraverso di essa (il riordino fa slittare tutte le pagine intermedie). Per spostamenti "puliti" attorno a una pagina bloccata, preferisci la [modalità scambio](#4-spostare-le-pagine), che coinvolge solo le due pagine scelte esplicitamente.

---

## 5. Creare, assegnare/rimuovere contenuti e modificare la percentuale

**Per creare un nuovo articolo o una nuova pubblicità:** bottone **"+ Nuovo contenuto"** sopra la griglia. Scegli il tipo (Articolo o Pubblicità): i campi del form cambiano di conseguenza.

- **Articolo**: titolo, rubrica (opzionale), **occupazione di pagina (obbligatoria, vedi [§5bis](#5bis-occupazione-di-pagina-obbligatoria))**, autore (opzionale), stato redazionale, lunghezza prevista (opzionale).
- **Pubblicità**: titolo, rubrica, cliente, agenzia (opzionale), **formato (obbligatorio, nessun valore già scelto: è l'occupazione della pubblicità)** (l'intero listino ufficiale: pagina intera, mezza pagina orizzontale/verticale, 1/3, 1/4, 2/3, battente copertina, doppia pagina, copertina 2ª/3ª/4ª, piedino, 1ª romana, controsommario, elenco inserzionisti, controeditoriale, pubbliredazionale — mostra già accanto la percentuale di pagina che occuperebbe di default), percentuale manuale (opzionale — la usa al posto di quella del formato se la compili), **pagina preferita (opzionale)** — solo un promemoria di dove piazzarla, non assegna nulla da sola, visibile nel pannello "Pubblicità prenotate" ([§8bis](#8bis-pubblicità-prenotate-e-chiusura-del-numero)), stato commerciale, note commerciali. Per **battente copertina** e **doppia pagina** (che occupano due pagine affiancate): trascinala sulla prima delle due pagine — occupa da sola anche la successiva, ciascuna al 100% come un'inserzione a piena pagina a sé, e le due pagine vengono collegate tra loro.

**Rubrica**: accanto al menu a tendina ci sono due bottoni — **➕** crea una nuova rubrica (nome, poi "Crea": viene selezionata subito), **✏️** rinomina quella attualmente selezionata. Il nome deve essere unico all'interno della rivista.

**Allegare un file (opzionale)**: nel form di creazione, riquadro tratteggiato "Allega PDF/immagine" — **trascinaci sopra il file** dal tuo computer, oppure cliccaci per sceglierlo come sempre (vedi [§7](#7-caricare-e-vedere-un-pdf) per i dettagli). Un PDF o un'immagine (JPG/PNG/WEBP, fino a 20MB). L'anteprima si genera da sola in pochi secondi (stessa elaborazione in coda già usata per i PDF di pagina) e compare al posto dell'iconcina 📄/📢 sul chip del contenuto nel pannello "Contenuti da assegnare", senza bisogno di ricaricare la pagina. A differenza del PDF caricato su una pagina ([§7](#7-caricare-e-vedere-un-pdf)), questo file è legato al singolo contenuto: su una pagina con più contenuti (es. metà pubblicità + metà articolo), ciascuno ha il proprio allegato distinto.

Una pubblicità creata ma non ancora assegnata a nessuna pagina è considerata **"prenotata"**: resta comunque conteggiata nel carico pubblicitario del cruscotto ([§8](#8-leggere-il-cruscotto-pubblicitario)) e gestibile dal pannello dedicato ([§8bis](#8bis-pubblicità-prenotate-e-chiusura-del-numero)).

Il contenuto appena creato compare subito nel pannello **"Contenuti da assegnare"** (e, se è una pubblicità, nel pannello "📌 Pubblicità prenotate" se è aperto), pronto per essere trascinato su una pagina — senza ricaricare la pagina, sia per te sia per i colleghi collegati allo stesso numero. Lo stesso vale quando archivi, ripristini o elimini un contenuto non assegnato: sparisce o ricompare subito anche sugli schermi degli altri.

Sopra la griglia c'è appunto il pannello **"Contenuti da assegnare"**: tutti gli articoli e le pubblicità di questo numero non ancora messi su una pagina. Se l'elenco è lungo, usa il campo **"Cerca per titolo..."** accanto al titolo del pannello per restringerlo.

**Archiviare o eliminare un contenuto non assegnato**: passando il mouse su un chip nel pannello "Contenuti da assegnare" compaiono due icone — **🗄️ Archivia** (lo nasconde dal pannello, ma resta nel database: recuperabile in qualsiasi momento dalla sezione "▸ Contenuti archiviati" subito sotto, bottone **↩️** per ripristinarlo) e **🗑️ Elimina** (cancellazione **definitiva**, non recuperabile — chiede doppia conferma). Un contenuto già assegnato a una pagina non può essere né archiviato né eliminato da qui: va prima rimosso dalla pagina ("✕" sulla card).

**Per assegnare:** trascina un contenuto dal pannello sopra una pagina. Quanto spazio occupa lo decide la sua **occupazione** ([§5bis](#5bis-occupazione-di-pagina-obbligatoria)):
- un articolo da **una frazione** (1/4, 1/3, 1/2, 2/3) prende quella parte della pagina (25%, 33,3%, 50%, 66,7%);
- un articolo da **N pagine** (es. un PDF di 3 pagine) occupa **N pagine consecutive** a partire da quella su cui lo lasci, ciascuna al 100% e con la miniatura della propria pagina del PDF; le pagine vengono collegate tra loro (🔗) e si spostano insieme;
- una pubblicità prende la percentuale del suo formato (es. mezza pagina = 50%), a meno che non sia stata impostata una percentuale manuale sull'inserzione.

**Tutto o niente**: se il contenuto non ci sta (il numero finisce prima, una delle pagine è bloccata o già occupata), compare un messaggio che spiega il motivo e **nessuna pagina viene modificata** — scegli un'altra pagina di partenza o libera spazio. Un contenuto creato prima che l'occupazione diventasse obbligatoria e ancora senza occupazione non viene mai piazzato "a caso" su una pagina: il messaggio te lo segnala.

**Per cambiare la percentuale** dopo l'assegnazione: modifica il numero accanto al contenuto direttamente sulla card. Se il nuovo valore supera lo spazio disponibile sulla pagina, viene rifiutato con un messaggio.

**Per rimuovere:** clic sulla ✕ accanto al contenuto — torna nel pannello "contenuti da assegnare" (solo da quella pagina: se il contenuto è anche su altre pagine, resta lì).

**Per un contenuto che continua su più pagine** (es. un articolo che prosegue da pagina 5 a pagina 8): una volta assegnato una prima volta, clicca sull'icona **"↗"** accanto al contenuto e indica il numero della pagina aggiuntiva. Il contenuto compare anche lì, con la sua percentuale calcolata separatamente per quella pagina; un piccolo **"×N"** accanto al titolo indica su quante pagine è presente in totale.

Una pagina può ospitare più contenuti insieme (es. metà pubblicità + metà articolo = pagina "mista"), purché il totale non superi il 100%.

---

## 5bis. Occupazione di pagina (obbligatoria)

Ogni contenuto deve dichiarare **quanto spazio occupa nel timone**: è il dato che usano il posizionamento sulle pagine, il cruscotto pubblicitario e i conteggi di foliazione. Non c'è un valore già scelto: finché non lo scegli, "Crea contenuto" segnala l'errore e non crea nulla.

- **Articolo**: menu **"Occupazione di pagina"** — 1/4, 1/3, 1/2 o 2/3 di pagina, oppure 1, 2, 3… pagine intere (fino al numero di pagine del numero).
- **Pubblicità**: l'occupazione **è il formato** scelto dal listino (mezza pagina, un quarto, doppia pagina…), anch'esso obbligatorio e senza valore preimpostato.

**Con un PDF allegato**: appena scegli (o trascini) il file, il sistema ne conta le pagine e, se non hai ancora scelto l'occupazione di un articolo, te la **propone** — sotto il menu compare "📄 Il PDF allegato ha N pagine: proposta N pagine — controlla e conferma prima di creare". Se avevi già scelto un valore, non viene cambiato. Le regole:
- PDF di **più pagine** → l'occupazione deve essere esattamente quel numero di pagine (altrimenti non si saprebbe come distribuirlo);
- PDF di **una pagina** → puoi scegliere 1 pagina o una frazione (es. un articolo da mezza pagina);
- PDF **illeggibile** (danneggiato o protetto) → viene rifiutato subito con un messaggio, invece di essere trattato come "1 pagina".

---

## 6. Cambiare lo stato di una pagina

Clicca sull'etichetta colorata in alto sulla card/riga (in modalità Griglia o Lista) e scegli il nuovo stato dal menu:

1. **Da assegnare**
2. **Assegnata**
3. **In bozza**
4. **Revisionata**
5. **Ok stampa**

Non c'è un ordine obbligato: puoi passare da uno stato all'altro liberamente — **eccetto verso «Ok stampa»**, che richiede un PDF già caricato sulla pagina ([§7](#7-caricare-e-vedere-un-pdf)).

---

## 6bis. Selezione multipla e azioni di massa

Bottone **☑️ Selezione multipla** nella toolbar in alto — attivalo per far comparire una casella di spunta su ogni card/riga (funziona in tutte e tre le modalità di vista, e puoi tenerlo attivo insieme alla modalità scambio senza conflitti).

Seleziona le pagine che ti interessano (clic sulla casella, oppure **Ctrl+click — o Cmd+click su Mac — direttamente sulla card/riga**, che attiva da sola la selezione multipla se non lo è già: vedi [§6quater](#6quater-scorciatoie-da-tastiera)), poi usa la barra che compare sotto la toolbar per:

- **Cambiare stato** a tutte le pagine selezionate in un colpo solo, scegliendolo dal menu a tendina "Cambia stato in";
- **Bloccare selezionate** / **Sbloccare selezionate** — applica il blocco pagina (vedi punto 4bis) a tutta la selezione.

Comodi anche i bottoni **Seleziona tutte** / **Deseleziona tutte** per non dover cliccare pagina per pagina.

Le pagine bloccate nella selezione, e — solo quando lo stato scelto è «Ok stampa» — quelle senza un PDF caricato, vengono **ignorate** da un cambio di stato multiplo (non bloccano l'intera operazione: il resto della selezione viene comunque aggiornato) — il messaggio di esito sotto la barra ti dice sempre quante pagine sono state effettivamente cambiate e quante sono state saltate e perché, es. "3 pagine aggiornate a «Ok stampa». 1 pagina bloccata ignorata. 2 pagine senza PDF ignorate."

**Trascinare l'intera selezione insieme**: con più pagine selezionate, afferra una di quelle selezionate (dalla maniglia ⠿) e trascinala — invece di spostare solo quella, **si sposta l'intera selezione come un unico blocco**, mantenendo l'ordine con cui le pagine erano messe tra loro prima dello spostamento. Se avevi selezionato pagine non vicine tra loro (es. pagina 2 e pagina 7), dopo lo spostamento **si ritrovano vicine, una accanto all'altra**, alla posizione dove hai rilasciato: è così che funziona un "blocco unico", non resta mai una selezione sparsa. Se anche solo una delle pagine selezionate è bloccata (🔒), l'intero spostamento viene rifiutato — sblocca prima quella pagina, o togli la spunta e riprova. Se trascini una pagina che **non** fa parte della selezione corrente, si sposta solo quella, normalmente.

Disattivando **☑️ Selezione multipla** la selezione corrente viene azzerata.

## 6ter. Collegare pagine in un gruppo permanente

Se un articolo o un'inserzione occupa sempre le stesse pagine (es. pagina 5 e pagina 6 di uno stesso articolo), puoi **collegarle in un gruppo**: da quel momento, trascinandone anche una sola, tutte le altre del gruppo si spostano insieme — senza dover rifare la selezione multipla ogni volta come al punto 6bis.

**Per collegare**: attiva **☑️ Selezione multipla**, seleziona almeno 2 pagine, poi clicca **🔗 Collega selezionate** nella barra che compare. Le pagine del gruppo mostrano subito un contorno colorato e un'etichetta **🔗** (colore diverso per ogni gruppo diverso nello stesso numero, così più gruppi si distinguono a colpo d'occhio).

**Per scollegare**: clicca l'etichetta **🔗** su una qualunque pagina del gruppo — l'intero gruppo viene sciolto (non solo quella pagina: un gruppo con una sola pagina non avrebbe senso). Il gruppo torna a essere pagine indipendenti, spostabili singolarmente come sempre.

**Da sapere**:
- Una pagina già bloccata (🔒) o già in un altro gruppo non può essere collegata: sblocca/scollega prima.
- Se una pagina del gruppo è bloccata, l'intero gruppo non si può né spostare né scollegare finché non la sblocchi.
- Se selezioni per un'azione di massa (punto 6bis) solo alcune pagine di un gruppo già esistente, le altre pagine del gruppo vengono **incluse automaticamente** nello spostamento — un gruppo non si spacca mai a metà.
- Funziona in tutte e tre le modalità di vista (Griglia, Doppia pagina, Lista).

## 6quater. Scorciatoie da tastiera

Riducono i click per le operazioni più comuni su selezione e collegamento pagine — identiche in tutte e tre le modalità di vista. Non si attivano mai mentre stai scrivendo in un campo di testo (una ricerca, un modulo in una finestra) per non entrare in conflitto con le stesse combinazioni usate dal browser lì.

| Scorciatoia | Effetto |
|---|---|
| **Ctrl+click** (Cmd+click su Mac) su una pagina | Aggiunge/rimuove quella pagina dalla selezione multipla (attivandola da sola se non lo è già) — stesso effetto della casella di spunta, un click più veloce |
| **Click semplice** su una pagina, con selezione multipla già attiva | Azzera la selezione e seleziona solo quella pagina (come in un file manager) |
| **Ctrl+Shift+G**, con almeno 2 pagine selezionate | Le collega in un gruppo se non lo sono già (come [§6ter](#6ter-collegare-pagine-in-un-gruppo-permanente)); se sono già collegate tra loro, le scollega — stesso tasto per entrambe le direzioni. Se la selezione non si può collegare/scollegare (es. una pagina bloccata, o pagine che appartengono a gruppi diversi), compare lo stesso messaggio di avviso che vedresti cliccando i bottoni corrispondenti, mai un silenzio |
| **Ctrl+A** (Cmd+A su Mac) | Seleziona tutte le pagine del numero corrente (equivale al bottone "Seleziona tutte", attivando da sola la selezione multipla se serve) |
| **Esc** | Deseleziona tutte le pagine (equivale al bottone "Deseleziona tutte") |

Le pagine selezionate con Ctrl+click hanno un contorno verde acceso, ben distinto dal contorno del gruppo collegato (colorato secondo il gruppo) e da quello della modalità scambio (indaco).

---

## 7. Caricare e vedere un PDF

**Dal 2026-09-02 il PDF (o un'immagine) si carica sempre su un contenuto, mai più direttamente su una pagina.** Due modi per farlo, con lo stesso risultato:

- **Dal bottone "📎 Carica materiale"** in basso su una card/riga ancora vuota — il modo più rapido: si apre il modulo "Nuovo contenuto" già pronto a collegare quello che crei alla pagina da cui sei partito (compare un promemoria "📌" in cima al modulo). Se il modulo non è visibile subito, la pagina scorre automaticamente per mostrartelo.
- **Dal bottone "+ Nuovo contenuto"** in cima alla griglia — crea un contenuto "libero", non ancora assegnato a nessuna pagina: lo trovi poi nel pannello "Contenuti da assegnare", da lì lo trascini dove vuoi come sempre.

In entrambi i casi, compila titolo/tipo (articolo o pubblicità) e l'occupazione ([§5bis](#5bis-occupazione-di-pagina-obbligatoria)), poi allega il file nel riquadro tratteggiato "Allega PDF/immagine" — PDF o immagine (jpg/png/webp), fino a **20MB**:
- **trascina il file** dal computer sopra il riquadro: mentre ci passi sopra il riquadro diventa blu ("Rilascia per caricare"); rilascialo e parte il caricamento, con una **barra di avanzamento** in percentuale;
- oppure **clicca sul riquadro** per sceglierlo dalla solita finestra del computer.

Il risultato è identico nei due modi (stessi controlli, stessa anteprima). Un file del tipo sbagliato o troppo grande viene rifiutato subito con un messaggio rosso sotto il riquadro — per un file troppo grande il messaggio dice quanto pesa e qual è il massimo accettato (lo stesso indicato nel riquadro: è il limite **reale** del server, che può essere più basso di 20MB se il server è configurato così). Se il caricamento viene rifiutato dal server prima ancora di controllare il file, compare il messaggio «Il file non è arrivato al server…»: riprova, e se si ripete avvisa l'amministratore indicando l'ora del tentativo (nel registro del server c'è la causa); trascinando più file insieme ti viene chiesto di trascinarne uno solo (ogni contenuto ha un solo PDF o immagine). Dopo il caricamento il nome del file compare nel riquadro con una ✓; appena il contenuto è creato, il file allegato genera automaticamente la sua anteprima, esattamente come succedeva prima per il caricamento diretto — un'immagine è pronta quasi subito, un PDF passa per lo stesso esame (conteggio pagine, poi generazione miniatura) di prima.

Se hai usato "📎 Carica materiale" (ancorato a una pagina) e il file allegato è un PDF di **una sola pagina** (o un'immagine), il contenuto si assegna **subito** a quella pagina, stessa immediatezza di prima. Se sulla pagina non c'è più spazio libero, compare un avviso giallo: il contenuto resta comunque creato, solo non assegnato lì — lo trovi tra i "Contenuti da assegnare".

Una volta pronta, clicca sulla miniatura sulla card/riga per aprire il file originale in una nuova scheda del browser.

Se la generazione dell'anteprima fallisce (icona ⚠️ rossa), il file più probabilmente è danneggiato o non è un PDF valido: crea un nuovo contenuto con un file diverso (non c'è più, per un contenuto già creato, un pulsante di "ricarica" — allega di nuovo dal modulo, come per qualunque altro contenuto).

**Ogni pagina deve avere un'anteprima (dal contenuto assegnato) prima di poter essere segnata «Ok stampa»** — se provi a farlo su una pagina senza anteprima, compare un messaggio d'errore e lo stato non cambia. Le pagine già "Revisionata"/"Ok stampa" ma ancora senza anteprima compaiono anche nel pannello "⚠️ Avvisi" ([§11](#11-avvisi-automatici)).

**Pubblicità con un PDF più lungo del formato** (es. un PDF di 3 pagine per una pubblicità "Copertina (2ª/3ª/4ª)" o "Pagina intera", che occupano una pagina): non è un errore. Sotto il riquadro del file compare un riquadro azzurro «📄 Il PDF ha 3 pagine, il formato … ne occupa 1» con due scelte:
- **«Usa tutte le 3 pagine»** (proposta di partenza): la pubblicità occuperà 3 pagine intere consecutive. Per il formato copertina la dicitura è «la prima sulla copertina, le altre sulle pagine successive»: piazzala sulla pagina di copertina e occuperà anche quelle dopo.
- **«Usa solo le pagine da X a Y»**: scrivi la prima e l'ultima pagina del PDF da usare (per una sola pagina, la stessa in entrambi i campi, es. da 2 a 2). Se il numero di pagine scelte è quello del formato, la pubblicità occupa lo spazio del formato (es. mezza pagina); se è maggiore, occupa quel numero di pagine intere.

Sotto le scelte una riga riassume cosa succederà («→ La pubblicità occuperà 3 pagine intere (pagine del PDF 1–3)»). Ogni pagina del timone mostra la pagina del PDF che le corrisponde: con «da 2 a 3», la prima pagina occupata mostra la pagina 2 del PDF e la successiva la pagina 3. Se il PDF ha *meno* pagine del formato (es. una doppia pagina consegnata come pagina singola) non compare nessuna scelta.

**PDF con più pagine creato da "+ Nuovo contenuto"**: il contenuto conserva tutte le pagine del PDF (una miniatura per pagina). Quando lo trascini dal pannello "Contenuti da assegnare" su una pagina, occupa automaticamente le N pagine consecutive da lì in poi, ognuna con la miniatura della propria pagina del PDF — se non c'è spazio per tutte, non viene piazzato e un messaggio spiega perché ([§5](#5-creare-assegnarerimuovere-contenuti-e-modificare-la-percentuale)).

**PDF con più pagine interne da "📎 Carica materiale"**: se il file che alleghi (dal bottone "📎 Carica materiale" di una pagina, con un titolo/tipo già compilati) ha più di una pagina interna (es. un PDF di 3 pagine per un'inserzione su tre pagine consecutive), il sistema propone di occupare automaticamente le pagine successive del timone a partire da quella da cui sei partito — **stessa identica finestra di conferma di sempre**, nessuna modifica lì:
- se nessuna delle pagine coinvolte ha già un'anteprima, un solo bottone **"Occupa le N pagine"** conferma tutto in un colpo;
- se una o più pagine hanno già un'anteprima, puoi scegliere tra tre opzioni: **"Salta le pagine in conflitto"** (occupa solo quelle libere, lascia intatte le altre), **"Sovrascrivi le pagine in conflitto"** (il contenuto nuovo si aggiunge comunque anche lì), oppure **"↪️ Sposta le pagine esistenti dopo"** — non perde nulla: contenuto, note e materiale già presenti sulle pagine in conflitto vengono spostati su nuove pagine bianche inserite subito dopo, e il numero di pagine totali si allunga automaticamente se serve spazio;
- **"Annulla il caricamento"** interrompe tutto senza scrivere nulla (il contenuto non viene creato).

**Le pagine derivate da un PDF multi-pagina vengono collegate automaticamente tra loro** (stesso 🔗 di [§6ter](#6ter-collegare-pagine-in-un-gruppo-permanente)) — trascinandone una si spostano tutte insieme. Se serve, puoi scollegarne una o più singolarmente senza sciogliere tutto il gruppo: selezionale (☑️ Selezione multipla) e usa **"✂️ Scollega selezionate dal gruppo"** ([§6bis](#6bis-selezione-multipla-e-azioni-di-massa)) — a differenza del bottone 🔗 sul badge (che scioglie sempre l'intero gruppo), questo rimuove solo le pagine che hai selezionato, lasciando le altre ancora collegate.

Mentre le anteprime si generano una alla volta (per questo o per qualunque altro caricamento in corso sul numero), compare in cima un banner **"🖼️ N pagine in elaborazione — circa Xs rimanenti in totale"**: il conteggio e la stima sono calcolati dal sistema in tempo reale sulla base di tutte le pagine del numero ancora in lavorazione — sparisce da solo appena non resta più nessuna pagina in coda, oppure puoi chiuderlo prima con la ✕ (ricompare da solo al prossimo caricamento, se c'è ancora lavoro in corso). Se aggiorni la pagina del browser mentre delle miniature sono ancora in lavorazione, sia questo banner sia le stime sulle singole card restano visibili — riflettono sempre lo stato reale, non si perdono con un refresh.

Ogni pagina timone coinvolta mostra la miniatura della propria pagina interna corrispondente (pagina 1 del PDF → prima card, pagina 2 → la successiva, ecc.), e il link "apri file" ti porta direttamente alla pagina interna giusta, non sempre alla prima.

**Formato pubblicitario non conforme**: se il PDF allegato finisce su una pagina con una pubblicità assegnata che ha un formato specifico (es. "1 pagina intera"), il sistema confronta le dimensioni reali del file con quelle attese dal listino ufficiale (comprensive di 3mm di abbondanza per lato) — stesso controllo di sempre. Se non corrispondono, compare un avviso rosso "⚠️ formato" con le dimensioni ricevute — non blocca nulla, ma segnala il problema. Se sei sicuro che vada bene comunque (un caso limite legittimo), clicca **"accetta"** accanto all'avviso: sparisce e resta registrato chi l'ha confermato. Se le dimensioni non sono misurabili (file danneggiato) o la pagina non ha un formato pubblicitario chiaro a cui riferirsi, non compare nessun avviso.

**Pagine caricate prima del 2026-09-02**: se una pagina ha ancora un PDF caricato con il vecchio sistema (direttamente sulla pagina, prima di questa modifica), continua a funzionare esattamente come prima — anteprima visibile, storico consultabile dall'icona 🕐 — finché non verrà migrata al nuovo sistema (operazione futura, non ancora effettuata: nessun dato esistente è stato toccato da questa modifica).

---

## 8. Leggere il cruscotto pubblicitario

Pannello sempre visibile sopra la griglia. Mostra, per il numero che stai guardando:

- **Percentuale di carico pubblicitario** (numero grande) — quanta parte del numero è occupata da pubblicità, **incluse le pubblicità prenotate ma non ancora assegnate a una pagina** ([§8bis](#8bis-pubblicità-prenotate-e-chiusura-del-numero)), non solo quelle già piazzate: il testo accanto al numero mostra il dettaglio "X già assegnate + Y prenotate". Diventa rossa se supera la soglia di allarme impostata. Le pagine di copertina non entrano mai in questo calcolo (né come "pagine totali" del denominatore, né un eventuale contenuto assegnato per errore su di esse) — vedi [§9bis](#9bis-le-copertine-i-ii-iii-iv-e-la-bandella).
- **Soglia di allarme**: il campo numerico accanto al titolo del cruscotto — impostala tu (es. `30` per il 30%); lasciala vuota per disattivare l'avviso. È un'impostazione della rivista, non del singolo numero: cambiarla qui vale per tutti i numeri di quella rivista.
- **Inserzioni assegnate** vs **pubblicità non ancora assegnate**.
- **Riepilogo per formato pubblicitario** (pagina intera, mezza pagina, ecc.) e **per stato commerciale** (in trattativa, confermata, annullata) — include anche le prenotazioni.

**Esportare il report:** i link "⬇️ CSV" e "⬇️ PDF" in alto a destra del pannello scaricano lo stesso riepilogo in un file, utile per condividerlo con chi non ha accesso al sistema.

---

## 8bis. Pubblicità prenotate e chiusura del numero

Bottone **"📌 Pubblicità prenotate"** sopra la griglia: elenca **tutte** le pubblicità di questo numero, non solo quelle già su una pagina, con uno stato calcolato automaticamente:

| Stato | Significato |
|---|---|
| 🟠 **Prenotato** | Cliente, formato e note commerciali già inseriti ([§5](#5-creare-assegnarerimuovere-contenuti-e-modificare-la-percentuale), "+ Nuovo contenuto" → Pubblicità), ma non ancora messo su nessuna pagina — è quello che succede automaticamente quando crei una pubblicità e non la trascini subito su una pagina. Se al momento della creazione hai indicato una **pagina preferita**, la vedi qui accanto (es. "posizione preferita: pagina 7") — è solo un promemoria per chi compone il timone, non assegna nulla da sola. |
| 🔵 **Assegnato** | È già su una pagina, ma quella pagina non ha ancora un PDF pronto (o il PDF caricato ha un formato non conforme non ancora accettato, [§7](#7-caricare-e-vedere-un-pdf)). |
| 🟢 **Completo** | È su una pagina e il materiale è a posto — pronta per la stampa. |

**Eliminare una prenotazione**: bottone "🗑 Elimina" — disponibile solo per le pubblicità ancora in stato "Prenotato" (non ancora su una pagina). Se una pubblicità è già assegnata e vuoi toglierla, usa la ✕ direttamente sulla card/riga della pagina ([§5](#5-creare-assegnarerimuovere-contenuti-e-modificare-la-percentuale)).

**Chiudere il numero**: bottone **"🔒 Chiudi numero"** in cima al pannello. Il numero **non può essere chiuso** finché esiste almeno una pubblicità non ancora "Completo" — compare un messaggio con i nomi dei clienti coinvolti. Per sbloccare: aspetta il materiale (così passa a "Completo" da sola) oppure elimina la prenotazione se non serve più. Un numero chiuso passa nell'"Archivio" della pagina della rivista ([§2](#2-scegliere-rivista-e-numero)) e il bottone si trasforma in un'etichetta "🔒 Numero chiuso" — al momento non c'è un modo per riaprirlo da qui.

---

## 9. Modificare il numero totale di pagine

Bottone **"⚙️ Pagine interne"** in alto nella barra degli strumenti del timone. Si cambiano solo le **pagine interne**: le quattro copertine restano sempre, a parte ([§9bis](#9bis-le-copertine-i-ii-iii-iv-e-la-bandella)).

Puoi scrivere liberamente nel campo (anche cancellarlo del tutto e riscrivere): la validazione e il ricalcolo avvengono solo quando esci dal campo (tab o clic altrove), non ad ogni carattere digitato. Se il valore lasciato non è un numero valido (vuoto, non numerico, negativo), compare un avviso chiaro e nessuna operazione viene tentata — non un errore imprevisto.

**Per aumentare le pagine:** scrivi il nuovo totale, scegli se le pagine nuove vanno **in coda** o **in una posizione specifica** (le pagine successive slittano automaticamente), poi clicca **Applica**. Le nuove pagine sono bianche e vuote.

**Per ridurre le pagine:** scrivi il nuovo totale (più basso) — vengono sempre tolte le pagine in coda, mai una scelta a caso. Prima di poter confermare, il pannello ti mostra **cosa andrebbe perso**:
- quante pagine verrebbero eliminate;
- quali di queste hanno già contenuti assegnati (torneranno automaticamente nel pannello "contenuti da assegnare" — **non vengono cancellati**, restano solo senza una pagina);
- quali hanno un PDF caricato (**quello sì, va perso davvero** — non c'è modo di spostare un file su un'altra pagina).

Solo dopo aver visto questo riepilogo puoi cliccare **"Conferma rimozione definitiva"** (compare anche una richiesta di conferma del browser). Senza questo secondo clic esplicito, nessuna pagina viene toccata.

---

## 9bis. Le copertine (I, II, III, IV) e la bandella

**Le copertine non sono pagine del timone.** Ogni numero ha sempre quattro copertine — **I di copertina** (la copertina vera e propria), **II** (il suo retro, a sinistra della pagina 1), **III** (la pagina interna della quarta) e **IV di copertina** (il retro della rivista) — create da sole insieme al numero, separate dalle pagine interne. Le pagine interne partono **sempre da 1** e arrivano al numero di pagine interne del numero: nessuno deve più sommare o sottrarre le copertine.

**Dove sono nel timone**: tutte insieme in una fascia azzurra «📘 Copertine» **in cima al timone, prima della pagina 1**, in ordine di sfoglio: I, eventuale bandella, II, III, IV. Ogni copertina ha un'etichetta blu «📘 I di copertina» al posto del numero di pagina e un contorno azzurro, per distinguerla subito da una pagina interna (anche senza guardare i colori: c'è sempre la scritta).

**Cosa si può fare su una copertina**: le stesse cose di una pagina — assegnarle contenuti e pubblicità trascinandoli dal pannello "Contenuti da assegnare", caricarle materiale col bottone «📎 Carica materiale», cambiarne lo stato, bloccarla, togliere un contenuto con ✕.

**Spostare le copertine**: trascina l'etichetta blu di una copertina sopra un'altra copertina e si **scambiano** (es. la pubblicità passa dalla II alla III, con il suo stato e il suo PDF). Una copertina **non** si può trascinare tra le pagine interne, e una pagina interna non si può trascinare dentro una copertina. Le copertine non entrano nella selezione multipla, nella modalità scambio e negli spostamenti con le frecce.

**Una pubblicità di più pagine su una copertina**: trascinata sulla **II** continua sulle pagine 1, 2… (la II e la pagina 1 sono affiancate quando si apre la rivista); trascinata sulla **III** occupa le ultime pagine interne e poi la III. La **I** e la **IV** accolgono una sola pagina: per più pagine compare un messaggio che spiega dove partire.

**La bandella** (il lembo ripiegato agganciato alla copertina, detto anche battente): bottone **«+ Aggiungi bandella»** nella fascia delle copertine iniziali, **«− Togli bandella»** per toglierla (solo se è vuota e non bloccata). Con la bandella le pagine di copertina diventano **6** invece di 4, e il peso si aggiorna da solo ([§9ter](#9ter-peso-della-rivista)). Se la rivista ha 6 pagine di copertina tra le sue impostazioni del peso, i nuovi numeri nascono già con la bandella.

**Conteggi**: in cima al timone «N pagine interne · copertine 4 (X assegnate)». Il cruscotto pubblicitario ([§8](#8-leggere-il-cruscotto-pubblicitario)) e gli avvisi contano solo le pagine interne; una pubblicità sulla IV di copertina non cambia la percentuale delle pagine interne.

**Nell'esportazione PDF** (bottone "📄 Esporta PDF", [§3](#3-leggere-la-griglia-del-timone)): le copertine compaiono in una sezione «Copertine» a sé, prima delle pagine interne.

**Numeri creati prima del 2026-10-26**: sono stati convertiti automaticamente — le pagine che erano copertina sono diventate le copertine I–IV (con i loro contenuti), le pagine interne sono state rinumerate da 1, e le copertine che mancavano (spesso la II e la III) sono state create vuote.

---

## 9ter. Peso della rivista

Riquadro **"⚖️ Peso"** in cima alla pagina del numero. Mostra il **peso teorico di una copia**, il **margine d'errore** (± grammi), lo **scaglione** di spedizione e il peso della **tiratura**, con le stesse formule del foglio Excel usato finora.

- **Peso copia**: es. "510,7 g ± 31 g (481 – 541 g)". Il peso è teorico: la carta reale e il taglio non sono mai esatti, e il "±" è lo scostamento massimo nel caso peggiore. Il riquadro "ℹ️ Come è calcolato il margine" elenca le tolleranze usate.
- **Scaglione**: badge **verde** se la copia resta nello scaglione anche nel caso peggiore; **giallo** se il margine potrebbe farla passare allo scaglione successivo ("Rischio di passare allo scaglione X g"); **rosso** se supera 1000 g (fuori scaglione). Scaglioni: 250, 350, 500, 1000 g. Il limite è incluso: 500,0 g sta nello scaglione 500.
- **Interno / Copertina / Maggiorazione / Tiratura**: il dettaglio del calcolo. Le **pagine interne** sono quelle del timone (es. 208); le **pagine di copertina** sono le copertine che esistono: 4, oppure 6 se il numero ha la bandella ([§9bis](#9bis-le-copertine-i-ii-iii-iv-e-la-bandella)). Quando cambi le pagine interne ([§9](#9-modificare-il-numero-totale-di-pagine)) o aggiungi/togli la bandella, il peso si aggiorna da solo.

**Modificare i parametri di questo numero** (Admin e Redattore): bottone **"Modifica parametri"**.
- In cima c'è la sezione **"Carta"**: grammatura interno, grammatura copertina e pagine copertina. Per un numero già creato le **pagine copertina non si scrivono a mano** (campo grigio): sono le copertine esistenti, 4 o 6 con la bandella.
- **Grammatura**: scrivi quella stampata sulla carta, in g/m² (es. **80** per l'interno, **170** per la copertina), **non il peso di una pagina**: il peso a pagina lo calcola il sistema e lo mostra sotto il campo, es. "80 g/m² → 2,268 g a pagina · 208 pagine = 471,7 g". Valori sotto 30 o sopra 400 vengono rifiutati con un messaggio che spiega l'errore. Puoi usare la virgola o il punto (`80,5` o `80.5`).
- Poi formato in **millimetri** (210 × 270, non 21 × 27: valori sotto 50 vengono rifiutati perché quasi certamente in centimetri), n. copie, n. bancali e peso reale. Maggiorazione e tolleranze stanno sotto "Avanzate".
- Mentre scrivi, l'**anteprima** in fondo alla finestra mostra il nuovo peso, il margine e lo scaglione **prima di salvare**, con la differenza dal peso salvato (es. "+12,4 g rispetto al valore salvato …, passa da scaglione 500 g a 1.000 g.").
- I valori diversi da quelli della rivista hanno il badge **"modificato"**; **"Ripristina default rivista"** li riporta a quelli della rivista (poi devi comunque premere "Salva").
- **"Salva" modifica solo questo numero**, mai i valori della rivista.
- Le pagine interne devono essere un numero **pari** (un foglio ha due facciate): un numero dispari blocca il calcolo con un messaggio rosso. Se non sono un multiplo di 4 compare solo un avviso.
- Ogni modifica resta nella cronologia ([§10](#10-consultare-lo-storico-spostamenti-e-la-cronologia-generale)).

**"Parametri peso da configurare"**: mancano dei valori, di solito il formato o le grammature (succede per i numeri creati prima di questa funzione). Inseriscili con "Modifica parametri".

**"Grammatura … fuori dall'intervallo ammesso"** o **"Formato … fuori dall'intervallo ammesso"** al posto del peso: sul numero è salvato un valore con l'unità sbagliata (il peso di una pagina, es. 0,8, invece della grammatura 80; oppure il formato in centimetri, 21 invece di 210). Correggilo con "Modifica parametri".

**Peso reale dopo la stampa**:
1. Pesa una copia e inserisci il valore in "Peso reale misurato (g)".
2. Il riquadro mostra la differenza dal teorico e la **maggiorazione** che avrebbe dato il peso esatto.
3. Un Admin può salvarla con **"Copia nei default della rivista"**, dopo una conferma. Vale solo per i numeri creati da quel momento.

Il suggerimento non viene mai applicato da solo.

**Simulazione**: nel campo "Simula +/- pagine interne" scrivi per esempio `8` o `-4` per vedere peso e scaglione con quelle pagine in più o in meno. Non modifica nulla. Sotto, il riquadro indica quante pagine puoi ancora aggiungere restando nello scaglione attuale (a passi di 2 pagine).

I valori di partenza vengono dalla rivista ([§12bis](#12bis-modificare-una-rivista-redattore-di-riferimento-e-scadenza-stampa)) e si possono cambiare già quando crei il numero ([§12](#12-creare-una-rivista--un-numero-duplicare-la-struttura)), nella sezione "Carta e peso".

---

## 10. Consultare lo storico spostamenti e la cronologia generale

Bottone **"📜 Storico spostamenti"** nella barra del timone: apre un elenco degli ultimi 50 spostamenti di pagina fatti su questo numero, con chi li ha fatti, da quale posizione a quale, e quando.

Bottone **"🕓 Cronologia"**, poco sotto la barra del timone: il registro di **tutto il resto** che succede su questo numero — cambi di stato pagina, contenuti creati/assegnati/rimossi/con percentuale modificata, PDF caricati, modifiche al numero di pagine totali, cambi della soglia di allarme pubblicitario. Ogni riga mostra chi ha fatto cosa e quando. Non si aggiorna da solo mentre resta aperto: chiudilo e riaprilo per vedere le voci più recenti.

---

## 11. Avvisi automatici

Se compare un riquadro **"⚠️ Avvisi"** sotto la barra del timone, il sistema ha notato qualcosa da controllare (non un errore bloccante, solo un promemoria):
- una pagina già segnata come **Revisionata** o **Ok stampa** ma ancora **senza nessun contenuto assegnato**;
- una pagina già segnata come **Revisionata** o **Ok stampa** ma ancora **senza un PDF caricato** — a differenza degli altri avvisi, per «Ok stampa» questo caso è anche bloccante: vedi [§7](#7-caricare-e-vedere-un-pdf);
- un contenuto assegnato a **pagine non consecutive** (es. pagina 3 e pagina 9) — a volte è voluto, ma vale la pena ricontrollare.

Il riquadro compare solo quando c'è almeno un avviso da mostrare; se il timone non ha nulla da segnalare, non si vede affatto. Il carico pubblicitario oltre soglia ([§8](#8-leggere-il-cruscotto-pubblicitario)) e il numero di pagine non multiplo di 4 hanno già i loro avvisi dedicati altrove nell'interfaccia.

---

## 12. Creare una rivista / un numero, duplicare la struttura

**Nuova rivista** (solo Admin): dal riquadro "Le tue riviste", bottone "+ Nuova rivista". Nome, periodicità, colore identificativo, soglia di allarme pubblicitario (opzionale), note. (L'opzione «il battente conta nel totale pagine» non c'è più dal 2026-10-26: la bandella è una copertina a parte e non entra mai nel totale delle pagine interne.)

**Nuovo numero**: dalla pagina di una rivista, bottone "+ Nuovo numero" (visibile ad Admin e Redattori con accesso a quella rivista). Titolo, data di uscita (opzionale), **scadenza stampa** (opzionale — vedi [§12bis](#12bis-modificare-una-rivista-redattore-di-riferimento-e-scadenza-stampa)), **numero di pagine interne** (le quattro copertine I–IV si aggiungono da sole, non vanno contate; con 6 «pagine copertina» nella sezione Carta e peso il numero nasce anche con la bandella), note. Se cancelli del tutto il numero di pagine per riscriverlo, l'anteprima del peso si ferma finché non scrivi un valore valido; se provi a creare il numero con il campo vuoto compare l'errore sotto il campo (prima la pagina andava in errore).

**Duplicare la struttura da un numero precedente**: nello stesso form di creazione numero, se la rivista ha già altri numeri, trovi un menu "Duplica struttura da un numero precedente". Selezionandone uno, il numero di pagine interne si pre-compila con quello del numero scelto (resta modificabile) e — dopo la creazione — ogni pagina interna e ogni copertina eredita lo stesso tipo (editoriale/pubblicità/mista/bianca) della pagina corrispondente del numero di origine. **Non vengono copiati**: i contenuti assegnati, gli stati delle pagine, i PDF caricati — solo lo schema di che tipo di pagina è cosa, per non ripartire ogni volta da un timone completamente vuoto.

**Carta e peso**: nel form di creazione del numero, la sezione richiudibile "⚖️ Carta e peso" è già compilata con i valori di default della rivista: formato in mm, grammature, pagine di copertina, poi n. copie e n. bancali, e sotto "Avanzate" maggiorazione e tolleranze. Puoi cambiarli solo per questo numero; i campi diversi dalla rivista mostrano il badge "modificato" e "Ripristina default rivista" li riporta ai valori della rivista. Sotto ogni grammatura compare il peso a pagina e sotto la sezione un'anteprima del peso che si aggiorna mentre scrivi. Dettagli in [§9ter](#9ter-peso-della-rivista).

---

## 12bis. Modificare una rivista, redattore di riferimento e scadenza stampa

**Modificare una rivista** (solo Admin): dalla pagina della rivista, bottone "Modifica rivista". Stessi campi della creazione (nome, periodicità, colore, soglia allarme pubblicitario, note), più un campo nuovo:

- **Redattore di riferimento**: l'utente responsabile di quella rivista. Assegnarlo gli dà automaticamente accesso alla rivista (se non ce l'aveva già) — non serve anche aggiungerlo da [§13](#13-gestire-gli-utenti-solo-admin). Riceve le mail di sollecito quando la scadenza stampa di un numero si avvicina (vedi sotto). Cambiare redattore non toglie l'accesso al precedente: se serve, va rimosso a mano da §13.

**Scadenza stampa di un numero**: si imposta in creazione ([§12](#12-creare-una-rivista--un-numero-duplicare-la-struttura)) o, per un numero già esistente, con il campo **"📅 Scadenza stampa"** nella barra in alto sopra il timone. Quando la scadenza si avvicina (di default 3 giorni prima — l'esatto preavviso è un'impostazione tecnica dell'installazione, non modificabile dall'interfaccia), il **redattore di riferimento della rivista** riceve automaticamente una mail con il numero, la rivista e un link diretto al timone. La mail parte **una sola volta per scadenza**: se la sposti, può ripartire per la nuova data; se il redattore l'ha già ricevuta per la scadenza attuale, vedrai una ✉️ accanto al campo data.

Se una rivista non ha un redattore assegnato, nessuna mail viene inviata per i suoi numeri finché non gliene assegni uno.

**Carta e peso — valori di default** (nella stessa pagina "Modifica rivista"): formato, grammature, pagine di copertina, maggiorazione, peso del bancale e tolleranze usati per il peso della rivista ([§9ter](#9ter-peso-della-rivista)). **Valgono solo per i nuovi numeri**: vengono copiati su ogni numero creato dopo, mentre i numeri già esistenti mantengono i propri valori. Anche qui le grammature vanno in g/m² (es. 80 e 170) e sotto ognuna compare il peso a pagina; l'anteprima del peso usa "Pagine totali per l'anteprima (non salvate)", proposte dall'ultimo numero della rivista.

---

## 13. Gestire gli utenti (solo Admin)

Voce **"Utenti"** in alto nel menu (visibile solo se sei Admin).

- **Elenco**: tutti gli utenti, il loro ruolo, a quante riviste hanno accesso.
- **Nuovo utente**: nome, email, password, ruolo, e le riviste a cui dargli accesso (checkbox — irrilevante se il ruolo è Admin, che vede sempre tutto).
- **Modifica utente**: stessi campi; la password si può lasciare vuota per non cambiarla.

Non è ancora possibile eliminare un utente da qui.

---

## 14. Cosa può fare ciascun ruolo

| Ruolo | Vede le riviste a cui ha accesso | Sposta pagine, assegna contenuti, cambia stati, carica PDF, modifica pagine totali | Crea numeri | Crea riviste | Gestisce utenti |
|---|:---:|:---:|:---:|:---:|:---:|
| **Admin** | Tutte, sempre | ✅ (su tutte) | ✅ | ✅ | ✅ |
| **Redattore** | Solo quelle a cui è stato dato accesso | ✅ (solo sulle sue riviste) | ✅ (solo sulle sue riviste) | ❌ | ❌ |
| **Commerciale** | Solo quelle a cui è stato dato accesso | ❌ (sola lettura) | ❌ | ❌ | ❌ |
| **Sola lettura** | Solo quelle a cui è stato dato accesso | ❌ (sola lettura) | ❌ | ❌ | ❌ |

> **Nota**: al momento **Commerciale** e **Sola lettura** si comportano esattamente allo stesso modo — entrambi possono solo consultare (griglia, cruscotto pubblicitario, export CSV/PDF) senza modificare nulla. Il ruolo Commerciale non ha ancora funzioni dedicate alla gestione della pubblicità: quando (e se) verranno aggiunte, questa tabella verrà aggiornata.

Chi può solo consultare può comunque esportare il report del cruscotto pubblicitario in CSV/PDF ([§8](#8-leggere-il-cruscotto-pubblicitario)) e il PDF completo del timone ([§3](#3-leggere-la-griglia-del-timone)) — l'esportazione non richiede permessi di modifica.
