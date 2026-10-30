#!/usr/bin/env bash
#
# Verifica che nessun segreto del backup compaia nel registro, nei file di
# stato letti dall'app (mail, pagina admin) o nei log Docker del servizio
# (2026-10-30). Cerca: password del repository, password del database di
# backup, password e chiavi delle destinazioni, indirizzi delle destinazioni.
# Uso, dalla cartella del progetto:  scripts/backup/verifica-segreti-nei-log.sh
# Esce con 1 se trova qualcosa (senza stampare il segreto).
set -euo pipefail
cd "$(dirname "$0")/../.."

[ -f backup.env ] || { echo "Manca backup.env." >&2; exit 2; }
valore() { grep -m1 "^$1=" backup.env | cut -d= -f2- || true; }

password_file="$(valore RESTIC_PASSWORD_FILE)"
password_file="${password_file:-backup-secrets/restic-password}"
segreti=()
[ -s "$password_file" ] && segreti+=("$(cat "$password_file")")
for chiave in BACKUP_DB_PASSWORD BACKUP_REMOTE_REST_PASSWORD BACKUP_REMOTE_S3_ACCESS_KEY_ID BACKUP_REMOTE_S3_SECRET_ACCESS_KEY \
    BACKUP_REMOTE_2_REST_PASSWORD BACKUP_REMOTE_2_S3_ACCESS_KEY_ID BACKUP_REMOTE_2_S3_SECRET_ACCESS_KEY; do
    v="$(valore "$chiave")"; [ "${#v}" -ge 8 ] && segreti+=("$v")
done
for chiave in BACKUP_REMOTE_REPOSITORY BACKUP_REMOTE_2_REPOSITORY; do
    v="$(valore "$chiave")"
    case "$v" in ''|*DA_CONFIGURARE*) continue ;; esac
    segreti+=("$v")
    host="${v#*://}"; host="${host%%[/:]*}"
    [ "${#host}" -ge 4 ] && segreti+=("$host")
done

testo="$(mktemp)"
trap 'rm -f "$testo"' EXIT
docker compose run --rm --no-deps --entrypoint sh backup-agent -c 'cat /status/backup.log /status/backup.log.1 /status/*.json 2>/dev/null' > "$testo" 2>/dev/null || true
docker compose logs --no-color backup-agent >> "$testo" 2>/dev/null || true

trovati=0
for i in "${!segreti[@]}"; do
    if grep -qF -- "${segreti[$i]}" "$testo"; then
        echo "❌ Trovato nei log un segreto o un indirizzo di backup.env (voce n. $((i + 1)), non mostrata)."
        trovati=1
    fi
done
righe="$(wc -l < "$testo" | tr -d ' ')"
if [ "$trovati" -eq 0 ]; then
    echo "✅ Nessun segreto né indirizzo di destinazione in $righe righe di registro, stato e log Docker (${#segreti[@]} valori cercati)."
fi
exit "$trovati"
