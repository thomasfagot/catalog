FROM php:8.4-fpm-alpine

RUN apk --no-cache --update add \
        shadow sudo \
        bash \
        nano \
        curl \
        nodejs-current \
        unzip \
        libpq-dev \
        libzip-dev \
        zip \
        icu-data-full icu-dev \
        acl \
    && corepack enable && corepack prepare pnpm@latest --activate

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        curl \
        apcu \
        intl \
        @composer \
        pdo \
        pdo_pgsql \
        zip \
        opcache

RUN adduser -u 1000 -h /home/docker -s /bin/bash -D docker && \
    chown -R docker:docker /home/docker /var/www/html /usr/local/etc/php-fpm.d

USER docker

WORKDIR /var/www/html
