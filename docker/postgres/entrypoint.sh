#!/usr/bin/env sh
set -eu

require_positive_integer() {
    name="$1"
    value="$2"

    case "$value" in
        ''|*[!0-9]*|0*)
            printf '%s must be a positive integer, got: %s\n' "$name" "${value:-<empty>}" >&2
            exit 1
            ;;
    esac
}

require_non_negative_integer() {
    name="$1"
    value="$2"

    case "$value" in
        ''|*[!0-9]*|0[0-9]*)
            printf '%s must be a non-negative integer, got: %s\n' "$name" "${value:-<empty>}" >&2
            exit 1
            ;;
    esac
}

require_positive_integer PHP_FPM_MAX_CHILDREN "${PHP_FPM_MAX_CHILDREN:-}"
require_positive_integer ROADRUNNER_NUM_WORKERS "${ROADRUNNER_NUM_WORKERS:-}"
require_non_negative_integer POSTGRES_CONNECTION_RESERVE "${POSTGRES_CONNECTION_RESERVE:-}"

POSTGRES_MAX_CONNECTIONS=$((
    PHP_FPM_MAX_CHILDREN
    + ROADRUNNER_NUM_WORKERS
    + POSTGRES_CONNECTION_RESERVE
))
export POSTGRES_MAX_CONNECTIONS

if [ "${1:-}" = 'postgres' ]; then
    set -- "$@" -c "max_connections=${POSTGRES_MAX_CONNECTIONS}"
fi

exec /usr/local/bin/docker-entrypoint.sh "$@"
