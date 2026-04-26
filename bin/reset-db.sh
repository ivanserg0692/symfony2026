#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd -- "$SCRIPT_DIR/.." && pwd)"
COMPOSE_FILE="$ROOT_DIR/docker-compose.yml"
SYMFONY_DIR="$ROOT_DIR/symfony"
CONSOLE="docker compose -f $COMPOSE_FILE exec -T symfony-cli php bin/console"

cd "$SYMFONY_DIR"

echo "Dropping database..."
$CONSOLE doctrine:database:drop --force --if-exists

echo "Creating database..."
$CONSOLE doctrine:database:create

echo "Running migrations..."
$CONSOLE doctrine:migrations:migrate -n

echo "Loading fixtures..."
$CONSOLE doctrine:fixtures:load -n

echo "Database reset completed."

$CONSOLE app:user:sync-admin
