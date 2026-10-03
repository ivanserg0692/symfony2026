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
    local tag_prefix="$2"
    local latest_version=0
    local images repository tag version

    images="$(docker image ls --format '{{.Repository}} {{.Tag}}' "$name")"
    while read -r repository tag; do
        if [[ "$repository" == "$name" && "$tag" =~ ^${tag_prefix}-([1-9][0-9]*)$ ]]; then
            version="${BASH_REMATCH[1]}"
            if (( version > latest_version )); then
                latest_version="$version"
            fi
        fi
    done <<< "$images"

    if (( latest_version > 0 )); then
        local current_signature latest_signature
        # Build metadata may change the image ID without changing runtime contents.
        current_signature="$(image_runtime_signature "${name}:${tag_prefix}")"
        latest_signature="$(image_runtime_signature "${name}:${tag_prefix}-${latest_version}")"
        if [[ "$current_signature" == "$latest_signature" ]]; then
            echo "${name}:${tag_prefix} is unchanged from ${name}:${tag_prefix}-${latest_version}"
            return
        fi
    fi

    local next_version=$((latest_version + 1))
    docker tag "${name}:${tag_prefix}" "${name}:${tag_prefix}-${next_version}"
    echo "Tagged ${name}:${tag_prefix}-${next_version}"
}

build_k8s_image() {
    local name="$1"
    local tag_prefix="$2"
    shift 2
    docker build "$@" -t "${name}:${tag_prefix}" .
    version_k8s_image "$name" "$tag_prefix"
}

build_php_profile() {
    local profile="$1" app_env="$2" app_debug="$3" turnstile_enabled="$4"
    local composer_flags=--no-dev
    local service
    if [[ "$profile" == dev ]]; then
        composer_flags=''
    fi
    for service in auth catalog cart; do
        build_k8s_image "symfony-${service}" "k8s-${profile}" \
            -f docker/php-symfony-cli/k8s/Dockerfile \
            --target "k8s-${service}" \
            --build-arg "K8S_APP_ENV=${app_env}" \
            --build-arg "K8S_APP_DEBUG=${app_debug}" \
            --build-arg "K8S_TURNSTILE_ENABLED=${turnstile_enabled}" \
            --build-arg "K8S_COMPOSER_FLAGS=${composer_flags}"
    done
}

docker build -f docker/php-symfony-cli/Dockerfile --target prod -t symfony-php:prod .
build_php_profile dev dev 1 1
build_php_profile prod prod 0 1
build_php_profile load-test prod 0 0
for service in auth catalog cart; do
    docker tag "symfony-${service}:k8s-prod" "symfony-${service}:k8s"
    version_k8s_image "symfony-${service}" k8s
done
docker build -f docker/nginx-api-gateway/Dockerfile -t symfony-api-gateway-nginx:1.27-vts .
build_k8s_image symfony-api-gateway-nginx k8s -f docker/nginx-api-gateway/k8s/Dockerfile
build_k8s_image symfony-prometheus k8s -f docker/prometheus/k8s/Dockerfile
build_k8s_image symfony-grafana k8s -f docker/grafana/k8s/Dockerfile
build_k8s_image symfony-minio k8s -f docker/minio/Dockerfile --target server
build_k8s_image symfony-mc k8s -f docker/minio/Dockerfile --target client
