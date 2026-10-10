#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd -- "${script_dir}/.." && pwd)"
source "${script_dir}/lib/k8s-common.sh"
cd "$repo_root"

reject_k8s_args k8s:cluster:init "$@"
ensure_k8s_settings

node scripts/check-prerequisites.mjs

if [[ "$runtime" == 'k3s' && -z "${KUBECONFIG:-}" && ! -f "$HOME/.kube/config" ]]; then
    k3s_kubeconfig='/etc/rancher/k3s/k3s.yaml'
    if [[ ! -f "$k3s_kubeconfig" ]]; then
        echo "Missing $k3s_kubeconfig. Make sure the k3s server is installed on this machine." >&2
        exit 1
    fi

    mkdir -p "$HOME/.kube"
    sudo cp "$k3s_kubeconfig" "$HOME/.kube/config"
    sudo chown "$(id -u):$(id -g)" "$HOME/.kube/config"
    chmod 600 "$HOME/.kube/config"
fi

ensure_k8s_cluster "$runtime" "$namespace"
echo "Kubernetes cluster is ready for namespace $namespace."
