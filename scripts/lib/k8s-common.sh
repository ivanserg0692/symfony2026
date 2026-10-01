#!/usr/bin/env bash

parse_k8s_deploy_args() {
    runtime='local'
    namespace_override=''

    while [[ $# -gt 0 ]]; do
        case "$1" in
            --runtime|--namespace)
                if [[ $# -lt 2 || -z "${2:-}" || "${2:-}" == --* ]]; then
                    echo "Missing value for $1." >&2
                    echo 'Usage: npm run k8s:deploy [-- --runtime k3s|kubernetes] [--namespace name]' >&2
                    return 2
                fi
                if [[ "$1" == --runtime ]]; then
                    runtime="$2"
                else
                    namespace_override="$2"
                fi
                shift 2
                ;;
            *)
                echo 'Usage: npm run k8s:deploy [-- --runtime k3s|kubernetes] [--namespace name]' >&2
                return 2
                ;;
        esac
    done
}

resolve_k8s_namespace() {
    local namespace repo_root

    if [[ $# -gt 1 ]]; then
        echo "resolve_k8s_namespace accepts at most one namespace override." >&2
        return 2
    fi

    if [[ $# -eq 1 ]]; then
        namespace="$1"
    else
        repo_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)" || return
        if ! namespace="$(
            cd -- "$repo_root"
            docker compose config --no-interpolate --format json | node -e '
                const fs = require("node:fs");
                const name = JSON.parse(fs.readFileSync(0, "utf8")).name;
                if (typeof name !== "string" || name.length === 0) {
                    console.error("Docker Compose project name is missing.");
                    process.exit(1);
                }
                process.stdout.write(name);
            '
        )"; then
            echo "Cannot determine Kubernetes namespace from the Docker Compose project name." >&2
            return 1
        fi
    fi

    if [[ ${#namespace} -gt 63 || ! "$namespace" =~ ^[a-z0-9]([-a-z0-9]*[a-z0-9])?$ ]]; then
        echo "Invalid Kubernetes namespace: use 1-63 lowercase letters, digits or hyphens; start and end with a letter or digit." >&2
        return 2
    fi

    printf '%s\n' "$namespace"
}
