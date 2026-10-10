#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
source "${script_dir}/lib/k8s-common.sh"

if [[ $# -gt 1 || ( $# -eq 1 && "$1" != --gateway ) ]]; then
    echo 'Usage: npm run k8s:forward [-- --gateway]' >&2
    exit 2
fi

ensure_k8s_settings

if ! command -v kubectl >/dev/null 2>&1; then
    echo 'kubectl is required for port-forwarding.' >&2
    exit 1
fi

context="$(kubectl config current-context)" || {
    echo 'No current kubectl context.' >&2
    exit 1
}
if [[ "$runtime" == local && "$context" != "kind-$namespace" ]]; then
    echo "Current context is $context; expected kind-$namespace for the selected local environment." >&2
    exit 1
fi
if ! kubectl cluster-info >/dev/null 2>&1; then
    echo "Cannot reach Kubernetes context $context." >&2
    exit 1
fi

for required_namespace in "$namespace" monitoring; do
    if ! kubectl get namespace "$required_namespace" >/dev/null 2>&1; then
        echo "Namespace $required_namespace is missing or inaccessible in context $context." >&2
        exit 1
    fi
done

labels=(Grafana Prometheus Kibana Mailpit RabbitMQ MinIO)
namespaces=(monitoring monitoring "$namespace" "$namespace" "$namespace" "$namespace")
services=(grafana prometheus kibana mailpit rabbitmq minio)
local_ports=(3000 9090 5601 8025 15672 9001)
service_ports=(3000 9090 5601 8025 15672 9001)
forward_addresses=(127.0.0.1 127.0.0.1 127.0.0.1 127.0.0.1 127.0.0.1 127.0.0.1)

if [[ ${1:-} == --gateway ]]; then
    gateway_forward_address="${K8S_GATEWAY_FORWARD_ADDRESS:-127.0.0.1}"
    case "$gateway_forward_address" in
        127.0.0.1|0.0.0.0) ;;
        *)
            echo 'K8S_GATEWAY_FORWARD_ADDRESS must be 127.0.0.1 or 0.0.0.0.' >&2
            exit 2
            ;;
    esac
    labels+=(Gateway)
    namespaces+=("$namespace")
    services+=(api-gateway)
    local_ports+=(8001)
    service_ports+=(8001)
    forward_addresses+=("$gateway_forward_address")

    # Keep a stable WSL loopback port for the Windows portproxy. WSL's
    # localhost forwarding exposes this port to Windows without a WSL IP.
    labels+=(Gateway-Windows-proxy)
    namespaces+=("$namespace")
    services+=(api-gateway)
    local_ports+=(18001)
    service_ports+=(8001)
    forward_addresses+=(127.0.0.1)
fi

# Check every required Service and Service port before opening any local port.
for i in "${!services[@]}"; do
    if ! ports="$(kubectl -n "${namespaces[i]}" get service "${services[i]}" -o jsonpath='{range .spec.ports[*]}{.port}{" "}{end}' 2>/dev/null)"; then
        echo "Service/${services[i]} is missing or inaccessible in namespace ${namespaces[i]}." >&2
        exit 1
    fi
    if [[ " $ports " != *" ${service_ports[i]} "* ]]; then
        echo "Service/${services[i]} in namespace ${namespaces[i]} has no port ${service_ports[i]}." >&2
        exit 1
    fi
done

pids=()
log_dir="$(mktemp -d)"

cleanup() {
    trap - EXIT INT TERM
    for pid in "${pids[@]}"; do
        kill "$pid" 2>/dev/null || true
    done
    for pid in "${pids[@]}"; do
        wait "$pid" 2>/dev/null || true
    done
    rm -rf -- "$log_dir"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

echo "Kubernetes context: $context; application namespace: $namespace"
for i in "${!services[@]}"; do
    log_file="$log_dir/$i.log"
    kubectl -n "${namespaces[i]}" port-forward --address "${forward_addresses[i]}" \
        "service/${services[i]}" "${local_ports[i]}:${service_ports[i]}" >"$log_file" 2>&1 &
    pids+=("$!")

    ready=false
    for ((attempt = 0; attempt < 50; attempt++)); do
        if grep -Fq "Forwarding from ${forward_addresses[i]}:" "$log_file" && kill -0 "${pids[i]}" 2>/dev/null; then
            ready=true
            break
        fi
        if ! kill -0 "${pids[i]}" 2>/dev/null; then
            break
        fi
        sleep 0.1
    done
    if [[ "$ready" != true ]]; then
        echo "Failed to start ${labels[i]} forward (${namespaces[i]}/service/${services[i]} ${local_ports[i]}:${service_ports[i]})." >&2
        cat "$log_file" >&2
        exit 1
    fi
    printf '%-12s -> http://localhost:%s\n' "${labels[i]}" "${local_ports[i]}"
    if [[ "${forward_addresses[i]}" == 0.0.0.0 ]]; then
        echo 'Gateway also listens on all WSL interfaces.'
    fi
done

echo 'Press Ctrl+C to stop these forwards.'
if wait -n "${pids[@]}"; then
    echo 'A port-forward stopped unexpectedly; stopping the remaining forwards.' >&2
else
    echo 'A port-forward failed; stopping the remaining forwards.' >&2
fi
exit 1
