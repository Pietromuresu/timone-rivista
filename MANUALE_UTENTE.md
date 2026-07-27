# Manuale utente — Timone Elettronico

Guida per chi lavora sul timone ogni giorno: redattori, commerciali, grafici. Organizzata per "voglio fare X → ecco come": cerca la sezione che ti serve, non serve leggerlo tutto di fila.

Per avviare l'applicazione la prima volta vedi invece [README.md](README.md).

---

## 1. Prima di iniziare

La struttura è sempre: **rivista → numero → timone**.

- Una **rivista** (es. "Motori Elettrici") è la testata.
- Un **numero** (es. "Novembre 2026") è una singola uscita di quella rivista, con un numero di pagine deciso.
- Il **timone** è la griglia delle pagine di un numero specifico: qui si fa il lavoro vero e proprio.

Accedi da `http://localhost` (o l'indirizzo che ti è stato dato) con l'email e la password che ti sono state assegnate. Se non hai ancora un account, chiedi a un Admin di crearlo (vedi [§12](#12-gestire-gli-utenti-solo-admin)).

Quello che vedi dipende dal tuo **ruolo** — riepilogo completo in [§13](#13-cosa-può-fare-ciascun-ruolo).

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
- **Doppia pagina** — le pagine affiancate come si vedrebbero sfogliando la rivista stampata (copertina da sola, poi coppie). Solo visualizzazione: qui non si trascina né si assegna nulla.
- **Lista** — una riga per pagina, utile per scorrere velocemente un numero lungo.

**Ogni card/riga mostra:**

| Elemento | Significato |
|---|---|
| Colore di sfondo | Tipo pagina: blu = editoriale, ambra = pubblicità, viola = mista (metà e metà), grigio = bianca (ancora vuota) |
| Numero con ⠿ | Posizione della pagina — è anche la "maniglia" per trascinarla |
| Etichetta colorata (es. "In bozza") | Stato della pagina — cliccabile per cambiarlo, vedi [§6](#6-cambiare-lo-stato-di-una-pagina) |
| 📄 / 📢 | Contenuto assegnato: 📄 articolo, 📢 pubblicità, seguito dal titolo/cliente |
| Pallino verde con iniziali, animato | Un collega ha in questo momento il focus su quella pagina (sta cambiando qualcosa) |
| 📤 | Nessun PDF caricato ancora — clic per caricarne uno |
| ⏳ PDF | PDF caricato, anteprima in elaborazione |
| Miniatura dell'immagine | PDF pronto — clic per aprirlo |
| ⚠️ PDF (rosso) | Generazione dell'anteprima fallita — clic per ricaricare il file |
| 🕐 | Apre lo storico di tutti i PDF caricati su questa pagina (compare solo se ce n'è almeno uno) |
| 📝 | La pagina ha una nota (passa il mouse per leggerla) |

Tutto si aggiorna da solo in tempo reale se un collega è collegato sullo stesso numero — non serve mai ricaricare la pagina. Se per qualche minuto non arrivano aggiornamenti (rete che taglia fuori i websocket), compare un avviso giallo: la pagina continua comunque ad aggiornarsi da sola, solo un po' più lentamente (ogni pochi secondi anziché all'istante).

---

## 4. Spostare le pagine

Solo in modalità **Griglia** e **Lista** (non in Doppia pagina).

**Con il mouse:** trascina una pagina prendendola dalla maniglia ⠿ (il numero di posizione) e rilasciala nel punto desiderato. Le altre pagine si spostano di conseguenza.

**Da tastiera:** clicca una volta sulla card/riga per selezionarla (o raggiungila con Tab), poi:
- in **Griglia**: freccia sinistra/destra;
- in **Lista**: freccia su/giù.

Se nel frattempo un collega ha spostato delle pagine e la tua vista non si è ancora aggiornata, lo spostamento viene rifiutato con un avviso invece di mescolare le posizioni in modo imprevedibile — la vista si allinea subito da sola e puoi riprovare.

---

## 5. Creare, assegnare/rimuovere contenuti e modificare la percentuale

**Per creare un nuovo articolo o una nuova pubblicità:** bottone **"+ Nuovo contenuto"** sopra la griglia. Scegli il tipo (Articolo o Pubblicità): i campi del form cambiano di conseguenza.

- **Articolo**: titolo, rubrica (opzionale), autore (opzionale), stato redazionale, lunghezza prevista (opzionale).
- **Pubblicità**: titolo, rubrica, cliente, agenzia (opzionale), formato (mostra già accanto la percentuale di pagina che occuperebbe di default), percentuale manuale (opzionale — la usa al posto di quella del formato se la compili), stato commerciale, note commerciali.

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

Non c'è un ordine obbligato: puoi passare da uno stato all'altro liberamente.

---

## 7. Caricare e vedere un PDF

Clicca sull'icona 📤 in basso sulla card/riga della pagina, scegli il file PDF dal tuo computer. L'anteprima (miniatura) viene generata automaticamente dopo qualche secondo (icona ⏳ nel frattempo).

Una volta pronta, clicca sulla miniatura per aprire il PDF originale in una nuova scheda del browser.

Se la generazione dell'anteprima fallisce (icona ⚠️ rossa), il file più probabilmente è danneggiato o non è un PDF valido: clicca di nuovo per ricaricarne uno.

Ogni nuovo caricamento sulla stessa pagina sostituisce quello mostrato in anteprima, ma non cancella i caricamenti precedenti: clicca sull'icona 🕐 (compare accanto all'anteprima non appena c'è almeno un file) per aprire lo storico completo — nome file, chi l'ha caricato, quando, e un link per aprire ciascuna versione, non solo l'ultima.

---

## 8. Leggere il cruscotto pubblicitario

Pannello sempre visibile sopra la griglia. Mostra, per il numero che stai guardando:

- **Percentuale di carico pubblicitario** (numero grande) — quanta parte del numero è occupata da pubblicità, calcolata sulle pagine equivalenti pubblicitarie rispetto al totale delle pagine. Diventa rossa se supera la soglia di allarme impostata.
- **Soglia di allarme**: il campo numerico accanto al titolo del cruscotto — impostala tu (es. `30` per il 30%); lasciala vuota per disattivare l'avviso. È un'impostazione della rivista, non del singolo numero: cambiarla qui vale per tutti i numeri di quella rivista.
- **Inserzioni assegnate** vs **pubblicità non ancora assegnate**.
- **Riepilogo per formato pubblicitario** (pagina intera, mezza pagina, ecc.) e **per stato commerciale** (in trattativa, confermata, annullata).

**Esportare il report:** i link "⬇️ CSV" e "⬇️ PDF" in alto a destra del pannello scaricano lo stesso riepilogo in un file, utile per condividerlo con chi non ha accesso al sistema.

---

## 9. Modificare il numero totale di pagine

Bottone **"⚙️ Pagine totali"** in alto nella barra degli strumenti del timone.

**Per aumentare le pagine:** scrivi il nuovo totale, scegli se le pagine nuove vanno **in coda** o **in una posizione specifica** (le pagine successive slittano automaticamente), poi clicca **Applica**. Le nuove pagine sono bianche e vuote.

**Per ridurre le pagine:** scrivi il nuovo totale (più basso) — vengono sempre tolte le pagine in coda, mai una scelta a caso. Prima di poter confermare, il pannello ti mostra **cosa andrebbe perso**:
- quante pagine verrebbero eliminate;
- quali di queste hanno già contenuti assegnati (torneranno automaticamente nel pannello "contenuti da assegnare" — **non vengono cancellati**, restano solo senza una pagina);
- quali hanno un PDF caricato (**quello sì, va perso davvero** — non c'è modo di spostare un file su un'altra pagina).

Solo dopo aver visto questo riepilogo puoi cliccare **"Conferma rimozione definitiva"** (compare anche una richiesta di conferma del browser). Senza questo secondo clic esplicito, nessuna pagina viene toccata.

---

## 10. Consultare lo storico spostamenti

Bottone **"📜 Storico spostamenti"** nella barra del timone: apre un elenco degli ultimi 50 spostamenti di pagina fatti su questo numero, con chi li ha fatti, da quale posizione a quale, e quando.

---

## 11. Creare una rivista / un numero, duplicare la struttura

**Nuova rivista** (solo Admin): dal riquadro "Le tue riviste", bottone "+ Nuova rivista". Nome, periodicità, colore identificativo, soglia di allarme pubblicitario (opzionale), note.

**Nuovo numero**: dalla pagina di una rivista, bottone "+ Nuovo numero" (visibile ad Admin e Redattori con accesso a quella rivista). Titolo, data di uscita (opzionale), numero totale di pagine, note.

**Duplicare la struttura da un numero precedente**: nello stesso form di creazione numero, se la rivista ha già altri numeri, trovi un menu "Duplica struttura da un numero precedente". Selezionandone uno, il numero di pagine si pre-compila con quello del numero scelto (resta modificabile) e — dopo la creazione — ogni pagina eredita lo stesso tipo (editoriale/pubblicità/mista/bianca) della pagina corrispondente del numero di origine. **Non vengono copiati**: i contenuti assegnati, gli stati delle pagine, i PDF caricati — solo lo schema di che tipo di pagina è cosa, per non ripartire ogni volta da un timone completamente vuoto.

---

## 12. Gestire gli utenti (solo Admin)

Voce **"Utenti"** in alto nel menu (visibile solo se sei Admin).

- **Elenco**: tutti gli utenti, il loro ruolo, a quante riviste hanno accesso.
- **Nuovo utente**: nome, email, password, ruolo, e le riviste a cui dargli accesso (checkbox — irrilevante se il ruolo è Admin, che vede sempre tutto).
- **Modifica utente**: stessi campi; la password si può lasciare vuota per non cambiarla.

Non è ancora possibile eliminare un utente da qui.

---

## 13. Cosa può fare ciascun ruolo

| Ruolo | Vede le riviste a cui ha accesso | Sposta pagine, assegna contenuti, cambia stati, carica PDF, modifica pagine totali | Crea numeri | Crea riviste | Gestisce utenti |
|---|:---:|:---:|:---:|:---:|:---:|
| **Admin** | Tutte, sempre | ✅ (su tutte) | ✅ | ✅ | ✅ |
| **Redattore** | Solo quelle a cui è stato dato accesso | ✅ (solo sulle sue riviste) | ✅ (solo sulle sue riviste) | ❌ | ❌ |
| **Commerciale** | Solo quelle a cui è stato dato accesso | ❌ (sola lettura) | ❌ | ❌ | ❌ |
| **Sola lettura** | Solo quelle a cui è stato dato accesso | ❌ (sola lettura) | ❌ | ❌ | ❌ |

> **Nota**: al momento **Commerciale** e **Sola lettura** si comportano esattamente allo stesso modo — entrambi possono solo consultare (griglia, cruscotto pubblicitario, export CSV/PDF) senza modificare nulla. Il ruolo Commerciale non ha ancora funzioni dedicate alla gestione della pubblicità: quando (e se) verranno aggiunte, questa tabella verrà aggiornata.

Chi può solo consultare può comunque esportare il report del cruscotto pubblicitario in CSV/PDF ([§8](#8-leggere-il-cruscotto-pubblicitario)) — l'esportazione non richiede permessi di modifica.
