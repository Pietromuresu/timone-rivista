# Timone Elettronico (timone-rivista)

Strumento interno della redazione per gestire il timone (flat plan) delle riviste: pagine, articoli, pubblicità, PDF di stampa e collaborazione in tempo reale tra più utenti. Non ha area pubblica: tutto è dietro login.

## Scheda rapida

| Voce | Valore |
|---|---|
| Nome | Timone Elettronico (repo `timone-rivista`) |
| URL produzione | ⚠️ DA VERIFICARE (non presente nel repo) |
| URL staging | ⚠️ DA VERIFICARE (nessuna traccia di staging) |
| URL locale | http://localhost (app), http://localhost:8081 (Adminer) |
| Repository | https://its.git/pietro/timone-rivista |
| Branch di produzione | master (unico branch) |
| Stack | PHP 8.2, Laravel 12, Livewire 3, Laravel Reverb (WebSocket), Tailwind 3 + Vite 6, MySQL 8.4, Redis 7 |
| Infrastruttura | Docker Compose: app (PHP-FPM), nginx, worker, reverb, scheduler, mysql, redis, adminer |
| Server | ⚠️ DA VERIFICARE (host, IP, OS, provider) |
| Referente | ⚠️ DA VERIFICARE |
| CI/CD | Nessuna |

## Accessi necessari

| Accesso | Dove si trova |
|---|---|
| Repository (https://its.git/pietro/timone-rivista) | Chiedere l'accesso all'amministratore del repo (⚠️ DA VERIFICARE chi è) |
| SSH al server di produzione | ⚠️ DA VERIFICARE |
| File .env di produzione (APP_KEY, DB_PASSWORD, MYSQL_ROOT_PASSWORD, REVERB_APP_ID/KEY/SECRET, MAIL_*) | Sul server, nella cartella del progetto. Va conservata una copia nel password manager aziendale. Mai nel repo |
| Credenziali SMTP | ⚠️ DA VERIFICARE (oggi le mail sono solo scritte nel log: MAIL_MAILER=log) |
| Primo utente Admin dell'app | Creato a mano con tinker al primo deploy (vedi docs/09-deploy.md nel repo) |
| Database | Adminer sulla porta 8081 del server, con DB_USERNAME e DB_PASSWORD del .env |

## Setup locale in breve

Serve solo Docker Desktop.

```bash
git clone https://its.git/pietro/timone-rivista
cd timone-rivista
./setup.sh dev
```

Lo script crea il .env, genera le password, avvia i container, esegue le migration e carica i dati demo. Login demo: admin@timone.test oppure redattore@timone.test, password "password" (solo in locale).

I PDF funzionano solo dentro Docker (servono Imagick e Ghostscript). Dettagli in docs/01-setup-locale.md nel repo.

## Deploy in breve

Aggiornamento di un server già installato:

```bash
cd <cartella-progetto>
docker compose --profile tools run --rm backup
git pull --ff-only origin master
docker compose exec -T app php artisan timone:missing-occupancy
docker compose build
docker compose run --rm --no-deps app php artisan migrate --force
docker compose up -d
docker compose ps
```

Punti chiave:

- Composer e npm girano dentro la build Docker, non sul server.
- Su un server nuovo NON lanciare ./setup.sh su DB vuoto: crea gli utenti demo con password nota. Seguire la procedura in docs/09-deploy.md.
- HTTPS e WebSocket sicuro non sono nel repo: serve un reverse proxy (template in docs/09-deploy.md).
- Se si cambiano le variabili VITE_REVERB_* serve la rebuild (docker compose up -d --build).

Rollback: git checkout del commit precedente, docker compose build, docker compose up -d. Se le migration lo richiedono, migrate:rollback oppure restore del backup.

## Servizi esterni

| Servizio | Uso | Configurazione | Se è giù |
|---|---|---|---|
| SMTP | Mail di sollecito scadenza stampa, reset password | MAIL_* nel .env | Solleciti persi (vengono marcati come inviati già all'accodamento); reset password in errore |
| Reverb (interno, container reverb) | Aggiornamenti in tempo reale tra utenti | REVERB_*, VITE_REVERB_* | Le azioni nel timone vanno in errore; i client passano al polling ogni secondo |
| Imagick + Ghostscript (dentro l'immagine) | Miniature e controllo formato dei PDF (Imagick, in coda); conteggio pagine al caricamento (Ghostscript, veloce) | nessuna | PDF rifiutati o miniature "fallite"; senza Ghostscript il caricamento di un PDF grande diventa lento |

Non ci sono API di terze parti (pagamenti, S3, ecc.).

## Job e cron

| Cosa | Dove gira | Quando |
|---|---|---|
| Miniature PDF (GenerateContentFileThumbnail) | Container worker (coda Redis, 3 tentativi) | A ogni upload, un job per pagina |
| Mail di sollecito (timone:send-deadline-reminders) | Container scheduler | Ogni giorno alle 07:30 UTC |
| Backup DB | Manuale: docker compose --profile tools run --rm backup | Non schedulato (⚠️ DA VERIFICARE cron sul server) |
| Pulizia PDF orfani (pagefiles:prune-orphaned) | Manuale | Su richiesta, sempre prima con --dry-run |

Non servono supervisor né crontab per Laravel: worker e scheduler sono container con restart automatico.

## Dove guardare quando si rompe

```bash
docker compose ps
docker compose logs -f app worker reverb scheduler nginx
docker compose exec app tail -n 100 storage/logs/laravel.log
docker compose exec app php artisan queue:failed
docker compose exec app php artisan migrate:status
```

| Sintomo | Prima cosa da controllare |
|---|---|
| Errore 500 | storage/logs/laravel.log |
| Errore 502 | docker compose restart nginx (tiene il vecchio IP del container app) |
| Azioni nel timone che falliscono | Container reverb attivo? (log: "Could not resolve host: reverb") |
| Drag&drop morto, console "You must pass your app key" (versioni prima del 2026-10-26) o "VITE_REVERB_APP_KEY non impostata" | REVERB_APP_KEY nel .env, poi rebuild. Dal 2026-10-26 il timone funziona comunque, in polling |
| Console: "Cannot read properties of undefined (reading 'editorFor')" | app.js non caricato o vecchio: rebuild degli asset (docker compose up -d --build); vedi docs/10-debugging.md |
| Deploy della modifica "copertine separate" | Prima del migrate: docker compose run --rm --no-deps app php artisan timone:covers-dry-run (mostra cosa diventa cosa, non scrive nulla) |
| Deploy della modifica "occupazione a frazioni" (2026-10-27) | Prima del migrate: docker compose run --rm --no-deps app php artisan timone:occupancy-dry-run (mostra per ogni contenuto e pagina la vecchia percentuale e la nuova frazione; le righe ⚠️ sono pagine da sistemare a mano) |
| Pagina con «⚠ Oltre capienza» in rosso | Dato vecchio arrotondato alla frazione più vicina: ridurre a mano lo spazio di uno dei contenuti dal menu sulla pagina |
| Non si caricano PDF/immagini | docker compose exec app php artisan timone:diagnose-upload, poi cercare upload_failed in storage/logs/laravel.log (checklist in docs/09-deploy.md §6bis). Causa più probabile: APP_URL diverso dall'indirizzo usato nel browser |
| Non si caricano file grandi (fino a 100 MB) | Limite dell'app: config/timone.php → upload_max_kb (100 MB). Sul server: upload_max_filesize e post_max_size ≥ 110M, max_input_time ≥ 300, max_execution_time ≥ 120, client_max_body_size ≥ 110M in nginx (o LimitRequestBody in apache), timeout del proxy ≥ 300 s, almeno 2 GB liberi per livewire-tmp. timone:diagnose-upload avvisa se PHP è sotto soglia (docs/09-deploy.md §6ter) |
| Miniature ferme | Container worker attivo? thumbnail_status in tabella content_files |
| Mail non arrivate | MAIL_MAILER (log = non inviate), queue:failed, redattore di riferimento impostato sulla rivista |
| migrate si ferma su page_occupancy | php artisan timone:missing-occupancy, poi valorizzare i contenuti elencati |
| Modifiche al codice non visibili | Immagini non ricostruite: docker compose build e docker compose up -d |

## Novità per la redazione (2026-10-26)

- La barra in alto resta sempre visibile mentre si scorre il timone.
- Bottoni S / M / L accanto a Griglia / Doppia pagina / Lista: ingrandiscono le pagine. La scelta vale solo per il proprio browser. Le pagine hanno le proporzioni del formato reale del numero.
- Trascinando una pagina di un gruppo collegato (🔗) o di una selezione multipla, tutte le pagine coinvolte si muovono insieme (bordo tratteggiato blu). Con le frecce della tastiera si sposta di un posto l'intero gruppo.
- Un contenuto appena creato compare subito tra i "Contenuti da assegnare", anche per i colleghi.
- Il riquadro per allegare un file indica il peso massimo accettato dal server; un file troppo pesante viene rifiutato subito, con il suo peso e il massimo.
- Una pubblicità con un PDF di più pagine del suo formato non viene più rifiutata: il modulo chiede se usare tutte le pagine (la pubblicità ne occuperà tante quante il PDF) o solo alcune (da pagina X a pagina Y). Ogni pagina del timone mostra la pagina del PDF giusta.

## Copertine (dal 2026-10-26)

- Ogni numero ha sempre quattro copertine: I (la copertina), II (il suo retro), III (l'interno della quarta), IV (il retro della rivista). Stanno tutte insieme in una fascia azzurra "Copertine" in cima al timone, prima della pagina 1.
- Le pagine interne partono sempre da 1. Il numero di pagine che si scrive creando un numero è solo quello delle pagine interne: le copertine si aggiungono da sole, non vanno contate né sottratte.
- Sulle copertine si mettono articoli e pubblicità come sulle pagine (trascinandoli dal pannello "Contenuti da assegnare" o con "📎 Carica materiale"). Una copertina si trascina solo sopra un'altra copertina, per scambiarle; non si mescola con le pagine interne.
- Una pubblicità di due pagine messa sulla II continua sulla pagina 1; messa sulla III prende l'ultima pagina interna e la III. La I e la IV accolgono una pagina sola.
- Bandella (battente): "+ Aggiungi bandella" nella fascia delle prime copertine. Con la bandella le pagine di copertina per il peso diventano 6.
- Percentuale di pubblicità e conteggi riguardano solo le pagine interne; le copertine hanno il loro conteggio in cima al timone ("copertine 4, X assegnate").
- I numeri creati prima di questa modifica sono stati convertiti in automatico: le loro copertine sono diventate I–IV con i contenuti che avevano, le pagine interne sono state rinumerate da 1.

## Occupazione a frazioni e riempimento delle pagine (dal 2026-10-27)

### Come si inserisce lo spazio di un contenuto

Nel modulo "+ Nuovo contenuto" ci sono due campi affiancati:

- Pagine intere: 0, 1, 2, 3…
- Frazione: nessuna, 1/4, 1/3, 1/2, 2/3, 3/4 di pagina.

Sotto compare il totale, per esempio «Occupa 2 e 1/2 pagine». Esempi: articolo di due pagine e mezza = 2 + 1/2; vetrina = 0 + 1/4; piedino = 0 + 1/3; servizio di 4 pagine = 4 + nessuna. Almeno uno dei due campi deve essere più di zero.

Per una pubblicità, scegliendo il formato i due campi si compilano da soli (pagina intera = 1, mezza pagina = 1/2, 2/3, piedino = 1/3, vetrina = 1/4, doppia pagina = 2) e si possono correggere. Il vecchio campo "Percentuale manuale" non c'è più.

Quando il contenuto viene trascinato su una pagina, occupa le pagine intere una dopo l'altra e la frazione sull'ultima: 2 e 1/2 pagine diventano pagina intera, pagina intera, mezza pagina. Poi, su ogni pagina, il menu accanto al titolo del contenuto permette di cambiare lo spazio solo su quella pagina (da 1/12 a «intera»).

### Come si legge il riempimento di una pagina

- Barra sul bordo sinistro della pagina: si riempie dal basso in proporzione allo spazio occupato.
- Etichetta in basso: «○ Vuota» (tratteggiata), «◐ Libero: 1/3» (ambra, dice quanto spazio resta), «● Piena» (scura). Simbolo e testo sono diversi per ogni stato, quindi si leggono anche senza distinguere i colori. In vista Lista c'è solo l'etichetta.
- Una pagina contiene al massimo una pagina intera di contenuti: 1/2 + 1/4 + 1/4 la riempie esattamente, 1/2 + 1/3 + 1/4 non ci sta. Se un contenuto non ci sta, il messaggio dice quanto spazio resta (es. «resta libero solo 1/3 di pagina») e non viene modificato nulla.
- «⚠ Oltre capienza» in rosso: può comparire solo su contenuti creati prima del 2026-10-27, convertiti in automatico dalla vecchia percentuale alla frazione più vicina (50% → 1/2, 33% → 1/3). Va sistemato a mano riducendo uno dei contenuti.

La percentuale di pubblicità del cruscotto ora è esatta: tre pubblicità da 1/3 su 3 pagine fanno 33,33%. Il sistema conta internamente in dodicesimi di pagina (1/4 = 3, 1/3 = 4, 1/2 = 6, 2/3 = 8, 3/4 = 9, pagina intera = 12), ma in redazione si vedono sempre e solo frazioni.

## Modificare e rinominare un contenuto (dal 2026-10-27)

- Rinominare: doppio click sul nome del contenuto dentro la pagina, scrivere il nuovo nome, Invio. Esc annulla. Il nome cambia su tutte le pagine del contenuto e anche per i colleghi collegati. Per una pubblicità si cambia il nome del cliente.
- Modificare tutto: matita ✏️ accanto al contenuto (sulla pagina o nel pannello "Contenuti da assegnare"). Si apre "Modifica contenuto" con gli stessi campi della creazione. Cambiando lo spazio occupato (pagine intere + frazione) il contenuto viene ridistribuito dalla sua prima pagina; se non ci sta, non cambia nulla e un messaggio spiega perché.
- Sostituire il PDF: nella stessa finestra si sceglie un nuovo file; quello vecchio viene eliminato. Un PDF di più pagine deve corrispondere allo spazio del contenuto (es. 3 pagine per un contenuto di 3 pagine o di 2 e 1/2).
- L'anteprima di un contenuto nel timone è sempre quella del suo PDF (o immagine) di stampa: non c'è una miniatura da caricare a parte.

## Pubblicità previste e segnaposto (dal 2026-10-27)

- Creando un numero, nella sezione «📢 Pubblicità previste» si indica quante pubblicità sono previste: pagina intera, 2/3, 1/2, piedino (1/3), vetrina (1/4). Mentre si scrive, il modulo mostra quante pagine di pubblicità sono in tutto, la percentuale sulle pagine interne e dove andranno. Se non ci stanno compare un errore rosso e il numero non viene creato finché non si correggono i valori.
- Dove vanno: le prime 4 pubblicità occupano tutte le copertine, di fila, nell'ordine II, III, IV e I (prima le pagine intere; se sono meno di 4, anche le frazioni più grandi). Quando viene usata la I compare un avviso. Le altre vanno nelle pagine interne una sì e una no da pagina 2 (2, 4, 6…), prima le pagine intere e poi le frazioni, una per pagina, lasciando il resto della pagina all'editoriale.
- Nel timone sono «segnaposto»: bordo tratteggiato ed etichetta «Da assegnare». Contano nella percentuale di pubblicità e nel contatore «Pubblicità da assegnare» del cruscotto.
- Per trasformarlo in una pubblicità vera: doppio click sul nome e scrivere il cliente, oppure ✏️ e indicare il cliente o caricare il PDF (dal 2026-10-28 il titolo da solo non basta). Si sposta e si modifica come ogni altro contenuto.
- Le posizioni vengono decise solo quando si crea il numero: dopo non cambiano mai da sole.

## Riepilogo in cima al timone (dal 2026-10-27)

- Sotto la barra in alto c'è una riga di riepilogo sempre visibile, anche scorrendo: pagine piene, parziali e bianche; spazio libero in tutto (es. «4 e 1/3 pagine»); percentuale di pubblicità sulle pagine interne; pubblicità ancora da assegnare, per formato; copertine assegnate e libere; peso di una copia e scaglione postale.
- Cliccando su «bianche» o su «Da assegnare» quelle pagine si evidenziano in azzurro e le altre si attenuano; un secondo clic toglie l'evidenziazione. Le pagine non si spostano.
- La percentuale di pubblicità non ha più soglie né colori di allarme: è solo il numero. Anche il campo «soglia di allarme» della rivista non c'è più.

## Disposizione dei contenuti nella pagina (dal 2026-10-27)

- Nella griglia e nella doppia pagina ogni contenuto occupa un riquadro grande quanto il suo spazio: una mezza pagina è metà della pagina, un terzo un terzo.
- Le pubblicità verticali (mezza pagina verticale, 1/3 verticale, 2/3) sono colonne alte quanto la pagina; gli altri contenuti sono righe una sotto l'altra; il piedino sta in fondo. Lo spazio ancora libero è un riquadro tratteggiato con scritto «libero».
- Ogni riquadro mostra l'anteprima del suo PDF. I comandi (spazio, posizione, ruota, apri il file, modifica, estendi, rimuovi) sono nel menu «⋯» nell'angolo del riquadro (dal 2026-10-27, vedi sopra e sotto).
- La posizione nella pagina si cambia con le frecce ← ↑ ↓ → del menu «⋯» (griglia 6×6, revisione 2, vedi sotto). La posizione resta salvata e la vedono anche i colleghi.
- È una rappresentazione dello spazio, non l'impaginato definitivo: gli articoli sono sempre disegnati orizzontali.
- Il PDF esportato dal timone («📄 Esporta PDF») è un foglio da stampare: in testa i numeri del riepilogo, poi copertine e pagine interne in doppie pagine come la rivista, con la stessa disposizione dei contenuti e, se scelte, le miniature di ciascun contenuto. Un pallino colorato accanto al numero indica lo stato della pagina.

## Novità per la redazione (revisione 2, Fase 1, 2026-10-27)

- Nuovo numero: scrivendo il numero di pagine (o le pubblicità previste, le copie, i bancali, le grammature) le cifre non si perdono più. Gli avvisi come «Non è un multiplo di 4» compaiono quando si esce dal campo, non mentre si scrive.
- Si può creare un numero con tante pubblicità previste (es. 60) senza l'errore «Text too long».
- Comandi di un contenuto nella pagina: ogni riquadro ha nell'angolo in alto a destra un bottone «⋯». Cliccandolo si apre un menu sopra tutto con: spazio occupato su quella pagina, sposta prima / sposta dopo, modifica, estendi a un'altra pagina, rimuovi. Funziona anche con riquadri piccoli (1/4) e con lo zoom S. Esc o un clic fuori lo chiudono.
- Trascinando un contenuto, una pagina, un gruppo o una copertina verso il bordo basso della finestra (o subito sotto la riga di riepilogo in alto) la pagina scorre da sola, più veloce vicino al bordo: si arriva alle ultime pagine e alle copertine III e IV senza rilasciare.
- File fino a 100 MB (prima 20 MB): si possono caricare i PDF di stampa ad alta risoluzione. Una barra mostra l'avanzamento, anche quando si sostituisce il file dalla finestra «Modifica contenuto»; un file troppo grande viene rifiutato con un messaggio che indica il limite.

## Novità per la redazione (revisione 2, Fase 2, 2026-10-27)

- Cliccando su un contenuto dentro una pagina si seleziona la pagina, come cliccando sulla pagina. Il file non si apre più così: per aprirlo, menu «⋯» del contenuto → «👁 Apri il file» (si apre in una nuova scheda). Se il contenuto non ha un file l'occhio è grigio e dice «Nessun file caricato».
- La riga di riepilogo e i comandi del timone (zoom S/M/L, Griglia/Doppia pagina/Lista, Pagine interne, Esporta PDF…) restano fermi in alto mentre si scorre, così non serve tornare in cima. Sul telefono scorrono con la pagina.
- Frecce di posizionamento: ogni pagina è divisa in una griglia di 6×6 caselle e ogni contenuto occupa un riquadro esatto (mezza pagina verticale = metà sinistra o destra, piedino = striscia in fondo, vetrina = un quarto). Nel menu «⋯» le frecce ← ↑ ↓ → spostano il contenuto di un passo; se nel posto c'è un altro contenuto della stessa larghezza (o altezza) i due si scambiano. Una freccia grigia vuol dire che non si può (fuori pagina o posto occupato). «⟳ Ruota» passa da verticale a orizzontale: per una mezza pagina o un 1/3 cambia anche il formato della pubblicità.
- Un contenuto da 3/4 di pagina non è un riquadro ma una forma a L: riempie lo spazio lasciato dagli altri e non ha frecce.
- Anteprime: se il PDF di un annuncio è impaginato su una pagina intera, nel riquadro si vede solo la parte che corrisponde alla sua posizione (una mezza verticale a sinistra mostra la metà sinistra del PDF). Se il file è già della misura dell'annuncio si vede intero. Se il sistema sbaglia: ✏️ Modifica → «Anteprima nel riquadro».
- Posizione già dalla creazione: nel modulo «+ Nuovo contenuto» e nella finestra «Modifica contenuto» c'è «Posizione nel timone»: si sceglie la pagina (o la copertina) e il contenuto va lì subito. Se non c'è posto il contenuto non viene creato (o spostato) e il messaggio dice cosa occupa la pagina e quanto spazio manca. «Da assegnare» lo lascia tra i contenuti da assegnare.
- Prima dell'aggiornamento sul server: docker compose run --rm --no-deps app php artisan timone:grid-dry-run (mostra dove finisce ogni contenuto sulla griglia, senza scrivere nulla). Dopo, facoltativo: php artisan timone:file-ratios per ritagliare anche le anteprime dei file già caricati.

## Novità per la redazione (revisione 2, Fase 3, 2026-10-28)

- Pubblicità senza cliente: in «+ Nuovo contenuto» → Pubblicità, cliente e titolo sono facoltativi. Se manca il cliente e non c'è un file, la pubblicità è un segnaposto «Da assegnare», come quelle previste alla creazione del numero, e conta nel riepilogo. Titolo vuoto → «Pubblicità 1/2 — da assegnare» (con la misura).
- Regola unica del segnaposto: è una pubblicità senza cliente e senza file. Diventa vera appena riceve il cliente o il file; il titolo non conta più. Se si toglie il cliente a una pubblicità senza file, torna segnaposto.
- Caricamento multiplo: riquadro «⇪ Carica più file» sotto «Nuovo contenuto». Si sceglie in alto Articoli o Pubblicità e si trascinano (o si scelgono) tanti PDF/immagini. Ogni file diventa un contenuto tra i «Contenuti da assegnare»: titolo = nome del file (trattini e underscore diventano spazi), pagine = pagine del PDF (1 per un'immagine); le pubblicità nascono «Pagina intera» senza cliente. Spazio, formato, cliente e posizione si correggono a mano dopo.
- I file partono uno alla volta: per ciascuno si vede «in coda», la percentuale, «creato» o l'errore con il motivo; un file sbagliato non ferma gli altri. Alla fine c'è il riepilogo e «Riprova i falliti». Un titolo già presente nel numero viene creato come «… (2)» con un avviso. Non chiudere la pagina finché l'elenco non è finito.
- Dopo l'aggiornamento sul server: docker compose exec app php artisan timone:placeholder-sync --dry-run (elenca le pubblicità senza cliente né file che tornano segnaposto), poi lo stesso comando senza --dry-run.

## Novità per la redazione (revisione 2, Fase 4, 2026-10-28)

- Ruolo Commerciale: ora lavora sulle pubblicità. Le crea (anche più file insieme), le modifica e le sposta: trascinamento, frecce, rotazione, pagina scelta nella finestra di modifica. Può spostare anche pagine intere, ma solo se non contengono articoli. Sugli articoli vede i comandi disattivati con il messaggio «Solo la redazione può spostare questo contenuto». Stati delle pagine, blocchi, gruppi, pagine totali e scadenza restano della redazione.
- Eliminare un numero o una rivista (solo amministratori): bottone «🗑️ Elimina» accanto al numero nella pagina della rivista, oppure in fondo a «Modifica rivista». Prima un riepilogo di cosa sparirà (pagine, contenuti, file e spazio occupato), poi si scrive il nome esatto e la propria password. L'eliminazione è definitiva. Chi aveva il numero aperto torna all'elenco con un avviso.
- Eliminare un utente (solo amministratori): in fondo a «Modifica utente», stessa conferma scrivendo la sua email. I file che aveva caricato e la cronologia restano, con «Utente eliminato». Non si può eliminare sé stessi né l'ultimo amministratore. Dal profilo non si può più eliminare il proprio account.

## Novità per la redazione (revisione 2, Fase 5, 2026-10-28)

- PDF sfogliabile della rivista: nella pagina del numero, riquadro «📖 PDF sfogliabile della rivista» sopra il timone, bottone «Esporta PDF rivista». Crea un PDF con tutte le pagine nell'ordine di stampa: I e II di copertina, pagine interne, III e IV di copertina. Ogni pagina è un A4 con dentro la pagina della rivista in scala, i PDF dei contenuti al loro posto e il numero di pagina sotto. Si sfoglia a coppie come una rivista.
- Dove manca il materiale (segnaposto, articoli senza PDF, file non trovato) c'è un riquadro tratteggiato con titolo e «File mancante»: si vede subito cosa manca prima della stampa. Una pagina vuota mostra «Pagina bianca».
- Il PDF si prepara in coda: nel riquadro lo stato passa da «In coda» a «In generazione» e poi a «Pronto», con «Scarica». Intanto si può continuare a lavorare. Ogni nuova esportazione sostituisce la precedente.
- Spuntando «Bassa risoluzione per anteprima» si ottiene un file leggero da mandare via mail (a parte, non sostituisce quello a qualità piena).

## Azioni sulle pagine nella fascia fissa (2026-10-28)

- Le azioni sulle pagine selezionate sono sempre in alto, nella fascia fissa del timone, insieme al riepilogo e ai comandi: si possono selezionare pagine in fondo al timone e collegarle, bloccarle o cambiarne lo stato senza tornare su.
- La riga dice cosa è selezionato («3 pagine selezionate: 12–14»). Un'azione che non si può fare resta al suo posto ma attenuata: passandoci sopra col mouse spiega perché. Su schermi stretti alcune azioni sono nel menu «Altro ⋯».
- Scorciatoie da tastiera (non mentre si scrive in un campo): Esc deseleziona tutto, Ctrl+A (⌘+A su Mac) seleziona tutte le pagine, Ctrl+Shift+G (⌘+Shift+G) collega o scollega le pagine selezionate. Su Mac si seleziona con ⌘+clic.
- Comprimere la fascia: bottone ⌃ a destra del riepilogo, che lo riduce a una riga corta; ⌄ per riaprirlo. La scelta resta salvata nel browser.
- Il Commerciale vede solo seleziona e deseleziona: le altre azioni sono della redazione.

## Pannello Contenuti: assegnare senza risalire (2026-10-28)

- Nella fascia fissa in alto c'è il bottone «📚 Contenuti · N da assegnare». Si apre un pannello con tutti gli articoli e le pubblicità: sugli schermi larghi come colonna a destra (il bordo sinistro si trascina per allargarla), sugli altri come tendina sotto la fascia. Resta aperto anche ricaricando la pagina.
- Dentro: schede «Da assegnare», «Tutti», «Segnaposto», filtro Articoli/Pubblicità, ricerca per titolo, ordine per data o alfabetico, e i bottoni «+ Nuovo contenuto» e «Carica più file».
- Per mettere un contenuto su una pagina senza tornare in cima: trascinarlo sulla pagina (il timone scorre da solo verso il bordo), oppure selezionare una o più pagine e premere «Assegna alla selezione», oppure scrivere il numero in «Vai a pag.» (o I, II, III, IV per le copertine) e premere Invio. Se non c'è spazio un messaggio in alto dice cosa occupa la pagina e quanto manca.
- Nel riepilogo in alto, quello che si chiamava «Da assegnare» ora si chiama «Segnaposto» (le pubblicità previste ancora senza cliente né file).

## Annullare e ripetere (2026-10-28)

- Ctrl+Z (⌘+Z su Mac) annulla l'ultima azione fatta sul timone, Ctrl+Shift+Z o Ctrl+Y la ripete. Ci sono anche i bottoni ↶ ↷ all'inizio della barra comandi: passandoci sopra dicono cosa annullano. Dopo un annulla compare un avviso con il bottone «Ripeti».
- Si annullano solo le proprie azioni, mai quelle dei colleghi, fino a 50 passi nelle ultime 24 ore. Mentre si scrive in un campo, Ctrl+Z annulla il testo come sempre.
- Si può annullare: spostare pagine e gruppi, assegnare o togliere contenuti, frecce e rotazione, spazio sulla pagina, collegare/scollegare, stato e blocco delle pagine, rinominare, modificare, creare (anche più file insieme), archiviare ed eliminare un contenuto (resta recuperabile per 24 ore).
- Non si può annullare: pagine totali, bandella, scadenza, peso, chiusura del numero, eliminazione di riviste/numeri/utenti, export, sostituzione del PDF di un contenuto.
- A volte l'annulla viene rifiutato: se dopo di te un collega ha cambiato le stesse pagine o lo stesso contenuto (l'avviso dice chi e a che ora), oppure se lo spazio dove tornerebbe il contenuto è stato occupato. In quel caso non cambia nulla.
- Il bottone 🕘 mostra la cronologia delle proprie ultime azioni.

## Punti di attenzione

- Vulnerabilità note: Livewire 3.8.2 (XSS) e Laravel 12.64 vanno aggiornati (composer audit: 19 advisory).
- La migration che rende obbligatorio page_occupancy blocca migrate finché esistono contenuti senza occupazione.
- Cancellare un utente (anche dal suo profilo) cancella a cascata i file che ha caricato.
- Backup non schedulati e file caricati (volume storage_data) non inclusi nel backup.
- Adminer è esposto sulla porta 8081; il WebSocket è su ws:// in chiaro sulla 8080.
- Ogni pagina di un PDF multipagina salva una copia intera del PDF: tenere d'occhio il disco.
- Mai lanciare docker compose down -v in produzione: cancella database e file.

## Calcolo peso rivista

Per ogni numero il gestionale calcola il peso teorico di una copia, il margine di errore (± grammi), lo scaglione di spedizione e il peso della tiratura. Usa le stesse formule del foglio Excel usato finora.

### Dove si impostano carta e formato

| Dove | Cosa | Quando modificarlo |
|---|---|---|
| Rivista, pagina "Modifica rivista", sezione "Carta e peso — valori di default" (solo Admin) | Formato in mm, grammatura interno e copertina, pagine di copertina, maggiorazione, peso bancale, tolleranze | Quando cambia la carta o il formato della testata. Vale solo per i numeri creati dopo |
| Nuovo numero, sezione "Carta e peso" del form | Gli stessi valori, già compilati con quelli della rivista, più n. copie e n. bancali | Quando un singolo numero esce con carta, copertina o tiratura diverse. Un badge "modificato" segnala i valori diversi dalla rivista |
| Numero già creato, pannello "Peso", pulsante "Modifica parametri" | Tutti i parametri del numero, più il peso reale misurato. In cima c'è la sezione "Carta": grammatura interno, grammatura copertina, pagine copertina | Per correggere un numero, cambiarne la carta o inserire il peso reale dopo la stampa |

**Grammatura**: si inserisce la grammatura scritta sulla carta (es. 80 per l'interno, 170 per la copertina), il sistema calcola da solo il peso di ogni pagina. Non va inserito il peso di una pagina: con 0,8 al posto di 80 il peso della copia risulterebbe di pochi grammi. Il sistema accetta solo grammature tra 30 e 400 g/m² e, sotto 30, lo dice esplicitamente. Si può scrivere sia con la virgola sia con il punto (80,5 o 80.5).

Sotto ogni grammatura compare il peso a pagina calcolato, per esempio nel formato 210 × 270 mm:

```text
80 g/m² → 2,268 g a pagina · 208 pagine = 471,7 g
170 g/m² → 4,820 g a pagina · 6 pagine = 28,9 g
```

Le pagine di copertina devono essere un numero pari tra 2 e 12.

Il formato si inserisce in millimetri: 210 × 270, non 21 × 27. Il sistema accetta solo valori tra 50 e 1000 mm e, sotto 50, chiede esplicitamente i millimetri.

### Cambiare la carta di un singolo numero

1. Nella pagina del numero, riquadro "Peso", premere "Modifica parametri".
2. Nella sezione "Carta" cambiare grammatura interno o grammatura copertina. Le pagine copertina di un numero già creato sono in grigio: dipendono dalle copertine (4, o 6 con la bandella, che si aggiunge dalla fascia copertine del timone).
3. Prima di salvare, l'anteprima in fondo mostra il nuovo peso, il margine, lo scaglione e la differenza dal peso salvato (es. "−60,1 g rispetto al valore salvato (510,7 g), passa da scaglione 1.000 g a 500 g.").
4. I valori diversi dalla rivista hanno il badge "modificato". "Ripristina default rivista" li riporta ai valori della rivista (non salva da solo).
5. "Salva" modifica solo questo numero, mai i valori della rivista. La modifica resta nella cronologia.

Le pagine interne non si inseriscono: sono le pagine del timone (esempio: 208 pagine interne, più 6 pagine di copertina se c'è la bandella). Se si cambiano le pagine del timone o si aggiunge/toglie la bandella, il peso si aggiorna da solo.

Cambiare i valori della rivista non modifica mai i numeri già creati: ogni numero conserva i suoi.

### Come leggere il pannello Peso

```text
Peso copia     510,7 g  ± 31 g   (481 – 541 g)
Scaglione      1000 g   ⚠ a 10,7 g oltre lo scaglione 500 g
Interno        208 pag. × 80 g/m²   471,7 g
Copertina      6 pag. × 170 g/m²    28,9 g
Maggiorazione  +2%                  10,0 g
Tiratura       8.200 copie → 4.187,53 kg netti / 4.287,53 kg lordi (5 bancali)
```

| Colore del badge scaglione | Significato |
|---|---|
| Verde | La copia sta nello scaglione anche nel caso peggiore |
| Giallo | A rischio: con il margine d'errore potrebbe passare allo scaglione successivo (compare "Rischio di passare allo scaglione X g") |
| Rosso | Fuori scaglione: oltre 1000 g |

Se compare "Parametri peso da configurare", mancano dei valori (di solito il formato o le grammature): vanno inseriti con "Modifica parametri".

Il peso è teorico e non preciso al grammo: la carta reale pesa un po' più o un po' meno della grammatura dichiarata e il taglio varia di qualche decimo di millimetro. Il "± g" indica di quanto può scostarsi nel caso peggiore.

### Peso reale dopo la stampa

1. Pesare una copia stampata e inserire il valore in "Peso reale misurato (g)" con "Modifica parametri".
2. Il pannello mostra la differenza dal teorico e la maggiorazione che avrebbe dato il peso esatto ("Usa questa maggiorazione per i prossimi numeri").
3. Se la differenza si ripete su più numeri, un Admin può premere "Copia nei default della rivista". Viene chiesta conferma e il valore vale solo per i numeri creati dopo.

Il suggerimento non viene mai applicato da solo: una singola pesata può essere sbagliata.

### Restare in uno scaglione con la simulazione

Nel pannello Peso, il campo "Simula +/- pagine interne" accetta per esempio 8 o -4. Mostra il peso e lo scaglione che si avrebbero aggiungendo o togliendo quelle pagine, senza modificare nulla.

Sotto, il pannello indica quante pagine si possono ancora aggiungere restando nello scaglione attuale, sia sul peso teorico sia considerando il margine. Il conteggio va a passi di 2 pagine; in tipografia si lavora di solito a multipli di 4.

### Scaglioni

| Peso della copia | Scaglione |
|---|---|
| fino a 250,0 g | 250 g |
| da 250,01 a 350,0 g | 350 g |
| da 350,01 a 500,0 g | 500 g |
| da 500,01 a 1000,0 g | 1000 g |
| oltre 1000,0 g | Fuori scaglione |

Gli scaglioni sono definiti nel file config/peso_rivista.php del repository: per cambiarli serve uno sviluppatore. Il limite è incluso (500,0 g sta nello scaglione 500). Da confermare con l'ufficio spedizioni: vedi docs/DA-VERIFICARE.md, domande DV-18 – DV-22.

## Documentazione completa nel repo

- docs/README.md (indice)
- docs/01-setup-locale.md
- docs/03-dominio-e-flussi.md (sezione "Calcolo peso rivista": formule, margine, casi di verifica)
- docs/09-deploy.md
- docs/10-debugging.md
- docs/11-debito-tecnico.md
- docs/DA-VERIFICARE.md
- HANDOFF.md (cronologia completa dello sviluppo), MANUALE_UTENTE.md (manuale per la redazione, anche nell'app su /manuale)
