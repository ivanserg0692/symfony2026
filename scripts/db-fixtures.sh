#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/db-common.sh"

CONSOLE_APP_ENV=dev run_for_database_services "doctrine:fixtures:load --no-interaction --no-debug" -d memory_limit=1G
