FROM php:8.4-cli-bookworm

ENV DEBIAN_FRONTEND=noninteractive
ENV PATH="/root/.symfony5/bin:${PATH}"

RUN apt-get update && apt-get install -y \
    bash \
    ca-certificates \
    curl \
    git \
    libicu-dev \
    libonig-dev \
    libpq-dev \
    librabbitmq-dev \
    libxslt1-dev \
    libzip-dev \
    unzip \
    wget \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install \
    intl \
    mbstring \
    pdo_pgsql \
    xsl \
    zip

RUN pecl install amqp xdebug \
    && docker-php-ext-enable amqp xdebug

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN wget https://get.symfony.com/cli/installer -O - | bash

WORKDIR /workspace

COPY docker/php-symfony-cli/entrypoint.sh /usr/local/bin/symfony-entrypoint
COPY docker/php-symfony-cli/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini
RUN chmod +x /usr/local/bin/symfony-entrypoint
