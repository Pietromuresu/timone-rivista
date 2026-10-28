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

**Il riepilogo e i comandi** (subito sotto la barra in alto, sempre visibili anche scorrendo, dal 2026-10-27 in un'unica fascia): sopra il colpo d'occhio sul numero, aggiornato in tempo reale; sotto i comandi del timone (scadenza stampa, ⚙️ Pagine interne, storico, modalità scambio, selezione multipla, esporta PDF, zoom S/M/L, Griglia/Doppia pagina/Lista), così non serve tornare in cima per usarli. Su un telefono la fascia scorre con la pagina, per non coprire metà dello schermo.
- **Pagine interne**: quante sono, quante **piene**, quante **parziali** e quante **bianche** (senza nessun contenuto).
- **Spazio libero**: tutto lo spazio ancora libero sulle pagine interne, sommato (es. «4 e 1/3 pagine»).
- **Pubblicità**: la percentuale di pubblicità sulle pagine interne (prenotate e segnaposto compresi), esatta perché calcolata in frazioni di pagina. Solo il numero: niente soglie né colori di allarme.
- **Da assegnare**: quante pubblicità segnaposto aspettano ancora inserzionista e PDF, con il dettaglio per formato ([§12](#12-creare-una-rivista--un-numero-duplicare-la-struttura)).
- **Copertine**: quante hanno già un contenuto e quante sono libere.
- **Peso copia** e **scaglione** postale, dal pannello Peso ([§9ter](#9ter-peso-della-rivista)); «parametri incompleti» se mancano carta o formato.

**Clic su «bianche»** o su **«Da assegnare»**: le pagine corrispondenti (copertine comprese, per i segnaposto) si evidenziano con un contorno azzurro e le altre si attenuano, in tutte e tre le viste. Le pagine non si spostano e restano utilizzabili. Un secondo clic sullo stesso bottone toglie l'evidenziazione.

**Ogni card/riga mostra:**

| Elemento | Significato |
|---|---|
| Colore di sfondo | Tipo pagina: blu = editoriale, ambra = pubblicità, viola = mista (metà e metà), grigio = bianca (ancora vuota) — pubblicità sempre riconoscibile a colpo d'occhio |
| Bordo colorato spesso (griglia/doppia) o striscia colorata a sinistra (lista) | Stato della pagina: grigio = da assegnare, azzurro = assegnata, giallo = in bozza, arancione = revisionata, verde = ok stampa — riconoscibile senza dover leggere l'etichetta |
| Numero con ⠿ | Numero della pagina interna, da 1 in su (le copertine sono a parte, vedi [§9bis](#9bis-le-copertine-i-ii-iii-iv-e-la-bandella)). È anche la "maniglia" per trascinare la pagina |
| 📘 «I di copertina» su sfondo blu | È una copertina, non una pagina interna: sta nella fascia azzurra «Copertine» in cima al timone, prima della pagina 1 ([§9bis](#9bis-le-copertine-i-ii-iii-iv-e-la-bandella)) |
| Etichetta colorata (es. "In bozza") | Stato della pagina, stessa palette del bordo — cliccabile per cambiarlo, vedi [§6](#6-cambiare-lo-stato-di-una-pagina) |
| 📄 / 📢 | Contenuto assegnato: 📄 articolo, 📢 pubblicità, seguito dal titolo/cliente e dal menu con lo spazio che occupa su quella pagina (1/4, 1/3, 1/2, … «intera») — vedi [§5](#5-creare-assegnarerimuovere-contenuti-e-modificare-lo-spazio-occupato). **Doppio click sul nome** per rinominarlo, **✏️** per modificare tutto il contenuto ([§5ter](#5ter-modificare-un-contenuto-e-rinominarlo)) |
| Etichetta «○ Vuota» / «◐ Libero: 1/3» / «● Piena» | **Quanto è piena la pagina**: l'etichetta dice quanto spazio resta. Pagina piena = etichetta scura con ●, pagina vuota = tratteggiata con ○, parziale = ambra con ◐ (simbolo e testo diversi, si distinguono anche senza i colori). «⚠ Oltre capienza» in rosso è un'anomalia da sistemare (vedi [§5bis](#5bis-occupazione-di-pagina-obbligatoria)). In Lista compare la stessa etichetta |
| Pallino verde con iniziali, animato | Un collega ha in questo momento il focus su quella pagina (sta cambiando qualcosa) |
| 📤 | Nessun PDF caricato ancora — clic per caricarne uno |
| ⏳ PDF | PDF caricato, anteprima in elaborazione |
| Blocchi dentro la card (griglia/doppia) | **Disposizione dei contenuti** (griglia 6×6 dal 2026-10-27): la pagina è divisa in 6 colonne e 6 righe e ogni contenuto occupa un rettangolo grande esattamente quanto il suo spazio: pagina intera 6×6, mezza verticale 3×6 (metà sinistra o destra), mezza orizzontale 6×3, 1/3 verticale 2×6, 1/3 orizzontale o piedino 6×2 (il piedino nasce in fondo), 2/3 4×6 o 6×4, vetrina 1/4 3×3. Un nuovo contenuto va nel primo posto libero. Lo spazio libero è tratteggiato «libero». Un **3/4 di pagina** (e 7/12, 11/12) non è un rettangolo: ha una **forma a L** che riempie lo spazio lasciato dagli altri e si adatta quando gli altri si spostano. **Cliccando su un contenuto si seleziona la pagina**, come cliccando sulla pagina (non si apre più il file). Ogni blocco mostra l'anteprima del **suo** file: se il file è un PDF a pagina intera con l'annuncio in una zona, si vede **solo la parte che corrisponde al riquadro** (una mezza verticale a sinistra mostra la metà sinistra del PDF); se il file è già della misura dell'annuncio si vede **intero**. Senza file, un fondo azzurro (articolo) o ambra (pubblicità). Il bottone **⋯** nell'angolo del blocco apre il menu: **Spazio su questa pagina**, **Posizione ← ↑ ↓ →** (sposta il contenuto di un passo: la vetrina di mezza pagina, la colonna 1/3 di una colonna; se il posto è occupato da un contenuto con cui forma un rettangolo — due vetrine affiancate, una riga 1/3 sopra una 2/3 — i due si scambiano; una freccia grigia vuol dire che si uscirebbe dalla pagina o ci si sovrapporrebbe), **⟳ Ruota** (verticale ↔ orizzontale: per una mezza pagina o un 1/3 cambia anche il formato della pubblicità, per un 2/3 o un articolo solo la forma; grigio se è quadrato o ruotato non ci sta), **👁 Apri il file** in una nuova scheda (grigio con «Nessun file caricato» se manca), **✏️ Modifica**, **↗ Estendi a un'altra pagina**, **✕ Rimuovi dalla pagina**. Le posizioni sono salvate e le vedono subito anche i colleghi; su una pagina bloccata non si cambiano. È una rappresentazione dello spazio, non l'impaginato definitivo. In Lista restano le righe di testo, con gli stessi comandi accanto al nome |
| ⚠️ PDF (rosso) | Generazione dell'anteprima fallita — clic per ricaricare il file |
| 🕐 | Apre lo storico di tutti i PDF caricati su questa pagina (compare solo se ce n'è almeno uno) |
| 📝 | La pagina ha una nota (passa il mouse per leggerla) |
| 🔓 / 🔒 | Blocca/sblocca la pagina — vedi [§4bis](#4bis-bloccare-una-pagina) |
| Casella di spunta (solo con "☑️ Selezione multipla" attivo) | Seleziona la pagina per un'azione di massa — vedi [§6bis](#6bis-selezione-multipla-e-azioni-di-massa) |

Tutto si aggiorna da solo in tempo reale se un collega è collegato sullo stesso numero — non serve mai ricaricare la pagina. Quando arriva un cambiamento fatto da qualcun altro, compare per pochi secondi un piccolo avviso in basso a destra (es. "Una pagina è stata spostata da un altro utente.") — un promemoria discreto che qualcosa è cambiato, non un blocco: la griglia si è già aggiornata da sola. Se per qualche minuto non arrivano aggiornamenti (rete che taglia fuori i websocket), compare invece un avviso giallo permanente: la pagina continua comunque a controllare da sola se c'è qualcosa di nuovo (circa una volta al secondo) e si aggiorna nello stesso identico modo, avviso "qualcosa è cambiato" compreso — l'unica differenza percepibile è un ritardo massimo di un secondo invece dell'istante.

**Per stampare o condividere il timone**: bottone **"📄 Esporta PDF"** nella barra in alto — un foglio A4 orizzontale pensato per essere appeso in redazione, come il timone cartaceo. In testa: rivista, numero, data di uscita e gli stessi numeri del riepilogo (pagine piene/parziali/bianche, spazio libero, % pubblicità, pubblicità da assegnare, copertine, peso copia). Poi le **copertine** e le **pagine interne**, tutte della stessa misura con le proporzioni del formato, **affiancate in doppie pagine** come sfogliando la rivista (la pagina 1 da sola a destra). Dentro ogni pagina la **disposizione dei contenuti** come nel timone (colonne per le pubblicità verticali, righe per gli altri, piedino in fondo, spazio libero tratteggiato) con il nome di ciascuno; accanto al numero un pallino colorato indica lo stato. In fondo una legenda e, a piè di pagina, «Foglio N di M». Prima di scaricare puoi scegliere di includere le **miniature** (ogni blocco mostra l'anteprima del suo contenuto, copertine comprese; senza miniature i blocchi sono azzurri per gli articoli e ambra per le pubblicità; le immagini WEBP potrebbero non comparire), mostrare solo le pagine con pubblicità, o solo quelle non ancora approvate (non in stato "Revisionata"/"Ok stampa"): con un filtro le pagine non sono più consecutive e vengono mostrate una per una, non in doppie pagine.

**PDF sfogliabile della rivista (dal 2026-10-28)**: riquadro **«📖 PDF sfogliabile della rivista»** sopra il timone → **«Esporta PDF rivista»**. A differenza dell'export del timone (un foglio riassuntivo), questo simula la **rivista finita**: una pagina del PDF per ogni pagina della rivista, nell'ordine di stampa — I di copertina, II di copertina, pagine interne 1…N, III e IV di copertina (la bandella, se c'è, dopo la I). Ogni pagina è un **A4 verticale** con dentro la pagina della rivista in scala, centrata e non deformata, e il numero di pagina in piccolo sotto. In ogni pagina:
- i contenuti con un file mostrano **il loro PDF (o la loro immagine) nel loro riquadro**, con la stessa regola delle anteprime del timone ([§5ter](#5ter-modificare-un-contenuto-e-rinominarlo), «Anteprima nel riquadro»): un file a pagina intera mostra solo la sua parte, un file già nel formato si vede intero. Un contenuto su più pagine usa, su ogni pagina, la pagina corrispondente del suo PDF;
- i contenuti **senza file** (segnaposto, articoli ancora senza PDF) **non spariscono**: riquadro tratteggiato con titolo, tipo e «File mancante». Lo stesso se il file non si trova più sul server;
- lo spazio libero resta bianco; una pagina vuota mostra «Pagina bianca».

Il PDF si apre **a coppie affiancate** come una rivista (la I di copertina da sola) e ha un segnalibro per ogni pagina. Si genera **in coda**: lo stato compare nel riquadro (**In coda → In generazione → Pronto**, oppure **Errore** con il motivo) e si aggiorna da solo; quando è pronto compare **«⬇ Scarica»** con il numero di pagine, il peso del file e quanti contenuti sono senza file. Puoi intanto continuare a lavorare. Con **«Bassa risoluzione per anteprima»** spuntato ottieni una versione leggera (immagini ricompresse) da mandare via mail: è un file a parte, che non sostituisce quello a qualità piena. Ogni nuova esportazione sostituisce la precedente dello stesso tipo. Può esportare chiunque veda il numero.

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
- **non può essere modificata**: niente cambio di stato, assegnazione/rimozione contenuti, modifica dello spazio occupato, caricamento di un nuovo PDF — tutti i controlli diventano di sola visualizzazione, in tutte e tre le modalità di vista;
- **non può essere eliminata** riducendo il numero totale di pagine — se provi a ridurre e una delle pagine che verrebbero tolte è bloccata, l'operazione viene rifiutata (il bottone di conferma nel pannello "⚙️ Pagine totali" si disabilita da solo se questo succede).

Per sbloccarla, clic sulla stessa icona (ora 🔒): torna 🔓 e la pagina è di nuovo modificabile normalmente. Utile per esempio per una pagina già mandata in stampa, che non deve più essere toccata per errore.

**Limite da sapere**: il blocco impedisce di toccare *direttamente* la pagina bloccata, ma non impedisce che la sua *posizione* cambi come conseguenza indiretta se un'altra pagina viene spostata attraverso di essa (il riordino fa slittare tutte le pagine intermedie). Per spostamenti "puliti" attorno a una pagina bloccata, preferisci la [modalità scambio](#4-spostare-le-pagine), che coinvolge solo le due pagine scelte esplicitamente.

---

## 5. Creare, assegnare/rimuovere contenuti e modificare lo spazio occupato

**Per creare un nuovo articolo o una nuova pubblicità:** bottone **"+ Nuovo contenuto"** sopra la griglia. Scegli il tipo (Articolo o Pubblicità): i campi del form cambiano di conseguenza.

- **Articolo**: titolo, rubrica (opzionale), **occupazione di pagina (obbligatoria, vedi [§5bis](#5bis-occupazione-di-pagina-obbligatoria))**, autore (opzionale), stato redazionale, lunghezza prevista (opzionale).
- **Pubblicità**: titolo (opzionale: se lo lasci vuoto diventa «Pubblicità 1/2 — da assegnare», con la misura dello spazio occupato), rubrica, cliente (opzionale: **senza cliente e senza file la pubblicità è un segnaposto «da assegnare»**, come quelle previste alla creazione del numero, [§12](#12-creare-una-rivista--un-numero-duplicare-la-struttura)), agenzia (opzionale), **formato (obbligatorio, nessun valore già scelto)** (l'intero listino ufficiale: pagina intera, mezza pagina orizzontale/verticale, 1/3, 1/4 (vetrina), 2/3, battente copertina, doppia pagina, copertina 2ª/3ª/4ª, piedino, 1ª romana, controsommario, elenco inserzionisti, controeditoriale, pubbliredazionale — accanto a ciascuno è indicato lo spazio che occupa, es. «(1/2 di pagina)»). Scelto il formato, i campi **"Pagine intere" + "Frazione"** ([§5bis](#5bis-occupazione-di-pagina-obbligatoria)) si compilano da soli con lo spazio del formato, e puoi **correggerli** (es. una mezza pagina che in questo numero occupa 2/3). Poi **pagina preferita (opzionale)** — solo un promemoria di dove piazzarla, non assegna nulla da sola, visibile nel pannello "Pubblicità prenotate" ([§8bis](#8bis-pubblicità-prenotate-e-chiusura-del-numero)), stato commerciale, note commerciali. Per la **doppia pagina** (2 pagine intere affiancate): trascinala sulla prima delle due pagine — occupa da sola anche la successiva, ciascuna per intero, e le due pagine vengono collegate tra loro. La **battente copertina** occupa la bandella, che nel timone è un solo riquadro ([§9bis](#9bis-le-copertine-i-ii-iii-iv-e-la-bandella)).

**Rubrica**: accanto al menu a tendina ci sono due bottoni — **➕** crea una nuova rubrica (nome, poi "Crea": viene selezionata subito), **✏️** rinomina quella attualmente selezionata. Il nome deve essere unico all'interno della rivista.

**Posizione nel timone (opzionale, dal 2026-10-27)**: nel modulo «+ Nuovo contenuto» puoi scegliere subito la pagina (1…N) o la copertina (I–IV) da cui il contenuto parte: viene messo lì appena creato, sulle pagine successive se ne occupa più d'una, nel primo posto libero della pagina. Se non c'è spazio il contenuto **non viene creato** e un messaggio dice cosa occupa già la pagina e quanto manca (es. «Sulla pagina ci sono già «Acme» (1/2) e «Beta» (1/4): manca 1/4 di pagina.»). Lasciato su «Da assegnare» il contenuto va tra i «Contenuti da assegnare», come sempre.

**Allegare un file (opzionale)**: nel form di creazione, riquadro tratteggiato "Allega PDF/immagine" — **trascinaci sopra il file** dal tuo computer, oppure cliccaci per sceglierlo come sempre (vedi [§7](#7-caricare-e-vedere-un-pdf) per i dettagli). Un PDF o un'immagine (JPG/PNG/WEBP, fino a 100 MB). L'anteprima si genera da sola in pochi secondi (stessa elaborazione in coda già usata per i PDF di pagina) e compare al posto dell'iconcina 📄/📢 sul chip del contenuto nel pannello "Contenuti da assegnare", senza bisogno di ricaricare la pagina. A differenza del PDF caricato su una pagina ([§7](#7-caricare-e-vedere-un-pdf)), questo file è legato al singolo contenuto: su una pagina con più contenuti (es. metà pubblicità + metà articolo), ciascuno ha il proprio allegato distinto.

Una pubblicità creata ma non ancora assegnata a nessuna pagina è considerata **"prenotata"**: resta comunque conteggiata nel carico pubblicitario del cruscotto ([§8](#8-leggere-il-cruscotto-pubblicitario)) e gestibile dal pannello dedicato ([§8bis](#8bis-pubblicità-prenotate-e-chiusura-del-numero)).

Il contenuto appena creato compare subito nel pannello **"Contenuti da assegnare"** (e, se è una pubblicità, nel pannello "📌 Pubblicità prenotate" se è aperto), pronto per essere trascinato su una pagina — senza ricaricare la pagina, sia per te sia per i colleghi collegati allo stesso numero. Lo stesso vale quando archivi, ripristini o elimini un contenuto non assegnato: sparisce o ricompare subito anche sugli schermi degli altri.

Sopra la griglia c'è appunto il pannello **"Contenuti da assegnare"**: tutti gli articoli e le pubblicità di questo numero non ancora messi su una pagina. Se l'elenco è lungo, usa il campo **"Cerca per titolo..."** accanto al titolo del pannello per restringerlo.

**Archiviare o eliminare un contenuto non assegnato**: passando il mouse su un chip nel pannello "Contenuti da assegnare" compaiono tre icone — **✏️ Modifica** ([§5ter](#5ter-modificare-un-contenuto-e-rinominarlo)), **🗄️ Archivia** (lo nasconde dal pannello, ma resta nel database: recuperabile in qualsiasi momento dalla sezione "▸ Contenuti archiviati" subito sotto, bottone **↩️** per ripristinarlo) e **🗑️ Elimina** (cancellazione **definitiva**, non recuperabile — chiede doppia conferma). Un contenuto già assegnato a una pagina non può essere né archiviato né eliminato da qui: va prima rimosso dalla pagina ("✕" sulla card).

**Per assegnare:** trascina un contenuto dal pannello sopra una pagina. Se la pagina che ti serve è più in basso (o più in alto), porta il contenuto verso il bordo inferiore della finestra (o subito sotto la barra di riepilogo in alto) **senza rilasciarlo**: la pagina scorre da sola, più veloce quanto più sei vicino al bordo. Vale anche trascinando pagine, gruppi di pagine e copertine. Quanto spazio occupa lo decide la sua **occupazione** ([§5bis](#5bis-occupazione-di-pagina-obbligatoria)), uguale per articoli e pubblicità:
- **meno di una pagina** (es. 1/3): prende quella parte della pagina su cui lo lasci;
- **pagine intere + una frazione** (es. 2 pagine e 1/2): occupa **pagine consecutive** a partire da quella su cui lo lasci — le pagine intere in sequenza e la frazione sull'ultima (pagina intera, pagina intera, 1/2). Con un PDF, ogni pagina mostra la miniatura della propria pagina del PDF; le pagine vengono collegate tra loro (🔗) e si spostano insieme.

**Tutto o niente**: se il contenuto non ci sta (il numero finisce prima, una delle pagine è bloccata o non ha abbastanza spazio), compare un messaggio che spiega il motivo — per lo spazio dice anche **quanto ne resta** (es. «richiede 1/2 di pagina, ma resta libero solo 1/3 di pagina») — e **nessuna pagina viene modificata** — scegli un'altra pagina di partenza o libera spazio. Un contenuto creato prima che l'occupazione diventasse obbligatoria e ancora senza occupazione non viene mai piazzato "a caso" su una pagina: il messaggio te lo segnala.

**Per cambiare lo spazio su una pagina** dopo l'assegnazione: usa il menu accanto al contenuto direttamente sulla card (da 1/12 a «intera»). Cambia solo quella pagina: la distribuzione automatica (es. intera, intera, 1/2) è un punto di partenza, poi ogni pagina si regola a mano. Se il nuovo valore non ci sta, viene rifiutato con un messaggio che dice quanto spazio resta.

**Per rimuovere:** clic sulla ✕ accanto al contenuto — torna nel pannello "contenuti da assegnare" (solo da quella pagina: se il contenuto è anche su altre pagine, resta lì).

**Per un contenuto che continua su più pagine** (es. un articolo che prosegue da pagina 5 a pagina 8): una volta assegnato una prima volta, clicca sull'icona **"↗"** accanto al contenuto e indica il numero della pagina aggiuntiva. Il contenuto compare anche lì, con una pagina intera (o tutto il contenuto, se occupa meno di una pagina), modificabile dal menu di quella pagina; un piccolo **"×N"** accanto al titolo indica su quante pagine è presente in totale.

Una pagina può ospitare più contenuti insieme (es. metà pubblicità + metà articolo = pagina "mista"), purché il totale non superi la pagina intera: per esempio 1/2 + 1/4 + 1/4 riempie esattamente la pagina («● Piena»), 1/2 + 1/3 + 1/4 non ci sta.

---

## 5bis. Occupazione di pagina (obbligatoria)

Ogni contenuto deve dichiarare **quanto spazio occupa nel timone**: è il dato che usano il posizionamento sulle pagine, il cruscotto pubblicitario e i conteggi di foliazione. Non c'è un valore già scelto per gli articoli: finché non lo indichi, "Crea contenuto" segnala l'errore e non crea nulla.

**Come si inserisce** (uguale per articoli e pubblicità): due campi affiancati.
- **Pagine intere**: 0, 1, 2, 3…
- **Frazione**: nessuna, 1/4, 1/3, 1/2, 2/3 o 3/4 di pagina.

Sotto compare il totale, es. **«Occupa 2 e 1/2 pagine»**. Esempi: un articolo di due pagine e mezza = 2 + 1/2; una vetrina = 0 + 1/4; un piedino = 0 + 1/3; un servizio di 4 pagine = 4 + nessuna. Almeno uno dei due campi deve essere più di zero.

**Pubblicità**: scegliendo il formato i due campi si compilano da soli (pagina intera = 1 + nessuna, 2/3 = 0 + 2/3, mezza pagina = 0 + 1/2, piedino e 1/3 = 0 + 1/3, vetrina/un quarto = 0 + 1/4, doppia pagina = 2 + nessuna, copertina 2ª/3ª/4ª e battente = 1 + nessuna); restano modificabili.

**Come si distribuisce sulle pagine**: pagine intere in sequenza e la frazione sull'ultima. 2 e 1/2 pagine → pagina intera, pagina intera, 1/2 pagina. Poi lo spazio su ciascuna pagina si può cambiare dal menu sulla card ([§5](#5-creare-assegnarerimuovere-contenuti-e-modificare-lo-spazio-occupato)).

**Capienza della pagina**: una pagina contiene al massimo una pagina intera di contenuti. Il sistema conta in **dodicesimi di pagina** (1/4 = 3, 1/3 = 4, 1/2 = 6, 2/3 = 8, 3/4 = 9, intera = 12), così le somme sono sempre esatte: 1/3 + 1/3 + 1/3 fa esattamente una pagina piena, senza arrotondamenti. Non devi fare tu questi conti: li vedi solo come frazioni.

**Con un PDF allegato**: appena scegli (o trascini) il file, il sistema ne conta le pagine e, se non hai ancora indicato l'occupazione di un articolo, **propone** tante pagine intere quante quelle del PDF (lo dice sotto i campi). Se avevi già indicato un valore, non viene cambiato. Le regole:
- PDF di **più pagine** → l'occupazione deve attraversare esattamente quel numero di pagine (un PDF di 3 pagine va bene con 3 pagine intere o con 2 e 1/2, non con 2);
- PDF di **una pagina** → al massimo 1 pagina, anche una frazione (es. mezza pagina);
- PDF **illeggibile** (danneggiato o protetto) → viene rifiutato subito con un messaggio, invece di essere trattato come "1 pagina".

**Contenuti creati prima di questa versione**: lo spazio che avevano sulle pagine è stato convertito alla frazione più vicina (50% → 1/2, 33% → 1/3, 99,7% → intera). Se su una pagina la somma risultasse oltre la capienza, la pagina mostra «⚠ Oltre capienza» in rosso: riduci a mano uno dei contenuti.

---

## 5ter. Modificare un contenuto e rinominarlo

**Rinominare al volo**: sulla pagina, **doppio click sul nome** del contenuto → diventa un campo di testo. **Invio** salva, **Esc** (o cliccare altrove) annulla. Il nome è del contenuto, non della pagina: un articolo su 3 pagine cambia nome su tutte e 3, e lo vedono subito anche i colleghi collegati. Per un articolo si cambia il titolo; per una pubblicità il **cliente** (è il nome che la pagina mostra). Funziona anche su una pagina bloccata: il blocco riguarda spostamenti e spazio, non i dati del contenuto.

**Modificare tutto**: icona **✏️** accanto al contenuto (sulla pagina) o sul chip in "Contenuti da assegnare" → si apre la finestra **"Modifica contenuto"** con tutti i campi della creazione: tipo (articolo/pubblicità), titolo, rubrica, campi dell'articolo o della pubblicità, **occupazione** (pagine intere + frazione) e materiale di stampa (il PDF o l'immagine allegati, che sono anche l'anteprima del contenuto nel timone). Dal 2026-10-27 ci sono anche **Posizione nel timone** (la prima pagina in cui si trova: cambiandola il contenuto si sposta da lì, tutto insieme, oppure torna tra i «Contenuti da assegnare» se scegli «Da assegnare»; se nella nuova pagina non c'è posto non cambia nulla e un messaggio dice cosa la occupa e quanto spazio manca) e **Anteprima nel riquadro**: «Automatico» (il sistema guarda le proporzioni del file e lo dice tra parentesi), «Il file è a pagina intera» (mostra solo la parte del riquadro) o «Il file è già nel formato» (mostralo intero). "Salva modifiche" applica tutto; "Annulla", ✕ o Esc chiudono senza salvare.

- Le modifiche valgono su **tutte le pagine** dove compare il contenuto.
- **Cambiare l'occupazione** di un contenuto già sulle pagine lo **ridistribuisce** a partire dalla sua prima pagina, con la regola solita (pagine intere poi la frazione): da 2 a 2 e 1/2 pagine → intera, intera, 1/2 sulle pagine consecutive; riducendolo, le pagine non più usate tornano libere (e il collegamento 🔗 tra le sue pagine viene sciolto). Le regolazioni fatte a mano pagina per pagina vengono sostituite dalla nuova distribuzione. Se non ci sta (numero finito, pagina successiva piena o bloccata) non cambia nulla e il messaggio spiega perché. Lasciando l'occupazione com'è, le pagine restano come sono.
- **Pagine bloccate**: occupazione e materiale non si possono cambiare finché il contenuto è su una pagina bloccata (gli altri campi sì).
- **Cambiare tipo** (articolo ↔ pubblicità): i campi del vecchio tipo si perdono, quelli del nuovo vanno compilati; il colore delle pagine si aggiorna.
- **Pubblicità**: cambiando formato viene riproposto lo spazio del formato, modificabile.
- **Materiale di stampa**: sotto "Attuale" vedi il file allegato. Scegliendo un nuovo file **sostituisci** quello vecchio (che viene eliminato); con un PDF di più pagine ogni pagina del timone riceve la pagina del PDF corrispondente. Il PDF deve attraversare lo stesso numero di pagine dell'occupazione (un PDF di 3 pagine con un contenuto di 2 pagine viene rifiutato: cambia prima l'occupazione). La scelta "usa tutte / solo da X a Y" esiste solo in creazione.

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
- **Da «⇪ Carica più file»** (riquadro «Caricamento multiplo», sotto «Nuovo contenuto») — per caricare tanti file in una volta, vedi sotto.

**Caricare più file insieme (dal 2026-10-28)**: apri «⇪ Carica più file», scegli in alto se creare **Articoli** o **Pubblicità** (vale per i file che aggiungi da quel momento), poi trascina i file nel riquadro tratteggiato o cliccalo per sceglierli (PDF, JPG, PNG, WebP, fino a 100 MB ciascuno). Ogni file diventa un contenuto tra i «Contenuti da assegnare»:
- **titolo** = nome del file senza estensione, con trattini e underscore trasformati in spazi («servizio_moto-elettrica.pdf» → «servizio moto elettrica»);
- **spazio occupato** = tante pagine intere quante le pagine del PDF (1 pagina per un'immagine); le pubblicità nascono come «Pagina intera», senza cliente;
- correggi poi a mano con ✏️ spazio, formato, cliente e posizione dove serve, e trascinali nel timone come sempre.

I file partono **uno alla volta**: nell'elenco vedi per ciascuno «in coda», la percentuale di caricamento, «✓ creato» o «✕ errore» con il motivo (formato non accettato, file troppo grande, PDF illeggibile). Un file scartato non ferma gli altri; alla fine compare il riepilogo («12 caricati, 1 scartato: …») con **«↻ Riprova i falliti»**. Se nel numero esiste già un contenuto con lo stesso titolo, il file viene caricato lo stesso come «… (2)» e la riga lo segnala. Non chiudere né ricaricare la pagina finché l'elenco non è finito: i file ancora in coda andrebbero persi (quelli già «creati» restano).

In entrambi i casi, compila titolo/tipo (articolo o pubblicità) e l'occupazione ([§5bis](#5bis-occupazione-di-pagina-obbligatoria)), poi allega il file nel riquadro tratteggiato "Allega PDF/immagine" — PDF o immagine (jpg/png/webp), fino a **20MB**:
- **trascina il file** dal computer sopra il riquadro: mentre ci passi sopra il riquadro diventa blu ("Rilascia per caricare"); rilascialo e parte il caricamento, con una **barra di avanzamento** in percentuale;
- oppure **clicca sul riquadro** per sceglierlo dalla solita finestra del computer.

Il risultato è identico nei due modi (stessi controlli, stessa anteprima). Si possono caricare file fino a **100 MB** (dal 2026-10-27; prima 20 MB), per esempio i PDF di stampa ad alta risoluzione. Un file del tipo sbagliato o troppo grande viene rifiutato subito con un messaggio rosso sotto il riquadro — per un file troppo grande il messaggio dice quanto pesa e qual è il massimo accettato (lo stesso indicato nel riquadro: è il limite **reale** del server, che può essere più basso di 100 MB se il server è configurato così). Anche sostituendo il file dalla finestra «Modifica contenuto» vedi la barra di avanzamento e il massimo accettato. Se il caricamento viene rifiutato dal server prima ancora di controllare il file, compare il messaggio «Il file non è arrivato al server…»: riprova, e se si ripete avvisa l'amministratore indicando l'ora del tentativo (nel registro del server c'è la causa); trascinando più file insieme ti viene chiesto di trascinarne uno solo (ogni contenuto ha un solo PDF o immagine). Dopo il caricamento il nome del file compare nel riquadro con una ✓; appena il contenuto è creato, il file allegato genera automaticamente la sua anteprima, esattamente come succedeva prima per il caricamento diretto — un'immagine è pronta quasi subito, un PDF passa per lo stesso esame (conteggio pagine, poi generazione miniatura) di prima.

Se hai usato "📎 Carica materiale" (ancorato a una pagina) e il file allegato è un PDF di **una sola pagina** (o un'immagine), il contenuto si assegna **subito** a quella pagina, stessa immediatezza di prima. Se sulla pagina non c'è più spazio libero, compare un avviso giallo: il contenuto resta comunque creato, solo non assegnato lì — lo trovi tra i "Contenuti da assegnare".

Una volta pronta, clicca sulla miniatura sulla card/riga per aprire il file originale in una nuova scheda del browser.

Se la generazione dell'anteprima fallisce (icona ⚠️ rossa), il file più probabilmente è danneggiato o non è un PDF valido: crea un nuovo contenuto con un file diverso (non c'è più, per un contenuto già creato, un pulsante di "ricarica" — allega di nuovo dal modulo, come per qualunque altro contenuto).

**Ogni pagina deve avere un'anteprima (dal contenuto assegnato) prima di poter essere segnata «Ok stampa»** — se provi a farlo su una pagina senza anteprima, compare un messaggio d'errore e lo stato non cambia. Le pagine già "Revisionata"/"Ok stampa" ma ancora senza anteprima compaiono anche nel pannello "⚠️ Avvisi" ([§11](#11-avvisi-automatici)).

**Pubblicità con un PDF più lungo del formato** (es. un PDF di 3 pagine per una pubblicità "Copertina (2ª/3ª/4ª)" o "Pagina intera", che occupano una pagina): non è un errore. Sotto il riquadro del file compare un riquadro azzurro «📄 Il PDF ha 3 pagine, il formato … ne occupa 1» con due scelte:
- **«Usa tutte le 3 pagine»** (proposta di partenza): la pubblicità occuperà 3 pagine intere consecutive. Per il formato copertina la dicitura è «la prima sulla copertina, le altre sulle pagine successive»: piazzala sulla pagina di copertina e occuperà anche quelle dopo.
- **«Usa solo le pagine da X a Y»**: scrivi la prima e l'ultima pagina del PDF da usare (per una sola pagina, la stessa in entrambi i campi, es. da 2 a 2). Se il numero di pagine scelte è quello del formato, la pubblicità occupa lo spazio del formato (es. mezza pagina); se è maggiore, occupa quel numero di pagine intere.

Sotto le scelte una riga riassume cosa succederà («→ La pubblicità occuperà 3 pagine intere (pagine del PDF 1–3)»). Ogni pagina del timone mostra la pagina del PDF che le corrisponde: con «da 2 a 3», la prima pagina occupata mostra la pagina 2 del PDF e la successiva la pagina 3. Se il PDF ha *meno* pagine del formato (es. una doppia pagina consegnata come pagina singola) non compare nessuna scelta.

**PDF con più pagine creato da "+ Nuovo contenuto"**: il contenuto conserva tutte le pagine del PDF (una miniatura per pagina). Quando lo trascini dal pannello "Contenuti da assegnare" su una pagina, occupa automaticamente le N pagine consecutive da lì in poi, ognuna con la miniatura della propria pagina del PDF — se non c'è spazio per tutte, non viene piazzato e un messaggio spiega perché ([§5](#5-creare-assegnarerimuovere-contenuti-e-modificare-lo-spazio-occupato)).

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

- **Percentuale di carico pubblicitario** (numero grande) — quanta parte del numero è occupata da pubblicità, **incluse le pubblicità prenotate ma non ancora assegnate a una pagina** ([§8bis](#8bis-pubblicità-prenotate-e-chiusura-del-numero)), non solo quelle già piazzate: il testo accanto al numero mostra il dettaglio "X già assegnate + Y prenotate". È calcolata sullo spazio realmente occupato in dodicesimi di pagina ([§5bis](#5bis-occupazione-di-pagina-obbligatoria)), quindi è esatta: tre pubblicità da 1/3 su 3 pagine fanno 1 pagina su 3 = 33,33%. Le prenotate contano per l'occupazione indicata nel loro modulo. Nessuna soglia né colore di allarme (tolti il 2026-10-27): è solo la percentuale, la stessa del riepilogo in alto ([§3](#3-leggere-la-griglia-del-timone)). Le pagine di copertina non entrano mai in questo calcolo (né come "pagine totali" del denominatore, né un eventuale contenuto assegnato per errore su di esse) — vedi [§9bis](#9bis-le-copertine-i-ii-iii-iv-e-la-bandella).
- **Inserzioni assegnate** vs **pubblicità non ancora assegnate**.
- **Pubblicità da assegnare (segnaposto)**: quante pubblicità previste non hanno ancora un inserzionista, in tutto il numero (copertine comprese), con il dettaglio per formato ([§12](#12-creare-una-rivista--un-numero-duplicare-la-struttura)).
- **Riepilogo per formato pubblicitario** (pagina intera, mezza pagina, ecc.) e **per stato commerciale** (in trattativa, confermata, annullata) — include anche le prenotazioni.

**Esportare il report:** i link "⬇️ CSV" e "⬇️ PDF" in alto a destra del pannello scaricano lo stesso riepilogo in un file, utile per condividerlo con chi non ha accesso al sistema.

---

## 8bis. Pubblicità prenotate e chiusura del numero

Bottone **"📌 Pubblicità prenotate"** sopra la griglia: elenca **tutte** le pubblicità di questo numero, non solo quelle già su una pagina, con uno stato calcolato automaticamente:

| Stato | Significato |
|---|---|
| 🟠 **Prenotato** | Cliente, formato e note commerciali già inseriti ([§5](#5-creare-assegnarerimuovere-contenuti-e-modificare-lo-spazio-occupato), "+ Nuovo contenuto" → Pubblicità), ma non ancora messo su nessuna pagina — è quello che succede automaticamente quando crei una pubblicità e non la trascini subito su una pagina. Se al momento della creazione hai indicato una **pagina preferita**, la vedi qui accanto (es. "posizione preferita: pagina 7") — è solo un promemoria per chi compone il timone, non assegna nulla da sola. |
| 🔵 **Assegnato** | È già su una pagina, ma quella pagina non ha ancora un PDF pronto (o il PDF caricato ha un formato non conforme non ancora accettato, [§7](#7-caricare-e-vedere-un-pdf)). |
| 🟢 **Completo** | È su una pagina e il materiale è a posto — pronta per la stampa. |

**Eliminare una prenotazione**: bottone "🗑 Elimina" — disponibile solo per le pubblicità ancora in stato "Prenotato" (non ancora su una pagina). Se una pubblicità è già assegnata e vuoi toglierla, usa la ✕ direttamente sulla card/riga della pagina ([§5](#5-creare-assegnarerimuovere-contenuti-e-modificare-lo-spazio-occupato)).

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

**Nell'esportazione PDF del timone** (bottone "📄 Esporta PDF", [§3](#3-leggere-la-griglia-del-timone)): le copertine compaiono in una sezione «Copertine» a sé, prima delle pagine interne.

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

Bottone **"🕓 Cronologia"**, poco sotto la barra del timone: il registro di **tutto il resto** che succede su questo numero — cambi di stato pagina, contenuti creati/assegnati/rimossi/con spazio sulla pagina modificato, PDF caricati, modifiche al numero di pagine totali, modifiche e rinomine dei contenuti, pubblicità previste create con il numero (le voci vecchie sui cambi della soglia di allarme restano leggibili, ma la soglia non esiste più). Ogni riga mostra chi ha fatto cosa e quando. Non si aggiorna da solo mentre resta aperto: chiudilo e riaprilo per vedere le voci più recenti.

---

## 11. Avvisi automatici

Se compare un riquadro **"⚠️ Avvisi"** sotto la barra del timone, il sistema ha notato qualcosa da controllare (non un errore bloccante, solo un promemoria):
- una pagina già segnata come **Revisionata** o **Ok stampa** ma ancora **senza nessun contenuto assegnato**;
- una pagina già segnata come **Revisionata** o **Ok stampa** ma ancora **senza un PDF caricato** — a differenza degli altri avvisi, per «Ok stampa» questo caso è anche bloccante: vedi [§7](#7-caricare-e-vedere-un-pdf);
- un contenuto assegnato a **pagine non consecutive** (es. pagina 3 e pagina 9) — a volte è voluto, ma vale la pena ricontrollare.

Il riquadro compare solo quando c'è almeno un avviso da mostrare; se il timone non ha nulla da segnalare, non si vede affatto. Il numero di pagine non multiplo di 4 ha già il suo avviso dedicato altrove nell'interfaccia. Il carico pubblicitario non ha più soglie né avvisi (dal 2026-10-27): si legge la percentuale nel riepilogo e nel cruscotto.

---

## 12. Creare una rivista / un numero, duplicare la struttura

**Nuova rivista** (solo Admin): dal riquadro "Le tue riviste", bottone "+ Nuova rivista". Nome, periodicità, colore identificativo, note. (La soglia di allarme pubblicitario non c'è più dal 2026-10-27: il carico pubblicitario si legge come semplice percentuale.) (L'opzione «il battente conta nel totale pagine» non c'è più dal 2026-10-26: la bandella è una copertina a parte e non entra mai nel totale delle pagine interne.)

**Nuovo numero**: dalla pagina di una rivista, bottone "+ Nuovo numero" (visibile ad Admin e Redattori con accesso a quella rivista). Titolo, data di uscita (opzionale), **scadenza stampa** (opzionale — vedi [§12bis](#12bis-modificare-una-rivista-redattore-di-riferimento-e-scadenza-stampa)), **numero di pagine interne** (le quattro copertine I–IV si aggiungono da sole, non vanno contate; con 6 «pagine copertina» nella sezione Carta e peso il numero nasce anche con la bandella), note. Se cancelli del tutto il numero di pagine per riscriverlo, l'anteprima del peso si ferma finché non scrivi un valore valido; se provi a creare il numero con il campo vuoto compare l'errore sotto il campo (prima la pagina andava in errore). Mentre scrivi un numero (pagine, pubblicità previste, copie, bancali, grammature) le anteprime si aggiornano un attimo dopo che hai smesso di scrivere, e gli avvisi (es. «Non è un multiplo di 4», «Non ci stanno», grammatura fuori scala) compaiono **quando esci dal campo**, non a metà numero (dal 2026-10-27: prima il campo pagine poteva perdere cifre scrivendo veloce). Le note possono essere lunghe fino a 2000 caratteri.

**Duplicare la struttura da un numero precedente**: nello stesso form di creazione numero, se la rivista ha già altri numeri, trovi un menu "Duplica struttura da un numero precedente". Selezionandone uno, il numero di pagine interne si pre-compila con quello del numero scelto (resta modificabile) e — dopo la creazione — ogni pagina interna e ogni copertina eredita lo stesso tipo (editoriale/pubblicità/mista/bianca) della pagina corrispondente del numero di origine. **Non vengono copiati**: i contenuti assegnati, gli stati delle pagine, i PDF caricati — solo lo schema di che tipo di pagina è cosa, per non ripartire ogni volta da un timone completamente vuoto.

**Pubblicità previste** (opzionale): nel form di creazione del numero, la sezione «📢 Pubblicità previste» chiede quante pubblicità sono già previste per tipo — **pagina intera**, **2/3**, **1/2**, **piedino (1/3)**, **vetrina (1/4)**. Mentre scrivi, sotto compaiono:
- quante pagine di pubblicità fanno in tutto (es. «7 pagine di pubblicità (8 inserzioni)»);
- la **percentuale sulle pagine interne** (le copertine non contano, come nel cruscotto);
- le **posizioni** in cui andranno;
- un **errore rosso** se non ci stanno: in quel caso il numero non viene creato finché non riduci le pubblicità o aumenti le pagine.

Alla creazione diventano pubblicità **segnaposto** già messe nel timone, con questa regola:
1. le **prime 4 pubblicità occupano tutte le copertine**, di fila, nell'ordine **II, III, IV** e per ultima la **I** — prima le pagine intere, poi, se le pagine intere sono meno di 4, le frazioni più grandi (2/3, 1/2…). Quando viene usata la I, in cima al numero appena creato compare un avviso;
2. le pagine intere rimaste vanno nelle pagine interne **una sì e una no, da pagina 2** (2, 4, 6…);
3. poi le frazioni rimaste (2/3, 1/2, 1/3, 1/4), **una per pagina**, continuando la stessa sequenza: il resto della pagina resta libero per l'editoriale;
4. finite le pagine alterne, le pagine libere rimaste partendo **dal fondo**.

Le posizioni si decidono **solo adesso**: dopo puoi spostare, togliere e modificare tutto a mano, e nulla viene mai ricalcolato da solo.

**I segnaposto nel timone**: bordo tratteggiato ed etichetta **«Da assegnare»**, titolo automatico (es. «Pubblicità 1/2 — da assegnare»), nessun cliente né PDF. Contano nella percentuale pubblicitaria e nel contatore «Pubblicità da assegnare (segnaposto)» del cruscotto ([§8](#8-leggere-il-cruscotto-pubblicitario)). Per trasformarne uno in una **pubblicità vera**: doppio click sul nome e scrivi il cliente, oppure ✏️ e indica il cliente o carica il PDF ([§5ter](#5ter-modificare-un-contenuto-e-rinominarlo)). Cambiare solo titolo, formato, stato o spazio non basta: resta «da assegnare» (dal 2026-10-28 anche il titolo da solo non basta più). Al contrario, se togli il cliente a una pubblicità che non ha file, torna segnaposto. Un segnaposto si può creare anche a mano: «+ Nuovo contenuto» → Pubblicità, lasciando vuoto il cliente ([§5](#5-creare-assegnarerimuovere-contenuti-e-modificare-lo-spazio-occupato)). La mezza pagina nasce orizzontale: se è verticale, cambia il formato dalla ✏️.

**Carta e peso**: nel form di creazione del numero, la sezione richiudibile "⚖️ Carta e peso" è già compilata con i valori di default della rivista: formato in mm, grammature, pagine di copertina, poi n. copie e n. bancali, e sotto "Avanzate" maggiorazione e tolleranze. Puoi cambiarli solo per questo numero; i campi diversi dalla rivista mostrano il badge "modificato" e "Ripristina default rivista" li riporta ai valori della rivista. Sotto ogni grammatura compare il peso a pagina e sotto la sezione un'anteprima del peso che si aggiorna mentre scrivi. Dettagli in [§9ter](#9ter-peso-della-rivista).

---

## 12bis. Modificare una rivista, redattore di riferimento e scadenza stampa

**Modificare una rivista** (solo Admin): dalla pagina della rivista, bottone "Modifica rivista". Stessi campi della creazione (nome, periodicità, colore, note), più un campo nuovo:

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

- **Eliminare un utente** (dal 2026-10-28): in fondo alla pagina «Modifica utente», riquadro rosso **«Elimina l'utente»** → stessa conferma in due passaggi delle riviste e dei numeri ([§13bis](#13bis-eliminare-una-rivista-un-numero-o-un-utente-solo-admin)), scrivendo la sua **email**. Il suo lavoro resta: i file che ha caricato, gli spostamenti e la cronologia rimangono, con **«Utente eliminato»** al posto del nome. Gli vengono tolti gli accessi alle riviste e le sessioni aperte si chiudono subito. Non puoi eliminare te stesso né l'ultimo amministratore (il bottone è disattivato e il tooltip dice perché).

Nessun utente può più eliminare il proprio account dalla pagina del profilo: lo fa solo un amministratore, da qui.

---

## 13bis. Eliminare una rivista, un numero o un utente (solo Admin)

Dal 2026-10-28 un **amministratore** può eliminare:
- un **numero**: bottone **«🗑️ Elimina»** accanto al numero, nella pagina della rivista;
- una **rivista** con tutti i suoi numeri: pagina «Modifica rivista», riquadro rosso **«Elimina la rivista»** in fondo;
- un **utente**: vedi [§13](#13-gestire-gli-utenti-solo-admin).

L'eliminazione è **definitiva** (non c'è un cestino), per questo si conferma in due passaggi:
1. un riepilogo di cosa sparirà: pagine, contenuti, file caricati e quanto spazio occupano (MB/GB); per una rivista anche l'elenco dei suoi numeri. «Continua» per andare avanti, «Annulla» (o Esc) per tornare indietro;
2. scrivi il **nome esatto** del numero o della rivista (l'email per un utente) e la **tua password**, poi «Elimina definitivamente». Dopo 5 password sbagliate in un minuto bisogna aspettare un minuto.

Chi ha in quel momento il numero aperto viene riportato all'elenco delle riviste con l'avviso «Il numero che avevi aperto è stato eliminato da un amministratore». Ogni eliminazione resta registrata (chi, cosa, quando) nel registro dell'applicazione.

---

## 14. Cosa può fare ciascun ruolo

| Ruolo | Vede le riviste a cui ha accesso | Pubblicità: crea, modifica, sposta (anche segnaposto) | Articoli; pagine con articoli; stati, blocchi, gruppi, pagine totali, scadenza | Crea numeri | Crea riviste | Gestisce ed elimina utenti; elimina riviste e numeri |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| **Admin** | Tutte, sempre | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Redattore** | Solo quelle a cui è stato dato accesso | ✅ | ✅ (solo sulle sue riviste) | ✅ (solo sulle sue riviste) | ❌ | ❌ |
| **Commerciale** | Solo quelle a cui è stato dato accesso | ✅ (dal 2026-10-28) | ❌ | ❌ | ❌ | ❌ |
| **Sola lettura** | Solo quelle a cui è stato dato accesso | ❌ | ❌ | ❌ | ❌ | ❌ |

> **Commerciale (dal 2026-10-28)**: lavora **solo sulle pubblicità**. Le crea («+ Nuovo contenuto», dove il tipo è già Pubblicità, e il caricamento multiplo), le modifica (✏️, doppio click sul nome) e le sposta: trascinamento, frecce e rotazione nel menu «⋯», «Posizione nel timone» nella modale, togliere dalla pagina. Può spostare anche **pagine intere** (o gruppi di pagine), ma solo se **non contengono articoli**. Sugli articoli vede i comandi disattivati con il tooltip **«Solo la redazione può spostare questo contenuto»**; il chip dell'articolo ha il cursore 🚫 e non si trascina; una pagina con articoli non ha la maniglia ⠿. Stato e blocco delle pagine, gruppi, pagine totali e scadenza stampa restano della redazione. I controlli valgono anche lato server: un tentativo diretto viene rifiutato.

Chi può solo consultare può comunque esportare il report del cruscotto pubblicitario in CSV/PDF ([§8](#8-leggere-il-cruscotto-pubblicitario)) e il PDF completo del timone ([§3](#3-leggere-la-griglia-del-timone)) — l'esportazione non richiede permessi di modifica.
