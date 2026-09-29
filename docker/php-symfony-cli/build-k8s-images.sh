#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd -- "${script_dir}/../.." && pwd)"
cd "$repo_root"

docker build -f docker/php-symfony-cli/Dockerfile --target prod -t symfony-php:prod .
docker build -f docker/php-symfony-cli/k8s/Dockerfile --target k8s-auth -t symfony-auth:k8s .
docker build -f docker/php-symfony-cli/k8s/Dockerfile --target k8s-catalog -t symfony-catalog:k8s .
docker build -f docker/php-symfony-cli/k8s/Dockerfile --target k8s-cart -t symfony-cart:k8s .
