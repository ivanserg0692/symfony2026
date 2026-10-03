#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd -- "${script_dir}/.." && pwd)"
source "${script_dir}/lib/k8s-common.sh"
action="${1:-run}"
if [[ "$action" == run || "$action" == recover ]]; then
    shift || true
else
    echo 'Usage: npm run k8s:catalog:reindex -- [run|recover]' >&2
    exit 2
fi
reject_k8s_args k8s:catalog:reindex "$@"
ensure_k8s_settings
lock=catalog-full-reindex-lock
worker=catalog-search-index-worker
job=catalog-full-reindex

recover() {
    local replicas
    # Read the worker replica count saved before reindex started. The lock is
    # the source of truth for manual recovery after an interrupted run.
    replicas="$(kubectl -n "$namespace" get configmap "$lock" -o jsonpath='{.data.replicas}')"
    if [[ ! "$replicas" =~ ^[0-9]+$ ]]; then
        echo 'Reindex lock does not contain a valid worker replica count.' >&2
        return 1
    fi
    # Stop any remaining full-reindex Job before restarting incremental indexing.
    kubectl -n "$namespace" delete job "$job" --ignore-not-found --wait=true
    # Restore the saved number of incremental indexing worker replicas.
    kubectl -n "$namespace" scale deployment "$worker" --replicas="$replicas"
    # Release the reindex lock so another full reindex can start.
    kubectl -n "$namespace" delete configmap "$lock"
    echo "Restored $worker to $replicas replica(s)."
}

if [[ "$action" == recover ]]; then
    recover
    exit
fi

# Save the current desired worker replica count for restoration after reindex.
replicas="$(kubectl -n "$namespace" get deployment "$worker" -o jsonpath='{.spec.replicas}')"
if [[ ! "$replicas" =~ ^[0-9]+$ ]]; then
    echo 'Cannot determine the index worker replica count.' >&2
    exit 1
fi

# Atomically create a ConfigMap lock in the API server. Only one reindex can
# create it, and its data records the worker replica count to restore later.
if ! kubectl -n "$namespace" create configmap "$lock" --from-literal="replicas=$replicas"; then
    echo 'A reindex lock already exists. Inspect it before using recover.' >&2
    exit 1
fi

cleanup() {
    local status=$?
    trap - EXIT INT TERM
    if [[ $status -ne 0 ]]; then
        # On failure or interruption, stop the full-reindex Job before workers resume.
        kubectl -n "$namespace" delete job "$job" --ignore-not-found --wait=true || true
    fi
    # Resume incremental indexing so workers can drain queued RabbitMQ messages.
    kubectl -n "$namespace" scale deployment "$worker" --replicas="$replicas" || true
    # Keep the lock until Kubernetes confirms the restored worker Deployment
    # has rolled out; otherwise a new reindex could start during recovery.
    if kubectl -n "$namespace" rollout status deployment "$worker" --timeout=5m; then
        # The worker is running again, so another full reindex may acquire the lock.
        kubectl -n "$namespace" delete configmap "$lock" || true
    else
        echo "Worker recovery failed; lock $lock remains for manual recovery." >&2
        exit 1
    fi
    exit "$status"
}
trap cleanup EXIT INT TERM

# Stop incremental indexing before the full reindex changes the search index.
kubectl -n "$namespace" scale deployment "$worker" --replicas=0
# Wait until every worker Pod has disappeared, ensuring no worker still writes
# to Elasticsearch while the full-reindex Job runs.
until [[ -z "$(kubectl -n "$namespace" get pod -l "app=$worker" -o name)" ]]; do
    sleep 2
done

# Use the PHP image currently deployed for Catalog so the Job runs matching code.
image="$(kubectl -n "$namespace" get deployment catalog-web -o jsonpath='{.spec.template.spec.containers[?(@.name=="php")].image}')"
if [[ ! "$image" =~ ^[a-zA-Z0-9._:/@-]+$ ]]; then
    echo 'Catalog image reference is missing or invalid.' >&2
    exit 1
fi
# Remove an earlier completed Job: Kubernetes Jobs cannot be rerun in place.
kubectl -n "$namespace" delete job "$job" --ignore-not-found --wait=true
# Insert the deployed Catalog image into the Job manifest and create the Job.
sed "s|symfony-catalog:k8s|$image|" "${repo_root}/kubernetes/jobs/catalog-reindex.yaml" | kubectl -n "$namespace" apply -f -
# Stream the Symfony reindex progress from the Job Pod to this terminal. A log
# connection failure does not determine the Job result; check it separately.
if ! kubectl -n "$namespace" logs -f "job/$job" --pod-running-timeout=5m; then
    echo "Could not stream logs for job/$job. Checking the Job result." >&2
fi
# Wait for the full-reindex Job to complete before restoring the workers.
kubectl -n "$namespace" wait --for=condition=complete "job/$job" --timeout=2h
echo 'Full reindex completed. Restoring the index worker to drain the RabbitMQ backlog.'
