FROM node:24-alpine AS node
FROM php:8.5-fpm-alpine

ARG user=app
ARG uid=1000

COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache \
      bash \
      git \
      unzip \
      zip \
      ffmpeg \
      libstdc++ \
      libgcc

RUN install-php-extensions \
      pdo_mysql \
      exif \
      bcmath \
      gd \
      zip \
      opcache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
 && ln -s ../lib/node_modules/corepack/dist/corepack.js /usr/local/bin/corepack

RUN addgroup -g ${uid} ${user} \
 && adduser -D -u ${uid} -G ${user} -s /bin/bash ${user} \
 && addgroup ${user} www-data \
 && mkdir -p /home/${user}/.composer \
 && chown -R ${user}:${user} /home/${user}

ENV COMPOSER_HOME=/home/${user}/.composer

WORKDIR /var/www
USER ${user}