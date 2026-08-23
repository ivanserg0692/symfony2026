# MR Result Log Task 9

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Summary](#summary)
- [Scope](#scope)
- [PHP Environment](#php-environment)
- [Performance Validation](#performance-validation)
- [Verification Plan](#verification-plan)
- [Out Of Scope](#out-of-scope)
- [2026-08-23 — Multi-stage Build Documentation](#2026-08-23--multi-stage-build-documentation)

<!-- END doctoc -->

## Summary

This document describes the planned merge request result for Task 9.

Merge request: [PR #13](https://github.com/ivanserg0692/symfony2026/pull/13)

Task file: [task-9.md](task-9.md)

The planned merge request establishes a reproducible performance baseline, optimizes the PHP production environment, removes confirmed application and infrastructure bottlenecks, and verifies the resulting RPS improvement with repeatable load tests.

The work must preserve application behavior, public API contracts, Composer dependencies, and the existing PHP extension set. Performance improvements are accepted only when supported by comparable before-and-after measurements without unacceptable latency or error-rate regressions.

## Scope

Planned:

- establish a repeatable k6 baseline with RPS, p50, p95, p99, error rate, CPU, RAM, and dependency metrics;
- correlate load-test results with Prometheus and Grafana metrics;
- identify bottlenecks before applying application, database, cache, gateway, or runtime optimizations;
- tune PHP-FPM, OPcache, Nginx, Symfony production mode, Doctrine, PostgreSQL, Redis, and container resources where measurements justify a change;
- compare maximum stable RPS and production image size before and after optimization;
- preserve existing Docker Compose `prod` and `dev` targets.

## PHP Environment

The PHP image is planned as a multi-stage build with separate builder and runtime responsibilities:

- build toolchains, headers, and `*-dev` packages remain in builder stages;
- production contains PHP-FPM, OPcache, required PHP extensions, and only their runtime shared libraries;
- development additionally contains Composer, Symfony CLI, and Xdebug;
- Xdebug, Composer, and Symfony CLI are excluded from production;
- compiled extensions are copied only between stages based on the same PHP image family, ABI, architecture, and compatible system libraries.

The existing `intl`, `mbstring`, `pdo_pgsql`, `sockets`, `amqp`, `grpc`, `redis`, `xsl`, and `zip` extensions remain in scope and must not be removed.

## Performance Validation

The result must be evaluated against a confirmed baseline under the same hardware, dataset, container configuration, warm-up procedure, and load profile.

Validation includes:

- repeated baseline and post-optimization k6 runs;
- maximum stable RPS and saturation-point comparison;
- p50, p95, and p99 latency comparison;
- HTTP/gRPC error-rate comparison;
- CPU, RAM, PHP-FPM worker, PostgreSQL, Redis, gateway, and service-level metric comparison;
- a recorded relationship between each confirmed bottleneck, the applied change, and its measured effect.

The preliminary Task 8 result of approximately 80 RPS is only a reference point and must be remeasured before use as the optimization baseline.

## Verification Plan

The implementation should be verified by checking that:

- production and development Docker targets build successfully;
- Docker Compose configuration remains valid for both environments;
- required PHP extensions are loaded in production and development;
- OPcache is installed and enabled in production;
- Xdebug is absent from production and present in development;
- Composer and Symfony CLI are absent from production and present in development;
- `ldd` reports no missing shared libraries for critical PHP extensions;
- compilers, PHP headers, build toolchains, and unnecessary `*-dev` packages are absent from production;
- Symfony smoke tests and existing automated tests pass;
- repeated load tests demonstrate the measured result without a functionality regression.

## Out Of Scope

- changing Composer dependencies without separate approval;
- removing existing PHP extensions;
- changing business logic or public API contracts;
- treating an unconfirmed absolute RPS value as the only success criterion;
- applying speculative optimizations unsupported by profiling or monitoring data.

## 2026-08-23 — Multi-stage Build Documentation

- Added dedicated bilingual documentation for the PHP multi-stage image build.
- Documented every builder and runtime stage, extension artifacts, system package boundaries, ABI requirements, Compose targets, layer-cache behavior, and verification commands.
- Added a localized PlantUML stage graph and linked the build documentation from the main README.
- Build documentation: [php-multi-stage-build.md](php-multi-stage-build.md)
