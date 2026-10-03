#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd -- "${script_dir}/.." && pwd)"
source "${script_dir}/lib/k8s-common.sh"
cd "$repo_root"

reject_k8s_args k8s:cluster:init "$@"
ensure_k8s_settings

node scripts/check-prerequisites.mjs
ensure_k8s_cluster "$runtime" "$namespace"
echo "Kubernetes cluster is ready for namespace $namespace."
