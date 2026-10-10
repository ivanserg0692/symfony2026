#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd -- "${script_dir}/.." && pwd)"
source "${script_dir}/lib/k8s-common.sh"

reject_k8s_args k8s:db:fixtures "$@"
ensure_k8s_settings

wait_for_deployment() {
    kubectl -n "$namespace" rollout status "deployment/$1" --timeout=10m
}

ensure_fixture_prerequisites() {
    local job deployment

    # Fixtures need migrated databases and running application Services. Cart
    # also reads Auth users, Catalog HTTP data, and Catalog gRPC data.
    for job in auth-migrate catalog-migrate cart-migrate; do
        kubectl -n "$namespace" wait --for=condition=complete "job/$job" --timeout=10m
    done
    for deployment in symfony-web catalog-web catalog-grpc cart-web; do
        wait_for_deployment "$deployment"
    done
}

run_fixture_job() {
    local service="$1" deployment="$2" image job manifest
    job="${service}-fixtures"
    manifest="${repo_root}/kubernetes/jobs/fixtures/${service}.yaml"

    # Use exactly the image serving this service, including its env profile.
    image="$(kubectl -n "$namespace" get deployment "$deployment" -o jsonpath='{.spec.template.spec.containers[?(@.name=="php")].image}')"
    if [[ ! "$image" =~ ^[a-zA-Z0-9._:/@-]+$ ]]; then
        echo "Cannot determine a valid PHP image from deployment/$deployment." >&2
        return 1
    fi

    # A completed Job cannot be rerun in place. A failed Job is kept for
    # inspection until the operator explicitly invokes this command again.
    kubectl -n "$namespace" delete job "$job" --ignore-not-found --wait=true
    sed "s|__${service^^}_IMAGE__|$image|" "$manifest" | kubectl -n "$namespace" apply -f -

    echo "==> Loading $service fixtures (job/$job)"
    if ! kubectl -n "$namespace" logs -f "job/$job" --pod-running-timeout=5m; then
        echo "Could not stream logs for job/$job. Checking the Job result." >&2
    fi
    kubectl -n "$namespace" wait --for=condition=complete "job/$job" --timeout=2h
}

ensure_fixture_prerequisites
run_fixture_job auth symfony-web
run_fixture_job catalog catalog-web
bash "${script_dir}/k8s-catalog-reindex.sh" run
run_fixture_job cart cart-web
