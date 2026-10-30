# syntax=docker/dockerfile:1
#
# Immagine del servizio «backup-agent» (profilo compose «backup»).
#
# Base mysql:8.4 e non un'immagine restic/alpine: serve lo STESSO mysqldump
# del server (mariadb-dump di alpine ha incompatibilità note con MySQL 8.4)
# e, per la prova di ripristino, un mysqld della stessa versione da avviare
# in una cartella temporanea. restic e supercronic sono binari statici
# ufficiali a versione fissa, verificati con il loro checksum.
FROM mysql:8.4

ARG TARGETARCH
ARG RESTIC_VERSION=0.19.1
ARG RESTIC_SHA256_amd64=f415415624dcc452f2a02b8c33641791a8c6d6d3b65bbb3543fcf9a25151585c
ARG RESTIC_SHA256_arm64=a5f64aaab53d51e311fa3829124c5b703f2d14cf187d8640b6be3b2b49376465
ARG SUPERCRONIC_VERSION=v0.2.49
ARG SUPERCRONIC_SHA256_amd64=a53ae236602c7338aba3fbaff40bda6300eae3b9fedb8261eb06cfe3724430c1
ARG SUPERCRONIC_SHA256_arm64=02aa0cb229ba09050cba6638059dadb9eedc2276632ea43d6a57a2f8c1629dd5

# git-core: hash del commit dalla cartella del progetto montata;
# jq: file di stato in JSON; util-linux: flock (un job alla volta) e ionice.
# Niente client SSH: SFTP non è ammesso come copia remota (docs/OPERATIONS.md).
RUN microdnf install -y --nodocs bzip2 git-core jq tzdata util-linux findutils \
    && microdnf clean all

RUN set -eu; \
    case "$TARGETARCH" in \
        amd64) restic_sum="$RESTIC_SHA256_amd64"; cron_sum="$SUPERCRONIC_SHA256_amd64" ;; \
        arm64) restic_sum="$RESTIC_SHA256_arm64"; cron_sum="$SUPERCRONIC_SHA256_arm64" ;; \
        *) echo "Architettura non supportata: $TARGETARCH" >&2; exit 1 ;; \
    esac; \
    curl -fsSL -o /tmp/restic.bz2 "https://github.com/restic/restic/releases/download/v${RESTIC_VERSION}/restic_${RESTIC_VERSION}_linux_${TARGETARCH}.bz2"; \
    echo "${restic_sum}  /tmp/restic.bz2" | sha256sum -c -; \
    bunzip2 -c /tmp/restic.bz2 > /usr/local/bin/restic; \
    chmod 755 /usr/local/bin/restic; \
    curl -fsSL -o /usr/local/bin/supercronic "https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-${TARGETARCH}"; \
    echo "${cron_sum}  /usr/local/bin/supercronic" | sha256sum -c -; \
    chmod 755 /usr/local/bin/supercronic; \
    rm -f /tmp/restic.bz2; \
    restic version

# Hash del commit da cui è stata costruita l'immagine: ripiego se la cartella
# .git del progetto non è visibile nel container (vedi timone-backup.sh).
ARG GIT_COMMIT=
ENV BUILD_GIT_COMMIT=${GIT_COMMIT}

COPY timone-backup.sh /usr/local/bin/timone-backup
COPY crontab /etc/timone-backup/crontab
RUN chmod 755 /usr/local/bin/timone-backup

ENTRYPOINT ["/usr/local/bin/timone-backup"]
CMD ["agent"]
