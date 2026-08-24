#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE="${PROJECT_DIR}/.env"

load_database_service_targets() {
  if [[ -z "${DATABASE_SERVICE_TARGETS:-}" && -f "${ENV_FILE}" ]]; then
    set -a
    source "${ENV_FILE}"
    set +a
  fi

  if [[ -z "${DATABASE_SERVICE_TARGETS:-}" ]]; then
    printf 'DATABASE_SERVICE_TARGETS is not configured. Add it to %s.\n' "${ENV_FILE}" >&2
    exit 1
  fi

  printf '%s\n' "${DATABASE_SERVICE_TARGETS}"
}

run_for_database_services() {
  local command="$1"
  shift

  local -a compose_exec_args=(-T)
  if [[ -n "${CONSOLE_APP_ENV:-}" ]]; then
    compose_exec_args+=(-e "APP_ENV=${CONSOLE_APP_ENV}")
  fi

  local targets
  targets="$(load_database_service_targets)"

  # DATABASE_SERVICE_TARGETS is a comma-separated list:
  # compose-cli-service:Human label,another-cli-service:Another label
  IFS="," read -r -a service_entries <<< "${targets}"

  for service_entry in "${service_entries[@]}"; do
    service_entry="${service_entry#${service_entry%%[![:space:]]*}}"
    service_entry="${service_entry%${service_entry##*[![:space:]]}}"

    if [[ -z "${service_entry}" ]]; then
      continue
    fi

    # Split each entry by the first colon. The service is used by docker compose,
    # while the label is only printed for readable command output.
    local service="${service_entry%%:*}"
    local label="${service_entry#*:}"

    if [[ -z "${service}" || "${service}" == "${label}" ]]; then
      printf 'Invalid DATABASE_SERVICE_TARGETS entry: %s\n' "${service_entry}" >&2
      exit 1
    fi

    printf '\n==> %s (%s)\n' "${label}" "${service}"
    docker compose exec "${compose_exec_args[@]}" "${service}" php "$@" bin/console ${command}
  done
}
