#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 0 ]]; then
    echo 'Usage: npm run k8s:grafana:password' >&2
    exit 2
fi

if ! command -v kubectl >/dev/null 2>&1; then
    echo 'kubectl is required to read the Grafana password.' >&2
    exit 1
fi

encoded_password="$(kubectl -n monitoring get secret grafana-config-secret -o jsonpath='{.data.admin-password}')"
if [[ -z "$encoded_password" ]]; then
    echo 'Secret/grafana-config-secret has no admin-password in namespace monitoring.' >&2
    exit 1
fi

printf '%s' "$encoded_password" | base64 --decode
printf '\n'
