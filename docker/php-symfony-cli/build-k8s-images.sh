#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd -- "${script_dir}/../.." && pwd)"
cd "$repo_root"

docker build -f docker/php-symfony-cli/Dockerfile --target prod -t symfony-php:prod .
docker build -f docker/php-symfony-cli/k8s/Dockerfile --target k8s-auth -t symfony-auth:k8s .
docker build -f docker/php-symfony-cli/k8s/Dockerfile --target k8s-catalog -t symfony-catalog:k8s .
docker build -f docker/php-symfony-cli/k8s/Dockerfile --target k8s-cart -t symfony-cart:k8s .
docker build -f docker/nginx-api-gateway/Dockerfile -t symfony-api-gateway-nginx:1.27-vts .
docker build -f docker/nginx-api-gateway/k8s/Dockerfile -t symfony-api-gateway-nginx:k8s .
docker build -f docker/prometheus/k8s/Dockerfile -t symfony-prometheus:k8s .
docker build -f docker/grafana/k8s/Dockerfile -t symfony-grafana:k8s .
