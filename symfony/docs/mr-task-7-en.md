# MR Task 7 Result Log

## Overview

This document will describe the visible result of merge request 7.

Merge request: TBD

Task file: [task-7.md](task-7.md)

The task documents and prepares the target backend service architecture with nginx as the external REST/HTTP API Gateway, the existing Symfony application as the main service for Auth, admin, news, and notifications, and gRPC reserved for internal service-to-service communication.

## Planned Result

The expected result includes:
- nginx as the external REST/HTTP gateway
- auth checks delegated from nginx to the Symfony Main Service
- trusted identity headers `X-User-Id` and `X-User-Role`
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

Implementation notes will be appended here as dated sections.
