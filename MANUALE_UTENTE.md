# Manuale utente — Timone Elettronico

Guida per chi lavora sul timone ogni giorno: redattori, commerciali, grafici. Organizzata per "voglio fare X → ecco come": cerca la sezione che ti serve, non serve leggerlo tutto di fila.

Per avviare l'applicazione la prima volta vedi invece [README.md](README.md).

---

## 1. Prima di iniziare

La struttura è sempre: **rivista → numero → timone**.

- Una **rivista** (es. "Motori Elettrici") è la testata.
- Un **numero** (es. "Novembre 2026") è una singola uscita di quella rivista, con un numero di pagine deciso.
- Il **timone** è la griglia delle pagine di un numero specifico: qui si fa il lavoro vero e proprio.

Accedi da `http://localhost` (o l'indirizzo che ti è stato dato) con l'email e la password che ti sono state assegnate. Se non hai ancora un account, chiedi a un Admin di crearlo (vedi [§13](#13-gestire-gli-utenti-solo-admin)).

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
- **Doppia pagina** — le pagine affiancate come si vedrebbero sfogliando la rivista stampata (copertina da sola, poi coppie). Puoi assegnare/rimuovere contenuti, cambiare lo stato pagina e trascinare per riordinare esattamente come in Griglia: ogni pagina resta trascinabile singolarmente anche dentro una coppia, sia per spostarla fuori sia per inserirne un'altra al suo posto (la coppia si ricompone da sola in base al nuovo ordine).
- **Lista** — una riga per pagina, utile per scorrere velocemente un numero lungo.

**Ogni card/riga mostra:**

| Elemento | Significato |
|---|---|
| Colore di sfondo | Tipo pagina: blu = editoriale, ambra = pubblicità, viola = mista (metà e metà), grigio = bianca (ancora vuota) — pubblicità sempre riconoscibile a colpo d'occhio |
| Bordo colorato spesso (griglia/doppia) o striscia colorata a sinistra (lista) | Stato della pagina: grigio = da assegnare, azzurro = assegnata, giallo = in bozza, arancione = revisionata, verde = ok stampa — riconoscibile senza dover leggere l'etichetta |
| Numero con ⠿ | Posizione della pagina — è anche la "maniglia" per trascinarla |
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

Tutto si aggiorna da solo in tempo reale se un collega è collegato sullo stesso numero — non serve mai ricaricare la pagina. Se per qualche minuto non arrivano aggiornamenti (rete che taglia fuori i websocket), compare un avviso giallo: la pagina continua comunque ad aggiornarsi da sola, solo un po' più lentamente (ogni pochi secondi anziché all'istante).

**Per stampare o condividere il timone**: bottone **"📄 Esporta PDF"** nella barra in alto — un foglio con tutte le pagine del numero, colori e stati come nell'interfaccia, con una **legenda colori** in cima (tipo pagina e stato) per chi lo guarda senza avere sotto mano lo schermo. Prima di scaricare puoi scegliere di includere le miniature dei PDF caricati, mostrare solo le pagine con pubblicità, o solo quelle non ancora approvate (non in stato "Revisionata"/"Ok stampa").

---

## 4. Spostare le pagine

**Con il mouse (tutte e tre le modalità):** trascina una pagina prendendola dalla maniglia ⠿ (il numero di posizione) e rilasciala nel punto desiderato. Le altre pagine si spostano di conseguenza — tutte le pagine intermedie slittano per fare spazio. Con "☑️ Selezione multipla" attiva e più pagine selezionate, trascinarne una sposta **l'intera selezione insieme** come blocco unico — vedi [§6bis](#6bis-selezione-multipla-e-azioni-di-massa).

**Da tastiera (tutte e tre le modalità):** clicca una volta sulla card/riga per selezionarla (o raggiungila con Tab), poi:
- in **Griglia**/**Doppia pagina**: freccia sinistra/destra;
- in **Lista**: freccia su/giù.

**Modalità scambio (bottone "🔀 Modalità scambio" in barra, funziona in tutte e tre le modalità, utile soprattutto in Doppia pagina che non supporta il trascinamento):** attivala, poi clicca una pagina (si evidenzia con un bordo colorato) e clicca una seconda pagina — si scambiano di posto direttamente, senza far slittare nient'altro. Un secondo click sulla stessa pagina annulla la selezione senza fare nulla. Utile per scambiare due pagine lontane tra loro senza dover trascinare attraverso tutto lo schermo. Contenuti, stato e PDF caricati restano legati alla pagina, non alla posizione: si "spostano" insieme ad essa.

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

- **Articolo**: titolo, rubrica (opzionale), autore (opzionale), stato redazionale, lunghezza prevista (opzionale).
- **Pubblicità**: titolo, rubrica, cliente, agenzia (opzionale), formato (l'intero listino ufficiale: pagina intera, mezza pagina orizzontale/verticale, 1/3, 1/4, 2/3, battente copertina, doppia pagina, copertina 2ª/3ª/4ª, piedino, 1ª romana, controsommario, elenco inserzionisti, controeditoriale, pubbliredazionale — mostra già accanto la percentuale di pagina che occuperebbe di default), percentuale manuale (opzionale — la usa al posto di quella del formato se la compili), **pagina preferita (opzionale)** — solo un promemoria di dove piazzarla, non assegna nulla da sola, visibile nel pannello "Pubblicità prenotate" ([§8bis](#8bis-pubblicità-prenotate-e-chiusura-del-numero)), stato commerciale, note commerciali. Per **battente copertina** e **doppia pagina** (che occupano due pagine affiancate): crea il contenuto, assegnalo alla prima pagina, poi usa "↗" per estenderlo anche alla pagina successiva — ciascuna riceve il 100% come se fosse un'inserzione a piena pagina a sé.

Una pubblicità creata ma non ancora assegnata a nessuna pagina è considerata **"prenotata"**: resta comunque conteggiata nel carico pubblicitario del cruscotto ([§8](#8-leggere-il-cruscotto-pubblicitario)) e gestibile dal pannello dedicato ([§8bis](#8bis-pubblicità-prenotate-e-chiusura-del-numero)).

Il contenuto appena creato compare subito nel pannello **"Contenuti da assegnare"**, pronto per essere trascinato su una pagina.

Sopra la griglia c'è appunto il pannello **"Contenuti da assegnare"**: tutti gli articoli e le pubblicità di questo numero non ancora messi su una pagina. Se l'elenco è lungo, usa il campo **"Cerca per titolo..."** accanto al titolo del pannello per restringerlo.

**Per assegnare:** trascina un contenuto dal pannello sopra una pagina libera (o con spazio residuo). La percentuale di pagina occupata viene calcolata da sola:
- un articolo prende tutto lo spazio libero rimasto sulla pagina;
- una pubblicità prende la percentuale del suo formato (es. mezza pagina = 50%), a meno che non sia stata impostata una percentuale manuale sull'inserzione.

**Per cambiare la percentuale** dopo l'assegnazione: modifica il numero accanto al contenuto direttamente sulla card. Se il nuovo valore supera lo spazio disponibile sulla pagina, viene rifiutato con un messaggio.

**Per rimuovere:** clic sulla ✕ accanto al contenuto — torna nel pannello "contenuti da assegnare" (solo da quella pagina: se il contenuto è anche su altre pagine, resta lì).

**Per un contenuto che continua su più pagine** (es. un articolo che prosegue da pagina 5 a pagina 8): una volta assegnato una prima volta, clicca sull'icona **"↗"** accanto al contenuto e indica il numero della pagina aggiuntiva. Il contenuto compare anche lì, con la sua percentuale calcolata separatamente per quella pagina; un piccolo **"×N"** accanto al titolo indica su quante pagine è presente in totale.

Una pagina può ospitare più contenuti insieme (es. metà pubblicità + metà articolo = pagina "mista"), purché il totale non superi il 100%.

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

Seleziona le pagine che ti interessano (clic sulla casella), poi usa la barra che compare sotto la toolbar per:

- **Cambiare stato** a tutte le pagine selezionate in un colpo solo, scegliendolo dal menu a tendina "Cambia stato in";
- **Bloccare selezionate** / **Sbloccare selezionate** — applica il blocco pagina (vedi punto 4bis) a tutta la selezione.

Comodi anche i bottoni **Seleziona tutte** / **Deseleziona tutte** per non dover cliccare pagina per pagina.

Le pagine bloccate nella selezione, e — solo quando lo stato scelto è «Ok stampa» — quelle senza un PDF caricato, vengono **ignorate** da un cambio di stato multiplo (non bloccano l'intera operazione: il resto della selezione viene comunque aggiornato) — il messaggio di esito sotto la barra ti dice sempre quante pagine sono state effettivamente cambiate e quante sono state saltate e perché, es. "3 pagine aggiornate a «Ok stampa». 1 pagina bloccata ignorata. 2 pagine senza PDF ignorate."

**Trascinare l'intera selezione insieme**: con più pagine selezionate, afferra una di quelle selezionate (dalla maniglia ⠿) e trascinala — invece di spostare solo quella, **si sposta l'intera selezione come un unico blocco**, mantenendo l'ordine con cui le pagine erano messe tra loro prima dello spostamento. Se avevi selezionato pagine non vicine tra loro (es. pagina 2 e pagina 7), dopo lo spostamento **si ritrovano vicine, una accanto all'altra**, alla posizione dove hai rilasciato: è così che funziona un "blocco unico", non resta mai una selezione sparsa. Se anche solo una delle pagine selezionate è bloccata (🔒), l'intero spostamento viene rifiutato — sblocca prima quella pagina, o togli la spunta e riprova. Se trascini una pagina che **non** fa parte della selezione corrente, si sposta solo quella, normalmente.

Disattivando **☑️ Selezione multipla** la selezione corrente viene azzerata.

---

## 7. Caricare e vedere un PDF

Clicca sull'icona 📤 in basso sulla card/riga della pagina, scegli il file PDF dal tuo computer (solo PDF: è l'unico formato accettato per il materiale definitivo di pagina, fino a **100MB**). Durante il caricamento vedi la percentuale reale di trasferimento; appena il file è arrivato al server, un messaggio "🔎 Analisi del PDF... (Ns)" con un contatore di secondi ti dice che il sistema sta ancora leggendo il file (conteggio pagine) — non è mai un'attesa silenziosa. Mentre l'anteprima (miniatura) viene generata, l'icona ⏳ mostra anche da quanti secondi la pagina è in coda e, appena il sistema ha una stima affidabile (basata sul tempo osservato per le pagine già completate in questo numero), quanti secondi mancano circa — passa il mouse sopra per vederli. Questa stima **sopravvive a un aggiornamento della pagina**: se ricarichi il browser mentre una miniatura è ancora in lavorazione, la trovi ancora lì.

Se il file è troppo grande o non valido, compare l'avviso "⚠️ upload fallito" accanto al pulsante di caricamento: passa il mouse sopra per leggere il motivo esatto.

Una volta pronta, clicca sulla miniatura per aprire il PDF originale in una nuova scheda del browser.

Se la generazione dell'anteprima fallisce (icona ⚠️ rossa), il file più probabilmente è danneggiato o non è un PDF valido: clicca di nuovo per ricaricarne uno.

Ogni nuovo caricamento sulla stessa pagina sostituisce quello mostrato in anteprima, ma non cancella i caricamenti precedenti: clicca sull'icona 🕐 (compare accanto all'anteprima non appena c'è almeno un file) per aprire lo storico completo — nome file, chi l'ha caricato, quando, e un link per aprire ciascuna versione, non solo l'ultima.

**Ogni pagina deve avere un PDF prima di poter essere segnata «Ok stampa»** — se provi a farlo su una pagina senza PDF, compare un messaggio d'errore e lo stato non cambia. Le pagine già "Revisionata"/"Ok stampa" ma ancora senza PDF compaiono anche nel pannello "⚠️ Avvisi" ([§11](#11-avvisi-automatici)).

**PDF con più pagine interne**: se il file che carichi ha più di una pagina (es. un PDF di 3 pagine per un'inserzione su tre pagine consecutive), il sistema propone di occupare automaticamente le pagine successive del timone a partire da quella su cui hai caricato. Prima di scrivere qualsiasi cosa, **si apre una finestra di conferma** (visibile sempre, anche se hai scrollato lontano dalla pagina su cui hai caricato) con un riepilogo:
- se nessuna delle pagine coinvolte ha già un PDF, un solo bottone **"Occupa le N pagine"** conferma tutto in un colpo;
- se una o più pagine hanno già un PDF caricato, puoi scegliere se **"Salta le pagine in conflitto"** (occupa solo quelle libere, lascia intatte le altre) o **"Sovrascrivi le pagine in conflitto"** (aggiunge comunque il nuovo PDF anche lì, senza cancellare lo storico di quello precedente — resta consultabile dall'icona 🕐);
- **"Annulla il caricamento"** interrompe tutto senza scrivere nulla.

Mentre le anteprime si generano una alla volta (per questo o per qualunque altro caricamento in corso sul numero), compare in cima un banner **"🖼️ N pagine in elaborazione — circa Xs rimanenti in totale"**: il conteggio e la stima sono calcolati dal sistema in tempo reale sulla base di tutte le pagine del numero ancora in lavorazione, non solo quelle appena caricate — sparisce da solo appena non resta più nessuna pagina in coda, oppure puoi chiuderlo prima con la ✕ (ricompare da solo al prossimo caricamento, se c'è ancora lavoro in corso). Se aggiorni la pagina del browser mentre delle miniature sono ancora in lavorazione, sia questo banner sia le stime sulle singole card restano visibili — riflettono sempre lo stato reale, non si perdono con un refresh.

Ogni pagina timone coinvolta mostra la miniatura della propria pagina interna corrispondente (pagina 1 del PDF → prima card, pagina 2 → la successiva, ecc.), e il link "apri PDF" ti porta direttamente alla pagina interna giusta, non sempre alla prima.

**Formato pubblicitario non conforme**: se carichi un PDF su una pagina con una pubblicità assegnata che ha un formato specifico (es. "1 pagina intera"), il sistema confronta le dimensioni reali del file con quelle attese dal listino ufficiale (comprensive di 3mm di abbondanza per lato). Se non corrispondono, compare un avviso rosso "⚠️ formato" con le dimensioni ricevute — non blocca nulla, ma segnala il problema. Se sei sicuro che vada bene comunque (un caso limite legittimo), clicca **"accetta"** accanto all'avviso: sparisce e resta registrato chi l'ha confermato. Se le dimensioni non sono misurabili (file danneggiato) o la pagina non ha un formato pubblicitario chiaro a cui riferirsi, non compare nessun avviso.

---

## 8. Leggere il cruscotto pubblicitario

Pannello sempre visibile sopra la griglia. Mostra, per il numero che stai guardando:

- **Percentuale di carico pubblicitario** (numero grande) — quanta parte del numero è occupata da pubblicità, **incluse le pubblicità prenotate ma non ancora assegnate a una pagina** ([§8bis](#8bis-pubblicità-prenotate-e-chiusura-del-numero)), non solo quelle già piazzate: il testo accanto al numero mostra il dettaglio "X già assegnate + Y prenotate". Diventa rossa se supera la soglia di allarme impostata.
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

Bottone **"⚙️ Pagine totali"** in alto nella barra degli strumenti del timone.

Puoi scrivere liberamente nel campo (anche cancellarlo del tutto e riscrivere): la validazione e il ricalcolo avvengono solo quando esci dal campo (tab o clic altrove), non ad ogni carattere digitato. Se il valore lasciato non è un numero valido (vuoto, non numerico, negativo), compare un avviso chiaro e nessuna operazione viene tentata — non un errore imprevisto.

**Per aumentare le pagine:** scrivi il nuovo totale, scegli se le pagine nuove vanno **in coda** o **in una posizione specifica** (le pagine successive slittano automaticamente), poi clicca **Applica**. Le nuove pagine sono bianche e vuote.

**Per ridurre le pagine:** scrivi il nuovo totale (più basso) — vengono sempre tolte le pagine in coda, mai una scelta a caso. Prima di poter confermare, il pannello ti mostra **cosa andrebbe perso**:
- quante pagine verrebbero eliminate;
- quali di queste hanno già contenuti assegnati (torneranno automaticamente nel pannello "contenuti da assegnare" — **non vengono cancellati**, restano solo senza una pagina);
- quali hanno un PDF caricato (**quello sì, va perso davvero** — non c'è modo di spostare un file su un'altra pagina).

Solo dopo aver visto questo riepilogo puoi cliccare **"Conferma rimozione definitiva"** (compare anche una richiesta di conferma del browser). Senza questo secondo clic esplicito, nessuna pagina viene toccata.

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

**Nuova rivista** (solo Admin): dal riquadro "Le tue riviste", bottone "+ Nuova rivista". Nome, periodicità, colore identificativo, soglia di allarme pubblicitario (opzionale), note.

**Nuovo numero**: dalla pagina di una rivista, bottone "+ Nuovo numero" (visibile ad Admin e Redattori con accesso a quella rivista). Titolo, data di uscita (opzionale), numero totale di pagine, note.

**Duplicare la struttura da un numero precedente**: nello stesso form di creazione numero, se la rivista ha già altri numeri, trovi un menu "Duplica struttura da un numero precedente". Selezionandone uno, il numero di pagine si pre-compila con quello del numero scelto (resta modificabile) e — dopo la creazione — ogni pagina eredita lo stesso tipo (editoriale/pubblicità/mista/bianca) della pagina corrispondente del numero di origine. **Non vengono copiati**: i contenuti assegnati, gli stati delle pagine, i PDF caricati — solo lo schema di che tipo di pagina è cosa, per non ripartire ogni volta da un timone completamente vuoto.

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
