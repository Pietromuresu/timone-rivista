#!/usr/bin/env bash
#
# timone-backup — backup automatico di Timone con restic (servizio compose
# «backup-agent», profilo «backup»). Guida: docs/OPERATIONS.md.
#
# Uso, dentro il container (es. docker compose run --rm backup-agent status):
#   agent          avvia l'agente: orari in /etc/timone-backup/crontab
#   db             dump del database → copia locale → copia remota
#   nightly        database, POI file e configurazione, controllo, copia remota, spazio
#   weekly         pulizia dello spazio (prune) e rilettura a campione dei dati
#   backup-check   prova la configurazione e spiega in italiano cosa non va
#   settings       riscrive le impostazioni lette dall'app (avvisi, soglie)
#   status         stampa l'esito degli ultimi job
#   verify-dump F  controlla che un dump SQL sia completo
#   locale ARGS    restic sulla copia locale  (es. «locale snapshots»)
#   remota ARGS    restic sulla copia remota  (es. «remota snapshots»)
#   remota2 ARGS   restic sulla seconda copia remota (facoltativa)
#
# Ogni job scrive /status/<job>.json (ultimo giro) e /status/<job>.ok.json
# (ultimo giro riuscito): l'app li legge per gli avvisi e la pagina admin.
set -Eeuo pipefail

PROJECT_DIR=/project
CONFIG_FILE="$PROJECT_DIR/backup.env"
STATUS_DIR=/status
LOG_FILE="$STATUS_DIR/backup.log"
LOCAL_REPO=/repo
DUMP_DIR=/dump
STORAGE_DIR=/var/www/html/storage/app
CACHE_DIR=/cache
# Nome fisso dell'host negli snapshot: il nome del container cambia a ogni
# ricreazione, e restic userebbe quello per trovare lo snapshot precedente
# (rileggendo tutti i file invece dei soli cambiati) e per la conservazione.
SNAPSHOT_HOST=timone
# Placeholder di backup.env.example: copia remota non ancora scelta.
REMOTE_PLACEHOLDER=DA_CONFIGURARE

JOB="${JOB:-agente}"

[ "${TIMONE_BACKUP_SOLO_FUNZIONI:-}" = 1 ] || mkdir -p "$STATUS_DIR" "$DUMP_DIR" "$CACHE_DIR"

# --- registro ------------------------------------------------------------------

log() {
    local line
    line="$(date '+%Y-%m-%d %H:%M:%S') [$JOB] $(redact "$*")"
    echo "$line"
    echo "$line" >> "$LOG_FILE"
}

rotate_log() {
    # 5 MB bastano per mesi di righe; una sola copia precedente.
    if [ -f "$LOG_FILE" ] && [ "$(stat -c %s "$LOG_FILE")" -gt 5242880 ]; then
        mv -f "$LOG_FILE" "$LOG_FILE.1"
    fi
}

human() {
    numfmt --to=iec --suffix=B --format='%.1f' "${1:-0}" 2>/dev/null || echo "${1:-0} B"
}

# --- configurazione ----------------------------------------------------------------

# Rilegge backup.env a ogni esecuzione invece di riceverlo con env_file:
# env_file vale solo quando il container viene creato, quindi una
# destinazione remota compilata dopo non verrebbe vista finché qualcuno non
# ricrea il container. Così basta salvare il file.
CONFIG_ERROR=""
load_config() {
    CONFIG_ERROR=""
    if [ ! -f "$CONFIG_FILE" ]; then
        CONFIG_ERROR="Manca backup.env nella cartella del progetto: esegui ./setup.sh, che lo crea da backup.env.example."
        return 1
    fi
    local line key value
    while IFS= read -r line || [ -n "$line" ]; do
        line="${line%$'\r'}"
        case "$line" in ''|'#'*) continue ;; esac
        key="${line%%=*}"
        value="${line#*=}"
        [[ "$key" =~ ^[A-Z_][A-Z0-9_]*$ ]] || continue
        if [[ "$value" =~ ^\"(.*)\"$ ]] || [[ "$value" =~ ^\'(.*)\'$ ]]; then
            value="${BASH_REMATCH[1]}"
        fi
        # Una chiave vuota (es. AWS_ACCESS_KEY_ID= per chi non usa S3) va tolta,
        # non esportata vuota: restic tratterebbe la stringa vuota come valore.
        if [ -z "$value" ]; then
            unset "$key"
        else
            export "$key=$value"
        fi
    done < "$CONFIG_FILE"

    : "${BACKUP_KEEP_WITHIN:=48h}" "${BACKUP_KEEP_DAILY:=14}" "${BACKUP_KEEP_WEEKLY:=8}" "${BACKUP_KEEP_MONTHLY:=12}"
    : "${BACKUP_CHECK_READ_SUBSET:=10%}" "${BACKUP_MAX_AGE_HOURS:=26}" "${BACKUP_LOCAL_MIN_FREE_GB:=10}"
    : "${BACKUP_REMOTE_WARN_PERCENT:=80}" "${BACKUP_TIMEZONE:=Europe/Rome}" "${BACKUP_DB_USER:=timone_backup}"
    : "${RESTIC_PASSWORD_FILE:=backup-secrets/restic-password}"
    case "$RESTIC_PASSWORD_FILE" in
        /*) ;;
        *) RESTIC_PASSWORD_FILE="$PROJECT_DIR/$RESTIC_PASSWORD_FILE" ;;
    esac
    export RESTIC_PASSWORD_FILE TZ="$BACKUP_TIMEZONE" RESTIC_CACHE_DIR="$CACHE_DIR"

    # La password non deve mai finire in un file che viene salvato (e quindi
    # copiato sulla destinazione): né scritta in backup.env, né in un file
    # dentro le cartelle salvate.
    if grep -qE '^RESTIC_PASSWORD(_COMMAND)?=' "$CONFIG_FILE"; then
        CONFIG_ERROR="backup.env contiene RESTIC_PASSWORD: backup.env viene salvato nel backup, quindi la password finirebbe sulla destinazione. Toglila e usa RESTIC_PASSWORD_FILE (file in backup-secrets/)."
        return 1
    fi
    case "$RESTIC_PASSWORD_FILE" in
        "$PROJECT_DIR/docker/"*|"$PROJECT_DIR/.env"|"$PROJECT_DIR/backup.env"|"$PROJECT_DIR"/docker-compose*|"$STORAGE_DIR"/*)
            CONFIG_ERROR="RESTIC_PASSWORD_FILE sta in una cartella che viene salvata nel backup: la password finirebbe sulla destinazione. Tienila in backup-secrets/."
            return 1 ;;
    esac
    if [ ! -s "$RESTIC_PASSWORD_FILE" ]; then
        CONFIG_ERROR="Manca la password del repository (${RESTIC_PASSWORD_FILE#"$PROJECT_DIR"/}). Se i backup esistono già, recuperala dal password manager; se è la prima installazione, esegui ./setup.sh."
        return 1
    fi
    if [ -z "${BACKUP_DB_PASSWORD:-}" ]; then
        CONFIG_ERROR="BACKUP_DB_PASSWORD è vuota in backup.env: esegui ./setup.sh, che la genera e crea l'utente MySQL di backup."
        return 1
    fi
    return 0
}

# --- copie remote --------------------------------------------------------------------

# Due destinazioni remote: la 1 (obbligatoria per la regola 3-2-1) e la 2
# (facoltativa, per una seconda copia immutabile fuori dall'intranet). Ognuna
# ha le sue variabili con prefisso BACKUP_REMOTE_ / BACKUP_REMOTE_2_ e le sue
# credenziali; select_remote le carica in R_* prima di ogni operazione.
select_remote() {
    local n="$1" p
    if [ "$n" = 1 ]; then p=BACKUP_REMOTE_; R_JOB=remote; R_LABEL="copia remota"
    else p=BACKUP_REMOTE_2_; R_JOB=remote_2; R_LABEL="seconda copia remota"; fi
    R_N="$n"
    R_PREFIX="$p"
    local v
    v="${p}REPOSITORY"; R_REPO="${!v:-}"
    v="${p}REST_USERNAME"; R_USER="${!v:-}"
    v="${p}REST_PASSWORD"; R_PASS="${!v:-}"
    v="${p}S3_ACCESS_KEY_ID"; R_KEY="${!v:-}"
    v="${p}S3_SECRET_ACCESS_KEY"; R_SECRET="${!v:-}"
    v="${p}S3_REGION"; R_REGION="${!v:-}"
    v="${p}CACERT"; R_CACERT="${!v:-}"
    v="${p}QUOTA_GB"; R_QUOTA="${!v:-}"
    v="${p}UPLOAD_LIMIT_KIB"; R_LIMIT="${!v:-}"
    case "$R_CACERT" in
        ''|/*) ;;
        *) R_CACERT="$PROJECT_DIR/$R_CACERT" ;;
    esac
}

remote_configured() {
    [ -n "$R_REPO" ] && [[ "$R_REPO" != *"$REMOTE_PLACEHOLDER"* ]]
}

remote_type() {
    case "$R_REPO" in
        rest:*) echo rest-server ;;
        s3:*) echo s3 ;;
        /*|local:*) echo cartella ;;
        *) echo "${R_REPO%%:*}" ;;
    esac
}

# Solo due tipi di copia remota sono ammessi, perché solo questi possono
# essere resi non cancellabili DAL server: chi compromette il server (o un
# ransomware) ha le stesse credenziali del backup, quindi la protezione deve
# stare dal lato della destinazione. Stampa il motivo del rifiuto, niente
# se la configurazione è accettabile.
remote_policy_error() {
    local name="${R_PREFIX}REPOSITORY"
    case "$R_REPO" in
        /*|local:*)
            echo "Una cartella (anche un NAS SMB/NFS montato sul server) non è ammessa come copia remota: chi compromette il server la vede e la cancella. Usala solo come copia locale (docs/OPERATIONS.md «Copia locale su un altro disco»); per la copia remota usa un rest-server con --append-only o S3 con Object Lock." ;;
        rest:http://*)
            echo "rest-server senza TLS: usa un indirizzo rest:https://… in $name (le credenziali e i dati viaggerebbero in chiaro)." ;;
        rest:*@*|s3:*@*)
            echo "Credenziali scritte dentro $name: toglile dall'indirizzo e mettile in ${R_PREFIX}REST_USERNAME / ${R_PREFIX}REST_PASSWORD (finirebbero nei messaggi e nei log)." ;;
        rest:https://*)
            if [ -z "$R_USER" ] || [ -z "$R_PASS" ]; then
                echo "rest-server senza autenticazione: compila ${R_PREFIX}REST_USERNAME e ${R_PREFIX}REST_PASSWORD (sul rest-server: htpasswd, niente --no-auth)."
            fi ;;
        s3:http://*)
            echo "S3 senza TLS: usa un indirizzo s3:https://… in $name." ;;
        s3:*)
            if [ -z "$R_KEY" ] || [ -z "$R_SECRET" ]; then
                echo "S3 senza credenziali: compila ${R_PREFIX}S3_ACCESS_KEY_ID e ${R_PREFIX}S3_SECRET_ACCESS_KEY (chiave SENZA permesso di cancellare gli snapshot)."
            fi ;;
        *)
            echo "Tipo di destinazione non supportato ($(remote_type)): sono ammessi solo rest-server (rest:https://…, con --append-only) e S3 con Object Lock (s3:https://…)." ;;
    esac
}

# --- restic ------------------------------------------------------------------------

# Priorità bassa di CPU e disco: il backup non deve rallentare chi lavora.
# ionice non è permesso in ogni ambiente Docker: si usa solo se funziona.
PRIO=(nice -n 10)
if ionice -c 3 true >/dev/null 2>&1; then
    PRIO+=(ionice -c 3)
fi

restic_local() {
    "${PRIO[@]}" restic --repo "$LOCAL_REPO" "$@"
}

# Le credenziali della destinazione arrivano a restic come variabili
# d'ambiente del solo processo restic (mai sulla riga di comando, che si
# vede con ps e nei log di supercronic), e solo quelle della destinazione in
# uso: le chiavi della 2 non sono mai visibili alle operazioni sulla 1.
remote_command() {
    REMOTE_CMD=(env -u AWS_ACCESS_KEY_ID -u AWS_SECRET_ACCESS_KEY -u RESTIC_REST_USERNAME -u RESTIC_REST_PASSWORD)
    [ -n "$R_USER" ] && REMOTE_CMD+=("RESTIC_REST_USERNAME=$R_USER")
    [ -n "$R_PASS" ] && REMOTE_CMD+=("RESTIC_REST_PASSWORD=$R_PASS")
    [ -n "$R_KEY" ] && REMOTE_CMD+=("AWS_ACCESS_KEY_ID=$R_KEY")
    [ -n "$R_SECRET" ] && REMOTE_CMD+=("AWS_SECRET_ACCESS_KEY=$R_SECRET")
    [ -n "$R_REGION" ] && REMOTE_CMD+=("AWS_DEFAULT_REGION=$R_REGION")
    REMOTE_CMD+=("${PRIO[@]}" restic --repo "$R_REPO")
    [ -n "$R_CACERT" ] && REMOTE_CMD+=(--cacert "$R_CACERT")
    [ -n "$R_LIMIT" ] && REMOTE_CMD+=(--limit-upload "$R_LIMIT")
    return 0
}

restic_remote() {
    remote_command
    "${REMOTE_CMD[@]}" "$@"
}

# Operazioni brevi verso la remota (sonda, creazione, scrittura di prova)
# con un tempo massimo: con la destinazione spenta restic ritenta da solo
# per molti minuti, e backup-check sembrerebbe bloccato. La copia vera non
# ha limite (la prima può durare ore). Il messaggio «timeout» finisce
# nell'errore, così explain_remote_error lo riconosce come problema di rete.
REMOTE_PROBE_SECONDS=60
restic_remote_quick() {
    local rc=0
    remote_command
    timeout "$REMOTE_PROBE_SECONDS" "${REMOTE_CMD[@]}" "$@" || rc=$?
    if [ "$rc" -eq 124 ]; then
        echo "timeout: nessuna risposta dalla destinazione remota entro $REMOTE_PROBE_SECONDS secondi" >&2
    fi
    return "$rc"
}

# Traduce in italiano l'errore di restic verso la copia remota.
explain_remote_error() {
    local err="$1" name="${R_PREFIX}REPOSITORY"
    if grep -qiE 'wrong password|no key found|ciphertext verification failed' "$err"; then
        echo "La password non apre il repository remoto: è stato creato con un'altra chiave. Usa un percorso/bucket nuovo o la password giusta."
    # Prima dei problemi di rete: restic ritenta anche gli errori di
    # certificato fino al timeout, e il messaggio contiene entrambi.
    elif grep -qiE 'x509|certificate' "$err"; then
        echo "Certificato HTTPS del server remoto non valido o non riconosciuto (per un certificato interno indica la CA in ${R_PREFIX}CACERT)."
    elif grep -qiE 'no such host|name resolution|server misbehaving|lookup .* on ' "$err"; then
        echo "Rete: il nome del server remoto non si trova (DNS). Controlla l'indirizzo in $name."
    elif grep -qiE 'connection refused|i/o timeout|timed out|timeout|network is unreachable|no route to host|connection reset' "$err"; then
        echo "Rete: il server remoto non risponde. Controlla indirizzo e porta, che il servizio sia acceso e che il suo firewall accetti l'IP di questo server."
    elif grep -qiE '401|unauthorized|InvalidAccessKeyId|SignatureDoesNotMatch|authentication|auth fail' "$err"; then
        echo "Credenziali rifiutate dalla destinazione remota: controlla utente/password o chiavi (${R_PREFIX}…) in backup.env."
    elif grep -qiE '403|AccessDenied|access denied|forbidden|permission denied' "$err"; then
        echo "Permessi insufficienti sulla destinazione remota (l'utente non può leggere o scrivere lì; per S3 il bucket va creato prima, dal fornitore, con Object Lock attivo)."
    elif grep -qiE 'NoSuchBucket|bucket does not exist' "$err"; then
        echo "Il bucket S3 indicato non esiste."
    else
        echo "Errore non riconosciuto dalla destinazione remota: $(tail -n 3 "$err" | tr '\n' ' ')"
    fi
}

# Nasconde indirizzi, utenti e credenziali delle destinazioni e del database
# in ogni testo che finisce nel registro, nei file di stato (letti dall'app:
# mail e pagina admin) o a video. Gli errori di restic citano spesso
# l'indirizzo completo del repository. Gira in una sottoshell (parentesi)
# perché select_remote cambia le variabili R_* della destinazione in uso.
redact() (
    local text="$*" secret n repo host
    for n in 1 2; do
        select_remote "$n"
        # Lo username non si sostituisce come testo libero: non è un segreto
        # e può essere una parola comune («timone»); dove conta (dentro un
        # indirizzo) lo toglie la sostituzione degli URL qui sotto.
        for secret in "$R_REPO" "$R_PASS" "$R_KEY" "$R_SECRET"; do
            [ "${#secret}" -ge 8 ] || continue
            [ "$secret" = "$REMOTE_PLACEHOLDER" ] && continue
            text="${text//"$secret"/[nascosto]}"
        done
        repo="${R_REPO#*://}"
        host="${repo%%[/:]*}"
        if [ "${#host}" -ge 4 ] && [ "$host" != "$REMOTE_PLACEHOLDER" ]; then
            text="${text//"$host"/[destinazione]}"
        fi
    done
    secret="${BACKUP_DB_PASSWORD:-}"
    [ "${#secret}" -ge 8 ] && text="${text//"$secret"/[nascosto]}"
    # Qualunque URL rimasto (es. host risolto in IP nei messaggi di rete).
    sed -E 's#(https?|rest|s3|sftp)://[^ "]*#[indirizzo]#g' <<<"$text"
)

# --- stato --------------------------------------------------------------------------

write_json() {
    local target="$1"
    cat > "$target.tmp"
    mv -f "$target.tmp" "$target"
}

write_status() {
    local job="$1" status="$2" message="$3" details="${4:-}" duration="${5:-0}"
    [ -n "$details" ] || details='{}'
    jq -n --arg job "$job" --arg status "$status" --arg message "$message" \
        --arg started "${JOB_STARTED_AT:-}" --arg finished "$(date -Iseconds)" \
        --argjson duration "$duration" --argjson details "$details" \
        '{job: $job, status: $status, message: $message, started_at: $started,
          finished_at: (if $status == "running" then null else $finished end),
          duration_seconds: $duration, details: $details}' \
        | write_json "$STATUS_DIR/$job.json"
    if [ "$status" = ok ]; then
        cp -f "$STATUS_DIR/$job.json" "$STATUS_DIR/$job.ok.json.tmp"
        mv -f "$STATUS_DIR/$job.ok.json.tmp" "$STATUS_DIR/$job.ok.json"
    fi
}

# Impostazioni senza segreti lette dall'app (avvisi e pagina admin): l'app
# non vede backup.env, che contiene credenziali. Scritte anche ogni 15
# minuti, così «written_at» dice all'app che l'agente è vivo.
write_settings() {
    local c1=false c2=false t1="" t2=""
    select_remote 2
    remote_configured && { c2=true; t2="$(remote_type)"; }
    select_remote 1
    remote_configured && { c1=true; t1="$(remote_type)"; }
    # Solo stato e tipo delle destinazioni: mai indirizzi, utenti o percorsi
    # (li mostrerebbe la pagina admin).
    jq -n --arg written "$(date -Iseconds)" --arg emails "${BACKUP_ALERT_EMAIL:-}" \
        --arg max_age "${BACKUP_MAX_AGE_HOURS:-26}" --arg min_free "${BACKUP_LOCAL_MIN_FREE_GB:-10}" \
        --arg quota "${BACKUP_REMOTE_QUOTA_GB:-}" --arg quota2 "${BACKUP_REMOTE_2_QUOTA_GB:-}" \
        --arg warn "${BACKUP_REMOTE_WARN_PERCENT:-80}" \
        --argjson c1 "$c1" --arg t1 "$t1" --argjson c2 "$c2" --arg t2 "$t2" \
        --arg tz "${BACKUP_TIMEZONE:-Europe/Rome}" --arg error "$(redact "$CONFIG_ERROR")" \
        '{written_at: $written, alert_emails: $emails,
          max_age_hours: ($max_age | tonumber), local_min_free_gb: ($min_free | tonumber),
          remote_quota_gb: (if $quota == "" then null else ($quota | tonumber) end),
          remote_2_quota_gb: (if $quota2 == "" then null else ($quota2 | tonumber) end),
          remote_warn_percent: ($warn | tonumber),
          remote_configured: $c1, remote_type: (if $c1 then $t1 else null end),
          remote_2_configured: $c2, remote_2_type: (if $c2 then $t2 else null end),
          timezone: $tz, config_error: (if $error == "" then null else $error end)}' \
        | write_json "$STATUS_DIR/settings.json"
}

# --- esecuzione di un job ------------------------------------------------------------

# Il corpo di ogni job gira in un processo bash a sé (__step) per avere
# davvero «set -e»: dentro un «if» o un «||» bash lo disattiva in silenzio, e
# un comando fallito a metà passerebbe per un successo.
run_job() {
    local job="$1" started rc=0 duration msg details status
    JOB="$job"
    started="$(date +%s)"
    JOB_STARTED_AT="$(date -Iseconds)"
    export JOB JOB_STARTED_AT
    : > "$STATUS_DIR/.$job.msg"
    echo '{}' > "$STATUS_DIR/.$job.details"
    write_status "$job" running "In corso"
    log "inizio"
    "$0" __step "$job" || rc=$?
    duration=$(( $(date +%s) - started ))
    msg="$(cat "$STATUS_DIR/.$job.msg")"
    details="$(cat "$STATUS_DIR/.$job.details")"
    jq -e . >/dev/null 2>&1 <<<"$details" || details='{}'
    case "$rc" in
        0) status=ok; log "OK in ${duration}s — $msg" ;;
        2) status=not_configured; log "$msg" ;;
        *) status=failed; [ -n "$msg" ] || msg="Interrotto senza messaggio (codice $rc)."; log "ERRORE dopo ${duration}s — $msg" ;;
    esac
    write_status "$job" "$status" "$msg" "$details" "$duration"
    rm -f "$STATUS_DIR/.$job.msg" "$STATUS_DIR/.$job.details"
    [ "$rc" -eq 0 ] || [ "$rc" -eq 2 ]
}

# Funzioni usate solo dentro __step.
step_message() { redact "$*" | tr -d '\n' > "$STATUS_DIR/.$JOB.msg"; }
step_details() { printf '%s' "$1" > "$STATUS_DIR/.$JOB.details"; }
fail() { step_message "$*"; exit 1; }
on_step_error() {
    # Solo se il job non ha già scritto un messaggio più chiaro.
    if [ ! -s "$STATUS_DIR/.$JOB.msg" ]; then
        step_message "Comando non riuscito alla riga $1: $2"
    fi
}

require_config() {
    load_config || fail "$CONFIG_ERROR"
}

ensure_local_repo() {
    local rc=0 err
    err="$(mktemp)"
    restic_local cat config >/dev/null 2>"$err" || rc=$?
    case "$rc" in
        0) ;;
        10) restic_local init >/dev/null 2>"$err" || fail "Impossibile creare la copia locale in backups/restic: $(tail -n 2 "$err" | tr '\n' ' ')"
            log "creata la copia locale (repository restic in backups/restic)" ;;
        12) fail "La password non apre la copia locale (backups/restic): il file della password non è quello con cui i backup sono stati creati." ;;
        *) fail "Copia locale illeggibile: $(tail -n 2 "$err" | tr '\n' ' ')" ;;
    esac
    rm -f "$err"
}

# Hash del commit in produzione. Le immagini dell'app non contengono .git
# (.dockerignore la esclude), quindi si legge dalla cartella del progetto
# montata in /project; se non c'è, dall'argomento GIT_COMMIT del build.
write_commit_info() {
    local head="" source="sconosciuto" dirty=null remotes="" git=(git -c safe.directory='*' -C "$PROJECT_DIR")
    if [ -e "$PROJECT_DIR/.git" ] && head="$("${git[@]}" rev-parse HEAD 2>/dev/null)"; then
        source=git
        if [ -n "$(GIT_OPTIONAL_LOCKS=0 "${git[@]}" status --porcelain --untracked-files=no 2>/dev/null)" ]; then
            dirty=true
        else
            dirty=false
        fi
        remotes="$("${git[@]}" branch -r --contains "$head" 2>/dev/null | sed 's/^ *//' | grep -v -- '->' | paste -sd, - || true)"
    elif [ -n "${BUILD_GIT_COMMIT:-}" ]; then
        head="$BUILD_GIT_COMMIT"
        source=build
    fi
    mkdir -p "$DUMP_DIR/info"
    jq -n --arg commit "$head" --arg source "$source" --argjson dirty "$dirty" \
        --arg remotes "$remotes" --arg build "${BUILD_GIT_COMMIT:-}" --arg at "$(date -Iseconds)" \
        '{commit: (if $commit == "" then null else $commit end), source: $source,
          local_changes: $dirty, remote_branches: ($remotes | split(",") | map(select(. != ""))),
          on_remote: (if $source == "git" then ($remotes != "") else null end),
          build_commit: (if $build == "" then null else $build end), recorded_at: $at}' \
        > "$DUMP_DIR/info/commit.json"
    if [ "$source" = git ] && [ -z "$remotes" ]; then
        log "attenzione: il commit ${head:0:12} non risulta su nessun ramo remoto (secondo l'ultimo git fetch del server)"
    fi
    if [ -z "$head" ]; then
        log "attenzione: hash del commit non disponibile (.git non visibile e immagine costruita senza GIT_COMMIT)"
    fi
}

# Un dump buono è non vuoto, contiene tabelle e finisce con la riga che
# mysqldump scrive solo se arriva in fondo: un dump interrotto a metà
# (rete, MySQL riavviato, disco pieno) non la ha.
verify_dump() {
    local file="$1"
    if [ ! -s "$file" ]; then
        echo "Il dump del database è vuoto."
        return 1
    fi
    if ! tail -n 1 "$file" | grep -q '^-- Dump completed'; then
        echo "Il dump del database è troncato: manca la riga finale «-- Dump completed»."
        return 1
    fi
    if ! grep -q '^CREATE TABLE' "$file"; then
        echo "Il dump del database non contiene nessuna tabella."
        return 1
    fi
    return 0
}

# Ultima riga «summary» dell'output --json di restic backup.
restic_summary() {
    jq -c 'select(.message_type == "summary")' "$1" 2>/dev/null | tail -n 1
}

# --- corpi dei job ------------------------------------------------------------------

step_db() {
    require_config
    ensure_local_repo
    write_commit_info
    local dump="$DUMP_DIR/db/timone.sql" err out summary problem tables
    err="$(mktemp)"
    out="$(mktemp)"
    rm -rf "$DUMP_DIR/db"
    mkdir -p "$DUMP_DIR/db"
    # --single-transaction: fotografia coerente senza bloccare chi lavora
    # (tabelle InnoDB). --no-tablespaces: l'utente di sola lettura non ha il
    # privilegio PROCESS, che servirebbe solo per i tablespace.
    MYSQL_PWD="$BACKUP_DB_PASSWORD" "${PRIO[@]}" mysqldump \
        --host="${DB_HOST:-mysql}" --user="$BACKUP_DB_USER" \
        --single-transaction --quick --routines --triggers --events \
        --no-tablespaces --set-gtid-purged=OFF --hex-blob --default-character-set=utf8mb4 \
        "${DB_DATABASE:-timone}" > "$dump" 2>"$err" \
        || fail "mysqldump non riuscito: $(grep -v 'Using a password' "$err" | tail -n 3 | tr '\n' ' ')"
    problem="$(verify_dump "$dump")" || fail "$problem"
    tables="$(grep -c '^CREATE TABLE' "$dump")"

    # Il dump entra in restic NON compresso: così restic lo deduplica tra un
    # giro e l'altro (cambiano poche righe) e lo comprime lui.
    restic_local backup --host "$SNAPSHOT_HOST" --tag db --json --quiet \
        "$DUMP_DIR/db" "$DUMP_DIR/info" > "$out" 2>"$err" \
        || fail "Salvataggio del dump nella copia locale non riuscito: $(tail -n 3 "$err" | tr '\n' ' ')"
    summary="$(restic_summary "$out")"
    [ -n "$summary" ] || fail "restic non ha restituito il riepilogo del backup del database."

    restic_local forget --host "$SNAPSHOT_HOST" --tag db --group-by host,tags \
        --keep-within "$BACKUP_KEEP_WITHIN" --keep-daily "$BACKUP_KEEP_DAILY" \
        --keep-weekly "$BACKUP_KEEP_WEEKLY" --keep-monthly "$BACKUP_KEEP_MONTHLY" >/dev/null 2>"$err" \
        || fail "Pulizia dei backup del database vecchi non riuscita: $(tail -n 2 "$err" | tr '\n' ' ')"

    step_details "$(jq -c --argjson dump "$(stat -c %s "$dump")" --argjson tables "$tables" \
        --slurpfile commit "$DUMP_DIR/info/commit.json" \
        '{snapshot_id: .snapshot_id, dump_bytes: $dump, tables: $tables,
          added_bytes: .data_added, commit: $commit[0]}' <<<"$summary")"
    step_message "dump di $(human "$(stat -c %s "$dump")") ($tables tabelle), aggiunti $(human "$(jq -r .data_added <<<"$summary")"), snapshot $(jq -r '.snapshot_id[0:8]' <<<"$summary")"
    rm -f "$err" "$out"
}

step_files() {
    require_config
    ensure_local_repo
    write_commit_info
    local paths=("$STORAGE_DIR") candidate err out summary rc=0
    # Configurazione: .env e backup.env entrano nel repository, che è cifrato.
    # La password del repository NO: senza di lei il backup non si apre
    # comunque, quindi va custodita fuori (password manager).
    for candidate in .env backup.env docker-compose.yml docker-compose.dev.yml \
        docker-compose.override.yml Dockerfile docker; do
        [ -e "$PROJECT_DIR/$candidate" ] && paths+=("$PROJECT_DIR/$candidate")
    done
    for candidate in ${BACKUP_EXTRA_PATHS:-}; do
        [ -e "$candidate" ] || fail "Percorso indicato in BACKUP_EXTRA_PATHS non trovato nel container: $candidate (va montato con docker-compose.override.yml, vedi docs/OPERATIONS.md)."
        paths+=("$candidate")
    done
    err="$(mktemp)"
    out="$(mktemp)"
    # Esclusi: livewire-tmp (upload in transito, Livewire li cancella dopo
    # 24 ore) ed exports (PDF sfogliabili, si rigenerano dal pannello del
    # numero). Le miniature restano: con la deduplicazione costano poco e
    # rigenerarle tutte dopo un ripristino terrebbe il worker occupato a lungo.
    restic_local backup --host "$SNAPSHOT_HOST" --tag files --json --quiet \
        --exclude "$STORAGE_DIR/private/livewire-tmp" \
        --exclude "$STORAGE_DIR/private/exports" \
        --exclude "$PROJECT_DIR/backup-secrets" --exclude "$RESTIC_PASSWORD_FILE" \
        "${paths[@]}" "$DUMP_DIR/info" > "$out" 2>"$err" || rc=$?
    if [ "$rc" -eq 3 ]; then
        fail "Backup dei file incompleto: alcuni file non si sono potuti leggere ($(grep -i error "$err" | tail -n 2 | tr '\n' ' '))."
    elif [ "$rc" -ne 0 ]; then
        fail "Backup dei file non riuscito: $(tail -n 3 "$err" | tr '\n' ' ')"
    fi
    summary="$(restic_summary "$out")"
    [ -n "$summary" ] || fail "restic non ha restituito il riepilogo del backup dei file."

    restic_local forget --host "$SNAPSHOT_HOST" --tag files --group-by host,tags \
        --keep-within "$BACKUP_KEEP_WITHIN" --keep-daily "$BACKUP_KEEP_DAILY" \
        --keep-weekly "$BACKUP_KEEP_WEEKLY" --keep-monthly "$BACKUP_KEEP_MONTHLY" >/dev/null 2>"$err" \
        || fail "Pulizia dei backup dei file vecchi non riuscita: $(tail -n 2 "$err" | tr '\n' ' ')"

    step_details "$(jq -c --slurpfile commit "$DUMP_DIR/info/commit.json" \
        '{snapshot_id: .snapshot_id, files: .total_files_processed, total_bytes: .total_bytes_processed,
          added_bytes: .data_added, commit: $commit[0]}' <<<"$summary")"
    step_message "$(jq -r .total_files_processed <<<"$summary") file, $(human "$(jq -r .total_bytes_processed <<<"$summary")") in totale, aggiunti $(human "$(jq -r .data_added <<<"$summary")"), snapshot $(jq -r '.snapshot_id[0:8]' <<<"$summary")"
    rm -f "$err" "$out"
}

step_check() {
    require_config
    local err
    err="$(mktemp)"
    restic_local check >/dev/null 2>"$err" \
        || fail "Controllo di integrità della copia locale non riuscito: $(tail -n 3 "$err" | tr '\n' ' ')"
    step_message "struttura della copia locale integra"
    rm -f "$err"
}

step_verify_data() {
    require_config
    local err
    err="$(mktemp)"
    restic_local check --read-data-subset="$BACKUP_CHECK_READ_SUBSET" >/dev/null 2>"$err" \
        || fail "Rilettura dei dati della copia locale non riuscita: $(tail -n 3 "$err" | tr '\n' ' ')"
    step_message "riletto e verificato il $BACKUP_CHECK_READ_SUBSET dei dati della copia locale"
    rm -f "$err"
}

step_prune() {
    require_config
    local err
    err="$(mktemp)"
    restic_local prune >/dev/null 2>"$err" \
        || fail "Pulizia dello spazio (prune) non riuscita: $(tail -n 3 "$err" | tr '\n' ' ')"
    step_message "spazio dei backup scaduti liberato"
    rm -f "$err"
}

# Codice d'uscita 2 = copia remota non configurata: non è un errore (i
# backup locali proseguono), l'app manda un solo avviso al giorno.
step_remote_n() {
    require_config
    select_remote "$1"
    if ! remote_configured; then
        if [ "$1" = 1 ]; then
            step_message "Copia remota non configurata: i backup esistono solo su questo server."
        else
            step_message "Seconda copia remota non configurata (facoltativa)."
        fi
        exit 2
    fi
    local problem err rc=0 stats used quota_bytes=null percent=null
    problem="$(remote_policy_error)"
    [ -z "$problem" ] || fail "$problem"
    err="$(mktemp)"
    restic_remote_quick cat config >/dev/null 2>"$err" || rc=$?
    case "$rc" in
        0) ;;
        10)
            # Prima copia: il repository remoto nasce con gli stessi
            # parametri di suddivisione dei file della copia locale,
            # altrimenti «restic copy» non potrebbe deduplicare e ogni giro
            # ricopierebbe tutto. La password NON viene scritta sulla
            # destinazione: restic ci salva solo la chiave cifrata con lei.
            log "prima $R_LABEL: creo il repository ($(remote_type))"
            restic_remote_quick init --from-repo "$LOCAL_REPO" --from-password-file "$RESTIC_PASSWORD_FILE" \
                --copy-chunker-params >/dev/null 2>"$err" \
                || fail "Impossibile creare il repository remoto. $(explain_remote_error "$err")" ;;
        *) fail "$(explain_remote_error "$err")" ;;
    esac
    restic_remote copy --from-repo "$LOCAL_REPO" --from-password-file "$RESTIC_PASSWORD_FILE" \
        --host "$SNAPSHOT_HOST" >/dev/null 2>"$err" \
        || fail "Copia verso la destinazione remota non riuscita. $(explain_remote_error "$err")"
    stats="$(restic_remote stats --mode raw-data --json 2>"$err")" \
        || fail "Copia fatta, ma lettura dello spazio remoto non riuscita. $(explain_remote_error "$err")"
    used="$(jq -r '.total_size // 0' <<<"$stats")"
    if [ -n "$R_QUOTA" ]; then
        quota_bytes=$(( R_QUOTA * 1024 * 1024 * 1024 ))
        percent=$(( used * 100 / quota_bytes ))
    fi
    step_details "$(jq -n -c --arg type "$(remote_type)" --argjson used "$used" \
        --argjson quota "$quota_bytes" --argjson percent "$percent" \
        '{type: $type, used_bytes: $used, quota_bytes: $quota, used_percent: $percent}')"
    step_message "$R_LABEL ($(remote_type)) aggiornata, $(human "$used") usati$([ "$percent" = null ] || echo " (${percent}% della quota)")"
    rm -f "$err"
}

step_remote() { step_remote_n 1; }
step_remote_2() { step_remote_n 2; }

step_space() {
    require_config
    local free size err
    err="$(mktemp)"
    free="$(df -B1 --output=avail "$LOCAL_REPO" | tail -n 1 | tr -d ' ')"
    size="$(restic_local stats --mode raw-data --json 2>"$err" | jq -r '.total_size // 0')" \
        || fail "Lettura dello spazio della copia locale non riuscita: $(tail -n 2 "$err" | tr '\n' ' ')"
    step_details "$(jq -n -c --argjson free "$free" --argjson size "$size" \
        '{local_free_bytes: $free, local_repository_bytes: $size}')"
    step_message "copia locale $(human "$size"), liberi sul disco $(human "$free")"
    rm -f "$err"
}

# --- comandi ------------------------------------------------------------------------

with_lock() {
    # Un job alla volta, anche tra container diversi (docker compose run
    # usa un container nuovo): il lucchetto sta nel volume condiviso.
    exec 9>"$STATUS_DIR/.lock"
    if ! flock -w 7200 9; then
        log "ERRORE — un altro backup è in corso da più di 2 ore: salto questo giro"
        return 1
    fi
    # Prima la configurazione: imposta anche il fuso orario delle righe di log.
    local configured=0
    load_config 2>/dev/null || configured=$?
    mark_interrupted_jobs
    # Lucchetti di restic lasciati da un giro ucciso a metà: «unlock» toglie
    # solo quelli scaduti (stesso host con processo morto, o più vecchi di
    # 30 minuti). Riconosce lo stesso host perché il container ha un
    # hostname fisso (docker-compose.yml); senza, un «check» subito dopo
    # un'interruzione fallirebbe per mezz'ora.
    if [ "$configured" -eq 0 ]; then
        restic_local unlock >/dev/null 2>&1 || true
    fi
}

# Con il lucchetto in mano nessun altro job può essere in corso: un job
# ancora «running» è un giro precedente interrotto (container fermato,
# server riavviato). Diventa «failed», così l'avviso parte invece di
# restare in corso per sempre.
mark_interrupted_jobs() {
    local file job
    for file in "$STATUS_DIR"/*.json; do
        [ -f "$file" ] || continue
        case "$file" in *.ok.json|*/settings.json) continue ;; esac
        if [ "$(jq -r '.status // empty' "$file" 2>/dev/null)" = running ]; then
            job="$(basename "$file" .json)"
            jq --arg now "$(date -Iseconds)" \
                '.status = "failed" | .finished_at = $now
                 | .message = "Interrotto a metà: il giro iniziato il \(.started_at) non è arrivato in fondo (container fermato o server riavviato)."' \
                "$file" | write_json "$file"
            JOB="$job" log "ERRORE — il giro precedente si era interrotto a metà; segnato come fallito"
        fi
    done
}

cmd_db() {
    rotate_log
    with_lock || exit 1
    load_config || true
    write_settings
    local rc=0
    run_job db || rc=1
    run_job remote || rc=1
    run_job remote_2 || rc=1
    exit "$rc"
}

cmd_nightly() {
    rotate_log
    with_lock || exit 1
    load_config || true
    write_settings
    local rc=0
    # Prima il database, poi i file: un file caricato tra i due finisce nel
    # backup dei file senza essere nel dump (innocuo, resta orfano), mentre
    # al contrario il dump citerebbe file che il backup non ha.
    run_job db || rc=1
    run_job files || rc=1
    run_job check || rc=1
    run_job remote || rc=1
    run_job remote_2 || rc=1
    run_job space || rc=1
    exit "$rc"
}

cmd_weekly() {
    rotate_log
    with_lock || exit 1
    load_config || true
    write_settings
    local rc=0
    run_job prune || rc=1
    run_job verify_data || rc=1
    run_job space || rc=1
    exit "$rc"
}

cmd_settings() {
    load_config || true
    write_settings
}

cmd_agent() {
    rotate_log
    if load_config; then
        select_remote 1
        log "agente avviato — fuso orario $TZ, copia remota: $(remote_configured && remote_type || echo 'NON configurata')"
    else
        log "ERRORE di configurazione — $CONFIG_ERROR (i job falliranno e partirà l'avviso finché non è corretto)"
    fi
    write_settings
    log "orari: database alle 8, 12, 16, 20 (lun-ven) e alle 2; file alle 2; pulizia e verifica la domenica alle 4:30"
    exec supercronic -passthrough-logs /etc/timone-backup/crontab
}

cmd_status() {
    local f
    for f in settings db files check remote remote_2 space prune verify_data; do
        [ -f "$STATUS_DIR/$f.json" ] || continue
        jq -r --arg f "$f" 'if $f == "settings" then
                "impostazioni: avvisi a \(.alert_emails), copia remota \(if .remote_configured then .remote_type else "NON configurata" end), seconda copia \(if .remote_2_configured then .remote_2_type else "non configurata" end), aggiornate \(.written_at)"
            else
                "\(.job): \(.status) (\(.finished_at // .started_at)) — \(.message)"
            end' "$STATUS_DIR/$f.json"
    done
    [ -f "$LOG_FILE" ] && { echo; echo "Ultime righe del registro ($LOG_FILE):"; tail -n 15 "$LOG_FILE"; }
    return 0
}

ok() { echo "✅ $(redact "$*")"; }
warn() { echo "⚠️  $(redact "$*")"; CHECK_WARNED=1; }
ko() { echo "❌ $(redact "$*")"; CHECK_FAILED=1; }

cmd_backup_check() {
    CHECK_FAILED=0
    CHECK_WARNED=0
    echo "Controllo della configurazione del backup (backup.env)"
    echo
    if ! load_config; then
        ko "$CONFIG_ERROR"
        write_settings
        exit 1
    fi
    ok "backup.env letto, password del repository presente."
    write_settings

    # Database: l'utente di backup si collega e non può scrivere.
    local grants
    if grants="$(MYSQL_PWD="$BACKUP_DB_PASSWORD" mysql --host="${DB_HOST:-mysql}" --user="$BACKUP_DB_USER" -N -e 'SHOW GRANTS' 2>/dev/null)"; then
        if grep -qiE 'ALL PRIVILEGES|INSERT|UPDATE|DELETE|DROP|ALTER' <<<"$grants"; then
            warn "L'utente MySQL $BACKUP_DB_USER si collega ma NON è in sola lettura: rilancia ./setup.sh o correggi i permessi."
        else
            ok "Utente MySQL $BACKUP_DB_USER: si collega, sola lettura."
        fi
    else
        ko "L'utente MySQL $BACKUP_DB_USER non si collega: esegui ./setup.sh (crea l'utente con la password di backup.env)."
    fi

    # Copia locale.
    local rc=0 err
    err="$(mktemp)"
    restic_local cat config >/dev/null 2>"$err" || rc=$?
    case "$rc" in
        0) ok "Copia locale (backups/restic) leggibile con la password." ;;
        10) if restic_local init >/dev/null 2>"$err"; then ok "Copia locale creata in backups/restic."; else ko "Impossibile creare la copia locale: $(tail -n 2 "$err" | tr '\n' ' ')"; fi ;;
        12) ko "La password non apre la copia locale: il file della password non è quello con cui i backup sono stati creati." ;;
        *) ko "Copia locale illeggibile: $(tail -n 2 "$err" | tr '\n' ' ')" ;;
    esac
    local free
    free="$(df -B1 --output=avail "$LOCAL_REPO" | tail -n 1 | tr -d ' ')"
    if [ "$free" -lt $(( BACKUP_LOCAL_MIN_FREE_GB * 1024 * 1024 * 1024 )) ]; then
        warn "Spazio libero per la copia locale: $(human "$free"), sotto la soglia di $BACKUP_LOCAL_MIN_FREE_GB GB."
    else
        ok "Spazio libero per la copia locale: $(human "$free")."
    fi

    check_remote 1
    check_remote 2
    rm -f "$err"
    echo
    select_remote 1
    if [ "$CHECK_FAILED" -ne 0 ]; then
        echo "Esito: ci sono errori da correggere (❌ sopra)."
    elif ! remote_configured; then
        echo "Esito: configurazione locale a posto, manca la copia remota."
    elif [ "$CHECK_WARNED" -eq 1 ]; then
        echo "Esito: la copia remota funziona, ma ci sono avvisi da leggere (⚠️ sopra)."
    else
        echo "Esito: tutto a posto. La prima copia remota completa parte da sola al prossimo backup (o subito con: docker compose run --rm backup-agent nightly)."
    fi
    exit "$CHECK_FAILED"
}

# Controllo di una destinazione remota: regole di sicurezza, raggiungibilità,
# creazione del repository, scrittura di prova e prova di cancellazione.
check_remote() {
    local n="$1" rc=0 err problem out snapshot
    select_remote "$n"
    echo
    if ! remote_configured; then
        if [ "$n" = 1 ]; then
            warn "Copia remota NON configurata (BACKUP_REMOTE_REPOSITORY=$REMOTE_PLACEHOLDER): i backup restano solo su questo server. Vedi docs/OPERATIONS.md «Configurare la copia remota»."
        else
            echo "   Seconda copia remota: non configurata (facoltativa)."
        fi
        return 0
    fi
    echo "   ${R_LABEL^}: tipo $(remote_type)"
    problem="$(remote_policy_error)"
    if [ -n "$problem" ]; then
        ko "$problem"
        return 0
    fi
    if [ -n "$R_CACERT" ] && [ ! -s "$R_CACERT" ]; then
        ko "Il certificato indicato in ${R_PREFIX}CACERT non esiste."
        return 0
    fi
    err="$(mktemp)"
    restic_remote_quick cat config >/dev/null 2>"$err" || rc=$?
    case "$rc" in
        0) ok "Destinazione raggiungibile (HTTPS), autenticazione accettata, repository leggibile con la password." ;;
        10)
            if restic_remote_quick init --from-repo "$LOCAL_REPO" --from-password-file "$RESTIC_PASSWORD_FILE" --copy-chunker-params >/dev/null 2>"$err"; then
                ok "Destinazione raggiungibile (HTTPS), autenticazione accettata: repository creato (stessa password della copia locale, che resta solo su questo server)."
            else
                ko "Repository remoto da creare, ma la creazione non riesce. $(explain_remote_error "$err")"
                rm -f "$err"; return 0
            fi ;;
        *) ko "$(explain_remote_error "$err")"; rm -f "$err"; return 0 ;;
    esac

    # Scrittura di prova, poi tentativo di cancellarla: se la cancellazione
    # riesce, chi controlla questo server può distruggere i backup remoti.
    # È un errore (non un avviso): append-only/Object Lock sono obbligatori.
    out="$(mktemp)"
    if printf 'Scrittura di prova del %s\n' "$(date -Iseconds)" \
        | restic_remote_quick backup --host "$SNAPSHOT_HOST" --tag prova-scrittura --stdin \
            --stdin-filename prova-scrittura.txt --json --quiet > "$out" 2>"$err"; then
        snapshot="$(restic_summary "$out" | jq -r .snapshot_id)"
        ok "Scrittura di prova riuscita."
        if restic_remote_quick forget "$snapshot" >/dev/null 2>"$err"; then
            if [ "$(remote_type)" = s3 ]; then
                ko "Questa chiave S3 può cancellare gli snapshot: usa una chiave senza s3:DeleteObject sugli snapshot (policy in docs/OPERATIONS.md). Le credenziali che cancellano servono solo alla pulizia annuale e non devono stare su questo server."
            else
                ko "Append-only NON attivo: questo server riesce a cancellare i backup remoti. Avvia il rest-server con --append-only (docs/OPERATIONS.md)."
            fi
        else
            ok "Append-only attivo: questo server non riesce a cancellare i backup remoti (la scrittura di prova resta, è minuscola)."
        fi
    else
        ko "Scrittura di prova non riuscita. $(explain_remote_error "$err")"
    fi
    if [ "$(remote_type)" = s3 ]; then
        warn "S3: che Object Lock sia attivo in modalità COMPLIANCE sul bucket non si può verificare da qui: controllalo nella console del fornitore (docs/OPERATIONS.md)."
    fi
    rm -f "$out" "$err"
    if [ -n "$R_QUOTA" ]; then
        ok "Quota dichiarata: $R_QUOTA GB, avviso sopra il ${BACKUP_REMOTE_WARN_PERCENT} per cento."
    else
        warn "${R_PREFIX}QUOTA_GB vuota: il controllo dello spazio della $R_LABEL (avviso sopra il ${BACKUP_REMOTE_WARN_PERCENT} per cento) non si fa."
    fi
}

main() {
    local command="${1:-agent}"
    shift || true
    case "$command" in
        __step)
            JOB="$1"
            export JOB
            trap 'on_step_error "$LINENO" "$BASH_COMMAND"' ERR
            "step_$1"
            ;;
        agent) cmd_agent ;;
        db) cmd_db ;;
        nightly) cmd_nightly ;;
        weekly) cmd_weekly ;;
        settings) cmd_settings ;;
        status) cmd_status ;;
        backup-check) JOB=backup-check; cmd_backup_check ;;
        verify-dump)
            local problem
            if problem="$(verify_dump "${1:?indica il file del dump}")"; then echo "Dump completo."; else echo "$problem"; exit 1; fi
            ;;
        locale) load_config || { echo "$CONFIG_ERROR" >&2; exit 1; }; restic_local "$@" ;;
        remota|remota2)
            load_config || { echo "$CONFIG_ERROR" >&2; exit 1; }
            if [ "$command" = remota ]; then select_remote 1; else select_remote 2; fi
            remote_configured || { echo "Destinazione non configurata in backup.env." >&2; exit 1; }
            restic_remote "$@" ;;
        *)
            sed -n '3,20p' "$0"
            exit 1
            ;;
    esac
}

# TIMONE_BACKUP_SOLO_FUNZIONI=1: carica le funzioni senza eseguire nulla
# (lo usano i test in docker/backup/tests/).
[ "${TIMONE_BACKUP_SOLO_FUNZIONI:-}" = 1 ] || main "$@"
