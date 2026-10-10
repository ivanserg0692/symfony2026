#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd -- "${script_dir}/.." && pwd)"
source "${script_dir}/lib/k8s-common.sh"
source "${script_dir}/lib/k8s-verification.sh"
cd "$repo_root"

# Resolve the deployment target from the selected environment.
reject_k8s_args k8s:deploy "$@"
ensure_k8s_settings
skip_image_load="${K8S_SKIP_IMAGE_LOAD:-0}"
if [[ "$skip_image_load" != 0 && "$skip_image_load" != 1 ]]; then
    echo 'K8S_SKIP_IMAGE_LOAD must be 0 or 1.' >&2
    exit 2
fi

node scripts/check-prerequisites.mjs

check_local_images_available() {
    local reference
    for reference in "$auth_image" "$catalog_image" "$cart_image" "$gateway_image" "$prometheus_image" "$grafana_image" "$minio_image" "$mc_image"; do
        docker image inspect "$reference" >/dev/null
    done
}

# Make application images available to the selected cluster: load local images
# into kind/k3s, or use registry references for k3s/another Kubernetes cluster.
case "$runtime" in
    local)
        load_local_image_refs true
        ensure_k8s_cluster "$runtime" "$namespace"
        if [[ "$skip_image_load" == 0 ]]; then
            check_local_images_available
            for image in "$auth_image" "$catalog_image" "$cart_image" "$gateway_image" "$prometheus_image" "$grafana_image" "$minio_image" "$mc_image"; do
                kind load docker-image --name "$namespace" "$image"
            done
        fi
        ;;
    k3s)
        registry="${K8S_IMAGE_REGISTRY:-}"
        tag="${K8S_IMAGE_TAG:-}"
        ensure_k8s_cluster "$runtime" "$namespace"
        if [[ -n "$registry" || -n "$tag" ]]; then
            resolve_registry_images "$registry" "$tag" \
                'Set both K8S_IMAGE_REGISTRY and K8S_IMAGE_TAG, or leave both unset to import local images into k3s.'
        else
            load_local_image_refs true
            if [[ "$skip_image_load" == 0 ]]; then
                check_local_images_available
                docker save "$auth_image" "$catalog_image" "$cart_image" "$gateway_image" "$prometheus_image" "$grafana_image" "$minio_image" "$mc_image" \
                    | sudo k3s ctr images import -
            fi
        fi
        ;;
    kubernetes)
        registry="${K8S_IMAGE_REGISTRY:-}"
        tag="${K8S_IMAGE_TAG:-}"
        resolve_registry_images "$registry" "$tag" \
            'Set K8S_IMAGE_REGISTRY and K8S_IMAGE_TAG to pushed image references for this runtime.'
        ensure_k8s_cluster "$runtime" "$namespace"
        ;;
    *)
        echo 'Unsupported Kubernetes runtime.' >&2
        exit 2
        ;;
esac

kubectl get namespace "$namespace" >/dev/null 2>&1 || kubectl create namespace "$namespace"
kubectl get namespace monitoring >/dev/null 2>&1 || kubectl create namespace monitoring

# Use the previously synchronized, reviewable Helm values. Secrets are synced
# separately and remain only in the cluster; fail before starting workloads if
# any required application, infrastructure or monitoring Secret is missing.
config_file="kubernetes/generated/$namespace/generated-values.yaml"
if [[ ! -f "$config_file" ]]; then
    echo "Missing $config_file. Run npm run k8s:config:sync first." >&2
    exit 1
fi
for secret in auth-config-secret catalog-config-secret cart-config-secret infra-config-secret; do
    kubectl -n "$namespace" get secret "$secret" >/dev/null || {
        echo "Missing Secret/$secret. Run npm run k8s:secrets:sync first." >&2
        exit 1
    }
done
kubectl -n monitoring get secret grafana-config-secret >/dev/null || {
    echo 'Missing Secret/grafana-config-secret. Run npm run k8s:secrets:sync first.' >&2
    exit 1
}

# All Auth replicas must mount the same JWT key pair. Bootstrap the Secret only
# when it does not already exist; later Pod creation must reuse that pair.
if ! kubectl -n "$namespace" get secret auth-jwt >/dev/null 2>&1; then
    AUTH_JWT_BOOTSTRAP_IMAGE="$auth_image" bash docker/php-symfony-cli/k8s/bootstrap-auth-jwt.sh
fi

# Helm creates ConfigMaps from the generated values with the other chart resources.
# Bootstrap Jobs are post-install/upgrade hooks and replace their completed
# predecessors before each release, including the first adoption from kubectl.
helm upgrade --install symfony2026 kubernetes/helm/symfony2026 \
    --namespace "$namespace" --create-namespace --take-ownership --timeout 15m \
    -f "$config_file" \
    --set-string "images.auth=$auth_image" \
    --set-string "images.catalog=$catalog_image" \
    --set-string "images.cart=$cart_image" \
    --set-string "images.gateway=$gateway_image" \
    --set-string "images.minio=$minio_image" \
    --set-string "images.mc=$mc_image" \
    --set-string "images.prometheus=$prometheus_image" \
    --set-string "images.grafana=$grafana_image"

# Build the initial Catalog search index once per namespace. The reindex script
# pauses the incremental index worker, waits for its Job, then restores the
# worker. This release-wide operation must not run in every Pod initContainer.
if ! kubectl -n "$namespace" get configmap catalog-initial-index >/dev/null 2>&1; then
    # The reindex Job uses the Catalog release image and runs after its schema
    # migration. Do not use an old Catalog template as the image source.
    verify_job_completion "$namespace" catalog-migrate "$catalog_image"
    verify_rollout "$namespace" deployment catalog-web "$catalog_image"
    bash scripts/k8s-catalog-reindex.sh run
    kubectl -n "$namespace" create configmap catalog-initial-index --from-literal=ready=true
fi

# Helm waits for the bootstrap hooks; run the separate validator to check
# current workload rollouts and image references.
if [[ "$skip_image_load" == 1 ]]; then
    echo "Helm release symfony2026 deployed in namespace $namespace without loading images."
    echo 'Ensure every selected image reference is already available to the cluster nodes.'
else
    echo "Helm release symfony2026 deployed in namespace $namespace."
fi
echo 'Validate current images, Jobs, and rollouts with: npm run k8s:verify'
echo "Gateway LoadBalancer: kubectl -n $namespace get service api-gateway"
echo 'Local tools: npm run k8s:forward (add -- --gateway for a local gateway forward)'
