#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd -- "${script_dir}/.." && pwd)"
source "${script_dir}/lib/k8s-common.sh"
cd "$repo_root"

parse_k8s_deploy_args k8s:cluster:init "$@"
if [[ -n "$namespace_override" ]]; then
    namespace="$(resolve_k8s_namespace "$namespace_override")"
else
    namespace="$(resolve_k8s_namespace)"
fi

node scripts/check-prerequisites.mjs --runtime "$runtime"
ensure_k8s_cluster "$runtime" "$namespace"
echo "Kubernetes cluster is ready for namespace $namespace."
