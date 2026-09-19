#!/usr/bin/env bash
set -euo pipefail

require_positive_integer() {
    local name="$1"
    local value="${!name:-}"

    if [[ ! "$value" =~ ^[1-9][0-9]*$ ]]; then
        printf '%s must be a positive integer, got: %s\n' "$name" "${value:-<empty>}" >&2
        exit 1
    fi
}

require_positive_integer PHP_FPM_MAX_CHILDREN
require_positive_integer NGINX_WORKER_PROCESSES

fastcgi_keepalive_per_worker=$((PHP_FPM_MAX_CHILDREN / NGINX_WORKER_PROCESSES))

if ((fastcgi_keepalive_per_worker < 1)); then
    printf '%s\n' 'NGINX_WORKER_PROCESSES must not exceed PHP_FPM_MAX_CHILDREN' >&2
    exit 1
fi

sed -E \
    "s/^worker_processes[[:space:]]+[^;]+;/worker_processes ${NGINX_WORKER_PROCESSES};/" \
    /etc/nginx/nginx.conf.template \
    > /etc/nginx/nginx.conf

sed \
    "s/__FASTCGI_KEEPALIVE_PER_WORKER__/${fastcgi_keepalive_per_worker}/" \
    /etc/nginx/sites-available/default.template \
    > /etc/nginx/sites-available/default

sed \
    "s/__PHP_FPM_MAX_CHILDREN__/${PHP_FPM_MAX_CHILDREN}/" \
    /usr/local/etc/php-fpm.d/zz-prod.conf.template \
    > /usr/local/etc/php-fpm.d/zz-prod.conf

mkdir -p /run/nginx
php-fpm -D
exec nginx -g "daemon off;"
