# Timone Elettronico

Applicazione web per pianificare le pagine di una rivista: chi ci lavora vede in tempo reale, insieme ai colleghi collegati, quali pagine sono occupate da articoli o pubblicità, quali sono ancora libere, e può spostarle, assegnarci contenuti e controllare quanto spazio pubblicitario c'è in ogni numero — al posto del vecchio foglio di carta/Excel.

Per l'uso quotidiano da parte della redazione (come spostare le pagine, assegnare contenuti, ecc.) vedi **[MANUALE_UTENTE.md](MANUALE_UTENTE.md)**. Lo stesso manuale si legge anche dentro l'applicazione, dalla voce **"📖 Manuale"** nel menu in alto (indirizzo `/manuale`).

---

## Come lo avvio

Serve solo [Docker Desktop](https://www.docker.com/products/docker-desktop/) installato e avviato (icona balena attiva).

**Primo avvio in assoluto** (o dopo un reset completo):

```bash
./setup.sh
```

Un solo comando: crea da solo tutte le password/chiavi necessarie, scarica e costruisce tutto, prepara il database con i dati demo. La primissima volta richiede qualche minuto.

**Riavvio successivo** (il computer si è riacceso, i container si sono fermati):

```bash
./setup.sh
```

Lo stesso identico comando — è sicuro rilanciarlo in qualsiasi momento, anche a container già avviati: non rompe né duplica nulla.

Tutti i dettagli (aggiornare il codice, backup, troubleshooting Docker) sono in **[DOCKER.md](DOCKER.md)**.

---

## Come ci accedo

- App: <http://localhost>
- Adminer (per guardare il database direttamente, se serve): <http://localhost:8081> — server `mysql`, credenziali nel file `.env` (`DB_USERNAME`/`DB_PASSWORD`)

**Utenti demo** (password per tutti: `password`):

| Email | Ruolo | Accesso |
|---|---|---|
| `admin@timone.test` | Admin | Tutte le riviste |
| `redattore@timone.test` | Redattore | Solo "Motori Elettrici" e "Casa & Giardino" (non "Sport Weekly", che infatti non ha ancora nessun numero) |

> Nota: i ruoli **Commerciale** e **Sola lettura** esistono nel sistema (vedi la gestione utenti) ma al momento non hanno un utente demo pronto — se vuoi provarli, creane uno tu da Admin (vedi il manuale utente).

---

## Come lo uso — panoramica

Il percorso è sempre: **rivista → numero → timone**.

- **Le tue riviste** (`/riviste`): elenco delle riviste a cui hai accesso, con il numero attualmente in lavorazione.
- **Pagina rivista**: numeri attivi e archivio dei numeri chiusi; da qui si crea un nuovo numero.
- **Timone** (la schermata principale, dentro un numero): la griglia delle pagine interne (numerate da 1), in tre modalità intercambiabili — griglia a card, doppia pagina (come si vedrebbe sfogliando la rivista stampata), lista — con le quattro **copertine** (I, II, III, IV, più l'eventuale bandella) in una fascia separata in cima, prima della pagina 1. Da qui:
  - **si spostano le pagine** trascinandole (drag&drop, in tutte e tre le modalità), da tastiera, oppure con la **modalità scambio** (due click per scambiare direttamente due pagine di posto);
  - **si creano nuovi articoli e pubblicità** dal bottone "+ Nuovo contenuto", indicando quanto spazio occupano come pagine intere + frazione (es. 2 e 1/2 pagine, 1/3 di pagina), e **si assegnano** trascinandoli dal pannello "contenuti da assegnare" sopra la griglia; ogni pagina mostra quanto è piena («Libero: 1/3», «Piena») e disegna i suoi contenuti su una griglia di 6×6 caselle, ognuno nel suo riquadro esatto (mezza verticale, piedino, vetrina…), con l'anteprima del suo file ritagliata sulla sua posizione; dal menu «⋯» di ogni contenuto lo si sposta con le frecce ← ↑ ↓ →, lo si ruota, se ne apre il file (👁) e se ne regola lo spazio pagina per pagina; la pagina di partenza si può scegliere già creando il contenuto (campo «Posizione nel timone»); un contenuto si **modifica** (✏️, tutti i campi, PDF incluso) e si **rinomina** con un doppio click sul nome direttamente nella pagina;
  - creando un numero si indicano le **pubblicità previste** (pagine intere, 2/3, 1/2, piedini, vetrine): nascono come segnaposto «Da assegnare» già messi nel timone, da completare con cliente e PDF; un segnaposto si crea anche a mano (pubblicità senza cliente né file);
  - con il **caricamento multiplo** si trascinano tanti PDF/immagini in una volta: ognuno diventa un articolo o una pubblicità da assegnare, con il nome del file come titolo e tante pagine quante quelle del PDF;
  - le azioni sul timone si **annullano e ripetono** (Ctrl+Z / Ctrl+Shift+Z, bottoni ↶ ↷): solo le proprie, fino a 50 passi per 24 ore, con il controllo dei conflitti con i colleghi;
  - il **pannello Contenuti** (bottone «📚 Contenuti» nella fascia in alto, colonna a destra sugli schermi larghi) permette di assegnare articoli e pubblicità trascinandoli, alla selezione di pagine o con «Vai a pag.», senza risalire il timone;
  - si esporta il **PDF sfogliabile della rivista**: tutte le pagine in ordine di stampa su A4, con i PDF dei contenuti al loro posto e i riquadri «File mancante» dove manca il materiale, in qualità piena o leggera da mandare via mail;
  - il ruolo **Commerciale** lavora solo sulle pubblicità (le crea, le modifica e le sposta, anche pagine senza articoli); un **amministratore** può eliminare riviste, numeri e utenti con una conferma in due passaggi (password + nome);
  - **si cambia lo stato** di ogni pagina (da assegnare → assegnata → in bozza → revisionata → ok stampa);
  - **si carica un PDF o un'immagine su un contenuto** (mai più direttamente su una pagina), con anteprima generata in automatico; un PDF che occupa più pagine chiede come distribuirlo e collega automaticamente le pagine risultanti;
  - **si legge il riepilogo**, insieme ai comandi del timone, sempre visibile in alto: pagine piene/parziali/bianche, spazio libero, percentuale di pubblicità, pubblicità da assegnare, copertine, peso e scaglione; un clic su «bianche» o «da assegnare» evidenzia quelle pagine;
  - **si consulta il cruscotto pubblicitario**: percentuale di carico pubblicitario del numero ed esportazione del report in CSV/PDF;
  - **si consulta lo storico degli spostamenti** delle pagine, e la **cronologia generale** di tutte le altre azioni (chi ha fatto cosa e quando);
  - **si modifica il numero totale di pagine** anche a lavorazione avviata, con anteprima dell'impatto prima di confermare una riduzione;
  - **si esporta il timone completo in PDF**, con filtri opzionali (solo pubblicità, solo pagine non approvate, con o senza miniature);
  - **si sceglie la dimensione delle pagine** (bottoni S / M / L, ricordati su questo browser): le pagine hanno le proporzioni del formato reale del numero;
  - **compaiono avvisi automatici** quando c'è qualcosa da controllare (una pagina già approvata ma ancora vuota, un contenuto su pagine non consecutive);
  - **si blocca una pagina** (🔓/🔒) per impedirne spostamento, modifica o eliminazione — utile per una pagina già mandata in stampa.
- **Gestione utenti** (`/utenti`, solo per chi ha ruolo Admin): creare account, cambiare ruolo, decidere a quali riviste ogni persona ha accesso.

Tutto quello che fai è visibile in tempo reale anche ai colleghi collegati sullo stesso numero (un badge mostra chi sta modificando cosa), senza bisogno di ricaricare la pagina.

Per la guida passo-passo di ogni funzione, vedi **[MANUALE_UTENTE.md](MANUALE_UTENTE.md)**.

---

## Come lo fermo / riavvio / resetto

```bash
docker compose down          # ferma tutto (i dati restano salvati)
./setup.sh                   # lo riavvia

docker compose down -v       # ferma tutto e CANCELLA i dati: reset completo
./setup.sh                   # riparte da zero, con i dati demo puliti
```

## Come vedo se qualcosa non va

```bash
docker compose ps            # stato dei container: devono essere tutti "Up"
                              # (mysql/redis/app/reverb anche "healthy")
docker compose logs -f       # log in diretta di tutti i servizi (Ctrl+C per uscire)
docker compose logs -f app   # log del solo servizio "app" (o "worker", "reverb", "mysql"...)
```

## Se qualcosa va storto

**Errore su una variabile mancante (es. `required variable ... is missing a value`) all'avvio**
Non dovrebbe più succedere: `./setup.sh` genera da solo tutte le credenziali necessarie. Se lo vedi comunque, vedi la sezione dedicata in [DOCKER.md](DOCKER.md#se-qualcosa-va-storto).

**Un tentativo di avvio fallisce con un errore tipo `mkdir ... file exists`**
Capita a volte alla primissima creazione dei volumi Docker — `./setup.sh` lo rileva e ritenta da solo fino a 3 volte. Se persiste, rilancia semplicemente `./setup.sh`.

**La pagina su `http://localhost` non si apre**
Aspetta qualche secondo e ricarica (i container potrebbero non aver ancora finito di avviarsi). Se persiste, controlla `docker compose ps` e i log come sopra.

Per l'elenco completo dei problemi noti vedi **[DOCKER.md](DOCKER.md#se-qualcosa-va-storto)**.

## Se il problema non è tra questi

Copia il messaggio d'errore per intero (dal terminale, o da `docker compose logs`) e incollalo a Claude Code in questa cartella insieme al file `HANDOFF.md` (contiene tutto lo storico delle decisioni tecniche del progetto) — è il modo più veloce per farsi aiutare senza dover rispiegare da capo com'è fatto il progetto.
