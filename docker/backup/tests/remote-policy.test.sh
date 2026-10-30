#!/usr/bin/env bash
# Test delle regole sulle copie remote e del mascheramento dei segreti
# (2026-10-30). Gira nell'immagine del backup, senza rete:
#   docker compose --profile backup run --rm --no-deps \
#     -v "$PWD/docker/backup/tests:/tests:ro" --entrypoint bash backup-agent /tests/remote-policy.test.sh
set -u
TIMONE_BACKUP_SOLO_FUNZIONI=1 . /usr/local/bin/timone-backup
set +e
failures=0
check() {
    local name="$1" expected="$2" actual="$3"
    if grep -q -- "$expected" <<<"$actual"; then echo "ok   - $name"; else echo "FAIL - $name: «$actual» non contiene «$expected»"; failures=$((failures + 1)); fi
}
check_empty() {
    if [ -z "$2" ]; then echo "ok   - $1"; else echo "FAIL - $1: atteso nessun errore, ottenuto «$2»"; failures=$((failures + 1)); fi
}
policy() {
    BACKUP_REMOTE_REPOSITORY="$1" BACKUP_REMOTE_REST_USERNAME="${2:-}" BACKUP_REMOTE_REST_PASSWORD="${3:-}" \
    BACKUP_REMOTE_S3_ACCESS_KEY_ID="${4:-}" BACKUP_REMOTE_S3_SECRET_ACCESS_KEY="${5:-}" \
        bash -c 'TIMONE_BACKUP_SOLO_FUNZIONI=1 . /usr/local/bin/timone-backup; select_remote 1; remote_policy_error'
}

check "cartella montata rifiutata"   "non è ammessa come copia remota" "$(policy /mnt/nas/timone)"
check "local: rifiutato"             "non è ammessa come copia remota" "$(policy local:/mnt/nas)"
check "sftp rifiutato"               "non supportato"                  "$(policy sftp:u@nas:/srv/timone)"
check "rest senza TLS rifiutato"     "senza TLS"                       "$(policy rest:http://nas:8000/timone/ u p)"
check "credenziali nell'indirizzo"   "Credenziali scritte dentro"      "$(policy rest:https://u:p@nas:8000/timone/)"
check "rest senza autenticazione"    "senza autenticazione"            "$(policy rest:https://nas:8000/timone/)"
check "s3 senza TLS rifiutato"       "S3 senza TLS"                    "$(policy s3:http://s3.esempio.it/b/t '' '' K S)"
check "s3 senza chiavi"              "S3 senza credenziali"            "$(policy s3:https://s3.esempio.it/b/t)"
check_empty "rest-server https con utente ammesso" "$(policy rest:https://nas.esempio.it:8000/timone/ timone segreto)"
check_empty "s3 https con chiavi ammesso"          "$(policy s3:https://s3.esempio.it/b/t '' '' CHIAVE SEGRETO)"

# Mascheramento: indirizzo, host, utente, password, chiavi, password del DB.
export BACKUP_REMOTE_REPOSITORY=rest:https://backup.esempio.it:8000/timone/ BACKUP_REMOTE_REST_USERNAME=timone
export BACKUP_REMOTE_REST_PASSWORD=PasswordSegreta123 BACKUP_REMOTE_2_REPOSITORY=s3:https://s3.fornitore.it/bucket-x/timone
export BACKUP_REMOTE_2_S3_ACCESS_KEY_ID=AKIAPROVA123 BACKUP_REMOTE_2_S3_SECRET_ACCESS_KEY=SegretoS3Prova BACKUP_DB_PASSWORD=dbpassword999
out="$(redact "Fatal: unable to open repository at rest:https://backup.esempio.it:8000/timone/: Get \"https://timone:PasswordSegreta123@backup.esempio.it:8000/timone/config\": dial tcp backup.esempio.it 10.0.0.5:8000 AKIAPROVA123 SegretoS3Prova s3.fornitore.it dbpassword999 http://10.0.0.5:9000/x")"
for secret in backup.esempio.it PasswordSegreta123 AKIAPROVA123 SegretoS3Prova s3.fornitore.it dbpassword999 "http://10.0.0.5"; do
    if grep -qF -- "$secret" <<<"$out"; then echo "FAIL - redact lascia «$secret»: $out"; failures=$((failures + 1)); else echo "ok   - redact nasconde $secret"; fi
done

# Lo username «timone» non deve rovinare i testi normali.
check "lo username non viene mascherato nel testo" "Utente MySQL timone_backup" "$(redact "Utente MySQL timone_backup: si collega")"

[ "$failures" -eq 0 ] && echo "Tutti i test passati." || { echo "$failures test falliti."; exit 1; }
