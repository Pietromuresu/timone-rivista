#!/usr/bin/env bash
#
# Avvio a un solo comando di Timone Elettronico via Docker.
#
# Uso:
#   ./setup.sh          # produzione: docker-compose.yml da solo, immagini
#                        # con codice/asset "congelati" nella build
#   ./setup.sh dev       # sviluppo: aggiunge docker-compose.dev.yml
#                        # (bind mount del codice, Vite con HMR su :5173)
#   ./setup.sh backup-check
#                        # prova la configurazione del backup (backup.env):
#                        # database, copia locale, copia remota, append-only
#
# Cosa fa, in ordine:
#   1. Se manca ".env", lo crea da ".env.example".
#   2. Genera da solo tutte le password/chiavi obbligatorie ancora vuote
#      (APP_KEY, DB_PASSWORD, MYSQL_ROOT_PASSWORD, REVERB_APP_ID/KEY/SECRET)
#      — nessun valore da inventare o copiare a mano.
#   3. Costruisce e avvia tutti i container Docker (nella modalità scelta).
#   4. Esegue le migrazioni del database e carica i dati demo.
#   5. Stampa un riepilogo con gli URL da aprire e i comandi utili.
#   Inoltre (backup, 2026-10-30): crea backup.env e la password del
#   repository se mancano, e l'utente MySQL di sola lettura per il dump.
#
# Rilanciabile in sicurezza in qualunque momento: non tocca i valori già
# presenti in ".env", genera solo quelli ancora vuoti. backup.env e la
# password del backup, se esistono, non vengono MAI riscritti.

set -euo pipefail
cd "$(dirname "$0")"

# --- colori per l'output (disattivati se il terminale non li supporta) -----
if [ -t 1 ]; then
    C_RED='\033[0;31m'; C_GREEN='\033[0;32m'; C_YELLOW='\033[0;33m'
    C_BLUE='\033[0;34m'; C_BOLD='\033[1m'; C_RESET='\033[0m'
else
    C_RED=''; C_GREEN=''; C_YELLOW=''; C_BLUE=''; C_BOLD=''; C_RESET=''
fi

info()  { printf "%b\n" "${C_BLUE}==>${C_RESET} $*"; }
ok()    { printf "%b\n" "${C_GREEN}✓${C_RESET} $*"; }
warn()  { printf "%b\n" "${C_YELLOW}!${C_RESET} $*"; }
fail()  { printf "%b\n" "${C_RED}✗ $*${C_RESET}"; exit 1; }

# -----------------------------------------------------------------------------
# -1. Modalità: produzione (default) o sviluppo (./setup.sh dev)
# -----------------------------------------------------------------------------
# docker-compose.dev.yml NON viene caricato automaticamente da "docker
# compose" (a differenza di un file chiamato "docker-compose.override.yml")
# — scelta deliberata per non rischiare di far girare per sbaglio bind
# mount/Vite dev server su un server di produzione con un comando "nudo".
# Qui lo aggiungiamo esplicitamente solo se richiesto.
MODE="${1:-prod}"
case "$MODE" in
    dev)
        DC=(docker compose -f docker-compose.yml -f docker-compose.dev.yml)
        info "Modalità sviluppo: bind mount del codice + Vite dev server (docker-compose.dev.yml)."
        ;;
    prod)
        DC=(docker compose -f docker-compose.yml)
        # docker-compose.override.yml è il posto previsto per le modifiche
        # locali del server (porte dietro il proxy HTTPS, docs/09-deploy.md;
        # montaggi del backup, docs/OPERATIONS.md). Con «-f» esplicito Compose
        # non lo carica più da solo: va aggiunto qui, altrimenti setup.sh
        # rimetterebbe nginx sulla porta 80 (corretto il 2026-10-30).
        if [ -f docker-compose.override.yml ]; then
            DC+=(-f docker-compose.override.yml)
            info "Modalità produzione: docker-compose.yml + docker-compose.override.yml (nessun bind mount del codice)."
        else
            info "Modalità produzione: solo docker-compose.yml (nessun bind mount)."
        fi
        ;;
    backup-check)
        # Non avvia né ricostruisce lo stack: prova solo la configurazione
        # del backup dentro un container usa e getta (restic è lì, non sull'host).
        [ -f backup.env ] || fail "Manca backup.env: esegui prima ./setup.sh, che lo crea."
        GIT_COMMIT="$(git rev-parse HEAD 2>/dev/null || true)"
        export GIT_COMMIT
        # Senza «-f»: Compose carica da solo docker-compose.override.yml se
        # c'è (montaggi del NAS o del secondo disco), come i comandi della guida.
        exec docker compose --profile backup run --rm --build backup-agent backup-check
        ;;
    *)
        fail "Argomento non riconosciuto: \"$MODE\". Uso: ./setup.sh, ./setup.sh dev oppure ./setup.sh backup-check"
        ;;
esac

# -----------------------------------------------------------------------------
# 0. Prerequisiti: Docker installato e il suo demone acceso
# -----------------------------------------------------------------------------

command -v docker >/dev/null 2>&1 || fail "Docker non è installato. Installa Docker Desktop da https://www.docker.com/products/docker-desktop/ e riprova."

if ! docker info >/dev/null 2>&1; then
    fail "Docker è installato ma non è in esecuzione. Apri l'app \"Docker Desktop\" (icona balena), aspetta che diventi verde/attiva, poi rilancia ./setup.sh."
fi

ok "Docker è installato ed è in esecuzione."

# -----------------------------------------------------------------------------
# 1. Crea .env da .env.example se manca
# -----------------------------------------------------------------------------

if [ ! -f .env ]; then
    info "Non trovo il file .env: lo creo da .env.example."
    cp .env.example .env
    ok "Creato .env"
else
    ok ".env già presente — non lo sovrascrivo, riuso quello che c'è."
fi

# -----------------------------------------------------------------------------
# 2. Genera automaticamente le variabili obbligatorie ancora vuote
# -----------------------------------------------------------------------------

command -v openssl >/dev/null 2>&1 || fail "openssl non è disponibile: dovrebbe essere già installato su macOS/Linux. Se manca davvero, installalo e rilancia."

# Legge il valore attuale di una chiave da .env (stringa vuota se assente o
# vuota). Terzo argomento facoltativo di set_env_var/get_env_var: il file
# (default .env), usato anche per backup.env appena creato.
get_env_var() {
    local file="${2:-.env}"
    grep -m1 "^${1}=" "$file" 2>/dev/null | cut -d'=' -f2- || true
}

# Sostituisce (o aggiunge, se la chiave non esiste già) il valore di una
# variabile in .env, preservando l'ordine e i commenti del file. Non
# sovrascrive mai il file da zero: riscrive le stesse righe cambiando solo
# quella della chiave. «cat >» e non «mv» (2026-10-30): mv sostituiva il
# file con uno nuovo con i permessi di default (644), facendo perdere il
# chmod 600 di .env/backup.env, che contengono password.
set_env_var() {
    local key="$1" value="$2" file="${3:-.env}"
    if grep -q "^${key}=" "$file"; then
        awk -v k="$key" -v v="$value" '$0 ~ "^"k"=" { print k"="v; next } { print }' "$file" > "$file.tmp"
        cat "$file.tmp" > "$file"
        rm -f "$file.tmp"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}

# Genera un valore (se ancora vuoto) e lo scrive in .env, spiegando cosa ha fatto.
generate_if_blank() {
    local key="$1" generator="$2" label="$3"
    local current
    current="$(get_env_var "$key")"
    if [ -z "$current" ]; then
        local value
        value="$(eval "$generator")"
        set_env_var "$key" "$value"
        ok "Generato $label ($key) — nuovo valore scritto in .env."
    else
        ok "$label ($key) già impostata — la lascio com'è."
    fi
}

info "Controllo le credenziali in .env, genero quelle mancanti..."

generate_if_blank APP_KEY 'echo "base64:$(openssl rand -base64 32)"' "la chiave di cifratura Laravel"
generate_if_blank DB_PASSWORD 'openssl rand -hex 20' "la password del database applicativo"
generate_if_blank MYSQL_ROOT_PASSWORD 'openssl rand -hex 20' "la password root di MySQL"
generate_if_blank REVERB_APP_ID 'openssl rand -hex 6' "l'ID dell'app Reverb"
generate_if_blank REVERB_APP_KEY 'openssl rand -hex 10' "la chiave dell'app Reverb"
generate_if_blank REVERB_APP_SECRET 'openssl rand -hex 16' "il secret dell'app Reverb"

# -----------------------------------------------------------------------------
# 3. Verifica finale: nessuna variabile obbligatoria deve essere rimasta vuota
#    (rete di sicurezza, in teoria il passo 2 le ha già tutte generate)
# -----------------------------------------------------------------------------

missing=()
for key in APP_KEY DB_PASSWORD MYSQL_ROOT_PASSWORD REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET; do
    [ -z "$(get_env_var "$key")" ] && missing+=("$key")
done

if [ "${#missing[@]}" -gt 0 ]; then
    warn "Queste variabili sono ancora vuote in .env, impostale a mano e rilancia ./setup.sh:"
    for key in "${missing[@]}"; do
        printf "    - %s\n" "$key"
    done
    fail "Avvio interrotto: variabili obbligatorie mancanti (vedi sopra)."
fi

ok "Tutte le credenziali obbligatorie sono impostate."

# -----------------------------------------------------------------------------
# 3bis. Configurazione del backup (backup.env + password del repository)
# -----------------------------------------------------------------------------
# Regola (2026-10-30): setup.sh crea ciò che manca e non riscrive MAI ciò che
# esiste. backup.env contiene la destinazione remota e le credenziali scelte
# a mano: un merge automatico rischierebbe di perderle. Le variabili nuove
# del modello vengono solo elencate.

if [ ! -f backup.env ]; then
    info "Non trovo backup.env: lo creo da backup.env.example."
    (umask 077; cp backup.env.example backup.env)
    set_env_var BACKUP_DB_PASSWORD "$(openssl rand -hex 20)" backup.env
    ok "Creato backup.env (permessi 600) con la password dell'utente MySQL di backup."
else
    ok "backup.env già presente — non lo modifico."
    missing_backup_keys=()
    while IFS= read -r key; do
        grep -q "^${key}=" backup.env || missing_backup_keys+=("$key")
    done < <(grep -oE '^[A-Z_][A-Z0-9_]*=' backup.env.example | tr -d '=')
    if [ "${#missing_backup_keys[@]}" -gt 0 ]; then
        warn "In backup.env.example ci sono variabili nuove che mancano in backup.env (aggiungile a mano se ti servono, i commenti sono nel modello):"
        for key in "${missing_backup_keys[@]}"; do
            printf "    - %s\n" "$key"
        done
    fi
fi

restic_password_file="$(get_env_var RESTIC_PASSWORD_FILE backup.env)"
restic_password_file="${restic_password_file:-backup-secrets/restic-password}"
case "$restic_password_file" in
    /project/*) restic_password_file="${restic_password_file#/project/}" ;;
esac
if [ -s "$restic_password_file" ]; then
    ok "Password del repository di backup presente ($restic_password_file) — non la modifico."
elif [ -e backups/restic/config ] || { [ -d backups/restic ] && { [ ! -r backups/restic ] || [ -n "$(ls -A backups/restic 2>/dev/null)" ]; }; }; then
    # Una password nuova non aprirebbe i backup già fatti: meglio fermarsi.
    # Anche una cartella non vuota o non leggibile conta come «backup già
    # esistente»: su Linux i file del repository appartengono a root (li
    # scrive il container) e un controllo solo su «config» potrebbe sbagliare.
    fail "Esiste già una copia di backup (backups/restic) ma manca la sua password ($restic_password_file). Recuperala dal password manager e rimettila in quel file (permessi 600), poi rilancia."
else
    mkdir -p "$(dirname "$restic_password_file")"
    chmod 700 "$(dirname "$restic_password_file")"
    (umask 077; openssl rand -base64 48 > "$restic_password_file")
    chmod 600 "$restic_password_file"
    ok "Generata la password del repository di backup in $restic_password_file."
    warn "Copiala SUBITO in un password manager fuori da questo server: senza, i backup non si possono leggere."
fi

backup_remote="$(get_env_var BACKUP_REMOTE_REPOSITORY backup.env)"
if [ -z "$backup_remote" ] || [[ "$backup_remote" == *DA_CONFIGURARE* ]]; then
    warn "Backup: destinazione remota da configurare in backup.env"
fi

# -----------------------------------------------------------------------------
# 4. Costruisce e avvia i container
# -----------------------------------------------------------------------------

info "Costruisco e avvio i container Docker (la prima volta può richiedere qualche minuto)..."

# Hash del commit per il backup (ripiego se .git non è visibile nel container
# del backup). Vuoto se non siamo in un clone git.
GIT_COMMIT="$(git rev-parse HEAD 2>/dev/null || true)"
export GIT_COMMIT

# Al primissimo avvio in assoluto (volumi Docker appena creati) può capitare
# — osservato davvero durante lo sviluppo di questo script — che più
# container che condividono lo stesso volume ("app"/"worker"/"reverb" su
# storage_data) provino a inizializzarlo nello stesso istante e Docker
# Desktop segnali un errore del tipo "mkdir ... file exists": è una
# condizione temporanea del motore Docker, non un problema di
# configurazione, e sparisce da sola ripetendo il comando. Un utente non
# tecnico non deve accorgersene: si ritenta da soli fino a 3 volte.
attempt=1
max_attempts=3
until "${DC[@]}" up -d --build; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        fail "\"docker compose up\" ha fallito $max_attempts volte di fila. Copia l'errore qui sopra e chiedi aiuto, oppure prova a rilanciare ./setup.sh tra qualche secondo."
    fi
    attempt=$((attempt + 1))
    warn "Primo tentativo non riuscito (capita al primissimo avvio, è normale) — riprovo (tentativo $attempt di $max_attempts)..."
    sleep 3
done

ok "Container avviati e in stato \"healthy\"."

# -----------------------------------------------------------------------------
# 5. Migrazioni + dati demo
# -----------------------------------------------------------------------------
# Il seed NON è pensato per essere rieseguito su un database che ha già dei
# dati (crea di nuovo gli stessi utenti demo con la stessa email, violando il
# vincolo di unicità) — scoperto rilanciando ./setup.sh una seconda volta
# senza "docker compose down -v" in mezzo. Le migrazioni sono sempre sicure
# da rilanciare (Laravel salta da solo quelle già applicate); il seed va
# invece eseguito una volta sola, solo se il database è ancora vuoto.

info "Eseguo le migrazioni del database..."
"${DC[@]}" exec -T app php artisan migrate --force

user_count="$("${DC[@]}" exec -T app php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tr -d '[:space:]')"

if [ "$user_count" = "0" ] || [ -z "$user_count" ]; then
    info "Database vuoto: carico gli utenti/riviste/numeri demo..."
    "${DC[@]}" exec -T app php artisan db:seed --force
    ok "Dati demo caricati."
else
    ok "Il database ha già dei dati ($user_count utenti) — non ripeto il seed per non duplicarli."
fi

ok "Database pronto."

# Utente MySQL di sola lettura per il dump del backup. Ogni volta (è
# idempotente): così la password di backup.env e quella in MySQL restano
# allineate anche se qualcuno la cambia nel file. La password generata è
# esadecimale; se la scrivi a mano usa solo lettere e numeri.
backup_db_user="$(get_env_var BACKUP_DB_USER backup.env)"
backup_db_user="${backup_db_user:-timone_backup}"
backup_db_password="$(get_env_var BACKUP_DB_PASSWORD backup.env)"
db_name="$(get_env_var DB_DATABASE)"
db_name="${db_name:-timone}"
if [ -n "$backup_db_password" ]; then
    if "${DC[@]}" exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot' <<SQL
CREATE USER IF NOT EXISTS '${backup_db_user}'@'%' IDENTIFIED BY '${backup_db_password}';
ALTER USER '${backup_db_user}'@'%' IDENTIFIED BY '${backup_db_password}';
GRANT SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES ON \`${db_name}\`.* TO '${backup_db_user}'@'%';
GRANT SHOW_ROUTINE ON *.* TO '${backup_db_user}'@'%';
SQL
    then
        ok "Utente MySQL di backup (${backup_db_user}, sola lettura) pronto."
    else
        warn "Non sono riuscito a creare l'utente MySQL di backup: il backup del database fallirà finché non rilanci ./setup.sh."
    fi
else
    warn "BACKUP_DB_PASSWORD vuota in backup.env: utente MySQL di backup non creato."
fi

# -----------------------------------------------------------------------------
# 6. Riepilogo finale
# -----------------------------------------------------------------------------

echo
printf "%b\n" "${C_BOLD}==================================================================${C_RESET}"
printf "%b\n" "${C_BOLD} Timone Elettronico è avviato ✅  (modalità: ${MODE})${C_RESET}"
printf "%b\n" "${C_BOLD}==================================================================${C_RESET}"
echo
echo "App:      http://localhost"
echo "Adminer:  http://localhost:8081  (server: mysql, credenziali in .env)"
echo "Reverb:   ws://localhost:8080    (usato in automatico dal browser)"
if [ "$MODE" = "dev" ]; then
    echo "Vite:     http://localhost:5173  (HMR — non aprirlo direttamente, lo usa il browser da solo)"
fi
echo
echo "Utenti demo (password per tutti: password):"
echo "  admin@timone.test      — Admin, vede tutte le riviste"
echo "  redattore@timone.test  — Redattore, solo su alcune riviste"
echo
echo "Comandi utili (stessa modalità di questo avvio):"
echo "  ${DC[*]} logs -f              # log di tutti i servizi in diretta"
echo "  ${DC[*]} logs -f app          # log del solo servizio \"app\""
echo "  ${DC[*]} ps                   # stato dei container"
echo "  ${DC[*]} down                 # ferma tutto (i dati restano)"
echo "  ${DC[*]} down -v              # ferma tutto e CANCELLA i dati (reset totale)"
echo "  ${DC[*]} --profile tools run --rm backup   # dump manuale pre-deploy in ./backups"
echo "  ./setup.sh backup-check             # prova la configurazione del backup automatico"
if ! "${DC[@]}" config --services 2>/dev/null | grep -qx backup-agent; then
    echo
    echo "Backup automatico NON attivo: per attivarlo aggiungi COMPOSE_PROFILES=backup a .env"
    echo "e rilancia ./setup.sh (guida: docs/OPERATIONS.md, «Attivare il backup»)."
fi
echo
printf "%b\n" "${C_BOLD}==================================================================${C_RESET}"
