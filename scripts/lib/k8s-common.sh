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
