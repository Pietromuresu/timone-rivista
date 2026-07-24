# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1: build degli asset frontend (Vite/Tailwind/Alpine/Sortable)
# ---------------------------------------------------------------------------
FROM node:20-alpine AS node_builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY resources/ resources/
COPY public/ public/
COPY vite.config.js ./
COPY postcss.config.js tailwind.config.js ./

RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2: dipendenze PHP (senza devDependencies)
# ---------------------------------------------------------------------------
FROM composer:2 AS composer_builder

WORKDIR /app

COPY composer.json composer.lock ./
# ext-imagick non è presente in questa immagine di build: composer.json ha già
# "config.platform.ext-imagick" per far finta che sia installata in fase di
# risoluzione dipendenze (verrà davvero presente nello stage finale sotto),
# stesso trucco già usato per lo sviluppo in locale (vedi HANDOFF.md).
RUN composer install --no-dev --no-scripts --no-autoloader --optimize-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize

# ---------------------------------------------------------------------------
# Stage 3: immagine finale, PHP-FPM con Imagick + Ghostscript
# ---------------------------------------------------------------------------
FROM php:8.2-fpm-alpine AS app

WORKDIR /var/www/html

# Dipendenze di sistema:
# - ghostscript: delegate PDF di Imagick (spatie/pdf-to-image ne ha bisogno)
# - imagemagick-dev + libtool: per compilare l'estensione PHP imagick via PECL
# - libpng-dev/libjpeg-turbo-dev/freetype-dev: formati immagine comuni per Imagick
RUN apk add --no-cache \
        ghostscript \
        imagemagick \
        libpng \
        libjpeg-turbo \
        freetype \
        mysql-client \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        imagemagick-dev \
        libtool \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath opcache gd pcntl \
    && pecl install imagick-3.7.0 redis \
    && docker-php-ext-enable imagick redis \
    && apk del .build-deps

# ImageMagick blocca di default la lettura/scrittura di PDF (policy di
# sicurezza legata a vecchie CVE del delegate Ghostscript) — senza questo,
# ogni conversione PDF->immagine fallisce silenziosamente in produzione.
RUN if [ -f /etc/ImageMagick-7/policy.xml ]; then \
        sed -i '/pattern="PDF"/d' /etc/ImageMagick-7/policy.xml; \
    fi

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini

COPY --from=composer_builder /app/vendor ./vendor
COPY --from=node_builder /app/public/build ./public/build
COPY . .

# bootstrap/cache/*.php è escluso dal build context (.dockerignore): il
# manifest di package discovery generato in locale con le dev-dependency
# (es. laravel/pail) non è valido per la vendor/ --no-dev appena copiata.
# Va rigenerato qui, altrimenti l'avvio fallisce con "PailServiceProvider
# not found" appena una dev-dependency con service provider viene rimossa.
RUN php artisan package:discover --ansi

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Stage 4: nginx, con gli stessi asset statici build/ generati sopra.
#
# Nginx NON monta public/ dall'host: public/build è gitignored e potrebbe non
# esistere lì (esiste solo dentro l'immagine, generato da node_builder). Fare
# build di nginx da questo stesso Dockerfile garantisce che serva sempre lo
# stesso manifest.json/asset hashati con cui l'app è stata effettivamente
# costruita.
# ---------------------------------------------------------------------------
FROM nginx:alpine AS nginx_runtime

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=node_builder /app/public /var/www/html/public

EXPOSE 80
