#!/usr/bin/env bash
# Runs inside the PHP HTTP container as symfony-prod-server. Kubernetes images
# invoke it via CMD, including in prod and test-load; Compose web services also
# use it. It configures and starts PHP-FPM and Nginx.
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

# The prod Dockerfile saves the Nginx package's nginx.conf as this template.
sed -E \
    "s/^worker_processes[[:space:]]+[^;]+;/worker_processes ${nginx_worker_count};/" \
    /etc/nginx/nginx.conf.template \
    > /etc/nginx/nginx.conf

# The prod Dockerfile copies docker/php-symfony-cli/nginx-prod.conf to this template.
sed \
    "s/__FASTCGI_KEEPALIVE_DIRECTIVE__/${fastcgi_keepalive_directive}/" \
    /etc/nginx/sites-available/default.template \
    > /etc/nginx/sites-available/default

if [[ "${K8S_ENVIRONMENT:-}" == prod || "${K8S_ENVIRONMENT:-}" == load-test ]]; then
    # The file is included inside nginx's http block. Only Kubernetes emits
    # this compact access record; Compose keeps its existing Nginx logging.
    nginx_site="$(mktemp)"
    {
        cat <<'NGINX_LOG_FORMAT'
log_format k8s_request escape=json '{"nginx_time":"$time_iso8601","request_id":"$http_x_request_id","request_uri":"$request_uri","http":{"request":{"method":"$request_method"},"response":{"status_code":$status}},"request_time":$request_time,"upstream_addr":"$upstream_addr","upstream_connect_time":"$upstream_connect_time","upstream_header_time":"$upstream_header_time","upstream_response_time":"$upstream_response_time"}';
NGINX_LOG_FORMAT
        sed '/^[[:space:]]*server[[:space:]]*{/a\    access_log /dev/stdout k8s_request;' /etc/nginx/sites-available/default
    } > "$nginx_site"
    cat "$nginx_site" > /etc/nginx/sites-available/default
    rm -f "$nginx_site"
fi

# The prod Dockerfile copies docker/php-symfony-cli/php-fpm-prod.conf to this template.
sed \
    "s/__PHP_FPM_MAX_CHILDREN__/${PHP_FPM_MAX_CHILDREN}/" \
    /usr/local/etc/php-fpm.d/zz-prod.conf.template \
    > /usr/local/etc/php-fpm.d/zz-prod.conf

mkdir -p /run/nginx
php-fpm -D
exec nginx -g "daemon off;"
