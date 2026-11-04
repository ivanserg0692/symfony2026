#!/usr/bin/env bash

reject_k8s_args() {
    local command="$1"
    shift
    if [[ $# -gt 0 ]]; then
        echo "Usage: npm run $command" >&2
        return 2
    fi
}

ensure_k8s_settings() {
    local repo_root settings

    if [[ ! -v K8S_RUNTIME && ! -v K8S_NAMESPACE && ! -v K8S_IMAGE_PROFILE ]]; then
        repo_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)" || return
        settings="$(
            set +u
            source "$repo_root/.env" || exit 1
            printf '%s\n%s\n%s' "${K8S_RUNTIME:-}" "${K8S_NAMESPACE:-}" "${K8S_IMAGE_PROFILE:-}"
        )" || return
        K8S_RUNTIME="${settings%%$'\n'*}"
        settings="${settings#*$'\n'}"
        K8S_NAMESPACE="${settings%%$'\n'*}"
        K8S_IMAGE_PROFILE="${settings#*$'\n'}"
    elif [[ ! -v K8S_RUNTIME || ! -v K8S_NAMESPACE || ! -v K8S_IMAGE_PROFILE ]]; then
        echo 'K8S_RUNTIME, K8S_NAMESPACE and K8S_IMAGE_PROFILE must all be set in the selected environment.' >&2
        return 2
    fi

    case "$K8S_RUNTIME" in
        local|k3s|kubernetes) ;;
        *)
            echo 'Invalid K8S_RUNTIME: use local, k3s or kubernetes.' >&2
            return 2
            ;;
    esac

    case "$K8S_IMAGE_PROFILE" in
        dev|prod|load-test) ;;
        *)
            echo 'Invalid K8S_IMAGE_PROFILE: use dev, prod or load-test.' >&2
            return 2
            ;;
    esac

    if [[ ${#K8S_NAMESPACE} -gt 63 || ! "$K8S_NAMESPACE" =~ ^[a-z0-9]([-a-z0-9]*[a-z0-9])?$ ]]; then
        echo 'Invalid K8S_NAMESPACE: use 1-63 lowercase letters, digits or hyphens; start and end with a letter or digit.' >&2
        return 2
    fi

    runtime="$K8S_RUNTIME"
    namespace="$K8S_NAMESPACE"
    image_profile="$K8S_IMAGE_PROFILE"
}

resolve_registry_images() {
    local registry="$1" tag="$2" error_message="$3"

    if [[ ! "$registry" =~ ^[a-zA-Z0-9._:/-]+$ || ! "$tag" =~ ^[a-zA-Z0-9._-]+$ ]]; then
        echo "$error_message" >&2
        return 2
    fi

    auth_image="${registry}/symfony-auth:${tag}-${image_profile}"
    catalog_image="${registry}/symfony-catalog:${tag}-${image_profile}"
    cart_image="${registry}/symfony-cart:${tag}-${image_profile}"
    gateway_image="${registry}/symfony-api-gateway-nginx:${tag}"
    prometheus_image="${registry}/symfony-prometheus:${tag}"
    grafana_image="${registry}/symfony-grafana:${tag}"
    minio_image="${registry}/symfony-minio:${tag}"
    mc_image="${registry}/symfony-mc:${tag}"
}

load_local_image_refs() {
    local validate_versioned="${1:-false}"
    local refs_file="kubernetes/generated/image-refs.tsv"
    local profile service reference expected_prefix

    if [[ ! -f "$refs_file" ]]; then
        echo "Missing $refs_file. Run npm run docker:k8s:build on this machine." >&2
        return 1
    fi
    while IFS=$'\t' read -r profile service reference; do
        if [[ "$profile" == "$image_profile" ]]; then
            case "$service" in
                auth) auth_image="$reference" ;;
                catalog) catalog_image="$reference" ;;
                cart) cart_image="$reference" ;;
                *) continue ;;
            esac
            expected_prefix="symfony-${service}:k8s-${image_profile}-"
        elif [[ "$profile" == shared ]]; then
            case "$service" in
                api-gateway-nginx) gateway_image="$reference" ;;
                prometheus) prometheus_image="$reference" ;;
                grafana) grafana_image="$reference" ;;
                minio) minio_image="$reference" ;;
                mc) mc_image="$reference" ;;
                *) continue ;;
            esac
            expected_prefix="symfony-${service}:k8s-"
        else
            continue
        fi
        if [[ "$validate_versioned" == true && ! "$reference" =~ ^${expected_prefix}[1-9][0-9]*$ ]]; then
            echo "Invalid image reference for $profile/$service in $refs_file." >&2
            return 1
        fi
    done < "$refs_file"
    for reference in "${auth_image:-}" "${catalog_image:-}" "${cart_image:-}" "${gateway_image:-}" "${prometheus_image:-}" "${grafana_image:-}" "${minio_image:-}" "${mc_image:-}"; do
        if [[ -z "$reference" ]]; then
            echo "Incomplete $refs_file for profile $image_profile." >&2
            return 1
        fi
    done
}

ensure_k8s_cluster() {
    local runtime="$1" namespace="$2"

    case "$runtime" in
        local)
            if ! kind get clusters | grep -Fx -- "$namespace" >/dev/null; then
                kind create cluster --name "$namespace"
            fi
            kubectl config use-context "kind-$namespace" >/dev/null
            ;;
        k3s|kubernetes)
            kubectl cluster-info >/dev/null
            ;;
        *)
            echo 'Unsupported Kubernetes runtime.' >&2
            return 2
            ;;
    esac
}
