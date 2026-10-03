# MR Result Log Task 11

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Summary](#summary)
- [Planned Scope](#planned-scope)
- [Verification Plan](#verification-plan)
- [Out Of Scope](#out-of-scope)
- [Task](#task)

<!-- END doctoc -->

## Summary

This document records the planned scope of [PR #15](https://github.com/ivanserg0692/symfony2026/pull/15) for [Task 11](task-11.md). At this point, the repository contains the Task 11 description; Kubernetes deployment and scaling have not been verified or recorded as completed.

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
