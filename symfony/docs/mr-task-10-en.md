# MR Result Log Task 10

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Summary](#summary)
- [Scope](#scope)
- [Architecture Notes](#architecture-notes)
- [Filtering And Presets](#filtering-and-presets)
- [Performance Validation](#performance-validation)
- [Verification Plan](#verification-plan)
- [Out Of Scope](#out-of-scope)
- [2026-09-24 — Performance Baseline and PHP-FPM Monitoring](#2026-09-24--performance-baseline-and-php-fpm-monitoring)
- [2026-09-24 — Persistent Connections and Latency Estimates](#2026-09-24--persistent-connections-and-latency-estimates)
- [2026-09-24 — Elasticsearch Delivery and Measured Results](#2026-09-24--elasticsearch-delivery-and-measured-results)

<!-- END doctoc -->

## Summary

This document describes the planned merge request result for Task 10.

Merge request: [PR #14](https://github.com/ivanserg0692/symfony2026/pull/14)

Task file: [task-10.md](task-10.md)

The planned merge request introduces an Elasticsearch-backed catalog read model for full-text search, heavy filtering, exact `COUNT`, facets, and aggregations over a catalog of approximately 1 million products. The work continues the performance investigation completed in Task 9, where these PostgreSQL operations were identified as the main remaining catalog bottleneck.

PostgreSQL remains the sole source of truth and write store. Elasticsearch contains a versioned, denormalized, eventually consistent projection optimized for catalog reads and derived search structures; it does not replace transactional storage or become a universal application cache.

## Scope

Planned:

- add Elasticsearch as a dedicated read infrastructure component for Catalog Service;
- define versioned index mappings, settings, analyzers, normalizers, aliases, and document schemas;
- implement idempotent initial indexing for approximately 1 million products with progress tracking and safe restart;
- implement reliable incremental synchronization for product creates, updates, and deletes;
- support retry, failed-event handling, replay, synchronization-lag monitoring, and recovery procedures;
- move full-text search, filtering, sorting, pagination, exact result counts, facets, and required aggregations to the read model;
- provide safe full reindexing into a new index version with atomic alias switching and rollback;
- compare Elasticsearch performance with a reproducible PostgreSQL baseline on the same data, hardware, and load profile;
- document indexing, reindexing, diagnostics, rollback, and failure-recovery procedures.

## Architecture Notes

- PostgreSQL stores canonical product, relation, and transactional data.
- Elasticsearch stores a denormalized projection built for explicitly supported catalog read scenarios.
- Catalog Service owns the search contract and does not expose Elasticsearch DSL to API clients.
- Product writes must not depend on synchronous dual writes to PostgreSQL and Elasticsearch.
- Changes are delivered asynchronously through a reliable event pipeline; a transactional outbox is the preferred direction and must be confirmed during integration design.
- Index mappings, document formats, and integration events are versioned.
- Eventual consistency is allowed only within a defined, measurable, and monitored window.
- Elasticsearch must not be used for transactional stock, order-price, or other strongly consistent checks.

## Filtering And Presets

- Define an allowlist of public filter fields, operators, sorts, and limits.
- Support exact matches, multi-select filters, ranges, value-existence checks, and safe combinations of allowed conditions.
- Keep Elasticsearch field names and query DSL internal to the infrastructure adapter.
- Add versioned system-defined named presets for common combinations of filters, sorting, and search parameters.
- Define deterministic precedence rules between a preset and explicit request parameters.
- Validate presets and prevent arbitrary client-provided Elasticsearch DSL from being stored or executed.

## Performance Validation

The PostgreSQL baseline and Elasticsearch result must be measured with the same approximately 1-million-product dataset, hardware, container configuration, warm-up procedure, and load profile.

Validation records:

- RPS and error rate;
- p50, p95, and p99 latency;
- PostgreSQL load reduction;
- Elasticsearch CPU, RAM, storage, index size, and search latency;
- indexing and reindexing duration;
- synchronization lag and failed-event counts;
- functional equivalence of search, filters, counts, facets, and aggregations within the declared consistency window.

## Verification Plan

The implementation should be verified by checking that:

- initial indexing is complete, observable, idempotent, and safely restartable;
- product create, update, and delete events update the read model correctly;
- duplicate, delayed, and retried events do not corrupt indexed data;
- a new index version can be built, verified, activated through an alias, and rolled back;
- unavailable Elasticsearch produces controlled and visible behavior without silently returning incorrect results;
- unsupported fields, operators, sorts, and excessive limits are rejected by the application contract;
- every system preset is validated and combined with explicit parameters according to documented rules;
- Elasticsearch results match canonical PostgreSQL data within the declared eventual-consistency window;
- existing catalog, API Gateway, Cart Service, and Order Service behavior remains functional;
- repeated load tests demonstrate a measurable p95 improvement for agreed heavy search scenarios and reduced PostgreSQL load.

## Out Of Scope

- replacing PostgreSQL as the source of truth or write store;
- synchronous dual writes as a consistency mechanism;
- exposing arbitrary Elasticsearch DSL through the public API;
- using Elasticsearch for transactional stock or order-price checks;
- personal user-saved filter presets;
- recommendations, personalization, autocomplete, typo tolerance, semantic or vector search, and advanced ranking unless separately approved;
- unrelated business-logic, API, or architecture changes.

## 2026-09-24 — Performance Baseline and PHP-FPM Monitoring

This entry records the completed performance and monitoring work. The earlier sections retain the original Task 10 plan; this entry does not certify completion of every acceptance criterion or a controlled PostgreSQL-versus-Elasticsearch comparison.

The recorded stable load-test baseline after optimizations averages approximately **1,750 requests/s** with `PHP_FPM_MAX_CHILDREN=120` for the measured run. This is a result on the infrastructure used for that test, not the system's absolute maximum. The [throughput capture](<../../docs/images/task 10.png>) and [full Grafana capture](<../../docs/images/test dashboard-1790239018436.png>) show a plateau around 1.7k RPS; the approximate average and worker setting are the reported run parameters, not exact values independently recoverable from the images.

The Grafana error gauge displays **0.00287%** external HTTP 4xx/5xx responses: the last available ratio of request rates over a rolling 5-minute window, not the overall k6 error rate. Application histogram p50/p95/p99 trends remain stable after the ramp; the endpoint panel shows p95. Both latency panels stack series, so their upper boundaries must not be read as individual percentile values or client-side k6 latency.

The infrastructure now includes persistent FastCGI connections with a keepalive cache sized from the PHP-FPM worker limit and Nginx worker count, configurable Nginx workers (`NGINX_WORKER_COUNT=auto`), and PHP-FPM monitoring. `hipages/php-fpm_exporter:2.2.0` reads the dedicated port 9001 status listeners of `symfony-web`, `catalog-web`, and `cart-web`. Prometheus adds a `service` label; the saved Grafana panel `web php fpm workers` plots `phpfpm_active_processes`, `phpfpm_idle_processes`, `phpfpm_total_processes`, and `phpfpm_listen_queue` by service. The [README dashboard guide](../../README.md#current-performance-baseline) explains where to inspect throughput, errors, latency, and pool pressure.

Measurement limitations: the exact scenario, VU count, duration, warm-up procedure, hardware/container limits, and allocation of the reported 120-worker setting across pools have not been recorded for this run. Shared configuration currently defaults to `PHP_FPM_MAX_CHILDREN=10`. A run-specific k6 summary and configuration snapshot are needed to reproduce the measurement and establish exact client-side percentiles and whole-run error rate; Task 9 parameters must not be assumed to apply.

## 2026-09-24 — Persistent Connections and Latency Estimates

The `when@prod` Doctrine configuration in Symfony, Catalog, and Cart enables `PDO::ATTR_PERSISTENT` for PostgreSQL. All three services configure persistent Redis connections for application caches (`persistent=1`) and metrics storage (`persistent_connections=true`). These complement the previously recorded Nginx–PHP-FPM FastCGI keepalive configuration.

The shared PHP image now uses PHP 8.5. The integrated Catalog Elasticsearch client explicitly supplies a persistent cURL share handle through `CURLOPT_SHARE`, sharing `CURL_LOCK_DATA_CONNECT` and `CURL_LOCK_DATA_DNS` in `ElasticsearchClientFactory`. PHP 8.5's [`curl_share_init_persistent()`](https://www.php.net/manual/en/function.curl-share-init-persistent.php) retains this state across PHP requests within a worker process; this is not a single connection pool shared by all workers. Persistence for all internal REST clients has not been verified and is not implied by the PHP upgrade alone.

The stable interval in the [full Grafana capture](<../../docs/images/test dashboard-1790239018436.png>) gives approximate application latency of **p50 ≈ 20 ms, p95 ≈ 45 ms, and p99 ≈ 60–65 ms**. These estimates read the thickness of the individual areas in the stacked `Latency P50 / P95 / P99` panel, not the cumulative upper boundaries. They describe application histogram percentiles over rolling 5-minute windows, not exact exported values or end-to-end k6 results. No isolated before/after measurement attributes a specific speedup to any one connection optimization.

## 2026-09-24 — Elasticsearch Delivery and Measured Results

The delivered Elasticsearch catalog reader supports search, available filters, sorting, pagination, and exact counts. `CATALOG_READ_MODEL` selects Doctrine or Elasticsearch; the tracked `.env` currently selects `elasticsearch`, while the configuration fallback is `doctrine`. PostgreSQL remains the source of truth. Incremental synchronization uses a transactional outbox, Symfony Messenger, and a durable RabbitMQ queue: the handler rebuilds the document from current PostgreSQL data by ID and updates the index idempotently. Full reindex creates a new versioned index and switches the alias after validation; see the [runbook](../../catalog-service/docs/elasticsearch-reindex.md).

The [verified full-reindex result](../../catalog-service/docs/elasticsearch-reindex.md#verified-result) is **1,000,000 products processed and indexed**, **0 failures**, **00:09:18**, approximately **1,792 documents/s** on average, with the alias switched. This measures read-model construction, not HTTP API throughput.

The application's measured progression can be reported separately: the final Task 9 mixed-load run averaged **525.54 requests/s**, and a later stable baseline averaged approximately **1,750 requests/s** with a reported 120 PHP-FPM workers. These are not a controlled PostgreSQL-versus-Elasticsearch comparison: the exact later run profile and selected read model were not preserved, and other optimizations occurred between measurements. The RPS change therefore is not a demonstrated Elasticsearch-specific speedup. Facets, system presets, and a controlled PostgreSQL-versus-Elasticsearch benchmark are not documented as completed.
