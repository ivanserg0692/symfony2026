#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/db-common.sh"

catalog_indexed=false

before_fixture_service() {
  if [[ "$1" == "cart-cli" && "${catalog_indexed}" != true ]]; then
    printf 'Catalog fixtures and Elasticsearch reindex must complete before Cart fixtures.\n' >&2
    return 1
  fi
}

after_fixture_service() {
  if [[ "$1" == "catalog-cli" ]]; then
    npm run catalog:elasticsearch:reindex
    catalog_indexed=true
  fi
}

DATABASE_SERVICE_BEFORE_HOOK=before_fixture_service \
DATABASE_SERVICE_AFTER_HOOK=after_fixture_service \
  run_for_database_services "doctrine:fixtures:load --no-interaction --no-debug" -d memory_limit=1G
