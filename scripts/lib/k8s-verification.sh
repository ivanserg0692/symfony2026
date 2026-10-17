#!/usr/bin/env bash

# Callers may override this to pin a kubectl context without changing the
# process-wide current context.
verification_kubectl() { kubectl "$@"; }

verify_job_image() {
    local target_namespace="$1" job="$2" expected_image="$3" actual_image
    actual_image="$(verification_kubectl -n "$target_namespace" get "job/$job" -o jsonpath='{.spec.template.spec.containers[0].image}')" || return
    if [[ "$actual_image" != "$expected_image" ]]; then
        echo "Job/$job in $target_namespace has image $actual_image, expected $expected_image." >&2
        return 1
    fi
}

verify_job_completion() {
    local target_namespace="$1" job="$2" expected_image="${3:-}" timeout="${4:-10m}"
    if [[ -n "$expected_image" ]]; then
        verify_job_image "$target_namespace" "$job" "$expected_image" || return
    fi
    verification_kubectl -n "$target_namespace" wait --for=condition=complete "job/$job" --timeout="$timeout"
}

verify_rollout() {
    local target_namespace="$1" resource_kind="$2" workload="$3" expected_image="${4:-}" timeout="${5:-10m}"
    local generation observed desired updated ready available current_revision update_revision status actual_images
    generation="$(verification_kubectl -n "$target_namespace" get "$resource_kind/$workload" -o jsonpath='{.metadata.generation}')" || return
    verification_kubectl -n "$target_namespace" wait --for="jsonpath={.status.observedGeneration}=$generation" \
        "$resource_kind/$workload" --timeout="$timeout" || return
    verification_kubectl -n "$target_namespace" rollout status "$resource_kind/$workload" --timeout="$timeout" || return
    if [[ "$resource_kind" == deployment ]]; then
        status="$(verification_kubectl -n "$target_namespace" get "$resource_kind/$workload" -o jsonpath='{.metadata.generation} {.status.observedGeneration} {.spec.replicas} {.status.updatedReplicas} {.status.readyReplicas} {.status.availableReplicas}')" || return
        read -r generation observed desired updated ready available <<< "$status"
        if [[ "$generation" != "$observed" || "$desired" != "$updated" || "$desired" != "$ready" || "$desired" != "$available" ]]; then
            echo "Current Pod template of $resource_kind/$workload in $target_namespace has not fully converged: $status" >&2
            return 1
        fi
    elif [[ "$resource_kind" == statefulset ]]; then
        status="$(verification_kubectl -n "$target_namespace" get "$resource_kind/$workload" -o jsonpath='{.metadata.generation} {.status.observedGeneration} {.spec.replicas} {.status.updatedReplicas} {.status.readyReplicas} {.status.currentRevision} {.status.updateRevision}')" || return
        read -r generation observed desired updated ready current_revision update_revision <<< "$status"
        if [[ "$generation" != "$observed" || "$desired" != "$updated" || "$desired" != "$ready" || "$current_revision" != "$update_revision" ]]; then
            echo "Current Pod template of $resource_kind/$workload in $target_namespace has not fully converged: $status" >&2
            return 1
        fi
    elif [[ "$resource_kind" == daemonset ]]; then
        status="$(verification_kubectl -n "$target_namespace" get "$resource_kind/$workload" -o jsonpath='{.metadata.generation} {.status.observedGeneration} {.status.desiredNumberScheduled} {.status.updatedNumberScheduled} {.status.numberReady} {.status.numberAvailable}')" || return
        read -r generation observed desired updated ready available <<< "$status"
        if [[ "$generation" != "$observed" || "$desired" != "$updated" || "$desired" != "$ready" || "$desired" != "$available" ]]; then
            echo "Current Pod template of $resource_kind/$workload in $target_namespace has not fully converged: $status" >&2
            return 1
        fi
    fi
    if [[ -n "$expected_image" ]]; then
        actual_images="$(verification_kubectl -n "$target_namespace" get "$resource_kind/$workload" -o jsonpath='{.spec.template.spec.containers[*].image}')" || return
        if [[ " $actual_images " != *" $expected_image "* ]]; then
            echo "$resource_kind/$workload in $target_namespace has images $actual_images, expected $expected_image." >&2
            return 1
        fi
    fi
}
