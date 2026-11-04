# syntax=docker/dockerfile:1
#
# Dev toolchain image for composer, bin/console, phpunit and the Symfony CLI.
# Third-party PHP code runs here, not as your macOS user. See compose.yaml.
#
# Base images are pinned by digest. To bump one, look up the new index digest
# (`docker buildx imagetools inspect <image>:<tag>`) and update tag + digest together.

FROM composer:2.11.1@sha256:5190d9b445add2fa26090108273cb1ef1e1af98175201c737b85b3c4f90fbf7c AS composer
FROM ghcr.io/symfony-cli/symfony-cli:5.22.2@sha256:a9e83ee6845088011241a1801ae33587a35552f0a1c236c1741a7152db06d274 AS symfony-cli

FROM php:8.4-cli-bookworm@sha256:54b467ede1db96c66675e7b78457dd43729d0e7be31978d4a1e5c069ceabcf76

# Extensions match .platform.app.yaml runtime.extensions plus what composer.lock needs
# (zip). ctype, iconv, mbstring, sodium, dom, xml, tokenizer ship with the base image.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git unzip \
        libpq-dev libxslt1-dev libicu-dev libzip-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql xsl intl zip opcache; \
    pecl install apcu; \
    docker-php-ext-enable apcu; \
    rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=symfony-cli /usr/local/bin/symfony /usr/local/bin/symfony

RUN { \
      echo 'memory_limit=512M'; \
      echo 'apc.enable_cli=1'; \
      echo 'display_errors=On'; \
    } > /usr/local/etc/php/conf.d/zz-dev.ini

# Containers run as your host UID (see Makefile), which has no passwd entry,
# so HOME and the caches live in world-writable (sticky) dirs. /cache is a
# named volume so composer/npm caches survive `docker compose run --rm`.
RUN mkdir -p /home/dev /cache/composer /cache/npm \
 && chmod 1777 /home/dev /cache /cache/composer /cache/npm

ENV HOME=/home/dev \
    COMPOSER_HOME=/cache/composer \
    COMPOSER_CACHE_DIR=/cache/composer \
    COMPOSER_ROOT_VERSION=1.0.0 \
    COMPOSER_NO_INTERACTION=1

WORKDIR /app
