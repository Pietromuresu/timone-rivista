# Inventario del manuale utente (2026-10-29)

Fonte: `docs/archivio/MANUALE_UTENTE_2026-10-29.md`, rifatta dal commit `e9cbad6` (`git show e9cbad6:MANUALE_UTENTE.md`): identica a quella inventariata in Fase 0 (sha1 `fd579c4d…` per entrambe), quindi l'inventario non è cambiato.
Una riga per ogni informazione: regola, passo, eccezione, limite, scorciatoia, messaggio, valore numerico.

**Colonne**
- **ID**: `S<sezione>-<n>`; la sezione è quella di origine (0 = introduzione, 3 = §3, 4b = §4bis, 5t = §5ter, 6q = §6quater, 6c = §6quinquies, 6s = §6sexies, 9b/9t/9q = §9bis/ter/quater, 12b = §12bis, 13b = §13bis).
- **Destinazione**: slug della pagina del nuovo manuale (struttura proposta in fondo a questo file).
- **Livello**: **B** = testo principale («In breve» o corpo della pagina) · **D** = blocco «Dettagli» della pagina · **N** = «Novità» (data e storico) · **A** = «Note per l'amministratore» · **R** = ripetizione identica di un'altra riga: non si riscrive, resta un rimando a quella indicata.
- **Nota**: ⚠ nome nell'app diverso da quello del manuale (verificato nelle view il 2026-10-29), da correggere in Fase 1.

## Introduzione (righe 1–7)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S0-01 | Guida per chi lavora sul timone: redattori, commerciali, grafici | inizia-qui | B | |
| S0-02 | Organizzata per «voglio fare X → ecco come»; non va letta di fila | inizia-qui | B | diventa la pagina «Cosa vuoi fare?» |
| S0-03 | Il manuale è anche nell'app: voce «📖 Manuale» nel menu in alto | inizia-qui | B | |
| S0-04 | Indirizzo `/manuale`; il vecchio `/documentazione` porta comunque lì | inizia-qui | D | |
| S0-05 | Visibile a qualunque utente loggato, nessun ruolo richiesto (materiale di aiuto, non modifica dati) | ruoli | D | |
| S0-06 | A sinistra un indice cliccabile; un clic porta alla sezione | inizia-qui | B | sostituito da indice + ricerca (Fase 2) |
| S0-07 | Avviso giallo «Il manuale utente non è disponibile» → avvisa chi gestisce l'installazione | problemi-frequenti | B | |
| S0-08 | Per avviare l'applicazione la prima volta vedi README.md | note-amministratore | A | |

## §1 Prima di iniziare (righe 11–21)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S1-01 | Struttura sempre: rivista → numero → timone | inizia-qui | B | |
| S1-02 | Rivista = la testata (es. «Motori Elettrici») | glossario | B | anche inizia-qui |
| S1-03 | Numero = singola uscita della rivista, con un numero di pagine deciso (es. «Novembre 2026») | glossario | B | anche inizia-qui |
| S1-04 | Timone = griglia delle pagine di un numero, dove si fa il lavoro | glossario | B | anche inizia-qui |
| S1-05 | Accesso da `{{APP_URL}}` (nell'app compare l'indirizzo vero) | inizia-qui | B | |
| S1-06 | Si entra con email e password assegnate | inizia-qui | B | |
| S1-07 | Senza account: chiedi a un Admin di crearlo | inizia-qui | B | rimando a utenti |
| S1-08 | Quello che vedi dipende dal ruolo | ruoli | R | rimando da inizia-qui |

## §2 Scegliere rivista e numero (righe 25–33)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S2-01 | Dopo il login: «Le tue riviste», solo quelle con accesso | riviste-e-numeri | B | |
| S2-02 | Un Admin vede tutte le riviste | riviste-e-numeri | B | anche ruoli |
| S2-03 | Ogni riquadro mostra il numero in lavorazione, se c'è | riviste-e-numeri | B | |
| S2-04 | Nella rivista: «Numeri attivi» (bozza o in lavorazione) | riviste-e-numeri | B | |
| S2-05 | «Archivio» (numeri chiusi) in fondo alla pagina | riviste-e-numeri | B | |
| S2-06 | Clic su un numero apre il timone | riviste-e-numeri | B | |

## §3 Leggere la griglia del timone (righe 37–91)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S3-01 | Tre modalità selezionabili in alto a destra | viste-del-timone | B | |
| S3-02 | Griglia: una card per pagina, vista principale | viste-del-timone | B | |
| S3-03 | Doppia pagina: più coppie per riga dal 2026-10-29 | novita | N | |
| S3-04 | Doppia pagina: pari a sinistra, dispari a destra | viste-del-timone | B | |
| S3-05 | Coppie attaccate (linea sottile in mezzo), più spazio tra coppie | viste-del-timone | B | |
| S3-06 | Prima coppia [II di copertina \| 1]: riquadro tratteggiato «II di copertina» | viste-del-timone | B | |
| S3-07 | Totale pari: ultima pagina a sinistra davanti al riquadro «III di copertina» | viste-del-timone | B | |
| S3-08 | Coppie per riga secondo larghezza e zoom (con M: 3 monitor, 2 tablet, 1 telefono), mai scorrimento orizzontale | viste-del-timone | D | |
| S3-09 | In doppia pagina si assegna, si cambia stato, si commenta e si trascina come in Griglia, anche più pagine e con le frecce | viste-del-timone | B | |
| S3-10 | Durante il drag le coppie si ricompongono e il posto vuoto mostra dove cade la pagina, anche dentro una coppia | spostare-pagine | D | |
| S3-11 | Dopo lo spostamento le pagine successive cambiano lato da sole | viste-del-timone | D | |
| S3-12 | Confini delle segnature tra le due pagine della coppia (es. [32 \| 33]) | segnature | R | |
| S3-13 | Lista: una riga per pagina, per scorrere un numero lungo | viste-del-timone | B | |
| S3-14 | Lista: etichetta DX/SX (destra/sinistra), pagina 1 sempre a destra, poi alternanza, come in Doppia pagina | viste-del-timone | B | anche legenda |
| S3-15 | Zoom S/M/L in Griglia e Doppia pagina, accanto ai bottoni delle modalità; M è il valore iniziale | viste-del-timone | B | |
| S3-16 | Lo zoom resta memorizzato su questo browser, non cambia la vista dei colleghi | viste-del-timone | D | |
| S3-17 | Pagine con le proporzioni del formato reale (base × altezza dal pannello Peso) | viste-del-timone | D | |
| S3-18 | Senza formato impostato: proporzione standard 3:4 | viste-del-timone | D | |
| S3-19 | La barra in alto (logo, Riviste, Utenti, Manuale, nome) resta visibile scorrendo | viste-del-timone | D | |
| S3-20 | Riepilogo e comandi in un'unica fascia fissa sotto la barra | riepilogo-del-numero | B | |
| S3-21 | Elenco dei comandi della fascia: scadenza stampa, ⚙️ Pagine interne, storico, scambio, selezione, esporta PDF, zoom, viste | viste-del-timone | B | ⚠ nell'app: «📜 Storico», «🔀 Scambio», «☑️ Selezione» |
| S3-22 | Su telefono la fascia scorre con la pagina | viste-del-timone | D | |
| S3-23 | Azioni sulle pagine selezionate dal 2026-10-28 | novita | N | |
| S3-24 | Con selezione attiva (o ⌘/Ctrl+clic) compare una terza riga nella fascia fissa | selezione-multipla | B | |
| S3-25 | La riga mostra «3 pagine selezionate: 12–14» | selezione-multipla | B | |
| S3-26 | Comandi: Deseleziona (Esc), Tutte (Ctrl/⌘+A), Stato, 🔗 Collega (Ctrl/⌘+Shift+G), ✂️ Scollega, 🔒 Blocca, 🔓 Sblocca | selezione-multipla | B | |
| S3-27 | Azione non possibile: resta attenuata, il tooltip dice perché (es. «Seleziona almeno 2 pagine da collegare», «Sono già collegate in un gruppo») | selezione-multipla | D | anche problemi-frequenti |
| S3-28 | Su schermi stretti Scollega, Blocca, Sblocca stanno nel menu «Altro ⋯» | selezione-multipla | D | |
| S3-29 | Il Commerciale vede solo seleziona e deseleziona | ruoli | B | anche selezione-multipla (D) |
| S3-30 | Le scorciatoie non funzionano mentre si scrive in un campo | scorciatoie | R | |
| S3-31 | Esiti ed errori (pagina bloccata, gruppo) compaiono nella fascia stessa | selezione-multipla | D | |
| S3-32 | Bottone ⌃ a destra del riepilogo lo riduce a una riga (⌄ per riaprire), ricordato nel browser | riepilogo-del-numero | B | |
| S3-33 | Su Mac si seleziona con ⌘+clic; Ctrl+clic apre il menu del tasto destro | scorciatoie | B | anche selezione-multipla |
| S3-34 | Pagine interne: totale, piene, parziali, bianche (senza contenuti) | riepilogo-del-numero | B | |
| S3-35 | Spazio libero: somma dello spazio libero sulle pagine interne (es. «4 e 1/3 pagine») | riepilogo-del-numero | B | |
| S3-36 | Pubblicità: percentuale sulle pagine interne, prenotate e segnaposto compresi, esatta (frazioni di pagina) | riepilogo-del-numero | B | |
| S3-37 | Pubblicità: solo il numero, niente soglie né colori di allarme | riepilogo-del-numero | D | |
| S3-38 | Segnaposto: fino al 2026-10-28 si chiamava «Da assegnare» | novita | N | |
| S3-39 | Segnaposto: quante pubblicità aspettano inserzionista e PDF, con dettaglio per formato | riepilogo-del-numero | B | |
| S3-40 | Copertine: quante hanno un contenuto e quante sono libere | riepilogo-del-numero | B | |
| S3-41 | Peso copia e scaglione postale dal pannello Peso; «parametri incompleti» se mancano carta o formato | riepilogo-del-numero | B | |
| S3-42 | Clic su «bianche» o «Segnaposto»: pagine evidenziate in azzurro, le altre attenuate, in tutte le viste (copertine comprese per i segnaposto) | riepilogo-del-numero | B | |
| S3-43 | Le pagine evidenziate non si spostano e restano utilizzabili; secondo clic toglie l'evidenziazione | riepilogo-del-numero | D | |
| S3-44 | Colore di sfondo = tipo pagina: blu editoriale, ambra pubblicità, viola mista, grigio bianca | legenda | B | |
| S3-45 | Bordo spesso (griglia/doppia) o striscia a sinistra (lista) = stato: grigio da assegnare, azzurro assegnata, giallo in bozza, arancione revisionata, verde ok stampa | legenda | B | |
| S3-46 | Numero con ⠿ = numero della pagina interna da 1 in su; è anche la maniglia per trascinare | legenda | B | |
| S3-47 | 📘 «I di copertina» su sfondo blu = copertina, nella fascia «Copertine» prima della pagina 1 | legenda | B | |
| S3-48 | Etichetta colorata (es. «In bozza») = stato, cliccabile per cambiarlo | legenda | B | rimando a stato-pagina |
| S3-49 | 📄 articolo / 📢 pubblicità, titolo/cliente e menu dello spazio (1/4, 1/3, 1/2 … «intera») | legenda | B | |
| S3-50 | Doppio click sul nome per rinominare, ✏️ per modificare tutto | modificare-contenuto | R | |
| S3-51 | Etichette «○ Vuota» / «◐ Libero: 1/3» / «● Piena»: quanto è piena la pagina | legenda | B | |
| S3-52 | Piena = scura ●, vuota = tratteggiata ○, parziale = ambra ◐; simbolo e testo diversi, leggibili senza colori | legenda | D | |
| S3-53 | «⚠ Oltre capienza» in rosso = anomalia da sistemare | problemi-frequenti | B | anche legenda |
| S3-54 | In Lista compare la stessa etichetta di riempimento | legenda | D | |
| S3-55 | Pallino verde con iniziali, animato = un collega ha il focus su quella pagina | legenda | B | |
| S3-56 | 📤 = nessun PDF caricato, clic per caricarne uno | legenda | B | |
| S3-57 | ⏳ PDF = PDF caricato, anteprima in elaborazione | legenda | B | |
| S3-58 | Disposizione dei contenuti sulla griglia 6×6 dal 2026-10-27 | novita | N | |
| S3-59 | La pagina è divisa in 6 colonne e 6 righe; ogni contenuto occupa un rettangolo grande quanto il suo spazio | disposizione-nella-pagina | B | |
| S3-60 | Misure: intera 6×6, mezza verticale 3×6 (sinistra o destra), mezza orizzontale 6×3, 1/3 verticale 2×6, 1/3 orizzontale o piedino 6×2 (piedino in fondo), 2/3 4×6 o 6×4, vetrina 1/4 3×3 | disposizione-nella-pagina | D | |
| S3-61 | Un nuovo contenuto va nel primo posto libero | disposizione-nella-pagina | B | |
| S3-62 | Spazio libero tratteggiato «libero» | legenda | B | |
| S3-63 | 3/4 (e 7/12, 11/12) ha forma a L, riempie lo spazio lasciato dagli altri e si adatta | disposizione-nella-pagina | D | |
| S3-64 | Clic su un contenuto seleziona la pagina (non apre più il file) | disposizione-nella-pagina | B | |
| S3-65 | Ogni blocco mostra l'anteprima del suo file | disposizione-nella-pagina | B | |
| S3-66 | PDF a pagina intera con annuncio in una zona: si vede solo la parte del riquadro (mezza verticale sinistra → metà sinistra) | disposizione-nella-pagina | D | anche modificare-contenuto (Anteprima nel riquadro) |
| S3-67 | File già della misura dell'annuncio: si vede intero | disposizione-nella-pagina | D | |
| S3-68 | Senza file: fondo azzurro (articolo) o ambra (pubblicità) | legenda | B | |
| S3-69 | Bottone ⋯ nell'angolo del blocco apre il menu | disposizione-nella-pagina | B | |
| S3-70 | Menu: Spazio su questa pagina | disposizione-nella-pagina | B | |
| S3-71 | Menu: Posizione ← ↑ ↓ → sposta di un passo (vetrina di mezza pagina, colonna 1/3 di una colonna) | disposizione-nella-pagina | B | |
| S3-72 | Freccia su un contenuto che forma un rettangolo (due vetrine affiancate, 1/3 sopra 2/3): i due si scambiano | disposizione-nella-pagina | D | |
| S3-73 | Freccia grigia: si uscirebbe dalla pagina o ci si sovrapporrebbe | disposizione-nella-pagina | D | |
| S3-74 | ⟳ Ruota: verticale ↔ orizzontale | disposizione-nella-pagina | B | |
| S3-75 | Ruotare mezza pagina o 1/3 cambia anche il formato della pubblicità; 2/3 o articolo solo la forma | disposizione-nella-pagina | D | |
| S3-76 | Ruota grigio se quadrato o se ruotato non ci sta | disposizione-nella-pagina | D | |
| S3-77 | 👁 Apri il file in una nuova scheda; grigio con «Nessun file caricato» se manca | disposizione-nella-pagina | B | |
| S3-78 | Menu: ✏️ Modifica, ↗ Estendi a un'altra pagina, ✕ Rimuovi dalla pagina | disposizione-nella-pagina | B | |
| S3-79 | Posizioni salvate e viste subito dai colleghi | disposizione-nella-pagina | D | |
| S3-80 | Su una pagina bloccata le posizioni non si cambiano | bloccare-pagina | D | anche disposizione-nella-pagina |
| S3-81 | È una rappresentazione dello spazio, non l'impaginato definitivo | disposizione-nella-pagina | B | |
| S3-82 | In Lista righe di testo, con gli stessi comandi accanto al nome | disposizione-nella-pagina | D | |
| S3-83 | ⚠️ PDF (rosso) = anteprima fallita, clic per ricaricare | legenda | B | contraddice §7 (nessun pulsante di ricarica): da verificare in Fase 1 |
| S3-84 | 🕐 = storico dei PDF caricati sulla pagina (solo se ce n'è almeno uno) | legenda | B | |
| S3-85 | 📝 = la pagina ha una nota (passa il mouse per leggerla) | legenda | B | |
| S3-86 | 🔓 / 🔒 = blocca/sblocca | legenda | B | rimando a bloccare-pagina |
| S3-87 | Casella di spunta (con selezione attiva) = seleziona la pagina | legenda | B | ⚠ «☑️ Selezione» |
| S3-88 | Tutto si aggiorna da solo in tempo reale, mai ricaricare | viste-del-timone | B | |
| S3-89 | Cambiamento di un collega: avviso per pochi secondi in basso a destra (es. «Una pagina è stata spostata da un altro utente.»), la griglia è già aggiornata | viste-del-timone | B | |
| S3-90 | Senza aggiornamenti per qualche minuto (rete, websocket): avviso giallo permanente | problemi-frequenti | B | |
| S3-91 | In quel caso la pagina controlla circa una volta al secondo; ritardo massimo un secondo | note-amministratore | A | |
| S3-92 | «📄 Esporta PDF» nella barra: foglio A4 orizzontale da appendere in redazione | esporta-timone-pdf | B | |
| S3-93 | In testa: rivista, numero, data di uscita, numeri del riepilogo (piene/parziali/bianche, libero, % pubblicità, da assegnare, copertine, peso) | esporta-timone-pdf | D | |
| S3-94 | Copertine e pagine interne della stessa misura, proporzioni del formato, in doppie pagine (pagina 1 sola a destra) | esporta-timone-pdf | D | |
| S3-95 | Disposizione dei contenuti come nel timone, con il nome di ciascuno; pallino colorato = stato | esporta-timone-pdf | D | |
| S3-96 | In fondo legenda; piè di pagina «Foglio N di M» | esporta-timone-pdf | D | |
| S3-97 | Opzione miniature (anche copertine); senza: blocchi azzurri/ambra | esporta-timone-pdf | B | |
| S3-98 | Le immagini WEBP potrebbero non comparire nell'export | esporta-timone-pdf | D | |
| S3-99 | Filtri: solo pagine con pubblicità, solo non ancora approvate (non Revisionata/Ok stampa) | esporta-timone-pdf | B | |
| S3-100 | Con un filtro le pagine sono mostrate una per una, non in doppie pagine | esporta-timone-pdf | D | |
| S3-101 | PDF sfogliabile della rivista dal 2026-10-28 | novita | N | |
| S3-102 | Riquadro «📖 PDF sfogliabile della rivista» sopra il timone → «Esporta PDF rivista» | pdf-sfogliabile | B | |
| S3-103 | Simula la rivista finita: una pagina del PDF per pagina della rivista, in ordine di stampa (I, II, 1…N, III, IV; bandella dopo la I) | pdf-sfogliabile | B | |
| S3-104 | Ogni pagina è un A4 verticale con la pagina in scala, centrata, non deformata, numero di pagina sotto | pdf-sfogliabile | D | |
| S3-105 | Contenuti con file mostrano il loro PDF/immagine nel riquadro, stessa regola delle anteprime | pdf-sfogliabile | D | |
| S3-106 | Contenuto su più pagine: su ogni pagina la pagina corrispondente del suo PDF | pdf-sfogliabile | D | |
| S3-107 | Contenuti senza file: riquadro tratteggiato con titolo, tipo e «File mancante» (anche se il file non è più sul server) | pdf-sfogliabile | B | |
| S3-108 | Spazio libero bianco; pagina vuota «Pagina bianca» | pdf-sfogliabile | D | |
| S3-109 | Il PDF si apre a coppie affiancate (I da sola), un segnalibro per pagina | pdf-sfogliabile | D | |
| S3-110 | Generato in coda: In coda → In generazione → Pronto, oppure Errore con il motivo; si aggiorna da solo | pdf-sfogliabile | B | |
| S3-111 | Pronto: «⬇ Scarica» con numero di pagine, peso del file, contenuti senza file | pdf-sfogliabile | B | |
| S3-112 | Si può continuare a lavorare durante la generazione | pdf-sfogliabile | B | |
| S3-113 | «Bassa risoluzione per anteprima»: versione leggera da mandare via mail, file a parte | pdf-sfogliabile | B | |
| S3-114 | Ogni nuova esportazione sostituisce la precedente dello stesso tipo | pdf-sfogliabile | D | |
| S3-115 | Può esportare chiunque veda il numero | pdf-sfogliabile | D | anche ruoli |

## §4 Spostare le pagine (righe 95–109)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S4-01 | Col mouse (tutte le viste): trascina dalla maniglia ⠿ e rilascia | spostare-pagine | B | |
| S4-02 | Le pagine intermedie slittano per fare spazio | spostare-pagine | B | |
| S4-03 | Con selezione attiva, trascinare una selezionata sposta tutta la selezione come blocco | selezione-multipla | R | rimando da spostare-pagine |
| S4-04 | Una pagina di un gruppo collegato porta con sé tutto il gruppo, anche senza selezione | gruppi-collegati | R | rimando da spostare-pagine |
| S4-05 | Durante il drag di gruppo/selezione le pagine si raccolgono sotto quella afferrata, bordo tratteggiato blu | spostare-pagine | D | |
| S4-06 | L'immagine che segue il mouse le mostra tutte affiancate; al rilascio scorrono alla posizione finale | spostare-pagine | D | |
| S4-07 | Da tastiera: clic sulla card (o Tab), poi freccia sinistra/destra (Griglia, Doppia) o su/giù (Lista) | spostare-pagine | B | anche scorciatoie |
| S4-08 | La pagina si sposta di un posto, resta selezionata, si può ripetere | spostare-pagine | B | |
| S4-09 | Con le frecce un gruppo collegato si sposta tutto, qualunque pagina sia selezionata | spostare-pagine | D | |
| S4-10 | «🔀 Modalità scambio» in barra, in tutte le viste | scambiare-pagine | B | ⚠ bottone «🔀 Scambio» (il banner dice «Modalità scambio attiva») |
| S4-11 | Clic su una pagina (bordo colorato), clic su una seconda: si scambiano senza far slittare nulla | scambiare-pagine | B | |
| S4-12 | Secondo clic sulla stessa pagina annulla la scelta | scambiare-pagine | D | |
| S4-13 | Utile per pagine lontane | scambiare-pagine | B | |
| S4-14 | Contenuti, stato e PDF seguono la pagina, non la posizione | spostare-pagine | B | anche scambiare-pagine |
| S4-15 | Se un collega ha spostato pagine e la vista non è aggiornata, l'operazione è rifiutata con un avviso; la vista si allinea, riprova | problemi-frequenti | B | anche spostare-pagine (D) |

## §4bis Bloccare una pagina (righe 113–123)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S4b-01 | Bottone 🔓 accanto allo stato; clic → 🔒 | bloccare-pagina | B | |
| S4b-02 | Bloccata: non si sposta né si scambia (drag, tastiera, scambio rifiutati con avviso) | bloccare-pagina | B | |
| S4b-03 | Bloccata: niente cambio stato, assegnazione/rimozione, spazio, nuovo PDF; controlli in sola visualizzazione in tutte le viste | bloccare-pagina | B | |
| S4b-04 | Bloccata: non si elimina riducendo il totale; operazione rifiutata, bottone di conferma disabilitato | bloccare-pagina | D | ⚠ manuale dice «⚙️ Pagine totali», nell'app «⚙️ Pagine interne» |
| S4b-05 | Per sbloccare: clic su 🔒, torna 🔓 | bloccare-pagina | B | |
| S4b-06 | Uso tipico: pagina già mandata in stampa | bloccare-pagina | B | |
| S4b-07 | Limite: la posizione può cambiare se un'altra pagina viene spostata attraverso | bloccare-pagina | D | |
| S4b-08 | Per spostamenti puliti attorno a una bloccata usa la modalità scambio | bloccare-pagina | D | |

## §5 Creare, assegnare/rimuovere contenuti, spazio occupato (righe 127–170)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S5-01 | «+ Nuovo contenuto» (oggi nel pannello Contenuti); scegli Articolo o Pubblicità, il modulo cambia | creare-contenuto | B | il manuale dice «sopra la griglia»: è nel pannello Contenuti |
| S5-02 | Articolo: titolo, rubrica (opz.), occupazione (obbl.), autore (opz.), stato redazionale, lunghezza prevista (opz.) | creare-contenuto | B | |
| S5-03 | Pubblicità: titolo opzionale; vuoto → «Pubblicità 1/2 — da assegnare» con la misura | creare-contenuto | D | |
| S5-04 | Pubblicità senza cliente e senza file = segnaposto «da assegnare» | segnaposto | B | |
| S5-05 | Agenzia opzionale | creare-contenuto | D | |
| S5-06 | Formato obbligatorio, nessun valore preselezionato | creare-contenuto | B | |
| S5-07 | Listino: intera, mezza orizzontale/verticale, 1/3, 1/4 (vetrina), 2/3, battente copertina, doppia pagina, copertina 2ª/3ª/4ª, piedino, 1ª romana, controsommario, elenco inserzionisti, controeditoriale, pubbliredazionale; accanto lo spazio (es. «(1/2 di pagina)») | creare-contenuto | D | anche legenda (formati) |
| S5-08 | Scelto il formato, «Pagine intere» + «Frazione» si compilano e restano correggibili | spazio-occupato | B | |
| S5-09 | Pagina preferita (opz.): solo promemoria, visibile in «Pubblicità prenotate» | pubblicita-prenotate | B | anche creare-contenuto (D) |
| S5-10 | Stato commerciale e note commerciali | creare-contenuto | D | |
| S5-11 | Doppia pagina: trascina sulla prima delle due, occupa anche la successiva, pagine collegate | assegnare-contenuto | D | |
| S5-12 | Battente copertina occupa la bandella (un solo riquadro) | copertine | B | |
| S5-13 | Rubrica: ➕ crea nuova (nome, «Crea», selezionata subito), ✏️ rinomina | creare-contenuto | D | |
| S5-14 | Nome rubrica unico nella rivista | creare-contenuto | D | |
| S5-15 | Posizione nel timone dal 2026-10-27 | novita | N | |
| S5-16 | Posizione nel timone: pagina 1…N o copertina I–IV; messo lì appena creato, sulle successive se più pagine, nel primo posto libero | creare-contenuto | B | |
| S5-17 | Se non c'è spazio non viene creato; messaggio con cosa occupa e quanto manca (es. «Sulla pagina ci sono già «Acme» (1/2) e «Beta» (1/4): manca 1/4 di pagina.») | problemi-frequenti | B | anche creare-contenuto (D) |
| S5-18 | «Da assegnare» → il contenuto va nel pannello Contenuti, scheda «Da assegnare» | creare-contenuto | B | |
| S5-19 | Allegare un file (opz.): riquadro «Allega PDF/immagine», trascina o clicca | caricare-file | B | |
| S5-20 | PDF o immagine JPG/PNG/WEBP fino a 100 MB | caricare-file | B | |
| S5-21 | Anteprima generata in pochi secondi, compare sul contenuto nel pannello senza ricaricare | caricare-file | B | |
| S5-22 | File legato al singolo contenuto: su una pagina con più contenuti ognuno ha il suo | caricare-file | D | |
| S5-23 | Pubblicità creata e non assegnata = «prenotata», conta nel carico pubblicitario | pubblicita-prenotate | B | |
| S5-24 | Il nuovo contenuto compare subito nel pannello (e in «📌 Pubblicità prenotate»), per te e per i colleghi | creare-contenuto | D | |
| S5-25 | Archiviare, ripristinare, eliminare: sparisce/ricompare subito anche per gli altri | pannello-contenuti | D | |
| S5-26 | Pannello Contenuti dal 2026-10-28 | novita | N | |
| S5-27 | Bottone «📚 Contenuti · N da assegnare» nella fascia fissa (N = non ancora su una pagina) | pannello-contenuti | B | |
| S5-28 | Si apre senza ricaricare né perdere il punto | pannello-contenuti | B | |
| S5-29 | Da 1440 px colonna a destra (bordo sinistro trascinabile per allargare); sotto, tendina sotto la fascia | pannello-contenuti | D | |
| S5-30 | Apertura e larghezza ricordate nel browser | pannello-contenuti | D | |
| S5-31 | Schede Da assegnare (predefinita), Tutti (con «p. 12–14» o copertina), Segnaposto | pannello-contenuti | B | |
| S5-32 | Filtro Articoli/Pubblicità, «Cerca per titolo…», ordine «Ultimi creati» o «A–Z» | pannello-contenuti | B | |
| S5-33 | «+ Nuovo contenuto» e «Caricamento multiplo» nel pannello (prima sopra il timone) | pannello-contenuti | B | «prima erano sopra il timone» → novita (N) |
| S5-34 | Per ogni voce: miniatura, titolo, tipo, spazio, «SEGNAPOSTO», 👁, ✏️ | pannello-contenuti | B | |
| S5-35 | Assegnare in tre modi, stessi controlli; se non c'è posto il messaggio dice cosa occupa e quanto manca | assegnare-contenuto | B | |
| S5-36 | Modo 1: trascina la voce su una pagina o copertina; vicino al bordo il timone scorre | assegnare-contenuto | B | |
| S5-37 | Modo 2: con pagine selezionate «Assegna alla selezione», parte dalla prima selezionata | assegnare-contenuto | B | |
| S5-38 | Modo 3: «Vai a pag.»: numero (es. 12) o copertina (I, II, III, IV), Invio | assegnare-contenuto | B | |
| S5-39 | Dal pannello si assegnano solo contenuti non in pagina; in «Tutti» quelli in pagina si vedono ma non si trascinano | pannello-contenuti | D | |
| S5-40 | Se un collega assegna un contenuto, la voce sparisce; se la stavi trascinando il rilascio è rifiutato | assegnare-contenuto | D | |
| S5-41 | Passando il mouse: ✏️ Modifica, 🗄️ Archivia, 🗑️ Elimina | pannello-contenuti | B | |
| S5-42 | Archivia: nascosto ma recuperabile da «▸ Contenuti archiviati», bottone ↩️ | pannello-contenuti | B | |
| S5-43 | Elimina: definitiva, doppia conferma | pannello-contenuti | B | contraddetto da §6quinquies (recuperabile 24 ore con Ctrl+Z): da correggere in Fase 1 |
| S5-44 | Un contenuto in pagina non si archivia né elimina da lì: prima ✕ sulla card | pannello-contenuti | D | |
| S5-45 | Trascinando verso il bordo inferiore (o sotto il riepilogo) la pagina scorre, più veloce vicino al bordo; vale per pagine, gruppi e copertine | assegnare-contenuto | D | anche spostare-pagine |
| S5-46 | Lo spazio lo decide l'occupazione, uguale per articoli e pubblicità | spazio-occupato | R | |
| S5-47 | Meno di una pagina: prende quella parte della pagina | assegnare-contenuto | B | |
| S5-48 | Pagine intere + frazione: pagine consecutive, frazione sull'ultima | assegnare-contenuto | B | |
| S5-49 | Con un PDF ogni pagina mostra la propria pagina del PDF; le pagine vengono collegate (🔗) | assegnare-contenuto | D | |
| S5-50 | Tutto o niente: se non ci sta (numero finito, pagina bloccata o piena) messaggio con il motivo e quanto resta; nessuna pagina modificata | assegnare-contenuto | B | anche problemi-frequenti |
| S5-51 | Esempio messaggio: «richiede 1/2 di pagina, ma resta libero solo 1/3 di pagina» | problemi-frequenti | B | |
| S5-52 | Contenuto vecchio senza occupazione: mai piazzato a caso, messaggio | note-amministratore | A | |
| S5-53 | Cambiare lo spazio su una pagina: menu accanto al contenuto (da 1/12 a «intera») | spazio-occupato | B | |
| S5-54 | Cambia solo quella pagina; la distribuzione automatica è un punto di partenza | spazio-occupato | D | |
| S5-55 | Nuovo valore che non ci sta: rifiutato con messaggio sullo spazio rimasto | spazio-occupato | D | |
| S5-56 | Rimuovere: ✕ accanto al contenuto, torna in «Da assegnare»; solo da quella pagina | assegnare-contenuto | B | |
| S5-57 | Contenuto su più pagine: ↗, indica la pagina aggiuntiva | assegnare-contenuto | B | |
| S5-58 | Sulla pagina aggiunta: pagina intera (o tutto il contenuto se meno di una), modificabile | assegnare-contenuto | D | |
| S5-59 | «×N» accanto al titolo = su quante pagine è presente | legenda | B | |
| S5-60 | Più contenuti per pagina (pagina «mista») purché il totale non superi l'intera | spazio-occupato | B | |
| S5-61 | 1/2 + 1/4 + 1/4 = piena; 1/2 + 1/3 + 1/4 non ci sta | spazio-occupato | D | |

## §5bis Occupazione di pagina (righe 174–195)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S5b-01 | Ogni contenuto dichiara quanto spazio occupa; lo usano posizionamento, cruscotto, foliazione | spazio-occupato | B | |
| S5b-02 | Nessun valore predefinito per gli articoli; «Crea contenuto» segnala l'errore | spazio-occupato | B | anche problemi-frequenti |
| S5b-03 | Due campi: Pagine intere (0, 1, 2…) e Frazione (nessuna, 1/4, 1/3, 1/2, 2/3, 3/4) | spazio-occupato | B | |
| S5b-04 | Sotto il totale, es. «Occupa 2 e 1/2 pagine» | spazio-occupato | B | |
| S5b-05 | Esempi: 2 e mezza = 2 + 1/2; vetrina 0 + 1/4; piedino 0 + 1/3; servizio di 4 pagine = 4 + nessuna | spazio-occupato | D | |
| S5b-06 | Almeno uno dei due campi > 0 | spazio-occupato | B | |
| S5b-07 | Pubblicità: i formati compilano i campi (intera 1, 2/3, mezza 1/2, piedino e 1/3 1/3, vetrina 1/4, doppia 2, copertina 2ª/3ª/4ª e battente 1), modificabili | spazio-occupato | D | |
| S5b-08 | Distribuzione: intere in sequenza, frazione sull'ultima (2 e 1/2 → intera, intera, 1/2) | spazio-occupato | B | |
| S5b-09 | Poi lo spazio di ogni pagina si cambia dal menu sulla card | spazio-occupato | R | |
| S5b-10 | Capienza: al massimo una pagina intera | spazio-occupato | B | |
| S5b-11 | Il sistema conta in dodicesimi (1/4=3, 1/3=4, 1/2=6, 2/3=8, 3/4=9, intera=12): somme esatte | note-amministratore | A | |
| S5b-12 | 1/3 + 1/3 + 1/3 = esattamente una pagina; non devi fare conti | spazio-occupato | D | |
| S5b-13 | Con un PDF: il sistema conta le pagine e propone l'occupazione per un articolo se non indicata | spazio-occupato | D | anche caricare-file |
| S5b-14 | Un valore già indicato non viene cambiato | spazio-occupato | D | |
| S5b-15 | PDF di più pagine: l'occupazione deve attraversare quel numero di pagine (3 pagine: 3 o 2 e 1/2, non 2) | caricare-file | D | |
| S5b-16 | PDF di una pagina: al massimo 1 pagina, anche una frazione | caricare-file | D | |
| S5b-17 | PDF illeggibile (danneggiato o protetto): rifiutato subito con messaggio | problemi-frequenti | B | anche caricare-file |
| S5b-18 | Contenuti precedenti convertiti alla frazione più vicina (50% → 1/2, 33% → 1/3, 99,7% → intera) | note-amministratore | A | |
| S5b-19 | Se la somma supera la capienza: «⚠ Oltre capienza», riduci a mano | problemi-frequenti | R | S3-53 |

## §5ter Modificare un contenuto e rinominarlo (righe 199–210)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S5t-01 | Rinominare: doppio click sul nome, Invio salva, Esc (o clic altrove) annulla | modificare-contenuto | B | |
| S5t-02 | Il nome è del contenuto: cambia su tutte le pagine, i colleghi lo vedono subito | modificare-contenuto | D | |
| S5t-03 | Articolo: si cambia il titolo; pubblicità: il cliente | modificare-contenuto | B | |
| S5t-04 | Funziona anche su pagina bloccata (il blocco riguarda spostamenti e spazio) | modificare-contenuto | D | anche bloccare-pagina |
| S5t-05 | ✏️ sulla pagina o nel pannello → finestra «Modifica contenuto» con tutti i campi | modificare-contenuto | B | |
| S5t-06 | Campi: tipo, titolo, rubrica, campi articolo/pubblicità, occupazione, materiale di stampa | modificare-contenuto | B | |
| S5t-07 | Posizione nel timone e Anteprima nel riquadro dal 2026-10-27 | novita | N | |
| S5t-08 | Posizione nel timone: cambiandola il contenuto si sposta tutto; «Da assegnare» lo toglie dalle pagine | modificare-contenuto | B | |
| S5t-09 | Nuova posizione senza posto: non cambia nulla, messaggio | modificare-contenuto | D | |
| S5t-10 | Anteprima nel riquadro: «Automatico» (dice tra parentesi cosa ha deciso), «Il file è a pagina intera», «Il file è già nel formato» | modificare-contenuto | D | |
| S5t-11 | «Salva modifiche» applica; «Annulla», ✕ o Esc chiudono senza salvare | modificare-contenuto | B | |
| S5t-12 | Le modifiche valgono su tutte le pagine del contenuto | modificare-contenuto | B | |
| S5t-13 | Cambiare l'occupazione ridistribuisce dalla prima pagina; riducendo, le pagine non usate tornano libere e il 🔗 si scioglie | modificare-contenuto | D | |
| S5t-14 | Le regolazioni a mano vengono sostituite; se non ci sta non cambia nulla | modificare-contenuto | D | |
| S5t-15 | Occupazione invariata: pagine invariate | modificare-contenuto | D | |
| S5t-16 | Su pagina bloccata: occupazione e materiale non si cambiano, gli altri campi sì | modificare-contenuto | D | anche bloccare-pagina |
| S5t-17 | Cambiare tipo: i campi del vecchio tipo si perdono; colore delle pagine aggiornato | modificare-contenuto | D | |
| S5t-18 | Pubblicità: cambiando formato viene riproposto lo spazio del formato | modificare-contenuto | D | |
| S5t-19 | Materiale: sotto «Attuale» il file; un nuovo file sostituisce il vecchio (eliminato) | modificare-contenuto | B | |
| S5t-20 | PDF di più pagine: ogni pagina del timone riceve la pagina corrispondente | modificare-contenuto | D | |
| S5t-21 | Il PDF deve attraversare lo stesso numero di pagine dell'occupazione (3 pagine su 2 rifiutato: cambia prima l'occupazione) | modificare-contenuto | D | anche problemi-frequenti |
| S5t-22 | La scelta «usa tutte / solo da X a Y» esiste solo in creazione | modificare-contenuto | D | |

## §6 Cambiare lo stato di una pagina (righe 214–224)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S6-01 | Clic sull'etichetta colorata in alto sulla card/riga, scegli lo stato | stato-pagina | B | il manuale dice «Griglia o Lista»: anche Doppia pagina |
| S6-02 | Stati: Da assegnare, Assegnata, In bozza, Revisionata, Ok stampa | stato-pagina | B | |
| S6-03 | Nessun ordine obbligato | stato-pagina | B | |
| S6-04 | Eccezione: «Ok stampa» richiede un PDF già caricato | stato-pagina | B | anche problemi-frequenti |

## §6bis Selezione multipla e azioni di massa (righe 228–243)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S6b-01 | Bottone «☑️ Selezione multipla» nella barra: casella su ogni card/riga | selezione-multipla | B | ⚠ nell'app «☑️ Selezione» |
| S6b-02 | Funziona in tutte le viste e insieme alla modalità scambio | selezione-multipla | D | |
| S6b-03 | Selezione: clic sulla casella o Ctrl+clic (Cmd su Mac) sulla card, che attiva da sola la selezione | selezione-multipla | B | |
| S6b-04 | Cambiare stato a tutte le selezionate dal menu «Cambia stato in» | selezione-multipla | B | ⚠ nell'app «Stato:» nella fascia |
| S6b-05 | «Bloccare selezionate» / «Sbloccare selezionate» | selezione-multipla | B | ⚠ nell'app «🔒 Blocca» / «🔓 Sblocca» |
| S6b-06 | «Seleziona tutte» / «Deseleziona tutte» | selezione-multipla | B | ⚠ nell'app «Tutte» / «Deseleziona» |
| S6b-07 | Cambio stato multiplo: pagine bloccate e (per Ok stampa) senza PDF ignorate, il resto aggiornato | selezione-multipla | D | |
| S6b-08 | Messaggio di esito, es. «3 pagine aggiornate a «Ok stampa». 1 pagina bloccata ignorata. 2 pagine senza PDF ignorate.» | selezione-multipla | D | |
| S6b-09 | Trascinare una selezionata sposta l'intera selezione come blocco, mantenendo l'ordine | selezione-multipla | B | |
| S6b-10 | Pagine non vicine (es. 2 e 7) dopo lo spostamento si ritrovano vicine | selezione-multipla | D | |
| S6b-11 | Una pagina bloccata nella selezione: spostamento rifiutato; sblocca o togli la spunta | selezione-multipla | D | |
| S6b-12 | Trascinare una pagina non selezionata sposta solo quella | selezione-multipla | D | |
| S6b-13 | Disattivando la selezione, la selezione si azzera | selezione-multipla | D | |

## §6ter Collegare pagine in un gruppo (righe 245–257)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S6t-01 | Pagine sempre insieme (es. 5 e 6 di un articolo): collegale in un gruppo; trascinandone una si spostano tutte | gruppi-collegati | B | |
| S6t-02 | Per collegare: Selezione, almeno 2 pagine, «🔗 Collega selezionate» | gruppi-collegati | B | ⚠ nell'app «🔗 Collega» |
| S6t-03 | Contorno colorato ed etichetta 🔗, colore diverso per ogni gruppo | gruppi-collegati | B | anche legenda |
| S6t-04 | Per scollegare: clic su 🔗 di una pagina del gruppo, si scioglie tutto il gruppo | gruppi-collegati | B | |
| S6t-05 | Pagina bloccata o già in un altro gruppo non si collega | gruppi-collegati | D | |
| S6t-06 | Una pagina del gruppo bloccata: il gruppo non si sposta né si scollega | gruppi-collegati | D | |
| S6t-07 | Selezionando solo alcune pagine di un gruppo, le altre si includono nello spostamento: un gruppo non si spacca | gruppi-collegati | D | |
| S6t-08 | Funziona in tutte le viste | gruppi-collegati | D | |

## §6quater Scorciatoie da tastiera (righe 259–271)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S6q-01 | Uguali in tutte le viste; mai attive mentre si scrive in un campo | scorciatoie | B | |
| S6q-02 | Ctrl+click (Cmd+click) aggiunge/toglie dalla selezione, la attiva da sola | scorciatoie | B | |
| S6q-03 | Click semplice con selezione attiva: azzera e seleziona solo quella | scorciatoie | B | |
| S6q-04 | Ctrl+Shift+G con almeno 2 pagine: collega, o scollega se già collegate | scorciatoie | B | |
| S6q-05 | Se non si può: stesso messaggio dei bottoni, mai silenzio | scorciatoie | D | |
| S6q-06 | Ctrl+A (Cmd+A): seleziona tutte, attivando la selezione | scorciatoie | B | |
| S6q-07 | Esc: deseleziona; con una finestra aperta chiude solo quella | scorciatoie | B | |
| S6q-08 | Selezionate con Ctrl+click: contorno verde acceso; gruppo colorato; scambio indaco | legenda | B | |

## §6quinquies Annullare e ripetere (righe 275–284)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S6c-01 | Annulla/ripeti dal 2026-10-28 | novita | N | |
| S6c-02 | Ctrl+Z (⌘+Z) annulla; Ctrl+Shift+Z (⌘+Shift+Z) o Ctrl+Y ripete | annullare-ripetere | B | anche scorciatoie |
| S6c-03 | Bottoni ↶ ↷ all'inizio della barra; tooltip con l'azione (es. «Annulla: Sposta pagina 12 → 20») | annullare-ripetere | B | |
| S6c-04 | Dopo un annulla: avviso «Annullato: …» per 4 secondi con «Ripeti» | annullare-ripetere | B | |
| S6c-05 | Non funziona in un campo (lì annulla il testo) né con una finestra aperta | annullare-ripetere | D | |
| S6c-06 | Ctrl+Z ripetuto velocemente: un passo alla volta, i tasti in eccesso ignorati | annullare-ripetere | D | |
| S6c-07 | Solo il proprio lavoro su quel numero, mai i colleghi | annullare-ripetere | B | |
| S6c-08 | Fino a 50 passi, per 24 ore | annullare-ripetere | B | |
| S6c-09 | Una nuova azione dopo un annulla cancella i «ripeti» | annullare-ripetere | D | |
| S6c-10 | Annullabili: spostare pagine e gruppi (un passo), scambiare pagine e copertine, assegnare/togliere, frecce e rotazione, spazio, collega/scollega, stato e blocco, rinomina, modifica, creazione (multiplo = un passo), archivia/ripristina, eliminazione di contenuto o prenotazione | annullare-ripetere | D | |
| S6c-11 | Un contenuto eliminato resta recuperabile per 24 ore | annullare-ripetere | B | |
| S6c-12 | Non annullabili: pagine totali, bandella, «sposta le pagine dopo», scadenza, peso, chiusura, rubriche, eliminazione di riviste/numeri/utenti, export, sostituzione del PDF | annullare-ripetere | D | |
| S6c-13 | Sostituzione del PDF: passo in cronologia segnato «non annullabile» | annullare-ripetere | D | |
| S6c-14 | Rifiutato se un collega ha modificato le stesse pagine («Non posso annullare: la pagina 12 è stata modificata da Mario alle 16:42») o se lo spazio è occupato | problemi-frequenti | B | anche annullare-ripetere (D) |
| S6c-15 | In quel caso nulla cambia, il passo resta «non annullabile», i precedenti si fermano lì | annullare-ripetere | D | |
| S6c-16 | 🕘 Cronologia: ultime azioni con ora e stato (eseguita, annullata, non annullabile) | annullare-ripetere | B | |

## §6sexies Commenti sulle pagine (righe 286–295)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S6s-01 | Commenti dal 2026-10-29 | novita | N | |
| S6s-02 | Ogni pagina, copertine comprese, ha i suoi commenti | commenti | B | |
| S6s-03 | 💬 sulla card (anche in Lista): numero di commenti; giallo = non letti | commenti | B | anche legenda |
| S6s-04 | Pannello a destra con autore, data, testo, in ordine di data; aprirlo li segna come letti | commenti | B | |
| S6s-05 | Scrivere in fondo, «Invia» | commenti | B | |
| S6s-06 | «Avvisa via mail…»: persone scelte o «Tutti gli utenti di questa rivista» | commenti | B | |
| S6s-07 | @Nome Cognome nel testo (nome come in «Utenti», maiuscole indifferenti, anche @MarioRossi) | commenti | B | |
| S6s-08 | La mail contiene rivista, numero, pagina, autore, testo e link alla pagina | commenti | D | |
| S6s-09 | Niente mail a sé stessi, nessuno la riceve due volte; il pannello dice a chi è partita | commenti | D | |
| S6s-10 | «Modifica» ed «Elimina» sui propri commenti; un Admin elimina qualsiasi commento | commenti | B | anche ruoli |
| S6s-11 | Commento modificato: scritta «modificato»; nessuna nuova mail | commenti | D | |
| S6s-12 | «💬 N commenti non letti» nella testata: elenco con «Pagina 12 →» | commenti | B | |
| S6s-13 | «Segna tutti come letti»; scheda «Tutti» | commenti | B | |
| S6s-14 | I commenti seguono la pagina; pagina eliminata → «Pagina 47, eliminata» in «Tutti» | commenti | D | |
| S6s-15 | Admin, Redattore, Commerciale leggono e scrivono; Sola lettura legge | ruoli | R | anche commenti |
| S6s-16 | Compaiono entro un paio di secondi; non si annullano con Ctrl+Z (non agisce con il pannello aperto) | commenti | D | |

## §7 Caricare e vedere un PDF (righe 297–347)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S7-01 | Dal 2026-09-02 il PDF si carica sempre su un contenuto, mai su una pagina | novita | N | regola attuale in caricare-file (B) |
| S7-02 | Il file si carica su un contenuto; tre modi con lo stesso risultato | caricare-file | B | |
| S7-03 | «📎 Carica materiale» in basso su una card vuota: modulo già collegato alla pagina, promemoria «📌» | caricare-file | B | |
| S7-04 | Se il modulo non è visibile la pagina scorre per mostrarlo | caricare-file | D | |
| S7-05 | «+ Nuovo contenuto» nel pannello Contenuti: contenuto libero, poi trascinalo | caricare-file | B | |
| S7-06 | «⇪ Carica più file» (Caricamento multiplo) | caricamento-multiplo | R | rimando da caricare-file |
| S7-07 | Caricamento multiplo dal 2026-10-28 | novita | N | |
| S7-08 | Scegli Articoli o Pubblicità (vale per i file aggiunti da lì), trascina o clicca | caricamento-multiplo | B | |
| S7-09 | PDF, JPG, PNG, WebP fino a 100 MB ciascuno | caricamento-multiplo | B | |
| S7-10 | Titolo = nome del file senza estensione, trattini e underscore → spazi (es. «servizio_moto-elettrica.pdf» → «servizio moto elettrica») | caricamento-multiplo | D | |
| S7-11 | Spazio = pagine intere quante le pagine del PDF (1 per un'immagine); pubblicità «Pagina intera», senza cliente | caricamento-multiplo | D | |
| S7-12 | Poi correggi con ✏️ spazio, formato, cliente, posizione | caricamento-multiplo | B | |
| S7-13 | File uno alla volta: «in coda», percentuale, «✓ creato» o «✕ errore» con motivo | caricamento-multiplo | B | |
| S7-14 | Un file scartato non ferma gli altri; riepilogo («12 caricati, 1 scartato: …») con «↻ Riprova i falliti» | caricamento-multiplo | B | |
| S7-15 | Titolo già esistente: caricato come «… (2)», segnalato | caricamento-multiplo | D | |
| S7-16 | Non chiudere né ricaricare finché l'elenco non è finito (in coda persi, creati restano) | caricamento-multiplo | B | riquadro Attenzione |
| S7-17 | Compila titolo/tipo e occupazione, poi allega in «Allega PDF/immagine» | caricare-file | B | |
| S7-18 | «fino a 20MB» nel modulo | note-amministratore | A | contraddice S7-21 (100 MB): limite vecchio, va in Novità come «prima 20 MB» e tolto dalle istruzioni |
| S7-19 | Trascina: il riquadro diventa blu («Rilascia per caricare»), barra di avanzamento in percentuale | caricare-file | B | |
| S7-20 | Oppure clic sul riquadro per scegliere | caricare-file | B | |
| S7-21 | Fino a 100 MB dal 2026-10-27 (prima 20 MB), es. PDF di stampa ad alta risoluzione | caricare-file | B | data → novita (N) |
| S7-22 | Tipo sbagliato o troppo grande: rifiutato con messaggio rosso; per la dimensione dice peso e massimo | caricare-file | B | anche problemi-frequenti |
| S7-23 | Il massimo indicato è il limite reale del server, può essere sotto 100 MB | note-amministratore | A | |
| S7-24 | Anche sostituendo dal «Modifica contenuto» barra di avanzamento e massimo | caricare-file | D | |
| S7-25 | «Il file non è arrivato al server…»: riprova; se si ripete avvisa l'amministratore con l'ora | problemi-frequenti | B | |
| S7-26 | Nel registro del server c'è la causa | note-amministratore | A | |
| S7-27 | Più file trascinati insieme: ti chiede di trascinarne uno (un solo file per contenuto) | caricare-file | D | |
| S7-28 | Dopo il caricamento nome del file con ✓; l'anteprima si genera automaticamente | caricare-file | B | |
| S7-29 | Immagine pronta quasi subito; PDF: conteggio pagine poi miniatura | caricare-file | D | |
| S7-30 | Da «📎 Carica materiale» con PDF di una pagina (o immagine): assegnato subito alla pagina | caricare-file | B | |
| S7-31 | Pagina senza spazio: avviso giallo, contenuto creato ma non assegnato (nel pannello) | caricare-file | D | anche problemi-frequenti |
| S7-32 | Clic sulla miniatura apre il file originale in una nuova scheda | caricare-file | B | |
| S7-33 | ⚠️ rossa = anteprima fallita, file probabilmente danneggiato: crea un nuovo contenuto con un altro file | problemi-frequenti | B | |
| S7-34 | Non c'è più un pulsante «ricarica» per un contenuto già creato: allega di nuovo dal modulo | caricare-file | D | vedi nota su S3-83 |
| S7-35 | Ok stampa richiede un'anteprima dal contenuto assegnato; altrimenti messaggio d'errore | stato-pagina | R | S6-04 |
| S7-36 | Pagine Revisionata/Ok stampa senza anteprima compaiono in «⚠️ Avvisi» | avvisi | R | |
| S7-37 | Pubblicità con PDF più lungo del formato: non è un errore; riquadro azzurro «📄 Il PDF ha 3 pagine, il formato … ne occupa 1» | caricare-file | D | |
| S7-38 | «Usa tutte le 3 pagine» (proposta): 3 pagine intere consecutive | caricare-file | D | |
| S7-39 | Per il formato copertina: «la prima sulla copertina, le altre sulle pagine successive» | caricare-file | D | |
| S7-40 | «Usa solo le pagine da X a Y» (una sola: stessa in entrambi, es. da 2 a 2) | caricare-file | D | |
| S7-41 | Pagine scelte = formato → spazio del formato; di più → pagine intere | caricare-file | D | |
| S7-42 | Riga riassuntiva («→ La pubblicità occuperà 3 pagine intere (pagine del PDF 1–3)») | caricare-file | D | |
| S7-43 | Con «da 2 a 3» la prima pagina mostra la 2 del PDF, la successiva la 3 | caricare-file | D | |
| S7-44 | PDF con meno pagine del formato: nessuna scelta | caricare-file | D | |
| S7-45 | PDF multipagina da «+ Nuovo contenuto»: conserva tutte le pagine; trascinato occupa N pagine consecutive, ognuna con la sua miniatura; se non c'è spazio non viene piazzato | caricare-file | D | |
| S7-46 | PDF multipagina da «📎 Carica materiale»: propone di occupare le pagine successive, stessa finestra di conferma di sempre | caricare-file | D | |
| S7-47 | Nessuna pagina con anteprima: un bottone «Occupa le N pagine» | caricare-file | D | |
| S7-48 | Pagine già con anteprima: «Salta le pagine in conflitto», «Sovrascrivi le pagine in conflitto», «↪️ Sposta le pagine esistenti dopo» (contenuti, note e materiale spostati su nuove pagine bianche, totale allungato) | caricare-file | D | |
| S7-49 | «Annulla il caricamento»: niente viene scritto, contenuto non creato | caricare-file | D | |
| S7-50 | Pagine da un PDF multipagina collegate automaticamente (🔗) | caricare-file | D | anche gruppi-collegati |
| S7-51 | Scollegarne alcune senza sciogliere il gruppo: Selezione + «✂️ Scollega selezionate dal gruppo» | gruppi-collegati | B | ⚠ nell'app «✂️ Scollega» (nel menu stretto «✂️ Scollega dal gruppo») |
| S7-52 | Il 🔗 sul badge scioglie sempre tutto; «Scollega» solo le selezionate | gruppi-collegati | D | |
| S7-53 | Banner «🖼️ N pagine in elaborazione — circa Xs rimanenti in totale», stima su tutto il numero | caricare-file | D | |
| S7-54 | Sparisce da solo o si chiude con ✕ (ricompare al prossimo caricamento) | caricare-file | D | |
| S7-55 | Banner e stime restano anche ricaricando la pagina | caricare-file | D | |
| S7-56 | Ogni pagina mostra la miniatura della sua pagina del PDF; «apri file» porta alla pagina giusta | caricare-file | D | |
| S7-57 | Formato pubblicitario non conforme: confronto con il listino (3 mm di abbondanza per lato) | caricare-file | D | |
| S7-58 | Avviso rosso «⚠️ formato» con le dimensioni ricevute; non blocca | problemi-frequenti | B | anche legenda |
| S7-59 | «accetta» accanto all'avviso: sparisce, resta registrato chi l'ha confermato | caricare-file | D | |
| S7-60 | Dimensioni non misurabili o formato non chiaro: nessun avviso | caricare-file | D | |
| S7-61 | Pagine caricate prima del 2026-09-02 col vecchio sistema funzionano come prima (anteprima, storico 🕐) | note-amministratore | A | |
| S7-62 | Migrazione al nuovo sistema non ancora effettuata; nessun dato toccato | note-amministratore | A | |

## §8 Cruscotto pubblicitario (righe 351–360)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S8-01 | Pannello sempre visibile sopra la griglia, per il numero corrente | cruscotto-pubblicitario | B | |
| S8-02 | Percentuale di carico pubblicitario (numero grande), prenotate incluse | cruscotto-pubblicitario | B | |
| S8-03 | Dettaglio «X già assegnate + Y prenotate» | cruscotto-pubblicitario | B | |
| S8-04 | Calcolata in dodicesimi, esatta: tre 1/3 su 3 pagine = 33,33% | cruscotto-pubblicitario | D | dodicesimi → note-amministratore |
| S8-05 | Le prenotate contano per l'occupazione del loro modulo | cruscotto-pubblicitario | D | |
| S8-06 | Soglia e colore di allarme tolti il 2026-10-27 | novita | N | |
| S8-07 | Stessa percentuale del riepilogo in alto | cruscotto-pubblicitario | R | |
| S8-08 | Le copertine non entrano mai nel calcolo (né nel denominatore né i contenuti) | cruscotto-pubblicitario | D | |
| S8-09 | Inserzioni assegnate vs pubblicità non ancora assegnate | cruscotto-pubblicitario | B | |
| S8-10 | Pubblicità da assegnare (segnaposto) in tutto il numero, copertine comprese, per formato | cruscotto-pubblicitario | B | |
| S8-11 | Riepilogo per formato e per stato commerciale (in trattativa, confermata, annullata), prenotazioni incluse | cruscotto-pubblicitario | D | |
| S8-12 | «⬇️ CSV» e «⬇️ PDF» in alto a destra scaricano il riepilogo | esporta-report-pubblicita | B | |

## §8bis Pubblicità prenotate e chiusura (righe 364–376)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S8b-01 | «📌 Pubblicità prenotate» sopra la griglia: tutte le pubblicità del numero, con stato automatico | pubblicita-prenotate | B | |
| S8b-02 | 🟠 Prenotato: dati inseriti ma non su nessuna pagina | pubblicita-prenotate | B | anche legenda |
| S8b-03 | Pagina preferita mostrata accanto (es. «posizione preferita: pagina 7»), solo promemoria | pubblicita-prenotate | D | |
| S8b-04 | 🔵 Assegnato: su una pagina senza PDF pronto (o formato non conforme non accettato) | pubblicita-prenotate | B | |
| S8b-05 | 🟢 Completo: su una pagina e materiale a posto | pubblicita-prenotate | B | |
| S8b-06 | «🗑 Elimina» solo per le «Prenotato»; per una assegnata usa ✕ sulla pagina | pubblicita-prenotate | B | |
| S8b-07 | «🔒 Chiudi numero» in cima al pannello | chiudere-numero | B | |
| S8b-08 | Non si chiude finché c'è una pubblicità non «Completo»; messaggio con i clienti | chiudere-numero | B | anche problemi-frequenti |
| S8b-09 | Per sbloccare: aspetta il materiale o elimina la prenotazione | chiudere-numero | B | |
| S8b-10 | Numero chiuso → «Archivio» della rivista, etichetta «🔒 Numero chiuso» | chiudere-numero | B | |
| S8b-11 | Non c'è modo di riaprirlo da qui | chiudere-numero | D | riquadro Attenzione |

## §9 Modificare il numero totale di pagine (righe 380–393)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S9-01 | «⚙️ Pagine interne» nella barra; si cambiano solo le pagine interne, le copertine restano | pagine-interne | B | |
| S9-02 | Si scrive liberamente; controllo e ricalcolo quando esci dal campo | pagine-interne | D | |
| S9-03 | Valore non valido (vuoto, non numerico, negativo): avviso, nessuna operazione | pagine-interne | D | |
| S9-04 | Aumentare: nuovo totale, «in coda» o «in una posizione specifica» (le successive slittano), «Applica»; nuove pagine bianche | pagine-interne | B | |
| S9-05 | Ridurre: tolte sempre le pagine in coda | pagine-interne | B | riquadro Attenzione |
| S9-06 | Prima di confermare: quante pagine, quali con contenuti (tornano in «Da assegnare», non cancellati), quali con PDF (perso davvero) | pagine-interne | B | |
| S9-07 | «Conferma rimozione definitiva» + conferma del browser; senza, nulla cambia | pagine-interne | B | |

## §9bis Copertine e bandella (righe 397–415)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S9b-01 | Le copertine non sono pagine del timone; ogni numero ne ha sempre quattro | copertine | B | |
| S9b-02 | I (copertina), II (retro, a sinistra della 1), III (interna della quarta), IV (retro della rivista) | copertine | B | anche glossario |
| S9b-03 | Create da sole con il numero, separate dalle pagine interne | copertine | B | |
| S9b-04 | Le pagine interne partono sempre da 1; nessuno deve sommare o sottrarre le copertine | copertine | B | |
| S9b-05 | Fascia azzurra «📘 Copertine» in cima, prima della pagina 1, ordine I, bandella, II, III, IV | copertine | B | |
| S9b-06 | Etichetta blu «📘 I di copertina» e contorno azzurro; sempre la scritta | copertine | D | anche legenda |
| S9b-07 | Su una copertina si fa tutto come su una pagina (assegnare, carica materiale, stato, blocco, ✕) | copertine | B | |
| S9b-08 | Trascinare l'etichetta su un'altra copertina: si scambiano (es. la pubblicità passa dalla II alla III con stato e PDF) | copertine | B | |
| S9b-09 | Copertina non va tra le pagine interne né viceversa | copertine | D | |
| S9b-10 | Le copertine non entrano in selezione, scambio, frecce | copertine | D | |
| S9b-11 | Pubblicità di più pagine sulla II continua su 1, 2…; sulla III occupa le ultime interne e poi la III | copertine | D | |
| S9b-12 | I e IV: una sola pagina; per più pagine un messaggio dice dove partire | copertine | D | |
| S9b-13 | Bandella = lembo ripiegato agganciato alla copertina (battente) | copertine | B | anche glossario |
| S9b-14 | «+ Aggiungi bandella» nella fascia; «− Togli bandella» solo se vuota e non bloccata | copertine | B | |
| S9b-15 | Con la bandella le facciate di copertina sono 6 invece di 4, il peso si aggiorna | copertine | B | |
| S9b-16 | Rivista con 6 pagine di copertina nei parametri del peso: i nuovi numeri nascono con la bandella | copertine | D | |
| S9b-17 | In cima «N pagine interne · copertine 4 (X assegnate)» | copertine | D | ⚠ testo da verificare: il riepilogo mostra «Copertine: X/4 assegnate» |
| S9b-18 | Cruscotto e avvisi contano solo le interne; una pubblicità sulla IV non cambia la percentuale | copertine | D | |
| S9b-19 | Nell'export del timone le copertine sono in una sezione a sé, prima delle interne | esporta-timone-pdf | D | |
| S9b-20 | Numeri creati prima del 2026-10-26 convertiti (copertine I–IV, interne rinumerate da 1, II e III mancanti create vuote) | note-amministratore | A | anche novita |

## §9ter Peso della rivista (righe 419–450)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S9t-01 | Riquadro «⚖️ Peso» in cima al numero: peso teorico di una copia, margine ±, scaglione, peso della tiratura | peso | B | |
| S9t-02 | Stesse formule del foglio Excel usato finora | peso | D | |
| S9t-03 | Esempio «510,7 g ± 31 g (481 – 541 g)»; peso teorico, «±» = scostamento massimo nel caso peggiore | peso | B | |
| S9t-04 | «ℹ️ Come è calcolato il margine» elenca le tolleranze | peso | D | |
| S9t-05 | Scaglione: verde (resta anche nel caso peggiore), giallo («Rischio di passare allo scaglione X g»), rosso sopra 1000 g | peso | B | |
| S9t-06 | Scaglioni 250, 350, 500, 1000 g; limite incluso (500,0 g sta nel 500) | peso | B | |
| S9t-07 | Dettaglio Interno / Copertina / Maggiorazione / Tiratura | peso | D | |
| S9t-08 | Pagine interne = quelle del timone (es. 208); copertina = 4 o 6 con bandella; aggiornamento automatico | peso | D | |
| S9t-09 | «Modifica parametri» (Admin e Redattore) | peso | B | anche ruoli |
| S9t-10 | Sezione «Carta»: grammature e pagine copertina; per un numero esistente le pagine copertina non si scrivono (campo grigio) | peso | D | |
| S9t-11 | Grammatura in g/m² (80 interno, 170 copertina), non il peso di una pagina | peso | B | |
| S9t-12 | Peso a pagina mostrato sotto (es. «80 g/m² → 2,268 g a pagina · 208 pagine = 471,7 g») | peso | D | |
| S9t-13 | Grammatura sotto 30 o sopra 400 rifiutata con messaggio | peso | D | |
| S9t-14 | Virgola o punto (80,5 o 80.5) | peso | D | |
| S9t-15 | Formato in mm (210 × 270, non 21 × 27); sotto 50 rifiutato | peso | B | |
| S9t-16 | N. copie, n. bancali, peso reale; maggiorazione e tolleranze sotto «Avanzate» | peso | D | |
| S9t-17 | Anteprima prima di salvare con la differenza (es. «+12,4 g rispetto al valore salvato …, passa da scaglione 500 g a 1.000 g.») | peso | D | |
| S9t-18 | Badge «modificato»; «Ripristina default rivista» (poi «Salva») | peso | D | |
| S9t-19 | «Salva» modifica solo questo numero | peso | B | |
| S9t-20 | Pagine interne pari (un foglio ha due facciate): dispari blocca il calcolo con messaggio rosso; non multiplo di 4: solo avviso | peso | D | anche problemi-frequenti |
| S9t-21 | Ogni modifica resta nella cronologia | peso | D | |
| S9t-22 | «Parametri peso da configurare»: mancano valori (spesso numeri vecchi); inseriscili | problemi-frequenti | B | |
| S9t-23 | «Grammatura … / Formato … fuori dall'intervallo ammesso»: unità sbagliata (0,8 invece di 80; 21 invece di 210) | problemi-frequenti | B | |
| S9t-24 | Peso reale: pesa una copia, scrivi in «Peso reale misurato (g)» | peso | D | |
| S9t-25 | Il riquadro mostra differenza e maggiorazione esatta | peso | D | |
| S9t-26 | Un Admin la salva con «Copia nei default della rivista» dopo conferma; vale per i numeri successivi | peso | D | |
| S9t-27 | Il suggerimento non viene mai applicato da solo | peso | D | |
| S9t-28 | «Simula +/- pagine interne» (es. 8 o -4): non modifica nulla | peso | D | |
| S9t-29 | Pagine ancora aggiungibili nello scaglione, a passi di 2 | peso | D | |
| S9t-30 | Valori di partenza dalla rivista, modificabili alla creazione del numero («Carta e peso») | peso | D | |

## §9quater Segnature (righe 454–460)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S9q-01 | Segnature dal 2026-10-29 | novita | N | |
| S9q-02 | Segnature di stampa, di solito da 32 pagine (trentaduesimi) | segnature | B | anche glossario |
| S9q-03 | Nella barra: «Segnature: 3 trentaduesimi + 24 pagine (16 + 8)» | segnature | B | |
| S9q-04 | Tra parentesi il resto in formati (16esimo, 8vo, quartino da 4) | segnature | B | |
| S9q-05 | Non multiplo di 4: «⚠️ 126 non è multiplo di 4: non stampabile così» in rosso | segnature | B | anche problemi-frequenti |
| S9q-06 | Suggerimento dopo la freccia (es. «→ +8 = 128 · −24 = 96») | segnature | B | |
| S9q-07 | Si aggiorna da solo, anche per i colleghi | segnature | D | |
| S9q-08 | Fascia colorata alternata azzurro/viola, linea rossa «32° n. 2» sulla prima pagina di ogni segnatura | segnature | B | anche legenda |
| S9q-09 | In Doppia pagina la linea è dentro la coppia [32 \| 33]; in Lista sopra la riga | segnature | D | |
| S9q-10 | Copertine senza fascia, salvo che la rivista le conti | segnature | D | |
| S9q-11 | Bottone «32°» all'inizio del conteggio (blu = visibili); preferenza personale | segnature | B | |
| S9q-12 | Impostazioni (Admin): «Modifica rivista» → «📐 Segnature di stampa»: 8, 16, 32, 64 | segnature | D | anche rivista |
| S9q-13 | «Copertina inclusa nelle segnature»: di solito no; se sì 4 (o 6) facciate in testa | segnature | D | |
| S9q-14 | Valgono subito per tutti i numeri della rivista | segnature | D | |

## §10 Storico e cronologia (righe 462–466)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S10-01 | «📜 Storico spostamenti»: ultimi 50 spostamenti con chi, da/a, quando | storico-e-cronologia | B | ⚠ bottone «📜 Storico» |
| S10-02 | «🕓 Cronologia» sotto la barra: tutto il resto (stati, contenuti, spazio, PDF, pagine totali, modifiche e rinomine, pubblicità previste) | storico-e-cronologia | B | |
| S10-03 | Le voci vecchie della soglia di allarme restano leggibili | note-amministratore | A | |
| S10-04 | Ogni riga: chi, cosa, quando | storico-e-cronologia | B | |
| S10-05 | Non si aggiorna mentre è aperta: chiudila e riaprila | storico-e-cronologia | D | |

## §11 Avvisi automatici (righe 470–477)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S11-01 | Riquadro «⚠️ Avvisi» sotto la barra: qualcosa da controllare, non bloccante | avvisi | B | |
| S11-02 | Revisionata/Ok stampa senza contenuto | avvisi | B | |
| S11-03 | Revisionata/Ok stampa senza PDF (per Ok stampa anche bloccante) | avvisi | B | |
| S11-04 | Contenuto su pagine non consecutive (es. 3 e 9), a volte voluto | avvisi | B | |
| S11-05 | Compare solo se c'è almeno un avviso | avvisi | D | |
| S11-06 | Il «non multiplo di 4» ha un avviso dedicato altrove | avvisi | D | |
| S11-07 | Carico pubblicitario senza soglie né avvisi dal 2026-10-27 | novita | N | |

## §12 Creare rivista / numero, duplicare (righe 481–505)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S12-01 | Nuova rivista (solo Admin): «+ Nuova rivista» da «Le tue riviste»; nome, periodicità, colore, note | rivista | B | |
| S12-02 | Soglia di allarme tolta il 2026-10-27 | novita | N | |
| S12-03 | Opzione «il battente conta nel totale pagine» tolta il 2026-10-26 (la bandella è una copertina a parte) | novita | N | |
| S12-04 | Nuovo numero: «+ Nuovo numero» (Admin e Redattori con accesso) | creare-numero | B | |
| S12-05 | Campi: titolo, data di uscita (opz.), scadenza stampa (opz.), pagine interne, note | creare-numero | B | |
| S12-06 | Le quattro copertine si aggiungono da sole, non vanno contate | creare-numero | B | |
| S12-07 | Con 6 «pagine copertina» in «Carta e peso» nasce con la bandella | creare-numero | D | |
| S12-08 | Campo pagine cancellato: anteprima del peso ferma finché non c'è un valore valido | creare-numero | D | |
| S12-09 | Creazione con campo vuoto: errore sotto il campo (prima la pagina andava in errore) | creare-numero | D | «prima…» → novita (N) |
| S12-10 | Anteprime aggiornate un attimo dopo aver smesso di scrivere; avvisi («Non è un multiplo di 4», «Non ci stanno», grammatura fuori scala) quando esci dal campo | creare-numero | D | |
| S12-11 | Dal 2026-10-27 (prima il campo pagine poteva perdere cifre) | novita | N | |
| S12-12 | Note fino a 2000 caratteri | creare-numero | D | |
| S12-13 | «Duplica struttura da un numero precedente» (se ci sono altri numeri) | creare-numero | B | |
| S12-14 | Pagine interne precompilate dal numero scelto (modificabili) | creare-numero | D | |
| S12-15 | Ogni pagina e copertina eredita il tipo (editoriale/pubblicità/mista/bianca) | creare-numero | B | |
| S12-16 | Non copiati: contenuti, stati, PDF | creare-numero | B | |
| S12-17 | «📢 Pubblicità previste»: quante per tipo (intera, 2/3, 1/2, piedino 1/3, vetrina 1/4) | segnaposto | B | anche creare-numero |
| S12-18 | Mentre scrivi: pagine di pubblicità in tutto (es. «7 pagine di pubblicità (8 inserzioni)») | segnaposto | D | |
| S12-19 | Percentuale sulle pagine interne (copertine escluse) | segnaposto | D | |
| S12-20 | Le posizioni previste | segnaposto | D | |
| S12-21 | Errore rosso se non ci stanno: il numero non viene creato | segnaposto | B | anche problemi-frequenti |
| S12-22 | Diventano segnaposto già nel timone | segnaposto | B | |
| S12-23 | Regola 1: prime 4 sulle copertine II, III, IV, poi I; prima intere poi frazioni più grandi | segnaposto | D | |
| S12-24 | Usata la I: avviso in cima al numero appena creato | segnaposto | D | |
| S12-25 | Regola 2: intere rimaste nelle interne una sì e una no da pagina 2 (2, 4, 6…) | segnaposto | D | |
| S12-26 | Regola 3: frazioni rimaste una per pagina nella stessa sequenza; resto libero per l'editoriale | segnaposto | D | |
| S12-27 | Regola 4: finite le alterne, le libere partendo dal fondo | segnaposto | D | |
| S12-28 | Posizioni decise solo alla creazione, mai ricalcolate | segnaposto | B | |
| S12-29 | Segnaposto nel timone: bordo tratteggiato, etichetta «Da assegnare», titolo automatico, niente cliente né PDF | segnaposto | B | ⚠ verificare: nel pannello l'etichetta è «SEGNAPOSTO» |
| S12-30 | Contano nella percentuale e nel contatore segnaposto del cruscotto | segnaposto | D | |
| S12-31 | Trasformarlo in pubblicità vera: doppio click e cliente, o ✏️ con cliente o PDF | segnaposto | B | |
| S12-32 | Titolo, formato, stato o spazio da soli non bastano | segnaposto | D | |
| S12-33 | Dal 2026-10-28 anche il titolo da solo non basta più | novita | N | |
| S12-34 | Togliendo il cliente a una pubblicità senza file torna segnaposto | segnaposto | D | |
| S12-35 | Segnaposto a mano: «+ Nuovo contenuto» → Pubblicità senza cliente | segnaposto | B | |
| S12-36 | La mezza pagina nasce orizzontale; se verticale cambia il formato da ✏️ | segnaposto | D | |
| S12-37 | «⚖️ Carta e peso» nella creazione, già compilata dalla rivista: formato, grammature, copertina, copie, bancali, «Avanzate» | creare-numero | D | |
| S12-38 | Valori cambiabili solo per questo numero, badge «modificato», «Ripristina default rivista» | creare-numero | D | |
| S12-39 | Peso a pagina e anteprima del peso mentre scrivi | creare-numero | D | |

## §12bis Modificare rivista, redattore, scadenza (righe 509–521)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S12b-01 | «Modifica rivista» (solo Admin) dalla pagina della rivista: campi della creazione | rivista | B | |
| S12b-02 | Redattore di riferimento: responsabile; assegnarlo dà accesso alla rivista | rivista | B | |
| S12b-03 | Non serve aggiungerlo anche da Utenti | rivista | D | |
| S12b-04 | Riceve le mail di sollecito della scadenza | scadenza-stampa | B | |
| S12b-05 | Cambiare redattore non toglie l'accesso al precedente (va tolto a mano da Utenti) | rivista | D | |
| S12b-06 | Scadenza stampa in creazione o con «📅 Scadenza stampa» nella barra | scadenza-stampa | B | |
| S12b-07 | Preavviso di default 3 giorni | scadenza-stampa | B | |
| S12b-08 | Il preavviso è un'impostazione tecnica, non modificabile dall'interfaccia | note-amministratore | A | |
| S12b-09 | Mail al redattore di riferimento con numero, rivista e link al timone | scadenza-stampa | B | |
| S12b-10 | Una sola volta per scadenza; spostandola può ripartire; ✉️ accanto al campo se già inviata | scadenza-stampa | D | |
| S12b-11 | Senza redattore assegnato nessuna mail | scadenza-stampa | D | |
| S12b-12 | Segnature di stampa nella stessa pagina | segnature | R | |
| S12b-13 | «Carta e peso — valori di default»: formato, grammature, copertina, maggiorazione, peso del bancale, tolleranze | rivista | D | |
| S12b-14 | Valgono solo per i nuovi numeri (copiati alla creazione) | rivista | B | |
| S12b-15 | Grammature in g/m² (80 e 170), peso a pagina sotto | rivista | D | |
| S12b-16 | Anteprima con «Pagine totali per l'anteprima (non salvate)» proposte dall'ultimo numero | rivista | D | ⚠ nell'app «Pagine interne per l'anteprima (non salvate)» |

## §13 Gestire gli utenti (righe 525–535)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S13-01 | Voce «Utenti» nel menu (solo Admin) | utenti | B | |
| S13-02 | Elenco: utenti, ruolo, numero di riviste | utenti | B | |
| S13-03 | Nuovo utente: nome, email, password, ruolo, riviste (checkbox, irrilevante per Admin) | utenti | B | |
| S13-04 | Modifica: stessi campi; password vuota = invariata | utenti | B | |
| S13-05 | Eliminare un utente dal 2026-10-28 | novita | N | |
| S13-06 | In fondo a «Modifica utente», riquadro «Elimina l'utente», conferma in due passaggi con la sua email | utenti | B | |
| S13-07 | Il suo lavoro resta, con «Utente eliminato» al posto del nome | utenti | D | |
| S13-08 | Tolti gli accessi, sessioni chiuse subito | utenti | D | |
| S13-09 | Non si elimina sé stessi né l'ultimo amministratore (bottone disattivato, tooltip) | utenti | D | |
| S13-10 | Nessuno elimina il proprio account dal profilo: solo un amministratore | utenti | D | |

## §13bis Eliminare rivista, numero, utente (righe 539–550)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S13b-01 | Eliminazione da parte dell'amministratore dal 2026-10-28 | novita | N | |
| S13b-02 | Numero: «🗑️ Elimina» accanto al numero nella pagina della rivista | eliminare | B | |
| S13b-03 | Rivista con tutti i numeri: «Modifica rivista», riquadro «Elimina la rivista» | eliminare | B | |
| S13b-04 | Utente: vedi Utenti | eliminare | R | |
| S13b-05 | Definitiva, nessun cestino: conferma in due passaggi | eliminare | B | riquadro Attenzione |
| S13b-06 | Passo 1: riepilogo (pagine, contenuti, file, spazio MB/GB; per una rivista i numeri); «Continua» o «Annulla»/Esc | eliminare | B | |
| S13b-07 | Passo 2: nome esatto (email per un utente) e la tua password, «Elimina definitivamente» | eliminare | B | |
| S13b-08 | Dopo 5 password sbagliate in un minuto si aspetta un minuto | eliminare | D | |
| S13b-09 | Chi ha il numero aperto torna all'elenco con «Il numero che avevi aperto è stato eliminato da un amministratore» | eliminare | D | anche problemi-frequenti |
| S13b-10 | Ogni eliminazione resta registrata (chi, cosa, quando) | eliminare | D | |

## §14 Cosa può fare ciascun ruolo (righe 554–565)

| ID | Informazione | Destinazione | Liv. | Nota |
|---|---|---|---|---|
| S14-01 | Admin: tutte le riviste; commenti (elimina anche altrui); pubblicità; articoli e pagine; crea numeri e riviste; gestisce/elimina utenti, riviste, numeri | ruoli | B | |
| S14-02 | Redattore: riviste con accesso; commenti; pubblicità; articoli e pagine e numeri solo sulle sue riviste; no riviste né utenti | ruoli | B | |
| S14-03 | Commerciale: riviste con accesso; commenti; pubblicità (dal 2026-10-28); niente articoli, numeri, riviste, utenti | ruoli | B | data → novita (N) |
| S14-04 | Sola lettura: riviste con accesso; legge i commenti; nient'altro | ruoli | B | |
| S14-05 | Commerciale dal 2026-10-28: solo pubblicità | novita | N | |
| S14-06 | Commerciale crea pubblicità («+ Nuovo contenuto» con tipo già Pubblicità, caricamento multiplo) | ruoli | B | |
| S14-07 | Le modifica (✏️, doppio click) e le sposta (trascina, frecce, rotazione, «Posizione nel timone», togliere) | ruoli | B | |
| S14-08 | Sposta pagine intere o gruppi solo senza articoli | ruoli | B | |
| S14-09 | Sugli articoli comandi disattivati, tooltip «Solo la redazione può spostare questo contenuto» | ruoli | D | anche problemi-frequenti |
| S14-10 | Chip dell'articolo con cursore 🚫, non trascinabile; pagina con articoli senza maniglia ⠿ | ruoli | D | |
| S14-11 | Stato, blocco, gruppi, pagine totali, scadenza restano della redazione | ruoli | B | |
| S14-12 | Controlli anche lato server: tentativo diretto rifiutato | note-amministratore | A | |
| S14-13 | Chi consulta può esportare il report CSV/PDF e il PDF del timone | ruoli | D | anche esporta-report-pubblicita |

## Riepilogo dei conteggi

| Livello | Righe |
|---|---|
| B — testo principale | 303 |
| D — Dettagli | 228 |
| N — Novità | 22 |
| A — Note per l'amministratore | 14 |
| R — rimando a una riga tenuta | 16 |
| **Totale** | **583** |

In più, 5 righe B/D hanno anche una data o un «prima era…» da spostare in «Novità» (S5-33, S7-21, S9b-20, S12-09, S14-03): «Novità» avrà quindi 27 voci. Il glossario prende i termini dalle righe con «anche glossario» oltre alle 3 dirette.

Nessuna riga senza destinazione e nessun ID ripetuto (controllo con uno script sulla colonna «Liv.», 2026-10-29).

### Incoerenze del manuale archiviato (corrette in Fase 1 dopo averle verificate nel codice)

| # | Righe | Cosa diceva il manuale | Cosa fa il codice | Cosa dice ora il manuale |
|---|---|---|---|---|
| 1 | S5-43, S6c-11 | §5: «🗑️ Elimina» è una cancellazione **definitiva**, non recuperabile; §6quinquies: un contenuto eliminato resta recuperabile per 24 ore | `Grid::deleteUnassignedContent()` chiama `$content->delete()` dentro `recordStep('content.delete', …)`: `Content` usa `SoftDeletes`, il passo si annulla con Ctrl+Z; `PruneTimelineHistory::handle()` (ogni ora) elimina davvero i contenuti nel cestino da più di 24 ore, file compresi. Il testo di conferma nel pannello dice «Si può annullare con Ctrl/⌘+Z per 24 ore» (`partials/content-panel-item.blade.php`) | `pannello-contenuti`: riquadro Attenzione «si recupera solo con Ctrl+Z entro 24 ore; dopo 24 ore l'eliminazione è definitiva, file compresi» |
| 2 | S7-18, S7-21 | §7: «fino a **20MB**» e, poche righe dopo, «fino a 100 MB (prima 20 MB)» | Limite in `config/timone.php` → `upload_max_kb` = 102400 KB (100 MB), usato da `App\Support\ContentFormRules`; il commento lì ricorda che prima era 20 MB. Il limite mostrato è quello effettivo (`App\Support\Uploads\UploadLimits::effective()`) | `caricare-file`: «fino a 100 MB», il massimo è scritto nel riquadro; «prima 20 MB» in `novita`; il limite reale del server in `note-amministratore` |
| 3 | S3-83, S7-34 | §3: «⚠️ PDF (rosso) … clic per ricaricare il file»; §7: «non c'è più un pulsante di ricarica» | In `partials/page-card.blade.php` lo stato fallito è un semplice `<span title="Generazione anteprima fallita">⚠️ PDF</span>`, non cliccabile. Il file si sostituisce da `ContentEdit::save()` (campo «Materiale di stampa») | `legenda` e `problemi-frequenti`: ⚠️ PDF = anteprima fallita; si allega un file diverso da «Modifica contenuto» o si crea un nuovo contenuto. Nessun «clic per ricaricare» |
| 4 | S4b-04 | §4bis: «il pannello "⚙️ Pagine totali"» | Il bottone è «⚙️ Pagine interne» (`grid.blade.php`), come dice anche §9 | `bloccare-pagina` e `pagine-interne`: «⚙️ Pagine interne» |
| 5 | S6-01 | §6: lo stato si cambia «in modalità Griglia o Lista» | Il menu dello stato è in `partials/page-card.blade.php` (`wire:change="changePageStatus…"`), usata anche dalla vista Doppia pagina; `TimoneGridTest` verifica che la doppia pagina abbia i select modificabili | `stato-pagina`: «Funziona in tutte e tre le viste» |
| 6 | S5-01 | §5: «+ Nuovo contenuto» «sopra la griglia» | Dal 2026-10-28 il componente `livewire:timone.content-create` è dentro il pannello Contenuti (`grid.blade.php`, sezione del pannello) | `creare-contenuto`: «nel pannello Contenuti»; «prima erano sopra il timone» in `novita` |
| 7 | S9b-17 | §9bis: in cima al timone «N pagine interne · copertine 4 (X assegnate)» | Quella riga è solo per i lettori di schermo (`<h3 class="sr-only">` in `grid.blade.php`); a video il riepilogo mostra «Copertine: X/4 assegnate (Y libere)» (`partials/summary-bar.blade.php`) | `copertine` e `riepilogo-del-numero`: «Copertine: X/4 assegnate (Y libere)» |

### Controllo delle righe (Fase 1)

- **Destinazioni**: ogni riga punta a una pagina esistente (controllato con uno script sulla colonna «Destinazione»; «bandella» → «copertine» dopo l'unione).
- **Contenuto**: per ogni riga uno script cerca nella pagina di destinazione gli elementi distintivi della colonna «Informazione» (testi tra «», numeri con unità, emoji). Su 583 righe ne ha segnalate 23: 3 erano dettagli davvero mancanti nella pagina giusta (esempio «Novembre 2026» nel glossario, «pagina 5 e pagina 6» nei gruppi, «almeno 2 pagine» per Ctrl+Shift+G) e sono stati aggiunti; le altre 20 sono bottoni rinominati (compare il nome nuovo), date spostate in «Novità», frasi riformulate con lo stesso significato (S0-02, S5t-22, S6c-12) o falsi positivi di punteggiatura (S9t-14, S9t-23).
- **Termini**: `php artisan manuale:verifica` (anche nella suite, `tests/Feature/ManualPagesTest.php`) controlla 428 grassetti, 64 emoji, 16 scorciatoie e 21 valori con unità del manuale archiviato: nessuno manca; 23 eccezioni motivate in `App\Support\Manual\ManualVerifier::EXCEPTIONS`.

### Nomi di bottoni non più corrispondenti all'app (verificati nelle view; nel nuovo manuale si usano i nomi attuali, i vecchi sono nelle eccezioni di `manuale:verifica` con il nome nuovo accanto)
| Nel manuale | Nell'app |
|---|---|
| ☑️ Selezione multipla | ☑️ Selezione (testo per lettori di schermo «Selezione multipla») |
| 🔀 Modalità scambio (bottone) | 🔀 Scambio (il banner dice «Modalità scambio attiva») |
| 📜 Storico spostamenti (bottone) | 📜 Storico (il pannello si intitola «Storico spostamenti») |
| 🔗 Collega selezionate | 🔗 Collega |
| ✂️ Scollega selezionate dal gruppo | ✂️ Scollega (menu stretto: ✂️ Scollega dal gruppo) |
| Bloccare / Sbloccare selezionate | 🔒 Blocca / 🔓 Sblocca |
| Seleziona tutte / Deseleziona tutte | Tutte / Deseleziona |
| menu «Cambia stato in» | «Stato:» nella fascia della selezione |
| ⚙️ Pagine totali (§4bis) | ⚙️ Pagine interne |
| Pagine totali per l'anteprima (non salvate) | Pagine interne per l'anteprima (non salvate) |
| «N pagine interne · copertine 4 (X assegnate)» | «Copertine: X/4 assegnate (Y libere)» nel riepilogo |
| Banner «🖼️ N pagine in elaborazione — circa Xs rimanenti in totale» | corrisponde (`grid.blade.php`); in più, senza stima: «— stima non ancora disponibile» (aggiunto in `caricare-file`) |

## Struttura (proposta in Fase 0, approvata con «Le copertine» e «La bandella» unite: 46 pagine)

Un file per pagina in `resources/manuale/<slug>.md`. Le pagine qui sotto sono le «Destinazioni» dell'inventario.

| # | Categoria | Slug | Titolo | Descrizione |
|---|---|---|---|---|
| 1 | Inizia qui | inizia-qui | Inizia qui | Cos'è il timone, rivista → numero → timone, come entrare, schermata annotata |
| 2 | Inizia qui | cosa-vuoi-fare | Cosa vuoi fare? | 12 riquadri con i compiti più frequenti |
| 3 | Lavorare sul timone | viste-del-timone | Le viste del timone | Griglia, Doppia pagina, Lista, zoom S/M/L, aggiornamenti dai colleghi |
| 4 | Lavorare sul timone | riepilogo-del-numero | Il riepilogo in alto | Pagine, spazio libero, pubblicità, segnaposto, copertine, peso; evidenziare bianche e segnaposto |
| 5 | Lavorare sul timone | spostare-pagine | Spostare le pagine | Trascinare, frecce da tastiera, scorrimento automatico, conflitti con i colleghi |
| 6 | Lavorare sul timone | scambiare-pagine | Scambiare due pagine | La modalità scambio |
| 7 | Lavorare sul timone | stato-pagina | Cambiare lo stato di una pagina | I cinque stati e la regola dell'Ok stampa |
| 8 | Lavorare sul timone | bloccare-pagina | Bloccare una pagina | Cosa impedisce il blocco e il suo limite |
| 9 | Lavorare sul timone | selezione-multipla | Selezionare più pagine | La fascia della selezione e le azioni su tante pagine |
| 10 | Lavorare sul timone | gruppi-collegati | Collegare pagine in un gruppo | Collega, scollega, regole dei gruppi |
| 11 | Lavorare sul timone | annullare-ripetere | Annullare e ripetere | Ctrl+Z, cosa si annulla, quando viene rifiutato, cronologia |
| 12 | Lavorare sul timone | commenti | Commenti sulle pagine | Leggere, scrivere, avvisare via mail, non letti |
| 13 | Lavorare sul timone | segnature | Segnature (trentaduesimi) | Conteggio, confini sulla griglia, impostazioni |
| 14 | Lavorare sul timone | storico-e-cronologia | Storico e cronologia | Chi ha fatto cosa e quando |
| 15 | Lavorare sul timone | avvisi | Avvisi automatici | Il riquadro «⚠️ Avvisi» |
| 16 | Contenuti e pubblicità | pannello-contenuti | Il pannello Contenuti | Schede, filtri, ricerca, archiviare, eliminare |
| 17 | Contenuti e pubblicità | creare-contenuto | Creare un articolo o una pubblicità | Il modulo «+ Nuovo contenuto», rubriche, posizione iniziale |
| 18 | Contenuti e pubblicità | assegnare-contenuto | Mettere un contenuto su una pagina | Trascinare, «Assegna alla selezione», «Vai a pag.», più pagine, togliere |
| 19 | Contenuti e pubblicità | spazio-occupato | Spazio occupato (occupazione) | Pagine intere + frazione, distribuzione, capienza |
| 20 | Contenuti e pubblicità | disposizione-nella-pagina | I contenuti dentro la pagina | Griglia 6×6, menu ⋯, frecce, ruota, anteprime |
| 21 | Contenuti e pubblicità | modificare-contenuto | Modificare o rinominare un contenuto | Doppio click, finestra «Modifica contenuto», cambiare il file |
| 22 | Contenuti e pubblicità | caricare-file | Caricare un PDF o un'immagine | Allegare, limiti, anteprime, PDF di più pagine, formato non conforme |
| 23 | Contenuti e pubblicità | caricamento-multiplo | Caricare tanti file insieme | «⇪ Carica più file» |
| 24 | Contenuti e pubblicità | segnaposto | Pubblicità segnaposto e previste | Previste alla creazione del numero, trasformarle in vere |
| 25 | Contenuti e pubblicità | pubblicita-prenotate | Pubblicità prenotate | Il pannello «📌 Pubblicità prenotate» e i suoi stati |
| 26 | Contenuti e pubblicità | cruscotto-pubblicitario | Il cruscotto pubblicitario | Percentuale, assegnate/prenotate, per formato e stato |
| 27–28 | Copertine e bandella | copertine | Le copertine e la bandella | Fascia in alto, scambio, pubblicità su più pagine, bandella (unite su richiesta dell'utente, Fase 1) |
| 29 | Numero e rivista | riviste-e-numeri | Trovare una rivista e un numero | «Le tue riviste», numeri attivi, archivio |
| 30 | Numero e rivista | creare-numero | Creare un numero | Campi, duplicare la struttura, carta e peso |
| 31 | Numero e rivista | pagine-interne | Aggiungere o togliere pagine | «⚙️ Pagine interne» |
| 32 | Numero e rivista | peso | Peso della rivista | Peso, margine, scaglioni, parametri, peso reale, simulazione |
| 33 | Numero e rivista | scadenza-stampa | Scadenza stampa e solleciti | Impostarla, chi riceve la mail |
| 34 | Numero e rivista | chiudere-numero | Chiudere un numero | Quando si può, cosa succede |
| 35 | Numero e rivista | rivista | Creare e modificare una rivista | Redattore di riferimento, segnature, valori di default del peso |
| 36 | Numero e rivista | utenti | Gestire gli utenti | Solo Admin |
| 37 | Numero e rivista | eliminare | Eliminare una rivista, un numero o un utente | Conferma in due passaggi, cosa resta |
| 38 | Esportare e stampare | esporta-timone-pdf | Esportare il timone in PDF | Il foglio A4 da appendere |
| 39 | Esportare e stampare | pdf-sfogliabile | PDF sfogliabile della rivista | La rivista finita, una pagina per pagina |
| 40 | Esportare e stampare | esporta-report-pubblicita | Report delle pubblicità (CSV/PDF) | Dal cruscotto |
| 41 | Riferimento | legenda | Legenda | Colori, bordi, icone, etichette, formati nella card |
| 42 | Riferimento | scorciatoie | Scorciatoie da tastiera | Mac e Windows affiancati |
| 43 | Riferimento | ruoli | Chi può fare cosa | Admin, Redattore, Commerciale, Sola lettura |
| 44 | Riferimento | problemi-frequenti | Problemi frequenti | Messaggio → cosa significa → cosa fare |
| 45 | Riferimento | glossario | Glossario | Le parole del timone |
| 46 | Riferimento | novita | Novità | Le modifiche per data, la più recente in alto |
| 47 | Riferimento | note-amministratore | Note per l'amministratore | Dettagli tecnici e di installazione |
