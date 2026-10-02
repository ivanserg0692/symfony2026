#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd -- "${script_dir}/../.." && pwd)"
cd "$repo_root"

image_runtime_signature() {
    docker image inspect \
        --format '{{json .RootFS}}|{{json .Config}}|{{json .Architecture}}|{{json .Os}}|{{json .Variant}}' \
        "$1"
}

version_k8s_image() {
    local name="$1"
    local latest_version=0
    local images repository tag version

    images="$(docker image ls --format '{{.Repository}} {{.Tag}}' "$name")"
    while read -r repository tag; do
        if [[ "$repository" == "$name" && "$tag" =~ ^k8s-([1-9][0-9]*)$ ]]; then
            version="${BASH_REMATCH[1]}"
            if (( version > latest_version )); then
                latest_version="$version"
            fi
        fi
    done <<< "$images"

    if (( latest_version > 0 )); then
        local current_signature latest_signature
        # Build metadata may change the image ID without changing runtime contents.
        current_signature="$(image_runtime_signature "${name}:k8s")"
        latest_signature="$(image_runtime_signature "${name}:k8s-${latest_version}")"
        if [[ "$current_signature" == "$latest_signature" ]]; then
            echo "${name}:k8s is unchanged from ${name}:k8s-${latest_version}"
            return
        fi
    fi

    local next_version=$((latest_version + 1))
    docker tag "${name}:k8s" "${name}:k8s-${next_version}"
    echo "Tagged ${name}:k8s-${next_version}"
}

build_k8s_image() {
    local name="$1"
    shift
    docker build "$@" -t "${name}:k8s" .
    version_k8s_image "$name"
}

docker build -f docker/php-symfony-cli/Dockerfile --target prod -t symfony-php:prod .
build_k8s_image symfony-auth -f docker/php-symfony-cli/k8s/Dockerfile --target k8s-auth
build_k8s_image symfony-catalog -f docker/php-symfony-cli/k8s/Dockerfile --target k8s-catalog
build_k8s_image symfony-cart -f docker/php-symfony-cli/k8s/Dockerfile --target k8s-cart
docker build -f docker/nginx-api-gateway/Dockerfile -t symfony-api-gateway-nginx:1.27-vts .
build_k8s_image symfony-api-gateway-nginx -f docker/nginx-api-gateway/k8s/Dockerfile
build_k8s_image symfony-prometheus -f docker/prometheus/k8s/Dockerfile
build_k8s_image symfony-grafana -f docker/grafana/k8s/Dockerfile
build_k8s_image symfony-minio -f docker/minio/Dockerfile --target server
build_k8s_image symfony-mc -f docker/minio/Dockerfile --target client
