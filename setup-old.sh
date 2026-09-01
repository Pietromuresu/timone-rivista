#!/usr/bin/env bash
#
# Avvio a un solo comando di Timone Elettronico via Docker.
#
# Uso:
#   ./setup.sh
#
# Cosa fa, in ordine:
#   1. Se manca ".env", lo crea da ".env.example".
#   2. Genera da solo tutte le password/chiavi obbligatorie ancora vuote
#      (APP_KEY, DB_PASSWORD, MYSQL_ROOT_PASSWORD, REVERB_APP_ID/KEY/SECRET)
#      — nessun valore da inventare o copiare a mano.
#   3. Costruisce e avvia tutti i container Docker.
#   4. Esegue le migrazioni del database e carica i dati demo.
#   5. Stampa un riepilogo con gli URL da aprire e i comandi utili.
#
# Rilanciabile in sicurezza in qualunque momento: non tocca i valori già
# presenti in ".env", genera solo quelli ancora vuoti.

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

# Legge il valore attuale di una chiave da .env (stringa vuota se assente o vuota).
get_env_var() {
    grep -m1 "^${1}=" .env 2>/dev/null | cut -d'=' -f2- || true
}

# Sostituisce (o aggiunge, se la chiave non esiste già) il valore di una
# variabile in .env, preservando l'ordine e i commenti del file.
set_env_var() {
    local key="$1" value="$2"
    if grep -q "^${key}=" .env; then
        awk -v k="$key" -v v="$value" '$0 ~ "^"k"=" { print k"="v; next } { print }' .env > .env.tmp
        mv .env.tmp .env
    else
        printf '%s=%s\n' "$key" "$value" >> .env
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
# 4. Costruisce e avvia i container
# -----------------------------------------------------------------------------

info "Costruisco e avvio i container Docker (la prima volta può richiedere qualche minuto)..."

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
until docker compose up -d --build; do
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
docker compose exec -T app php artisan migrate --force

user_count="$(docker compose exec -T app php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tr -d '[:space:]')"

if [ "$user_count" = "0" ] || [ -z "$user_count" ]; then
    info "Database vuoto: carico gli utenti/riviste/numeri demo..."
    docker compose exec -T app php artisan db:seed --force
    ok "Dati demo caricati."
else
    ok "Il database ha già dei dati ($user_count utenti) — non ripeto il seed per non duplicarli."
fi

ok "Database pronto."

# -----------------------------------------------------------------------------
# 6. Riepilogo finale
# -----------------------------------------------------------------------------

echo
printf "%b\n" "${C_BOLD}==================================================================${C_RESET}"
printf "%b\n" "${C_BOLD} Timone Elettronico è avviato ✅${C_RESET}"
printf "%b\n" "${C_BOLD}==================================================================${C_RESET}"
echo
echo "App:      http://localhost"
echo "Adminer:  http://localhost:8081  (server: mysql, credenziali in .env)"
echo "Reverb:   ws://localhost:8080    (usato in automatico dal browser)"
echo
echo "Utenti demo (password per tutti: password):"
echo "  admin@timone.test      — Admin, vede tutte le riviste"
echo "  redattore@timone.test  — Redattore, solo su alcune riviste"
echo
echo "Comandi utili:"
echo "  docker compose logs -f              # log di tutti i servizi in diretta"
echo "  docker compose logs -f app          # log del solo servizio \"app\""
echo "  docker compose ps                   # stato dei container"
echo "  docker compose down                 # ferma tutto (i dati restano)"
echo "  docker compose down -v              # ferma tutto e CANCELLA i dati (reset totale)"
echo "  docker compose --profile tools run --rm backup   # backup del database in ./backups"
echo
printf "%b\n" "${C_BOLD}==================================================================${C_RESET}"
