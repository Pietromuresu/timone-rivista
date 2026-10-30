#!/usr/bin/env bash
#
# Controllo indipendente del backup di Timone (2026-10-30).
#
# Gira su UN'ALTRA MACCHINA (il NAS del rest-server, un PC dell'IT…), non sul
# server del Timone: se il server è spento, compromesso o il suo backup non
# parte, questo controllo se ne accorge lo stesso e manda una mail.
#
# Come fa senza la password del backup: in un repository restic ogni
# snapshot è un file a sé nella cartella «snapshots/», scritto quando la
# copia arriva sulla destinazione. Basta guardare la data del file più
# recente: niente password (che non deve mai stare sulla destinazione), solo
# lettura, nessuna dipendenza dal server del Timone.
#
# Requisiti: bash, curl (con --aws-sigv4 per S3: curl ≥ 7.75), find, date.
# Uso:
#   controllo-indipendente.sh /percorso/controllo-indipendente.conf             # controllo
#   controllo-indipendente.sh /percorso/controllo-indipendente.conf --prova-mail
# Configurazione: copia controllo-indipendente.conf.example e compilala.
# Guida: docs/OPERATIONS.md, «Controllo indipendente da un'altra macchina».
set -euo pipefail

conf="${1:?Uso: controllo-indipendente.sh FILE_DI_CONFIGURAZIONE [--prova-mail]}"
[ -r "$conf" ] || { echo "Configurazione non leggibile: $conf" >&2; exit 2; }
# shellcheck disable=SC1090
. "$conf"
: "${MODO:?MODO mancante (cartella oppure s3)}"
: "${MAX_ORE:=26}"
: "${NOME:=Timone}"

# --- mail (curl SMTP, senza dipendere da sendmail sulla macchina) ----------
manda_mail() {
    local oggetto="$1" testo="$2" file rcpt args=()
    file="$(mktemp)"
    {
        printf 'From: %s\r\nTo: %s\r\nSubject: %s\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n' \
            "$MAIL_DA" "$MAIL_A" "$oggetto"
        printf '%s\r\n\r\nControllo indipendente eseguito da %s il %s.\r\nGuida: docs/OPERATIONS.md, sezione «Controllo indipendente da un'"'"'altra macchina».\r\n' \
            "$testo" "$(hostname)" "$(date '+%Y-%m-%d %H:%M')"
    } > "$file"
    IFS=',' read -ra destinatari <<<"$MAIL_A"
    for rcpt in "${destinatari[@]}"; do
        args+=(--mail-rcpt "$(echo "$rcpt" | tr -d ' ')")
    done
    [ "${SMTP_TLS:-si}" = si ] && args+=(--ssl-reqd)
    [ -n "${SMTP_UTENTE:-}" ] && args+=(--user "$SMTP_UTENTE:$SMTP_PASSWORD")
    curl --silent --show-error --max-time 60 --url "$SMTP_URL" --mail-from "$MAIL_DA" "${args[@]}" -T "$file"
    rm -f "$file"
}

problema() {
    echo "PROBLEMA: $1"
    manda_mail "[$NOME] Backup: $2" "$1"
    exit 1
}

if [ "${2:-}" = --prova-mail ]; then
    manda_mail "[$NOME] Backup: prova del controllo indipendente" "Mail di prova: il controllo indipendente del backup riesce a mandare mail."
    echo "Mail di prova inviata a $MAIL_A."
    exit 0
fi

# Data (UTC, formato ISO) oltre la quale l'ultimo snapshot è troppo vecchio.
# date -d @N (GNU, busybox) oppure date -r N (macOS/BSD).
soglia_epoch=$(( $(date +%s) - MAX_ORE * 3600 ))
soglia_iso="$(date -u -d "@$soglia_epoch" +%Y-%m-%dT%H:%M:%S 2>/dev/null || date -u -r "$soglia_epoch" +%Y-%m-%dT%H:%M:%S)"

case "$MODO" in
    cartella)
        # Sul NAS: la cartella dei dati del rest-server per questo repository
        # (es. /srv/restic/timone). Basta un utente in sola lettura.
        dir="${CARTELLA:?CARTELLA mancante}/snapshots"
        [ -d "$dir" ] && [ -r "$dir" ] || problema "Non riesco a leggere $dir: la destinazione del backup non è montata o non è accessibile." "destinazione non leggibile"
        totale="$(find "$dir" -type f | wc -l | tr -d ' ')"
        [ "$totale" -gt 0 ] || problema "La destinazione $CARTELLA non contiene nessuno snapshot." "nessun backup sulla destinazione"
        recenti="$(find "$dir" -type f -mmin "-$(( MAX_ORE * 60 ))" | wc -l | tr -d ' ')"
        [ "$recenti" -gt 0 ] || problema "Nessun backup nuovo arrivato sulla destinazione nelle ultime $MAX_ORE ore ($totale snapshot in tutto, tutti più vecchi). Il server del Timone non sta copiando: controlla con «docker compose run --rm backup-agent status» sul server." "nessun backup da più di $MAX_ORE ore"
        echo "OK: $recenti snapshot arrivati nelle ultime $MAX_ORE ore ($totale in tutto)."
        ;;
    s3)
        # Elenco degli oggetti sotto <prefisso>/snapshots/ con una chiave di
        # SOLA LETTURA (s3:ListBucket), firmato da curl. Si guarda il
        # LastModified più recente; più pagine se gli snapshot sono tanti.
        : "${S3_ENDPOINT:?}" "${S3_BUCKET:?}" "${S3_REGIONE:?}" "${S3_CHIAVE:?}" "${S3_SEGRETO:?}"
        prefisso="${S3_PREFISSO:+$S3_PREFISSO/}snapshots/"
        ultimo=""; totale=0; token=""
        while :; do
            args=(--get --data-urlencode "list-type=2" --data-urlencode "prefix=$prefisso" --data-urlencode "max-keys=${S3_PAGINA:-1000}")
            [ -n "$token" ] && args+=(--data-urlencode "continuation-token=$token")
            [ -n "${S3_CACERT:-}" ] && args+=(--cacert "$S3_CACERT")
            risposta="$(curl --silent --show-error --fail --max-time 60 \
                --aws-sigv4 "aws:amz:$S3_REGIONE:s3" --user "$S3_CHIAVE:$S3_SEGRETO" \
                "${args[@]}" "$S3_ENDPOINT/$S3_BUCKET" 2>&1)" \
                || problema "Non riesco a leggere il bucket $S3_BUCKET: $risposta" "destinazione non leggibile"
            date_pagina="$(grep -o '<LastModified>[^<]*</LastModified>' <<<"$risposta" | sed -E 's#</?LastModified>##g; s#\..*##; s#Z$##' || true)"
            if [ -n "$date_pagina" ]; then
                totale=$(( totale + $(wc -l <<<"$date_pagina") ))
                massimo="$(sort <<<"$date_pagina" | tail -n 1)"
                [[ "$massimo" > "$ultimo" ]] && ultimo="$massimo"
            fi
            grep -q '<IsTruncated>true</IsTruncated>' <<<"$risposta" || break
            token="$(grep -o '<NextContinuationToken>[^<]*</NextContinuationToken>' <<<"$risposta" | sed -E 's#</?NextContinuationToken>##g')"
            [ -n "$token" ] || break
        done
        [ "$totale" -gt 0 ] || problema "Il bucket $S3_BUCKET non contiene nessuno snapshot sotto $prefisso." "nessun backup sulla destinazione"
        # Confronto tra date ISO in UTC: l'ordine alfabetico è quello cronologico.
        [[ "$ultimo" > "$soglia_iso" ]] || problema "L'ultimo backup arrivato sul bucket è del $ultimo UTC, più vecchio di $MAX_ORE ore ($totale snapshot in tutto). Il server del Timone non sta copiando: controlla con «docker compose run --rm backup-agent status» sul server." "nessun backup da più di $MAX_ORE ore"
        echo "OK: ultimo backup arrivato il $ultimo UTC ($totale snapshot in tutto)."
        ;;
    *)
        echo "MODO sconosciuto: $MODO (usa cartella oppure s3)" >&2
        exit 2
        ;;
esac
