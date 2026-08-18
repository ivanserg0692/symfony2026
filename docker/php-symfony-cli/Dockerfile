FROM ghcr.io/roadrunner-server/roadrunner:2025.1.1 AS roadrunner

FROM php:8.4-cli-bookworm AS php-base

ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y \
    bash \
    ca-certificates \
    curl \
    libicu-dev \
    libonig-dev \
    libpq-dev \
    librabbitmq-dev \
    libxslt1-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    intl \
    mbstring \
    pdo_pgsql \
    sockets \
    xsl \
    zip

RUN pecl install amqp grpc redis \
    && docker-php-ext-enable amqp grpc redis

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

COPY docker/php-symfony-cli/entrypoint.sh /usr/local/bin/symfony-entrypoint
RUN chmod +x /usr/local/bin/symfony-entrypoint

WORKDIR /workspace

FROM php-base AS prod

RUN docker-php-ext-install opcache
COPY docker/php-symfony-cli/opcache-prod.ini /usr/local/etc/php/conf.d/99-opcache-prod.ini

ENV APP_ENV=prod
ENV APP_DEBUG=0

FROM php-base AS dev

ENV PATH="/root/.symfony5/bin:${PATH}"

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    wget \
    && rm -rf /var/lib/apt/lists/*

RUN wget https://get.symfony.com/cli/installer -O - | bash

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY docker/php-symfony-cli/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini
