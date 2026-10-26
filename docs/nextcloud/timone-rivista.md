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
| Imagick + Ghostscript (dentro l'immagine) | Miniature, conteggio pagine e controllo formato dei PDF | nessuna | PDF rifiutati o miniature "fallite" |

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
| Non si caricano PDF/immagini | docker compose exec app php artisan timone:diagnose-upload, poi cercare upload_failed in storage/logs/laravel.log (checklist in docs/09-deploy.md §6bis). Causa più probabile: APP_URL diverso dall'indirizzo usato nel browser |
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
2. Nella sezione "Carta" cambiare grammatura interno, grammatura copertina o pagine copertina.
3. Prima di salvare, l'anteprima in fondo mostra il nuovo peso, il margine, lo scaglione e la differenza dal peso salvato (es. "−60,1 g rispetto al valore salvato (510,7 g), passa da scaglione 1.000 g a 500 g.").
4. I valori diversi dalla rivista hanno il badge "modificato". "Ripristina default rivista" li riporta ai valori della rivista (non salva da solo).
5. "Salva" modifica solo questo numero, mai i valori della rivista. La modifica resta nella cronologia.

Le pagine interne non si inseriscono: sono le pagine totali del timone meno le pagine di copertina (esempio: 214 pagine nel timone con 6 di copertina = 208 interne). Se si cambiano le pagine del timone, il peso si aggiorna da solo.

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
