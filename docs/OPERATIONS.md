# Operazioni — Timone

Guida per chi gestisce il server di Timone. Non serve conoscere il codice: ogni comando è completo e si copia così com'è. In questo file non ci sono password né chiavi.

Tutti i comandi si lanciano **dalla cartella del progetto sul server** (quella con `docker-compose.yml`, per esempio `/opt/timone-rivista`), con un utente che può usare Docker.

[← Indice della documentazione tecnica](README.md)

---

## Backup e ripristino

### In breve

- Un servizio Docker dedicato, **`backup-agent`**, salva da solo il **database** e i **file caricati** con [restic](https://restic.net), uno strumento di backup affermato che **cifra** e **deduplica** i dati.
- Le copie sono almeno due: una **sul server** (`backups/restic/`, per ripristinare in fretta) e una **fuori dal server** (la *copia remota*, per sopravvivere a guasti, cancellazioni e ransomware). La copia remota è **non cancellabile dal server**: rest-server append-only o S3 con Object Lock. Facoltativa, una seconda copia remota.
- Un [controllo indipendente](#controllo-indipendente-da-unaltra-macchina), su un'altra macchina, manda una mail se sulla destinazione non arrivano backup da 26 ore.
- Tutta la configurazione sta in **`backup.env`** nella cartella del progetto. Il file è fuori da git e non entra nelle immagini Docker.
- La **password del repository** (la chiave di cifratura) sta in `backup-secrets/restic-password`. **Senza quella password i backup non si possono leggere**: va custodita anche fuori dal server (vedi [La chiave di cifratura](#la-chiave-di-cifratura)).

### Cosa viene salvato e cosa no

| Cosa | Salvato? | Perché |
|---|---|---|
| Database MySQL (riviste, numeri, pagine, contenuti, utenti, cronologia, commenti…) | **Sì**, con un dump logico (`mysqldump --single-transaction`) | È il dato principale. Il dump è coerente e non blocca chi lavora. I file grezzi di MySQL (volume `mysql_data`) **non** si copiano mai a caldo: una copia fatta mentre MySQL scrive può risultare inutilizzabile. |
| File caricati: PDF, immagini, storico dei PDF di pagina (volume `storage_data`, cartelle `private/contents` e `private/pages`) | **Sì** | Non si possono ricreare. |
| Miniature e ritagli (`thumbnails/`, `crops/` dentro `private/contents`) | **Sì** | Si potrebbero rigenerare, ma grazie alla deduplicazione occupano poco (in sviluppo 70 MB). Rigenerarle tutte dopo un ripristino terrebbe il worker occupato a lungo. |
| `.env`, `backup.env`, `docker-compose*.yml`, `docker-compose.override.yml` (se c'è), `Dockerfile`, cartella `docker/` (nginx, PHP, script di backup) | **Sì**, dentro il repository cifrato | Servono a ricostruire il server identico. La configurazione di Reverb sta in `.env`. |
| Proxy HTTPS e certificati sull'host (es. `/etc/nginx`, `/etc/letsencrypt`) | **Solo se li indichi** in `BACKUP_EXTRA_PATHS` | Stanno fuori dal progetto: vedi [Salvare cartelle fuori dal progetto](#salvare-cartelle-fuori-dal-progetto). |
| Hash del commit in produzione | **Sì** (`info/commit.json` in ogni backup) | Il codice è su git: basta sapere quale commit girava. Vedi [Il commit in produzione](#il-commit-in-produzione). |
| Password del repository (`backup-secrets/restic-password`) | **No** | Senza di lei il backup non si apre comunque: va custodita a parte. |
| `private/livewire-tmp` | No | Upload in transito: Livewire li cancella da solo dopo 24 ore. |
| `private/exports` (PDF sfogliabili) | No | Si rigenerano dal pannello del numero. Dopo un ripristino il link di scaricamento risponde «non trovato» finché non si rigenerano. |
| `vendor/`, `node_modules/`, `public/build`, cache di Laravel | No | Si ricreano dalla build Docker (`docker compose build`). |
| Log (`storage_logs`) | No | Non servono a ricostruire il servizio. |
| Redis (code e cache) | No | Vedi [Perché Redis non è nel backup](#perché-redis-non-è-nel-backup). |

### Frequenze e conservazione

Gli orari sono nel fuso `Europe/Rome`, modificabile con `BACKUP_TIMEZONE`. Li definisce il file `docker/backup/crontab`.

| Quando | Cosa |
|---|---|
| Lunedì–venerdì alle **8, 12, 16 e 20** | Database |
| **Ogni notte alle 2:00** | Database, **poi** file e configurazione, controllo di integrità, copia remota, misura dello spazio |
| **Domenica alle 4:30** | Pulizia dello spazio dei backup scaduti (`prune`) e rilettura del 10% dei dati per verificarli |
| Ogni 15 minuti | Rilettura di `backup.env` per l'app (destinatari degli avvisi, soglie) e «segno di vita» dell'agente; nessun backup |

Il database va **sempre prima** dei file. Così, nel caso peggiore, un file caricato tra i due passaggi c'è nel backup dei file ma non nel dump (resta un file in più, innocuo). Il caso opposto, cioè un dump che cita un file assente dal backup, non può capitare.

**Conservazione** della copia locale, uguale per database e file:
- tutti i backup delle ultime **48 ore**;
- poi **1 al giorno per 14 giorni**;
- **1 a settimana per 8 settimane**;
- **1 al mese per 12 mesi**.

La pulizia è automatica. I valori si cambiano in `backup.env` (`BACKUP_KEEP_*`).

La **copia remota** invece è *append-only*: il server può solo aggiungere. I backup lì si accumulano e si puliscono **una volta l'anno** da un'altra macchina (vedi [Pulizia annuale della copia remota](#pulizia-annuale-della-copia-remota)).

**Spazio stimato.** Restic salva una sola volta ogni pezzo di file uguale. In sviluppo 3,2 GB di file sul disco sono diventati circa 140 MB nel repository: lo stesso PDF multipagina è copiato per ogni pagina. A 12 mesi il repository pesa circa quanto **i PDF unici caricati in 12 mesi più quelli presenti oggi**; il database pesa poco. Per esempio, con 4 riviste, un numero al mese ciascuna, 100 pagine e 2 MB unici per pagina, servono circa 10 GB per copia. I numeri reali di produzione non sono ancora noti (vedi `HANDOFF.md`).

### Dove finiscono le copie

| Copia | Dove | A cosa serve |
|---|---|---|
| Locale | `backups/restic/` nella cartella del progetto (repository restic cifrato) | Ripristino veloce, errori umani |
| Remota | `BACKUP_REMOTE_REPOSITORY`: solo rest-server append-only o S3 con Object Lock, che il server non può cancellare | Guasto del server, furto, ransomware, server compromesso |
| Seconda remota (facoltativa) | `BACKUP_REMOTE_2_REPOSITORY`, stesse regole, per esempio fuori dall'intranet | Perdita della prima destinazione o della sede |
| Esito e registro | Volume Docker `timone-rivista_backup_status` (file `*.json` e `backup.log`) | Avvisi, pagina admin, diagnosi |

La copia locale è una **cartella** e non un volume Docker, perché `docker compose down -v` (il «reset totale») cancella i volumi. Sta però sullo stesso disco del server: per spostarla su un secondo disco vedi [Copia locale su un altro disco](#copia-locale-su-un-altro-disco).

### Attivare il backup (checklist del primo giorno)

Da fare una volta, in quest'ordine. Ogni passo dice cosa deve comparire.

**1. Verifica che lo scheduler di Laravel giri davvero.** Da lì partiranno il controllo «nessun backup da 26 ore» e le altre mail di avviso (Fase 2), e già oggi le mail di sollecito della scadenza stampa.

```bash
docker compose ps scheduler
docker compose logs --since 2h scheduler | grep -E "Running|DONE|FAIL"
```

Output atteso: il servizio `scheduler` risulta `Up` e, a ogni ora piena, compare una coppia di righe come queste:

```
scheduler-1  |   2026-10-30 14:00:00 Running ['artisan' timone:history-prune] . 188.02ms DONE
scheduler-1  |   ⇂ '/usr/local/bin/php' 'artisan' timone:history-prune > '/dev/null' 2>&1
```

Se `scheduler` non c'è, è fermo (`Exited`) o non mostra nessun `Running` nelle ultime due ore, **fermati**. Avvia lo stack con `docker compose up -d` e ricontrolla dopo l'ora piena successiva. Se ancora non gira, segnalalo: oggi senza scheduler non partono nemmeno le mail di sollecito della scadenza stampa.

**2. Aggiorna il codice e crea la configurazione del backup.**

```bash
git pull --ff-only origin master
./setup.sh
```

`./setup.sh` fa queste cose:
- se manca, crea `backup.env` da `backup.env.example` (permessi 600);
- genera la password del repository in `backup-secrets/restic-password` e quella dell'utente MySQL di backup;
- crea in MySQL l'utente **di sola lettura** `timone_backup`;
- ricostruisce e riavvia lo stack, come un deploy: fallo fuori dall'orario di lavoro. Se sul server c'è un `docker-compose.override.yml` (per esempio per le porte dietro il proxy HTTPS), `setup.sh` lo usa.

Righe attese:

```
✓ Creato backup.env (permessi 600) con la password dell'utente MySQL di backup.
✓ Generata la password del repository di backup in backup-secrets/restic-password.
! Copiala SUBITO in un password manager fuori da questo server: senza, i backup non si possono leggere.
! Backup: destinazione remota da configurare in backup.env
✓ Utente MySQL di backup (timone_backup, sola lettura) pronto.
```

Se `backup.env` esiste già, `setup.sh` **non lo tocca mai**. Se nel modello ci sono variabili nuove, si limita a elencarle.

> ⚠️ `./setup.sh` carica i dati demo **solo se il database è vuoto**. Su un server di produzione con utenti veri questo non succede. Su un server nuovo con il database vuoto, segui invece `docs/09-deploy.md`.

**3. Salva la password del repository fuori dal server.** Subito, prima di tutto il resto.

```bash
cat backup-secrets/restic-password
```

Copia la riga nel password manager aziendale, in una voce chiamata per esempio «Timone — password backup restic». Chi la custodisce va deciso ed è scritto in `HANDOFF.md`. Vedi [La chiave di cifratura](#la-chiave-di-cifratura).

**4. Scegli chi riceve gli avvisi.** Apri `backup.env` (`nano backup.env`) e compila:

```
BACKUP_ALERT_EMAIL=sistemista@tuodominio.it,altro@tuodominio.it
```

**5. Accendi il servizio di backup.** Aggiungi il profilo a `.env` e rilancia:

```bash
grep -q '^COMPOSE_PROFILES=' .env || printf '\nCOMPOSE_PROFILES=backup\n' >> .env
grep '^COMPOSE_PROFILES=' .env      # deve contenere «backup»
./setup.sh
docker compose ps backup-agent      # deve essere «Up»
docker compose logs backup-agent | tail -5
```

Nei log deve comparire `agente avviato — fuso orario Europe/Rome, copia remota: …`. Con `COMPOSE_PROFILES=backup` in `.env`, anche i `docker compose up -d` dei deploy successivi ricostruiscono il servizio di backup.

**6. Controlla la configurazione.**

```bash
./setup.sh backup-check
```

Deve finire con `Esito: configurazione locale a posto, manca la copia remota.`, oppure `Esito: tutto a posto.` se hai già configurato la copia remota. Ogni riga ❌ dice cosa correggere, ogni ⚠️ è un avviso da leggere. Se la destinazione remota non risponde, il controllo aspetta al massimo 60 secondi prima di dirlo.

**7. Fai subito il primo backup completo.**

```bash
docker compose run --rm backup-agent nightly
docker compose run --rm backup-agent status
```

Ogni job deve risultare `ok` (oppure `not_configured` per `remote`, finché non c'è la copia remota). Tempi misurati in sviluppo, con 3,2 GB di file e un database di 1,7 MB: primo giro completo **46 secondi**, giri successivi **14 secondi**.

**8. Prova le mail di avviso.** Senza un SMTP vero gli avvisi finiscono nel registro dell'app e non li vede nessuno.

```bash
docker compose run --rm backup-agent settings      # rende subito visibile all'app BACKUP_ALERT_EMAIL (altrimenti entro 15 minuti)
docker compose exec app php artisan timone:backup-test-alert
```

Ogni indirizzo di `BACKUP_ALERT_EMAIL` deve ricevere entro un minuto la mail «Backup: mail di prova» (controlla anche lo spam). Se il comando avvisa `MAIL_MAILER=log`, configura prima l'SMTP in `.env` (`MAIL_*`, vedi `docs/05-configurazione.md`) e rilancia.

**9. Configura la copia remota**, appena è stata scelta, e attiva il controllo indipendente sull'altra macchina: vedi [Configurare la copia remota](#configurare-la-copia-remota) e [Controllo indipendente](#controllo-indipendente-da-unaltra-macchina).

**10. Il giorno dopo**, controlla che i backup notturni siano partiti da soli:

```bash
docker compose run --rm backup-agent status
```

La data di `db` e di `files` deve essere quella della notte appena passata.

### Configurare la copia remota

#### Quali destinazioni sono ammesse, e perché

Il server del Timone ha per forza le credenziali della copia remota. Quindi chi lo compromette, o un ransomware che ci gira sopra, ha le stesse credenziali. La copia remota sopravvive solo se è **la destinazione stessa** a impedire di cancellare. Per questo sono ammessi **solo** due tipi di destinazione:

| Tipo | Requisiti obbligatori |
|---|---|
| **rest-server** (`rest:https://…`) | TLS (HTTPS), utente e password (htpasswd), `--append-only`, firewall che accetta solo l'IP del server del Timone |
| **S3 compatibile** (`s3:https://…`) | Bucket con **Object Lock in modalità COMPLIANCE**; chiave del server **senza** permesso di cancellare gli snapshot; HTTPS |

**Non ammessi**, e rifiutati da `./setup.sh backup-check` e dal backup stesso con un messaggio che spiega il motivo:
- una cartella o un NAS montato sul server (SMB/NFS): chi compromette il server la vede come un disco e la cancella. Un NAS montato può essere solo la [copia locale](#copia-locale-su-un-altro-disco);
- SFTP;
- indirizzi `http://` senza TLS;
- utente e password scritti dentro l'indirizzo.

Le credenziali che **possono cancellare** servono solo alla [pulizia annuale](#pulizia-annuale-della-copia-remota). Stanno nel password manager e si usano da un'altra macchina: **mai sul server, mai in `backup.env`**.

La **password del repository** (`backup-secrets/restic-password`) non va mai salvata sulla destinazione. Sulla destinazione restic scrive solo dati cifrati e la chiave cifrata con quella password. Il backup non include `backup-secrets/`, e `backup.env` non può contenere `RESTIC_PASSWORD` (l'agente si rifiuta di partire).

#### Configurare il server del Timone (5 passi)

1. Prepara la destinazione come descritto sotto (rest-server oppure S3).
2. Apri `backup.env` (`nano backup.env`) e compila `BACKUP_REMOTE_REPOSITORY` e le credenziali del tipo scelto: `BACKUP_REMOTE_REST_*` oppure `BACKUP_REMOTE_S3_*`. Se il certificato della destinazione è interno (non di una CA pubblica), copia il certificato della CA in `backup-secrets/` e indicalo in `BACKUP_REMOTE_CACERT`. Se conosci lo spazio disponibile, compila anche `BACKUP_REMOTE_QUOTA_GB`.
3. Lancia `./setup.sh backup-check`. Deve dire `Destinazione raggiungibile (HTTPS), autenticazione accettata`, `Scrittura di prova riuscita` e **`Append-only attivo`**. Qualunque ❌ va corretto: una destinazione da cui il server riesce a cancellare viene rifiutata.
4. Non serve riavviare nulla: il servizio rilegge `backup.env` a ogni giro. Al backup successivo parte da sola la **prima copia remota completa**. Per farla subito: `docker compose run --rm backup-agent nightly`.
5. Verifica con `docker compose run --rm backup-agent status`, che deve mostrare `remote: ok … copia remota (…) aggiornata`. Poi attiva il [controllo indipendente](#controllo-indipendente-da-unaltra-macchina) sull'altra macchina.

La copia remota usa **la stessa password** della copia locale.

#### Destinazione A — rest-server (per esempio su un NAS o un server dell'ufficio)

Sulla macchina di destinazione, **non** sul server del Timone (esempio con Docker; `/srv/restic` è la cartella dei dati):

```bash
# 1. Certificato: quello del dominio (es. Let's Encrypt) oppure di una CA interna.
#    Servono il certificato e la chiave in /srv/restic-tls/ (cert.pem, key.pem).

# 2. Utente e password per il server del Timone (la password va anche in backup.env).
docker run --rm -v /srv/restic:/data --entrypoint htpasswd restic/rest-server:0.14.0 \
  -B -c /data/.htpasswd timone          # chiede la password

# 3. Avvio: TLS, autenticazione, append-only, ogni utente vede solo il suo percorso.
docker run -d --name rest-server --restart unless-stopped -p 8000:8000 \
  -v /srv/restic:/data -v /srv/restic-tls:/tls:ro restic/rest-server:0.14.0 \
  rest-server --path /data --listen :8000 --tls --tls-cert /tls/cert.pem --tls-key /tls/key.pem \
  --append-only --private-repos
```

**Firewall: la porta 8000 accetta solo l'IP del server del Timone**, più quello della macchina del controllo indipendente, se è un'altra. Esempio con `ufw`:

```bash
sudo ufw default deny incoming
sudo ufw allow from 203.0.113.10 to any port 8000 proto tcp     # IP pubblico o interno del server del Timone
sudo ufw allow from 192.0.2.20 to any port 22 proto tcp         # accesso amministrativo, solo dalla rete dell'IT
sudo ufw enable
sudo ufw status numbered
```

Su un NAS Synology o QNAP la regola si fa dal pannello del firewall: consenti la porta 8000 solo all'IP del server del Timone, nega tutto il resto. La cartella `/srv/restic` **non** va condivisa via SMB/NFS con il server del Timone.

In `backup.env` sul server del Timone:

```
BACKUP_REMOTE_REPOSITORY=rest:https://backup.tuodominio.it:8000/timone/
BACKUP_REMOTE_REST_USERNAME=timone
BACKUP_REMOTE_REST_PASSWORD=la-password-scelta-al-passo-2
BACKUP_REMOTE_CACERT=                      # solo se la CA è interna: backup-secrets/ca-backup.pem
```

Con `--private-repos` l'utente `timone` può usare solo il percorso `/timone/`.

#### Destinazione B — S3 con Object Lock

Dalla console del fornitore (AWS, Wasabi, Backblaze B2, Scaleway, ecc.: ognuno ha la sua console, i nomi delle opzioni sono quelli standard S3):

1. Crea il bucket **con Object Lock attivo**: va scelto alla creazione, dopo non si può più attivare. Il versioning si attiva da solo.
2. Imposta la **conservazione predefinita**: modalità **COMPLIANCE** (nemmeno l'amministratore dell'account può accorciarla), durata **30 giorni** (o più). In quel periodo nessuna versione di nessun file può essere cancellata da nessuno.
3. Aggiungi una regola di ciclo di vita che elimina le **versioni non correnti** dopo 31 giorni: è lì che lo spazio liberato dalla pulizia annuale torna davvero libero.
4. Crea una chiave per il server del Timone con **questa** policy: può leggere e scrivere, cancellare **solo** i lucchetti temporanei di restic (`locks/`), mai gli snapshot o i dati.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    { "Effect": "Allow", "Action": ["s3:ListBucket", "s3:GetBucketLocation"],
      "Resource": "arn:aws:s3:::nome-bucket" },
    { "Effect": "Allow", "Action": ["s3:GetObject", "s3:PutObject"],
      "Resource": "arn:aws:s3:::nome-bucket/timone/*" },
    { "Effect": "Allow", "Action": ["s3:DeleteObject"],
      "Resource": "arn:aws:s3:::nome-bucket/timone/locks/*" }
  ]
}
```

5. Se il fornitore supporta le condizioni sull'IP, aggiungi al bucket una policy che **nega** tutto ciò che non arriva dall'IP del server del Timone, della macchina del controllo indipendente e di quella della pulizia annuale (condizione `aws:SourceIp`). È l'equivalente del firewall.
6. Crea una seconda chiave, **di sola lettura** (`s3:ListBucket` sul bucket), per il controllo indipendente, e una terza, **con permesso di cancellare**, per la pulizia annuale. Entrambe vanno nel password manager e **non** sul server del Timone.

In `backup.env` sul server del Timone:

```
BACKUP_REMOTE_REPOSITORY=s3:https://s3.fornitore.it/nome-bucket/timone
BACKUP_REMOTE_S3_ACCESS_KEY_ID=chiave-del-passo-4
BACKUP_REMOTE_S3_SECRET_ACCESS_KEY=segreto-del-passo-4
BACKUP_REMOTE_S3_REGION=eu-central-1
```

`backup-check` verifica che la chiave **non** riesca a cancellare uno snapshot. Che Object Lock sia davvero in COMPLIANCE, invece, non si può verificare dal server: lo ricorda con un ⚠️, e va controllato nella console del bucket.

#### Seconda copia remota (facoltativa)

Per una seconda copia immutabile **fuori dall'intranet** (per esempio la prima su un rest-server in ufficio, la seconda su S3 presso un fornitore esterno), compila le variabili `BACKUP_REMOTE_2_*` in `backup.env`. Sono le stesse della prima, con il prefisso `BACKUP_REMOTE_2_`, e valgono le stesse regole.

Il servizio la aggiorna nello stesso giro, subito dopo la prima (job `remote_2`), e `backup-check` la controlla allo stesso modo. Se è vuota non viene usata e non manda avvisi. Comandi restic sulla seconda copia: `docker compose run --rm backup-agent remota2 snapshots`.

#### Cosa vuol dire ogni errore di `backup-check`

| Messaggio | Cosa controllare |
|---|---|
| `Una cartella (anche un NAS SMB/NFS montato sul server) non è ammessa…` | Usa un rest-server o S3, come sopra. Il NAS montato può essere solo la copia locale. |
| `rest-server senza TLS` / `S3 senza TLS` | L'indirizzo deve iniziare con `rest:https://` o `s3:https://`. |
| `Credenziali scritte dentro …REPOSITORY` | Togli `utente:password@` dall'indirizzo e usa `…_REST_USERNAME` / `…_REST_PASSWORD`. |
| `rest-server senza autenticazione` / `S3 senza credenziali` | Compila utente e password, o le chiavi. |
| `Tipo di destinazione non supportato` | Solo `rest:https://…` o `s3:https://…`. |
| `Certificato HTTPS … non valido o non riconosciuto` | Per un certificato interno, metti la CA in `backup-secrets/` e indicala in `…_CACERT`. |
| `Rete: il nome del server remoto non si trova (DNS)` | L'indirizzo in `…REPOSITORY`. |
| `Rete: il server remoto non risponde` | Il servizio remoto è acceso? La porta è giusta? Il firewall della destinazione accetta l'IP di questo server? (Aspetta fino a 60 secondi prima di dirlo.) |
| `Credenziali rifiutate` | Utente e password del rest-server, oppure le chiavi S3. |
| `Permessi insufficienti` | L'utente non può scrivere lì. Per S3 il bucket va creato prima, dal fornitore. |
| `La password non apre il repository remoto` | In quel percorso c'è un repository creato con un'altra password: usa un percorso nuovo. |
| `Append-only NON attivo` / `Questa chiave S3 può cancellare gli snapshot` | La destinazione non protegge i backup: `--append-only` sul rest-server, oppure la policy S3 qui sopra. |

### Pulizia annuale della copia remota

Siccome il server non può cancellare, la copia remota cresce. **Una volta l'anno** qualcuno dell'IT la pulisce **da un'altra macchina** (un PC con Docker), con credenziali che **sul server del Timone non ci sono**. Le prende dal password manager e le usa solo per la durata della pulizia.

Ti servono, dal password manager:
- la password del repository («Timone — password backup restic»);
- per rest-server: l'accesso **amministrativo** alla cartella dei dati sul NAS (account del NAS, non quello del rest-server);
- per S3: la chiave **con permesso di cancellare** del passo 6.

Passo per passo, sul PC dell'IT:

1. Scrivi la password del repository in un file temporaneo **sul PC**, mai sul NAS o sul bucket:
   ```bash
   umask 077; nano /tmp/timone-restic-password
   ```
2. Indica il repository.
   - **rest-server**: monta sul PC la cartella dei dati del NAS con l'account amministrativo (es. in `/mnt/restic-admin`) e usa `REPO=/repo/timone`, con `-v /mnt/restic-admin:/repo` nei comandi sotto.
   - **S3**: `REPO=s3:https://s3.fornitore.it/nome-bucket/timone` e, nei comandi sotto, `-e AWS_ACCESS_KEY_ID=… -e AWS_SECRET_ACCESS_KEY=…` con la chiave che cancella, digitata solo qui.

   Prova la lettura:
   ```bash
   docker run --rm -v /mnt/restic-admin:/repo -v /tmp/timone-restic-password:/pw:ro \
     restic/restic:0.19.1 -r "$REPO" --password-file /pw snapshots
   ```
3. Applica la stessa conservazione della copia locale e togli le scritture di prova di `backup-check`:
   ```bash
   docker run --rm -v /mnt/restic-admin:/repo -v /tmp/timone-restic-password:/pw:ro \
     restic/restic:0.19.1 -r "$REPO" --password-file /pw forget --host timone --group-by host,tags \
     --keep-within 48h --keep-daily 14 --keep-weekly 8 --keep-monthly 12 --prune
   docker run --rm -v /mnt/restic-admin:/repo -v /tmp/timone-restic-password:/pw:ro \
     restic/restic:0.19.1 -r "$REPO" --password-file /pw forget --tag prova-scrittura --prune
   ```
4. Controlla: lo stesso comando con `check` al posto di `forget …` deve finire con `no errors were found`.
5. Cancella la password dal PC (`rm /tmp/timone-restic-password`), smonta la cartella del NAS e chiudi la sessione del password manager.

Ripeti per la seconda copia remota, se c'è. Con S3 e Object Lock, i file eliminati restano occupati fino alla fine della conservazione e della regola di ciclo di vita.

### Controllo indipendente da un'altra macchina

Il controllo «nessun backup da 26 ore» che parte dal server del Timone non serve se il problema **è** il server: spento, compromesso, disco pieno. Per questo c'è un secondo controllo, `scripts/backup/controllo-indipendente.sh`, che gira **su un'altra macchina** e non dipende in niente dal server del Timone:
- legge la data dell'ultimo backup arrivato **direttamente sulla destinazione**, in sola lettura;
- se supera le 26 ore (`MAX_ORE`), oppure se non riesce a leggere la destinazione, manda una mail con il **suo** server SMTP.

**Non serve la password del backup**, che quindi non va messa su quella macchina. In un repository restic ogni backup è un file a sé nella cartella `snapshots/`, e basta la sua data.

Requisiti della macchina: `bash`, `curl` (per S3, versione 7.75 o successiva), `find`, `date`. Provato in un container Alpine con curl 8.14.

**Dove farlo girare**:
- **rest-server**: sul NAS stesso (cron del NAS), con modalità `cartella` sulla cartella dei dati (es. `/srv/restic/timone`) e un utente in sola lettura;
- **S3**: su qualunque PC o server dell'IT, con modalità `s3` e la chiave **di sola lettura** del passo 6.

**Installazione**, sull'altra macchina:

```bash
mkdir -p /opt/controllo-backup-timone && cd /opt/controllo-backup-timone
# copia qui dal repository del Timone (git, chiavetta…) questi due file:
#   scripts/backup/controllo-indipendente.sh
#   scripts/backup/controllo-indipendente.conf.example  → rinominalo controllo-indipendente.conf
chmod 700 controllo-indipendente.sh && chmod 600 controllo-indipendente.conf
nano controllo-indipendente.conf                          # MODO, cartella o bucket, SMTP, destinatari
./controllo-indipendente.sh controllo-indipendente.conf --prova-mail   # deve arrivare «prova del controllo indipendente»
./controllo-indipendente.sh controllo-indipendente.conf               # deve dire «OK: …»
```

**Pianificazione**, una volta l'ora, con `crontab -e` o con l'«Utilità di pianificazione» del NAS:

```cron
15 * * * * /opt/controllo-backup-timone/controllo-indipendente.sh /opt/controllo-backup-timone/controllo-indipendente.conf >> /var/log/controllo-backup-timone.log 2>&1
```

Le mail sono:
- «nessun backup da più di 26 ore»: il server non sta copiando. Sul server lancia `docker compose run --rm backup-agent status`;
- «destinazione non leggibile»: il problema è la destinazione, oppure la configurazione del controllo;
- «nessun backup sulla destinazione»: il percorso o il prefisso è sbagliato, oppure i backup non sono mai arrivati.

Se il server è spento per una notte, la mail arriva una volta l'ora finché non riparte.

Provato in sviluppo:
- modalità `cartella` sulla cartella dei dati di un rest-server;
- modalità `s3` su un server compatibile S3 con chiave di sola lettura e lettura a pagine (18 snapshot letti 2 alla volta, lo stesso numero che conta restic);
- mail a più destinatari con curl.

### La chiave di cifratura

- È la password in `backup-secrets/restic-password` (permessi 600, fuori da git, mai nel backup). La stessa password apre la copia locale e le copie remote.
- **Va salvata fuori dal server appena generata** (passo 3 della checklist), nel password manager aziendale. Meglio anche una seconda copia in un luogo diverso: per esempio due persone diverse, o una busta chiusa in cassaforte.
- **Non va mai salvata sulla destinazione**: né sul NAS, né nel bucket, né sulla macchina del controllo indipendente, che non ne ha bisogno.
- Se il server va perso e la password non è salvata altrove, **tutti i backup sono illeggibili**.
- `setup.sh` non la rigenera mai se esiste già una copia di backup. Se il file manca, si ferma e chiede di recuperarla dal password manager.
- Per cambiarla: [Dopo un incidente di sicurezza](#dopo-un-incidente-di-sicurezza), punto 6.

### Segreti e log

- Nessun segreto passa sulla riga di comando: restic riceve le credenziali come variabili d'ambiente del solo processo, mysqldump la password in `MYSQL_PWD`.
- Ogni testo che finisce nel registro, nei file di stato o a video passa da un filtro che nasconde indirizzi, password e chiavi delle destinazioni e la password del database (`[nascosto]`, `[destinazione]`, `[indirizzo]`). Gli errori di restic, infatti, citano spesso l'indirizzo completo.
- L'app (avvisi e pagina admin) riceve dalle destinazioni solo «configurata sì/no», il tipo (`rest-server`, `s3`), date, esiti e dimensioni: mai indirizzi, utenti o percorsi.
- Verifica in qualunque momento, dalla cartella del progetto:
  ```bash
  scripts/backup/verifica-segreti-nei-log.sh
  ```
  Cerca nel registro, nei file di stato e nei log Docker del servizio i valori segreti e gli indirizzi presi da `backup.env` e dal file della password. Esce con `✅ Nessun segreto…`, oppure con `❌` e il numero della voce trovata, senza stamparla.

### Dopo un incidente di sicurezza

Se il server è stato compromesso, o se una copia di `.env`, `backup.env` o di un backup è finita in mani sbagliate, considera esposti **tutti** i segreti qui sotto. Il backup contiene `.env` e `backup.env`, quindi anche chi legge un backup li ha. Fai i passi in quest'ordine, dalla cartella del progetto, poi un backup completo nuovo.

1. **Credenziali delle destinazioni remote**, prima di tutto, così chi le ha non può più scrivere:
   - rest-server: sul NAS, `docker run --rm -v /srv/restic:/data --entrypoint htpasswd restic/rest-server:0.14.0 -B /data/.htpasswd timone`, poi aggiorna `BACKUP_REMOTE_REST_PASSWORD`;
   - S3: dalla console del fornitore crea una chiave nuova con la stessa policy, cancella la vecchia e aggiorna `BACKUP_REMOTE_S3_*`.

   Lo stesso per la seconda copia. Verifica con `./setup.sh backup-check`.
2. **Database.** Password dell'utente dell'app e di root:
   ```bash
   nuova_app="$(openssl rand -hex 20)"; nuova_root="$(openssl rand -hex 20)"
   docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot' <<SQL
   ALTER USER '$(grep '^DB_USERNAME=' .env | cut -d= -f2-)'@'%' IDENTIFIED BY '${nuova_app}';
   ALTER USER 'root'@'%' IDENTIFIED BY '${nuova_root}';
   ALTER USER 'root'@'localhost' IDENTIFIED BY '${nuova_root}';
   SQL
   sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${nuova_app}|; s|^MYSQL_ROOT_PASSWORD=.*|MYSQL_ROOT_PASSWORD=${nuova_root}|" .env
   docker compose up -d
   ```
   Poi la password dell'utente di backup: `sed -i "s|^BACKUP_DB_PASSWORD=.*|BACKUP_DB_PASSWORD=$(openssl rand -hex 20)|" backup.env && ./setup.sh`. `setup.sh` riallinea l'utente MySQL.
3. **`APP_KEY`** (sessioni, cookie, link firmati). Tutti gli utenti dovranno rifare il login; nessun dato salvato dipende da questa chiave.
   ```bash
   vecchia="$(grep '^APP_KEY=' .env | cut -d= -f2-)"
   sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
   docker compose up -d
   ```
   Non mettere la vecchia chiave in `APP_PREVIOUS_KEYS`: è proprio quella da non accettare più.
4. **Reverb** (tempo reale): `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`. La chiave entra nel JavaScript compilato, quindi serve una ricostruzione:
   ```bash
   sed -i "s|^REVERB_APP_ID=.*|REVERB_APP_ID=$(openssl rand -hex 6)|; s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=$(openssl rand -hex 10)|; s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=$(openssl rand -hex 16)|" .env
   docker compose up -d --build
   ```
5. **Mail**: cambia la password della casella SMTP dal fornitore, aggiorna `MAIL_PASSWORD` in `.env`, poi `docker compose up -d` e `docker compose exec app php artisan timone:backup-test-alert`.
6. **Password del repository di backup.** Ci sono due casi:
   - **Solo la password è trapelata** (per esempio letta da qualcuno), ma nessuno ha avuto i dati del backup: basta cambiarla. Restic cambia la chiave d'accesso e i backup vecchi restano leggibili con la nuova. Copia locale e copie remote usano la stessa password, quindi l'ordine conta: prima si **aggiunge** la nuova a tutte, poi si sostituisce il file, poi si toglie la vecchia.
     ```bash
     (umask 077; openssl rand -base64 48 > backup-secrets/restic-password.nuova)
     docker compose run --rm backup-agent locale  key add --new-password-file /project/backup-secrets/restic-password.nuova
     docker compose run --rm backup-agent remota  key add --new-password-file /project/backup-secrets/restic-password.nuova
     docker compose run --rm backup-agent remota2 key add --new-password-file /project/backup-secrets/restic-password.nuova   # solo se c'è la seconda copia
     mv backup-secrets/restic-password.nuova backup-secrets/restic-password
     docker compose run --rm backup-agent locale key list      # la chiave vecchia è quella senza «*»
     docker compose run --rm backup-agent locale key remove ID_DELLA_CHIAVE_VECCHIA
     docker compose run --rm backup-agent nightly              # deve finire con tutti i job OK
     ```
     Salva subito la password nuova nel password manager e segna la vecchia come «non più valida». Sulle copie remote `key remove` dal server non funziona, perché l'append-only lo vieta (`403 Forbidden`): la chiave vecchia si toglie dall'altra macchina, come nella [pulizia annuale](#pulizia-annuale-della-copia-remota), con `key list` e `key remove`.
   - **Il server è stato compromesso**: chi ha avuto password e repository insieme può essersi copiato la chiave interna di cifratura, che cambiare password non cambia. Servono **repository nuovi**:
     1. ferma il servizio: `docker compose stop backup-agent`;
     2. sposta la copia locale vecchia, `sudo mv backups/restic backups/restic-prima-dell-incidente`, e la password vecchia nel password manager come «password backup PRIMA dell'incidente»;
     3. rimuovi `backup-secrets/restic-password`, poi `./setup.sh`, che ne genera una nuova perché la copia locale non c'è più;
     4. in `backup.env` cambia il percorso remoto: `/timone-2/` sul rest-server, oppure un nuovo prefisso o bucket su S3;
     5. `./setup.sh backup-check` e `docker compose run --rm backup-agent nightly`.

     I repository remoti vecchi restano intatti (sono immutabili) e si leggono con la password vecchia. Si eliminano con la pulizia annuale quando non servono più.
7. **Utenti dell'app**: le password sono salvate come hash, ma chi ha il database può provare a indovinarle. Chiedi a tutti di cambiarla («Profilo» → password).
8. **Fine**: `scripts/backup/verifica-segreti-nei-log.sh`, `./setup.sh backup-check` e un backup completo. Annota data e cosa è stato ruotato in `HANDOFF.md` o nel registro dell'IT.

Verificato in sviluppo il 2026-10-30:
- il cambio della password del repository con i comandi qui sopra, su copia locale, rest-server (TLS, append-only) e S3, seguito da un giro `nightly` tutto OK; la password vecchia non apre più la copia locale;
- su copie dei repository, `key remove` dall'accesso amministrativo, dopo il quale la password nuova apre tutti gli snapshot vecchi;
- attraverso il rest-server append-only, `key remove` è rifiutato (403).

I passi 1–5 e 7 sono procedure standard e non sono stati eseguiti qui.

### Il registro e lo stato dei backup

```bash
docker compose run --rm backup-agent status       # esito dell'ultimo giro di ogni job + ultime righe del registro
docker compose logs --since 24h backup-agent      # cosa ha fatto l'agente nelle ultime 24 ore
```

Il registro completo è nel volume di stato, file `backup.log` (5 MB, più una copia precedente `backup.log.1`):

```bash
docker compose run --rm --entrypoint tail backup-agent -n 50 /status/backup.log
```

Ogni riga ha data e ora, il job tra parentesi quadre e l'esito, con durata e dimensioni:

```
2026-10-30 02:00:03 [db] OK in 3s — dump di 1.2MB (34 tabelle), aggiunti 15.3KB, snapshot 1a2b3c4d
2026-10-30 02:04:51 [files] OK in 288s — 640 file, 3.1GB in totale, aggiunti 2.4MB, snapshot 5e6f7a8b
2026-10-30 02:04:58 [remote] Copia remota non configurata: i backup esistono solo su questo server.
2026-10-30 02:05:10 [check] ERRORE dopo 2s — Controllo di integrità della copia locale non riuscito: …
```

I job sono:
- `db`: dump del database;
- `files`: file e configurazione;
- `check`: integrità della struttura;
- `remote`: copia remota;
- `remote_2`: seconda copia remota (`not_configured` se non usata);
- `space`: spazio;
- `prune`: pulizia;
- `verify_data`: rilettura a campione dei dati.

Gli esiti possibili sono `ok`, `failed`, `running` (in corso) e `not_configured` (solo per `remote` e `remote_2`).

Se un giro si interrompe a metà (container fermato, server riavviato), il giro successivo lo segna come fallito nel registro: `ERRORE — il giro precedente si era interrotto a metà; segnato come fallito`. Poi riparte normalmente: non serve sbloccare nulla a mano.

**Elenco dei backup disponibili:**

```bash
docker compose run --rm backup-agent locale snapshots              # copia locale
docker compose run --rm backup-agent remota snapshots              # copia remota
docker compose run --rm backup-agent locale snapshots --tag db     # solo database
```

### Dump manuale pre-deploy

Il servizio **`backup`** (profilo `tools`) è un'altra cosa, più vecchia e indipendente: fa un dump compresso del solo database in `./backups/timone_AAAA-MM-GG_HHMMSS.sql.gz`. Si lancia a mano **prima di ogni deploy** (passo 1 di `docs/09-deploy.md`), per avere un punto di ritorno immediato in caso di migrazione sbagliata:

```bash
docker compose --profile tools run --rm backup
```

Non cifra, non salva i file, non pulisce i dump vecchi e non va fuori dal server. Non sostituisce il backup automatico. Ripristino: `DOCKER.md`, «Ripristinare un backup».

### Il commit in produzione

Ogni backup contiene `info/commit.json` con l'hash del commit che gira, letto da `.git` della cartella del progetto (montata nel container di backup). Le immagini dell'app **non** contengono `.git` (`.dockerignore` la esclude). Se la cartella `.git` non c'è, si usa l'hash passato alla build (`GIT_COMMIT`, impostato da `setup.sh`).

`commit.json` dice anche:
- se ci sono modifiche locali non committate (`local_changes`);
- se il commit è su un ramo remoto (`on_remote`, secondo l'ultimo `git fetch` del server).

Se il commit non è su nessun ramo remoto, il registro lo segnala. In quel caso fai `git push` dal server o da chi ha quel commit, altrimenti dopo un disastro quel codice non si recupera.

Per vederlo dall'ultimo backup:

```bash
docker compose run --rm backup-agent locale dump latest --tag files /dump/info/commit.json
```

### Perché Redis non è nel backup

Redis contiene solo la **coda dei lavori** (miniature, mail, PDF sfogliabili, cancellazione file) e la **cache**. Le sessioni stanno in MySQL. I lavori in coda durano pochi secondi: rimettere in coda lavori vecchi di ore farebbe più danni che altro, per esempio mail mandate due volte. Dopo un ripristino:

- **Miniature rimaste «in elaborazione»**: rimettile in coda.
  ```bash
  docker compose exec app php artisan tinker --execute='App\Models\ContentFile::whereIn("thumbnail_status", ["pending","processing"])->each(fn ($f) => App\Jobs\GenerateContentFileThumbnail::dispatch($f));'
  ```
- **Lavori falliti**: `docker compose exec app php artisan queue:failed`, poi `queue:retry all` oppure `queue:forget <id>` (dettagli in `docs/08-job-e-scheduler.md`).
- **PDF sfogliabili**: rigenerali dal pannello del numero.
- **Mail di sollecito**: chi è stato segnato come avvisato nel database non riceve di nuovo la mail. Se ne serve una, chiedila al redattore a voce.

### Salvare cartelle fuori dal progetto

Per includere per esempio il proxy HTTPS e i certificati dell'host, montali in sola lettura sotto `/host` con `docker-compose.override.yml`:

```yaml
services:
  backup-agent:
    volumes:
      - /etc/nginx:/host/etc/nginx:ro
      - /etc/letsencrypt:/host/etc/letsencrypt:ro
```

In `backup.env`:

```
BACKUP_EXTRA_PATHS=/host/etc/nginx /host/etc/letsencrypt
```

Poi `docker compose up -d backup-agent`. Se un percorso indicato non esiste nel container, il backup dei file fallisce e lo dice: un percorso dimenticato non deve passare inosservato.

### Copia locale su un altro disco

Con un secondo disco, oppure una cartella di un NAS montata via SMB/NFS, per esempio in `/mnt/backup`, in `docker-compose.override.yml`:

```yaml
services:
  backup-agent:
    volumes:
      - /mnt/backup/timone-restic:/repo
```

Prima di applicarlo, sposta la copia esistente: `sudo mv backups/restic /mnt/backup/timone-restic`. Poi `docker compose up -d backup-agent` e `./setup.sh backup-check`.

Un NAS montato va bene **solo qui**, come copia locale: il server lo vede come un disco e può cancellarlo, quindi non conta come copia fuori dal server. La copia remota resta comunque obbligatoria.

### Ripristino rapido (provato in sviluppo)

Il runbook completo, con i tre scenari, arriva nella Fase 2. Questi due comandi sono stati provati in sviluppo il 2026-10-30: rivista eliminata e PDF cancellato, poi entrambi tornati identici, con lo stesso checksum e lo stesso proprietario del file.

**Riportare il database all'ultimo backup.** Sostituisce **tutto** il database attuale, quindi prima fai un dump di sicurezza:

```bash
docker compose --profile tools run --rm backup                     # dump di sicurezza in ./backups
docker compose run --rm -T backup-agent locale dump latest --tag db /dump/db/timone.sql \
  | docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot "$MYSQL_DATABASE"'
```

Per un backup precedente, al posto di `latest` metti l'ID preso da `docker compose run --rm backup-agent locale snapshots --tag db`.

**Recuperare un file** (qui un PDF di un contenuto), senza toccare il resto:

```bash
docker compose run --rm -T -v timone-rivista_storage_data:/ripristino backup-agent \
  locale restore latest:/var/www/html/storage/app --tag files --target /ripristino \
  --include /private/contents/9/bb3e72e2-0a81-4904-b179-6c123c29943d.pdf
```

Il percorso dopo `--include` è quello del file sotto `storage/app` (la colonna `path` di `content_files`, preceduto da `/private/`). Con `--include /private/contents/9` si recupera l'intera cartella del contenuto 9. Il nome del volume `timone-rivista_storage_data` viene da `name: timone-rivista` in `docker-compose.yml` e non dipende dal nome della cartella (verifica: `docker volume ls | grep storage_data`).

### In arrivo (Fase 2)

Non sono ancora disponibili, e quindi non sono descritti qui:
- runbook dei tre scenari di ripristino (server nuovo, solo database, singolo numero o file);
- prova di ripristino automatica settimanale;
- mail di avviso automatiche (backup fallito, nessun backup da 26 ore, spazio, copia remota non configurata), con cosa fare per ciascuna;
- pagina admin «Backup»: mostrerà solo stato, date e dimensioni, mai indirizzi, utenti, percorsi o credenziali delle destinazioni;
- cambio di destinazione (la rotazione della password è già in [Dopo un incidente di sicurezza](#dopo-un-incidente-di-sicurezza));
- checklist mensile.
