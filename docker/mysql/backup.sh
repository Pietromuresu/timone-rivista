#!/bin/sh
set -eu

# Eseguito dentro il servizio "backup" del docker-compose (profilo "tools",
# non parte con `docker compose up`): fa un mysqldump del database applicativo
# e lo scrive compresso in /backups (montato dall'host su ./backups).
#
# Uso: docker compose run --rm backup

: "${DB_HOST:=mysql}"
: "${DB_DATABASE:?variabile DB_DATABASE mancante}"
: "${DB_USERNAME:?variabile DB_USERNAME mancante}"
: "${DB_PASSWORD:?variabile DB_PASSWORD mancante}"

timestamp="$(date +%Y-%m-%d_%H%M%S)"
out_file="/backups/timone_${timestamp}.sql.gz"

mkdir -p /backups

echo "Backup di '${DB_DATABASE}' su ${DB_HOST} -> ${out_file}"

mysqldump \
    --host="${DB_HOST}" \
    --user="${DB_USERNAME}" \
    --password="${DB_PASSWORD}" \
    --single-transaction \
    --quick \
    --routines \
    --no-tablespaces \
    "${DB_DATABASE}" | gzip > "${out_file}"

echo "Backup completato: ${out_file} ($(du -h "${out_file}" | cut -f1))"
