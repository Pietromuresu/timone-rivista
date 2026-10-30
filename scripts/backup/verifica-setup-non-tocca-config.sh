#!/usr/bin/env bash
#
# Verifica che ./setup.sh non modifichi mai la configurazione esistente
# (2026-10-30): lancia setup.sh nelle modalità indicate, una dopo l'altra, e
# confronta i checksum di backup.env, della password del backup e di .env
# prima e dopo. Uso, dalla cartella del progetto (ci vogliono minuti: ogni
# giro ricostruisce i container):
#   scripts/backup/verifica-setup-non-tocca-config.sh dev dev
#   scripts/backup/verifica-setup-non-tocca-config.sh dev prod dev
# ATTENZIONE: «prod» ricrea i container senza bind mount; per tornare allo
# sviluppo l'ultimo giro deve essere «dev».
set -euo pipefail
cd "$(dirname "$0")/../.."

[ "$#" -gt 0 ] || set -- dev dev
password_file="$(grep -m1 '^RESTIC_PASSWORD_FILE=' backup.env 2>/dev/null | cut -d= -f2- || true)"
password_file="${password_file:-backup-secrets/restic-password}"
files=(backup.env "$password_file" .env)

for f in "${files[@]}"; do
    [ -f "$f" ] || { echo "Manca $f: lancia prima ./setup.sh una volta." >&2; exit 1; }
done

checksums() { shasum -a 256 "${files[@]}"; stat -f '%Lp %N' "${files[@]}" 2>/dev/null || stat -c '%a %n' "${files[@]}"; }

before="$(checksums)"
echo "Prima:"; echo "$before"
for mode in "$@"; do
    echo; echo "=== ./setup.sh $mode ==="
    if [ "$mode" = prod ]; then ./setup.sh > /dev/null; else ./setup.sh "$mode" > /dev/null; fi
    after="$(checksums)"
    if [ "$after" != "$before" ]; then
        echo "ERRORE: dopo ./setup.sh $mode la configurazione è cambiata:"
        diff <(echo "$before") <(echo "$after") || true
        exit 1
    fi
    echo "Identici (checksum e permessi)."
done
echo; echo "OK: backup.env, $password_file e .env identici dopo ${#} esecuzioni di setup.sh ($*)."
