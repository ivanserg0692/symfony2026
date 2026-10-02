#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd -- "${script_dir}/.." && pwd)"
source "${script_dir}/lib/k8s-common.sh"
cd "$repo_root"

# Resolve the deployment target; local development defaults to a kind cluster.
parse_k8s_deploy_args k8s:deploy "$@"
if [[ -n "$namespace_override" ]]; then
    namespace="$(resolve_k8s_namespace "$namespace_override")"
else
    namespace="$(resolve_k8s_namespace)"
fi

node scripts/check-prerequisites.mjs --runtime "$runtime"

auth_image='symfony-auth:k8s'
catalog_image='symfony-catalog:k8s'
cart_image='symfony-cart:k8s'
gateway_image='symfony-api-gateway-nginx:k8s'
prometheus_image='symfony-prometheus:k8s'
grafana_image='symfony-grafana:k8s'

# Make application images available to the selected cluster: load local images
# into kind, or use registry references for an existing k3s/Kubernetes cluster.
case "$runtime" in
    local)
        ensure_k8s_cluster "$runtime" "$namespace"
        for image in "$auth_image" "$catalog_image" "$cart_image" "$gateway_image" "$prometheus_image" "$grafana_image"; do
            docker image inspect "$image" >/dev/null
            kind load docker-image --name "$namespace" "$image"
        done
        ;;
    k3s|kubernetes)
        registry="${K8S_IMAGE_REGISTRY:-}"
        tag="${K8S_IMAGE_TAG:-}"
        if [[ ! "$registry" =~ ^[a-zA-Z0-9._:/-]+$ || ! "$tag" =~ ^[a-zA-Z0-9._-]+$ ]]; then
            echo 'Set K8S_IMAGE_REGISTRY and K8S_IMAGE_TAG to pushed image references for this runtime.' >&2
            exit 2
        fi
        auth_image="${registry}/symfony-auth:${tag}"
        catalog_image="${registry}/symfony-catalog:${tag}"
        cart_image="${registry}/symfony-cart:${tag}"
        gateway_image="${registry}/symfony-api-gateway-nginx:${tag}"
        prometheus_image="${registry}/symfony-prometheus:${tag}"
        grafana_image="${registry}/symfony-grafana:${tag}"
        ensure_k8s_cluster "$runtime" "$namespace"
        ;;
    *)
        echo 'Unsupported Kubernetes runtime.' >&2
        exit 2
        ;;
esac

# Apply application manifests with image references for the selected runtime.
apply_images() {
    sed \
        -e "s|symfony-auth:k8s|$auth_image|g" \
        -e "s|symfony-catalog:k8s|$catalog_image|g" \
        -e "s|symfony-cart:k8s|$cart_image|g" \
        -e "s|symfony-api-gateway-nginx:k8s|$gateway_image|g" \
        "$1" | kubectl -n "$namespace" apply -f -
}

kubectl get namespace "$namespace" >/dev/null 2>&1 || kubectl create namespace "$namespace"
kubectl get namespace monitoring >/dev/null 2>&1 || kubectl create namespace monitoring

# Apply the previously synchronized, reviewable ConfigMaps. Secrets are synced
# separately and remain only in the cluster; fail before starting workloads if
# any required application, infrastructure or monitoring Secret is missing.
config_file="kubernetes/generated/$namespace/configmaps.yaml"
if [[ ! -f "$config_file" ]]; then
    echo "Missing $config_file. Run npm run k8s:config:sync -- $namespace first." >&2
    exit 1
fi
kubectl apply -f "$config_file"
for secret in auth-config-secret catalog-config-secret cart-config-secret infra-config-secret; do
    kubectl -n "$namespace" get secret "$secret" >/dev/null || {
        echo "Missing Secret/$secret. Run npm run k8s:secrets:sync -- $namespace first." >&2
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
    AUTH_JWT_BOOTSTRAP_IMAGE="$auth_image" bash docker/php-symfony-cli/k8s/bootstrap-auth-jwt.sh "$namespace"
fi

# Deploy persistent infrastructure and wait for each StatefulSet rollout:
#   PostgreSQL (Auth)     -> Ready
#   PostgreSQL (Catalog)  -> Ready
#   PostgreSQL (Cart)     -> Ready
#   Redis (Auth)          -> Ready
#   Redis (Catalog)       -> Ready
#   Redis (Cart)          -> Ready
#   Redis (metrics)       -> Ready
#   RabbitMQ              -> Ready
#   Elasticsearch         -> Ready
#   MinIO                 -> Ready
kubectl -n "$namespace" apply -f kubernetes/infra/stateful.yaml
for workload in database catalog-db cart-db redis-symfony redis-catalog redis-cart redis-metrics rabbitmq elasticsearch minio; do
    kubectl -n "$namespace" rollout status "statefulset/$workload" --timeout=10m
done

# Recreate the bootstrap Jobs on every deploy. A completed Job cannot run again
# when its manifest is reapplied, so remove the previous instances first.
for job in elasticsearch-setup minio-bucket-setup auth-migrate catalog-migrate cart-migrate; do
    kubectl -n "$namespace" delete job "$job" --ignore-not-found --wait=true
done

# Run setup and migrations, then wait for every Job to finish successfully:
#   Elasticsearch setup -> Complete (including Kibana credentials)
#   MinIO bucket setup  -> Complete
#   Auth migrations     -> Complete
#   Catalog migrations  -> Complete
#   Cart migrations     -> Complete
apply_images kubernetes/jobs/bootstrap.yaml
for job in elasticsearch-setup minio-bucket-setup auth-migrate catalog-migrate cart-migrate; do
    kubectl -n "$namespace" wait --for=condition=complete "job/$job" --timeout=10m
done

# Kibana depends on Elasticsearch setup; Mailpit provides the SMTP test service.
# Wait until both infrastructure Deployments have completed their rollouts:
#   Kibana  -> Ready
#   Mailpit -> Ready
for workload in kibana mailpit; do
    kubectl -n "$namespace" rollout status "deployment/$workload" --timeout=10m
done

# Start the application HTTP/gRPC services, background workers, gateway, and
# database exporters. Apply monitoring resources now; verify them after the
# initial Catalog reindex.
for manifest in kubernetes/app/workloads.yaml kubernetes/app/exporters.yaml kubernetes/app/gateway.yaml; do
    apply_images "$manifest"
done
sed \
    -e "s|symfony-prometheus:k8s|$prometheus_image|g" \
    -e "s|symfony-grafana:k8s|$grafana_image|g" \
    -e "s|__APP_NAMESPACE__|$namespace|g" \
    kubernetes/monitoring/workloads.yaml | kubectl apply -f -
if [[ "$runtime" == local ]]; then
    # Local images reuse the :k8s tag, so restart these Pods to read rebuilt config.
    kubectl -n monitoring rollout restart deployment/prometheus deployment/grafana
fi

# Wait for application traffic and background processing to be available:
#   Auth HTTP, Catalog HTTP, Cart HTTP -> Ready
#   Catalog gRPC -> Ready
#   Catalog outbox/index workers, Auth async worker -> Ready
#   API gateway -> Ready
#   PostgreSQL exporters (Auth, Catalog, Cart) -> Ready
for workload in symfony-web catalog-web cart-web catalog-grpc catalog-search-outbox-worker catalog-search-index-worker auth-async-worker api-gateway database-exporter catalog-db-exporter cart-db-exporter; do
    kubectl -n "$namespace" rollout status "deployment/$workload" --timeout=10m
done

# Build the initial Catalog search index once per namespace. The reindex script
# pauses the incremental index worker, waits for the full reindex Job, then
# restores the worker so it can drain queued updates. The ConfigMap records
# successful initialization and skips this step on later deploys.
if ! kubectl -n "$namespace" get configmap catalog-initial-index >/dev/null 2>&1; then
    bash scripts/k8s-catalog-reindex.sh run "$namespace"
    kubectl -n "$namespace" create configmap catalog-initial-index --from-literal=ready=true
fi

# Confirm the monitoring stack is ready after application initialization:
#   Prometheus              -> Ready
#   Grafana                 -> Ready
#   Grafana image renderer  -> Ready
#   kube-state-metrics      -> Ready
#   node-exporter DaemonSet -> Ready on eligible nodes
for workload in prometheus grafana grafana-image-renderer kube-state-metrics node-exporter; do
    kind=deployment
    if [[ "$workload" == node-exporter ]]; then kind=daemonset; fi
    kubectl -n monitoring rollout status "$kind/$workload" --timeout=10m
done

# All deployment stages completed; print local access commands.
echo "Kubernetes deployment is ready in namespace $namespace."
echo "Gateway: kubectl -n $namespace port-forward service/api-gateway 8001:80"
echo 'Grafana: kubectl -n monitoring port-forward service/grafana 3000:3000'
