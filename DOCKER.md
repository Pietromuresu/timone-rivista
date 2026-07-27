# Avvio rapido (Docker)

## Cosa serve prima

Solo [Docker Desktop](https://www.docker.com/products/docker-desktop/) installato e avviato (icona balena nella barra in alto, deve essere attiva).

## Avvio

Apri il Terminale nella cartella del progetto e lancia:

```bash
./setup.sh
```

Un solo comando. Fa tutto da solo: crea `.env`, genera le password/chiavi necessarie, costruisce e avvia i container, prepara il database. La primissima volta impiega qualche minuto (scarica e costruisce le immagini); le volte successive è molto più veloce.

Alla fine stampa un riepilogo con gli indirizzi da aprire e le credenziali demo.

## Uso quotidiano

```bash
./setup.sh                # avvia tutto (se è già tutto pronto, riparte e basta)
docker compose logs -f    # log in diretta di tutti i servizi (Ctrl+C per uscire)
docker compose ps         # cosa sta girando in questo momento
docker compose down       # ferma tutto (i dati restano salvati)
```

Puoi rilanciare `./setup.sh` ogni volta che vuoi, anche a container già avviati: non rompe né duplica nulla.

## Reset completo (si è rotto tutto, ripartire da zero)

```bash
docker compose down -v
./setup.sh
```

`down -v` cancella anche i dati salvati (database, file caricati) — dopo, `./setup.sh` riparte come alla primissima volta, con dati demo puliti.

## Se qualcosa va storto

**"Docker non è installato" oppure "non è in esecuzione"**
Apri l'app Docker Desktop e aspetta che l'icona diventi attiva, poi rilancia `./setup.sh`.

**Un tentativo di avvio fallisce con un errore tipo `mkdir ... file exists`, ma il secondo tentativo va bene**
Può capitare alla primissima creazione dei volumi Docker su alcune macchine — `./setup.sh` lo rileva da solo e ritenta automaticamente fino a 3 volte. Se fallisce anche dopo 3 tentativi, rilancia semplicemente `./setup.sh` un'altra volta.

**Errore tipo `required variable ... is missing a value` durante l'avvio**
Non dovrebbe più succedere: `./setup.sh` genera da solo tutte le password/chiavi obbligatorie. Se lo vedi comunque, apri il file `.env` e controlla che le righe `APP_KEY=`, `DB_PASSWORD=`, `MYSQL_ROOT_PASSWORD=`, `REVERB_APP_ID=`, `REVERB_APP_KEY=`, `REVERB_APP_SECRET=` abbiano davvero un valore dopo l'`=` (non vuote) — se una è vuota, cancellala dal file e rilancia `./setup.sh`: la rigenera lui.

**La pagina su `http://localhost` non si apre / errore di connessione**
Aspetta qualche secondo (l'avvio dei container può richiedere un attimo) e ricarica. Se persiste, controlla lo stato con `docker compose ps`: tutti i servizi devono essere "Up" (mysql/redis/app/reverb anche "healthy"). Guarda i log del servizio in difficoltà con `docker compose logs <nome-servizio>`.

**Voglio ripartire completamente da zero, anche cancellando le password generate**
```bash
docker compose down -v
rm .env
./setup.sh
```

## Indirizzi e credenziali

- App: <http://localhost>
- Adminer (gestione database): <http://localhost:8081> — server `mysql`, credenziali in `.env` (`DB_USERNAME`/`DB_PASSWORD`)
- Utenti demo (password per tutti: `password`): `admin@timone.test` (Admin, vede tutte le riviste), `redattore@timone.test` (Redattore, solo su alcune riviste)

## Backup del database

Non parte in automatico con `./setup.sh` — va lanciato quando serve:

```bash
docker compose --profile tools run --rm backup
```

Il file compresso (`.sql.gz`) viene salvato nella cartella `./backups`.

## Pulizia dei file PDF orfani

Quando una pagina viene eliminata (es. riducendo il numero totale di pagine di un numero), le righe del database si cancellano da sole, ma i file PDF/anteprime fisici restano sul disco. Non parte in automatico — va lanciato ogni tanto:

```bash
# prima un'anteprima di cosa verrebbe eliminato, senza cancellare nulla
docker compose exec app php artisan pagefiles:prune-orphaned --dry-run

# poi la pulizia vera
docker compose exec app php artisan pagefiles:prune-orphaned
```

## Ripristinare un backup

Sostituisce **completamente** il database attuale con il contenuto del backup — tutto quello che c'è oggi nel database va perso. Se hai dubbi, fanne prima uno nuovo con il comando sopra.

```bash
# 1. Scegli il file da ripristinare, es. ./backups/timone_2026-07-27_143611.sql.gz
# 2. Decomprimilo e caricalo nel database dentro Docker:
gunzip -c ./backups/timone_2026-07-27_143611.sql.gz | docker compose exec -T mysql \
  mysql -u root -p"$(grep '^MYSQL_ROOT_PASSWORD=' .env | cut -d'=' -f2-)" timone
```

Alla fine ricarica la pagina dell'app: i dati sono quelli del backup.

## Aggiornare il codice

Quando arrivano modifiche al progetto (es. da git):

```bash
git pull
docker compose up -d --build      # ricostruisce le immagini con il codice nuovo
docker compose exec -T app php artisan migrate --force   # applica eventuali nuove migrazioni del database
```

Non serve rilanciare `./setup.sh`: le credenziali in `.env` restano quelle già generate, e il seed dei dati demo non va ripetuto (andrebbe in errore su un database che ha già dei dati — vedi sopra).
