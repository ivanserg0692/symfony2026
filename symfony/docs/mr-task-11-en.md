# MR Result Log Task 11

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Summary](#summary)
- [Planned Scope](#planned-scope)
- [Verification Plan](#verification-plan)
- [Out Of Scope](#out-of-scope)
- [Task](#task)
- [2026-10-04 — Kubernetes Monitoring Result](#2026-10-04--kubernetes-monitoring-result)
- [2026-11-01 — Load observations and HTTP logs](#2026-11-01--load-observations-and-http-logs)

<!-- END doctoc -->

## Summary

This document records the original scope of [PR #15](https://github.com/ivanserg0692/symfony2026/pull/15) for [Task 11](task-11.md) and subsequent monitoring results. The plan below reflects the start of the task; the task file records the observations and measurement limits.

The goal is to run the same project through either Docker Compose or Kubernetes and demonstrate independent horizontal scaling of Auth, Catalog, and Cart without depending on a fixed number of Kubernetes nodes.

## Planned Scope

- Prepare self-contained Auth, Catalog, and Cart images that run without source-code bind mounts while preserving the existing Compose workflow.
- Provide a one-time Auth JWT bootstrap that reuses an existing complete key pair or generates it with `bin/init-jwt`, using the current `JWT_PASSPHRASE`. All Auth Pods will mount the same keys and share required secrets and session state.
- Run Catalog HTTP, gRPC, and Messenger workers as independent roles without changing their application behavior unnecessarily.
- Add Kubernetes configuration and deployment resources separately from Docker Compose.
- Expose PHP-FPM metrics for each Pod. Scrape Symfony application metrics backed by shared Redis once per logical service.
- Add a separate Kubernetes Grafana dashboard using discovery labels for service and Pod views; keep the Compose dashboard unchanged.
- Verify independent manual scaling and HPA with existing k6 scenarios, including readiness, errors, throughput, latency, resource use, and Pod-count history.

## Verification Plan

- Run each service image without bind mounts and check each Catalog role.
- Check JWT bootstrap with and without existing keys; verify sessions and JWT requests across Auth replicas.
- Check per-Pod PHP-FPM series and ensure shared Symfony metrics are not multiplied by replica count.
- Compare one-Pod and multi-Pod k6 runs and inspect scaling in the Kubernetes dashboard.
- Regression-check the existing Docker Compose startup and dashboard.

## Out Of Scope

- Replacing the Docker Compose deployment path or its Grafana dashboard.
- VPA, Redis Cluster, and PostgreSQL HA.
- Claiming Kubernetes or HPA results before they have been implemented and measured.

## Task

Task file: [task-11.md](task-11.md).

## 2026-10-04 — Kubernetes Monitoring Result

The repository now contains three independent Kubernetes Grafana dashboards: [primary](../../docker/grafana/k8s/grafana-dashboard.json), [original dashboard port](../../docker/grafana/k8s/grafana-original-ported-dashboard.json), and [combined](../../docker/grafana/k8s/grafana-combined-dashboard.json). The combined view removes redundant panels while retaining the two source dashboards. The original Docker Compose dashboard remains unchanged.

Each Kubernetes dashboard supports one application service or All. PHP-FPM series identify Pods, while shared application metrics are scraped once per logical service. Added resource views include stacked Top 15 Pods by CPU across namespaces and memory usage as a percentage of limit by service, by Pod across namespaces, and by service/container. The memory ratios use summed usage over summed positive limits for the matching containers; containers without a positive limit are excluded. The CPU stack covers only the selected 15 Pods.

Dashboard captures and a reading guide are in the [root README](../../README.md#run-with-kubernetes); dashboard details are in the [Kubernetes monitoring guide](../../kubernetes/README.md#monitoring).

## 2026-11-01 — Load observations and HTTP logs

Task 11 implemented independent PHP-FPM queue HPAs for Auth, Catalog, and Cart through Prometheus Adapter. Current local Helm values target 10 queued requests per Pod, with 1–20 replicas and a 300-second scale-down stabilization window. The Grafana capture shows queues and ready HTTP Pod counts rising during two load intervals, then returning; it does not include HPA decisions or manual-action history, so the quantitative effect is unverified. Task 11 also implemented Gateway/application Nginx → Filebeat → separate Elasticsearch Logs → Kibana Logs for Kubernetes `prod`/`load-test`. The saved four-panel Kibana dashboard shows approximately **1,400 Gateway access records/s** in one interval, latency distribution, maximum latency by source, and individual 2xx/4xx/5xx frequencies. Filters and Discover expose individual errors and slow requests by time, service, status, path, and `request_id`. This is neither final k6 RPS nor an application maximum. See the [HPA and Kibana results](task-11.md#recorded-load-testing-and-monitoring-results); controlled comparative k6 runs and the cause of the RPS limit remain for Performance Bottleneck Investigation.
