# MR Result Log Task 7.3

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Summary](#summary)
- [Scope](#scope)
- [Public API Boundary](#public-api-boundary)
- [Security Notes](#security-notes)
- [Verification](#verification)
- [Known Follow-Up](#known-follow-up)
- [Out Of Scope](#out-of-scope)

<!-- END doctoc -->

## Summary

This document describes the visible merge request result for Task 7.3.

Merge request: TBD

Task file: [task-7.3.md](task-7.3.md)

The merge request adds an external REST/HTTP API Gateway based on nginx. The Gateway becomes a dedicated public proxy layer for client-facing `/api/v1/...` routes and proxies requests to the internal Symfony, Catalog, and Cart services through their current service-level REST endpoints.

## Scope

Implemented:

- added the `api-gateway` Docker Compose service based on nginx;
- added nginx Gateway configuration with upstreams for `symfony-web`, `catalog-web`, and `cart-web`;
- described public Gateway routes in `api-gateway/routes.json`;
- generated nginx routes into `api-gateway/nginx/snippets/generated-api-gateway-routes.conf`;
- generated the Gateway-level OpenAPI contract into `symfony/public/api-gateway/openapi.json`;
- extracted the base OpenAPI header into `api-gateway/openapi-header.json`;
- extracted common nginx proxy/auth settings into snippets;
- mapped external `/api/v1/catalog/...`, `/api/v1/stores`, `/api/v1/cart`, and `/api/v1/orders` routes to the current internal Catalog and Cart service routes;
- protected cart/order routes with nginx `auth_request`.

## Public API Boundary

The Gateway exposes the client REST/HTTP contract under `/api/v1/...`.

Key route mappings:

- `/api/v1/auth/...` -> Symfony `/api/v1/auth/...`;
- `/api/v1/news...` -> Symfony `/api/v1/news...`;
- `/api/v1/notification...` -> Symfony `/api/v1/notification...`;
- `/api/v1/catalog/elements...` -> Catalog `/api/catalog/elements...`;
- `/api/v1/catalog/sections...` -> Catalog `/api/catalog/sections...`;
- `/api/v1/stores...` -> Catalog `/api/stores...`;
- `/api/v1/cart...` -> Cart `/api/cart...`;
- `/api/v1/orders...` -> Cart `/api/orders...`.

The Gateway OpenAPI contract is available as a generated artifact: `/api-gateway/openapi.json`.

## Security Notes

For protected cart/order routes, the Gateway performs an internal auth check through `/_auth`, which is proxied to Symfony `/api/v1/auth/me`.

After a successful auth check, the Gateway reads `X-User-Id` from the auth endpoint response and forwards it to the internal Cart service as a trusted header.

Client-supplied `X-User-Id` is not trusted:

- for public routes, the Gateway clears `X-User-Id`;
- for the auth-check request, the Gateway also clears `X-User-Id`;
- for protected routes, the Gateway sets `X-User-Id` from `$auth_user_id`, not from the client header;
- `X-User-Id` returned by Symfony `/api/v1/auth/me` is hidden from the frontend with `proxy_hide_header`.

`X-User-Roles` and `X-User-Email` were intentionally not added yet.

## Verification

Verified during implementation:

- `node --check scripts/generate-api-gateway.mjs`;
- `docker compose config --quiet`;
- `npm run gateway:generate`.

The OpenAPI generation command completed successfully. The PHP container output still contains the existing warning `Cannot load Xdebug - it was already loaded`.

## Known Follow-Up

In the current development Docker Compose setup, direct ports for internal web services are still published externally:

- Symfony web: `8000:8000`;
- Catalog web: `8010:8000`;
- Cart web: `8020:8000`.

For a complete production-like security boundary, the next step is to change internal web services from `ports` to internal `expose` and keep only `api-gateway` as the public REST entrypoint.

## Out Of Scope

This merge request does not change business logic inside the internal services.

Role headers, email headers, and a dedicated lightweight internal Symfony auth-check endpoint were not added. The current integration reuses `/api/v1/auth/me`.
