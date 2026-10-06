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
        check_local_images_available
        ensure_k8s_cluster "$runtime" "$namespace"
        for image in "$auth_image" "$catalog_image" "$cart_image" "$gateway_image" "$prometheus_image" "$grafana_image" "$minio_image" "$mc_image"; do
            kind load docker-image --name "$namespace" "$image"
        done
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
            check_local_images_available
            docker save "$auth_image" "$catalog_image" "$cart_image" "$gateway_image" "$prometheus_image" "$grafana_image" "$minio_image" "$mc_image" \
                | sudo k3s ctr images import -
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

# Render all project image references; each invocation is a complete YAML stream.
render_images() {
    sed \
        -e "s|__AUTH_IMAGE__|$auth_image|g" \
        -e "s|__CATALOG_IMAGE__|$catalog_image|g" \
        -e "s|__CART_IMAGE__|$cart_image|g" \
        -e "s|__API_GATEWAY_IMAGE__|$gateway_image|g" \
        -e "s|__MINIO_IMAGE__|$minio_image|g" \
        -e "s|__MC_IMAGE__|$mc_image|g" \
        -e "s|__PROMETHEUS_IMAGE__|$prometheus_image|g" \
        -e "s|__GRAFANA_IMAGE__|$grafana_image|g" \
        -e "s|__AUTH_POSTGRES_CAPACITY_CONFIGMAP__|$auth_capacity_configmap|g" \
        -e "s|__CATALOG_POSTGRES_CAPACITY_CONFIGMAP__|$catalog_capacity_configmap|g" \
        -e "s|__CART_POSTGRES_CAPACITY_CONFIGMAP__|$cart_capacity_configmap|g" \
        -e "s|__APP_NAMESPACE__|$namespace|g" \
        "$@"
}

apply_manifest_set() {
    local manifest_file
    manifest_file="$(mktemp)"
    for file in "$@"; do
        render_images "$file" >> "$manifest_file"
        printf '\n---\n' >> "$manifest_file"
    done
    if ! kubectl apply -f "$manifest_file"; then
        rm -f "$manifest_file"
        return 1
    fi
    rm -f "$manifest_file"
}

kubectl get namespace "$namespace" >/dev/null 2>&1 || kubectl create namespace "$namespace"
kubectl get namespace monitoring >/dev/null 2>&1 || kubectl create namespace monitoring

# Use the previously synchronized, reviewable ConfigMaps. Secrets are synced
# separately and remain only in the cluster; fail before starting workloads if
# any required application, infrastructure or monitoring Secret is missing.
config_file="kubernetes/generated/$namespace/configmaps.yaml"
if [[ ! -f "$config_file" ]]; then
    echo "Missing $config_file. Run npm run k8s:config:sync first." >&2
    exit 1
fi
capacity_configmap_name() {
    local service="$1" name
    name="$(sed -n "s/^  name: \"\(postgres-capacity-${service}-[1-9][0-9]*\)\"$/\1/p" "$config_file")"
    if [[ ! "$name" =~ ^postgres-capacity-${service}-[1-9][0-9]*$ ]]; then
        echo "Missing or invalid PostgreSQL capacity ConfigMap for $service in $config_file. Run npm run k8s:config:sync." >&2
        return 1
    fi
    printf '%s' "$name"
}
auth_capacity_configmap="$(capacity_configmap_name auth)"
catalog_capacity_configmap="$(capacity_configmap_name catalog)"
cart_capacity_configmap="$(capacity_configmap_name cart)"
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

# Recreate the bootstrap Jobs on every deploy. A completed Job cannot run again
# when its manifest is reapplied, so remove the previous instances first.
for job in elasticsearch-setup minio-bucket-setup auth-migrate catalog-migrate cart-migrate; do
    kubectl -n "$namespace" delete job "$job" --ignore-not-found --wait=true
done

# Submit the complete manifest set once. Migration completion is checked by
# the dependent Pods; unrelated Pods can start while those Jobs are running.
apply_manifest_set "$config_file" kubernetes/infra/stateful.yaml \
    kubernetes/jobs/bootstrap.yaml kubernetes/app/workloads.yaml \
    kubernetes/app/exporters.yaml kubernetes/app/gateway.yaml \
    kubernetes/monitoring/workloads.yaml

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

# The apply command reports submission, not rollout success. Run the separate
# validator to check the current Pod template and the one-time Jobs.
echo "Kubernetes manifests submitted in namespace $namespace."
echo 'Validate current images, Jobs, and rollouts with: npm run k8s:verify'
echo "Gateway LoadBalancer: kubectl -n $namespace get service api-gateway"
echo 'Local tools: npm run k8s:forward (add -- --gateway for a local gateway forward)'
