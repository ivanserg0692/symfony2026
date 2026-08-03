# MR Task 7 Result Log

## Overview

This document will describe the visible result of merge request 7.

Merge request: https://github.com/ivanserg0692/symfony2026/pull/7

Task file: [task-7.md](task-7.md)

The task documents and prepares the target backend service architecture with nginx as the external REST/HTTP API Gateway, the existing Symfony application as the main service for Auth, admin, news, and notifications, and gRPC reserved for internal service-to-service communication.

## Planned Result

The expected result includes:
- nginx as the external REST/HTTP gateway
- auth checks delegated from nginx to the Symfony Main Service
- trusted identity header `X-User-Id`
- REST/HTTP proxying from nginx to downstream backend services
- internal gRPC communication between backend services where needed
- separate ownership of service databases
- local RabbitMQ-based async/event-driven processing inside the Symfony Main Service
- a documented future path for expanding notifications and event-driven integrations

## Architecture Diagram

<!-- plantuml src="plantuml/backend-architecture/services.puml" alt="Backend service architecture" out="images/plantuml/backend-architecture/services.png" -->
![Backend service architecture](images/plantuml/backend-architecture/services.png)
<!-- /plantuml -->

## Screenshots

Not applicable for this architecture/documentation task.

## Updates

Implementation notes are appended here as dated sections.

### 2026-07-19 - Service Applications Created

The current implementation now includes two separate Symfony service applications that match the Task 7 architecture diagram:

- `catalog-service` for the Catalog Service boundary;
- `cart-service` for the Order/Cart Service boundary.

At this stage they are recorded as the service-level foundation for later REST/HTTP endpoints, internal gRPC integration, and independent data ownership.

### 2026-08-03 - Architecture Scope Completed

Task 7 is now completed across the implemented architecture track:

- Catalog Service and Cart Service exist as separate Symfony applications with their own service boundaries;
- Cart/Order scenarios use internal Catalog gRPC integration where product validation or snapshots are required;
- nginx API Gateway is the target public REST/HTTP entrypoint and exposes the external `/api/v1/...` contract;
- Gateway routes, nginx snippets, and the public Gateway OpenAPI contract are generated from `api-gateway/routes.json` and `api-gateway/openapi-header.json`;
- protected Gateway routes validate the current user through Symfony Main Service and forward the trusted `X-User-Id` header to downstream services;
- client-supplied identity headers are cleared or overwritten at the Gateway before proxying;
- shared CORS values, internal service URLs, and database maintenance targets are centralized in the repository root `.env`;
- shared DB maintenance scripts cover database creation, migrations, status checks, and fixtures for all configured Symfony services.

The development Docker Compose setup may keep individual service ports published for debugging. In production-like deployments, direct external access to those service ports should be blocked at the perimeter firewall, while the Gateway remains the public REST/HTTP contract.
