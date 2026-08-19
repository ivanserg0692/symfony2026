#!/usr/bin/env bash
set -euo pipefail

mkdir -p /run/nginx
php-fpm -D
exec nginx -g "daemon off;"
