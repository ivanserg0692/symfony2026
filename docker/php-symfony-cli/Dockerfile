FROM ghcr.io/roadrunner-server/roadrunner:2025.1.1 AS roadrunner

FROM php:8.4-fpm-bookworm AS php-builder

ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y \
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


FROM php-builder AS php-prod-builder

RUN docker-php-ext-install opcache


FROM php-builder AS php-dev-builder

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug


FROM php:8.4-fpm-bookworm AS php-runtime-base

ENV DEBIAN_FRONTEND=noninteractive
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        bash \
        ca-certificates \
        curl \
        libicu72 \
        libonig5 \
        libpq5 \
        librabbitmq4 \
        libstdc++6 \
        libxslt1.1 \
        libzip4 \
        xz-utils \
        zlib1g; \
    buildPackages="autoconf dpkg-dev file g++ gcc libc6-dev make pkg-config re2c"; \
    for package in $buildPackages; do \
        dpkg-query -W -f='${db:Status-Status}\n' "$package" | grep -qx installed; \
    done; \
    apt-get purge -y --auto-remove \
        -o APT::AutoRemove::RecommendsImportant=false \
        $buildPackages; \
    rm -rf \
        /var/lib/apt/lists/* \
        /usr/local/include/php \
        /usr/local/lib/php/build; \
    rm -f \
        /usr/local/bin/docker-php-ext-configure \
        /usr/local/bin/docker-php-ext-enable \
        /usr/local/bin/docker-php-ext-install \
        /usr/local/bin/docker-php-source \
        /usr/local/bin/php-config \
        /usr/local/bin/phpize

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php-symfony-cli/entrypoint.sh /usr/local/bin/symfony-entrypoint
RUN chmod +x /usr/local/bin/symfony-entrypoint

WORKDIR /workspace

FROM php-runtime-base AS prod

COPY --from=php-prod-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-prod-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

ENV APP_ENV=prod \
    APP_DEBUG=0
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php-symfony-cli/opcache-prod.ini /usr/local/etc/php/conf.d/99-opcache-prod.ini
COPY docker/php-symfony-cli/nginx-prod.conf /etc/nginx/sites-available/default
COPY docker/php-symfony-cli/php-fpm-prod.conf /usr/local/etc/php-fpm.d/zz-prod.conf
COPY docker/php-symfony-cli/prod-web-server.sh /usr/local/bin/symfony-prod-server
RUN chmod +x /usr/local/bin/symfony-prod-server


FROM php-runtime-base AS dev

COPY --from=php-dev-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-dev-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

ENV PATH="/root/.symfony5/bin:${PATH}"

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    wget \
    && rm -rf /var/lib/apt/lists/*

RUN wget https://get.symfony.com/cli/installer -O - | bash

COPY docker/php-symfony-cli/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini
COPY docker/php-symfony-cli/dev-web-server.sh /usr/local/bin/symfony-dev-server
RUN chmod +x /usr/local/bin/symfony-dev-server


FROM prod AS load-test

ENV CLOUDFLARE_TURNSTILE_ENABLED=0
