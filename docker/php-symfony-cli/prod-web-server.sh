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

if [[ "${NGINX_WORKER_COUNT:-}" == "auto" ]]; then
    if ! nginx_worker_count="$(getconf _NPROCESSORS_ONLN 2>/dev/null)" \
        || [[ ! "$nginx_worker_count" =~ ^[1-9][0-9]*$ ]]; then
        printf '%s\n' 'Unable to determine the Nginx worker count with getconf _NPROCESSORS_ONLN' >&2
        exit 1
    fi
else
    require_positive_integer NGINX_WORKER_COUNT
    nginx_worker_count="$NGINX_WORKER_COUNT"
fi

fastcgi_keepalive_per_worker=$(((PHP_FPM_MAX_CHILDREN * 80) / (100 * nginx_worker_count)))

if ((fastcgi_keepalive_per_worker > 0)); then
    fastcgi_keepalive_directive="keepalive ${fastcgi_keepalive_per_worker};"
else
    fastcgi_keepalive_directive='# FastCGI keepalive is disabled because the calculated cache size is 0.'
fi

sed -E \
    "s/^worker_processes[[:space:]]+[^;]+;/worker_processes ${nginx_worker_count};/" \
    /etc/nginx/nginx.conf.template \
    > /etc/nginx/nginx.conf

sed \
    "s/__FASTCGI_KEEPALIVE_DIRECTIVE__/${fastcgi_keepalive_directive}/" \
    /etc/nginx/sites-available/default.template \
    > /etc/nginx/sites-available/default

sed \
    "s/__PHP_FPM_MAX_CHILDREN__/${PHP_FPM_MAX_CHILDREN}/" \
    /usr/local/etc/php-fpm.d/zz-prod.conf.template \
    > /usr/local/etc/php-fpm.d/zz-prod.conf

mkdir -p /run/nginx
php-fpm -D
exec nginx -g "daemon off;"
