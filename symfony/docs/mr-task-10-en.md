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

