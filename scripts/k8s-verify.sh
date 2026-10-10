#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd -- "${script_dir}/.." && pwd)"
source "${script_dir}/lib/k8s-common.sh"
source "${script_dir}/lib/k8s-verification.sh"
cd "$repo_root"

reject_k8s_args k8s:verify "$@"
ensure_k8s_settings

# Verification must never create a cluster or change the selected kubectl
# context. Local deployments have a predictable kind context name.
kubectl_args=()
if [[ "$runtime" == local ]]; then
    kubectl_args=(--context "kind-$namespace")
fi
verification_kubectl() { kubectl "${kubectl_args[@]}" "$@"; }

# Resolve the image references requested by the most recent build or by the
# explicit registry release. A ready Pod from an older release is insufficient.
if [[ "$runtime" == kubernetes || -n "${K8S_IMAGE_REGISTRY:-}${K8S_IMAGE_TAG:-}" ]]; then
    registry="${K8S_IMAGE_REGISTRY:-}"
    tag="${K8S_IMAGE_TAG:-}"
    resolve_registry_images "$registry" "$tag" \
        'K8S_IMAGE_REGISTRY and K8S_IMAGE_TAG must both be set for registry verification.'
else
    load_local_image_refs
fi

verify_job_completion "$namespace" elasticsearch-setup
verify_job_completion "$namespace" minio-bucket-setup "$mc_image"
verify_job_completion "$namespace" auth-migrate "$auth_image"
verify_job_completion "$namespace" catalog-migrate "$catalog_image"
verify_job_completion "$namespace" cart-migrate "$cart_image"

# Verify each application Deployment's current generation and rollout in the
# project namespace. Compare Auth, Catalog, Cart, and API Gateway Pod template
# images with their expected release images; skip that comparison for exporters.
for workload in symfony-web catalog-web cart-web catalog-grpc catalog-search-outbox-worker catalog-search-index-worker auth-async-worker api-gateway database-exporter catalog-db-exporter cart-db-exporter; do
    expected_image=''
    # Assign the owning service's expected image. Exporters have no assignment,
    # so verify_rollout checks their generation and rollout without comparing images.
    case "$workload" in
        symfony-web|auth-async-worker) expected_image="$auth_image" ;;
        catalog-web|catalog-grpc|catalog-search-outbox-worker|catalog-search-index-worker) expected_image="$catalog_image" ;;
        cart-web) expected_image="$cart_image" ;;
        api-gateway) expected_image="$gateway_image" ;;
    esac
    verify_rollout "$namespace" deployment "$workload" "$expected_image"
done
# Verify each monitoring workload's current generation and rollout. Compare
# Prometheus and Grafana Pod template images with their expected release images;
# skip image comparison for the other workloads. node-exporter is a DaemonSet.
for workload in prometheus grafana grafana-image-renderer kube-state-metrics node-exporter; do
    kind=deployment
    if [[ "$workload" == node-exporter ]]; then kind=daemonset; fi
    expected_image=''
    case "$workload" in
        prometheus) expected_image="$prometheus_image" ;;
        grafana) expected_image="$grafana_image" ;;
    esac
    verify_rollout monitoring "$kind" "$workload" "$expected_image"
done
# Verify each infrastructure StatefulSet's current generation and rollout.
# Compare MinIO's Pod template image with its expected release image; skip image
# comparison for the other StatefulSets because they do not use project images.
for workload in database catalog-db cart-db redis-symfony redis-catalog redis-cart redis-metrics rabbitmq elasticsearch minio; do
    expected_image=''
    if [[ "$workload" == minio ]]; then expected_image="$minio_image"; fi
    verify_rollout "$namespace" statefulset "$workload" "$expected_image"
done
# Verify that Kibana and Mailpit complete their current Deployment rollouts.
# This script does not compare their images with project release images.
for workload in kibana mailpit; do
    verify_rollout "$namespace" deployment "$workload"
done

echo "Current Jobs and workload Pod templates are ready in namespace $namespace."
