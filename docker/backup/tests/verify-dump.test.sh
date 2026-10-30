#!/usr/bin/env bash
# Test della verifica del dump (timone-backup verify-dump). Gira nell'immagine
# del backup, senza database né repository:
#   docker compose --profile backup run --rm --no-deps \
#     -v "$PWD/docker/backup/tests:/tests:ro" --entrypoint bash backup-agent /tests/verify-dump.test.sh
set -u
dir="$(mktemp -d)"
failures=0

expect() {
    local name="$1" expected_code="$2" expected_text="$3" file="$4" output code
    output="$(timone-backup verify-dump "$file" 2>&1)"
    code=$?
    if [ "$code" -eq "$expected_code" ] && grep -q "$expected_text" <<<"$output"; then
        echo "ok   - $name"
    else
        echo "FAIL - $name (codice $code, output: $output)"
        failures=$((failures + 1))
    fi
}

: > "$dir/vuoto.sql"
printf -- '-- MySQL dump\nCREATE TABLE `users` (\n  `id` int\n);\nINSERT INTO `users` VALUES (1);\n' > "$dir/troncato.sql"
printf -- '-- MySQL dump\nSET NAMES utf8mb4;\n-- Dump completed on 2026-10-30 15:00:00\n' > "$dir/senza-tabelle.sql"
printf -- '-- MySQL dump\nCREATE TABLE `users` (\n  `id` int\n);\nINSERT INTO `users` VALUES (1);\n-- Dump completed on 2026-10-30 15:00:00\n' > "$dir/completo.sql"

expect "dump vuoto"           1 "vuoto"            "$dir/vuoto.sql"
expect "dump troncato"        1 "troncato"         "$dir/troncato.sql"
expect "dump senza tabelle"   1 "nessuna tabella"  "$dir/senza-tabelle.sql"
expect "dump completo"        0 "Dump completo"    "$dir/completo.sql"
expect "file inesistente"     1 "vuoto"            "$dir/non-esiste.sql"

rm -rf "$dir"
[ "$failures" -eq 0 ] && echo "Tutti i test passati." || { echo "$failures test falliti."; exit 1; }
