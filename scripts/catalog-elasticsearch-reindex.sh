#!/usr/bin/env bash

set -euo pipefail

LOCK_FILE="${TMPDIR:-/tmp}/symfony2026-catalog-elasticsearch-reindex.lock"
exec 9>"${LOCK_FILE}"

if ! flock -n 9; then
    echo "Another catalog Elasticsearch reindex orchestration is already running." >&2
    exit 1
fi

worker_started=false

restore_incremental_worker() {
    if [[ "${worker_started}" == "true" ]]; then
        docker compose start catalog-search-index-worker >/dev/null
    fi
}

trap restore_incremental_worker EXIT INT TERM

if [[ -n "$(docker compose ps --status running --services catalog-search-index-worker)" ]]; then
    worker_started=true
    docker compose stop catalog-search-index-worker >/dev/null
fi

docker compose exec \
    -e PRODUCT_SEARCH_BATCH_SIZE="${PRODUCT_SEARCH_BATCH_SIZE:-100}" \
    -e PRODUCT_SEARCH_INCREMENTAL_WORKER_PAUSED=1 \
    catalog-cli \
    php -d memory_limit="${PRODUCT_SEARCH_REINDEX_MEMORY_LIMIT:-512M}" \
    bin/console --env=prod --no-debug app:elasticsearch:reindex
