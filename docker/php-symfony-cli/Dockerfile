ARG UBUNTU_VERSION=24.04
FROM ubuntu:${UBUNTU_VERSION}

ENV DEBIAN_FRONTEND=noninteractive
ENV PATH="/root/.symfony5/bin:${PATH}"

RUN apt-get update && apt-get install -y \
    bash \
    ca-certificates \
    composer \
    curl \
    git \
    php-cli \
    php-curl \
    php-intl \
    php-mbstring \
    php-xml \
    php-zip \
    unzip \
    wget \
    && rm -rf /var/lib/apt/lists/*

RUN wget https://get.symfony.com/cli/installer -O - | bash

WORKDIR /workspace

COPY docker/php-symfony-cli/entrypoint.sh /usr/local/bin/symfony-entrypoint
RUN chmod +x /usr/local/bin/symfony-entrypoint
