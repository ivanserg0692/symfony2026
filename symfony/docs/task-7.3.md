# Task 7.3

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [RU](#ru)
  - [Название](#%D0%BD%D0%B0%D0%B7%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5)
  - [Описание задачи](#%D0%BE%D0%BF%D0%B8%D1%81%D0%B0%D0%BD%D0%B8%D0%B5-%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B8)
  - [Цель](#%D1%86%D0%B5%D0%BB%D1%8C)
  - [Архитектурный контекст](#%D0%B0%D1%80%D1%85%D0%B8%D1%82%D0%B5%D0%BA%D1%82%D1%83%D1%80%D0%BD%D1%8B%D0%B9-%D0%BA%D0%BE%D0%BD%D1%82%D0%B5%D0%BA%D1%81%D1%82)
  - [Критерии приемки](#%D0%BA%D1%80%D0%B8%D1%82%D0%B5%D1%80%D0%B8%D0%B8-%D0%BF%D1%80%D0%B8%D0%B5%D0%BC%D0%BA%D0%B8)
  - [Технический подход](#%D1%82%D0%B5%D1%85%D0%BD%D0%B8%D1%87%D0%B5%D1%81%D0%BA%D0%B8%D0%B9-%D0%BF%D0%BE%D0%B4%D1%85%D0%BE%D0%B4)
  - [Как тестировать](#%D0%BA%D0%B0%D0%BA-%D1%82%D0%B5%D1%81%D1%82%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D1%82%D1%8C)
  - [Примечания](#%D0%BF%D1%80%D0%B8%D0%BC%D0%B5%D1%87%D0%B0%D0%BD%D0%B8%D1%8F)
- [EN](#en)
  - [Title](#title)
  - [Task Description](#task-description)
  - [Goal](#goal)
  - [Architecture Context](#architecture-context)
  - [Acceptance Criteria](#acceptance-criteria)
  - [Technical Approach](#technical-approach)
  - [How To Test](#how-to-test)
  - [Notes](#notes)

<!-- END doctoc -->

## RU

### Название

Реализация внешнего API Gateway для публичного REST/HTTP контракта.

### Описание задачи

Реализовать внешний API Gateway на базе nginx для публичного REST/HTTP API проекта. Gateway должен стать единственной внешней точкой входа для клиентских приложений и проксировать запросы во внутренние backend-сервисы по их внутренним REST endpoints.

Задача продолжает Task 7: основные backend-сервисы уже выделены, их внутренние endpoints описываются через OpenAPI каждого сервиса, а внешний публичный API должен быть зафиксирован и реализован отдельно на уровне gateway.

### Цель

Разделить внутренние сервисные endpoints и внешний публичный API-контракт, чтобы клиенты работали только с API Gateway, а `symfony`, `catalog-service` и `cart-service` не были доступны напрямую извне.

### Архитектурный контекст

Внутренние REST endpoints сервисов остаются контрактом конкретного backend-сервиса и нужны для service-level реализации, локальной проверки и генерации внутренних таблиц API endpoints в README.

Внешний публичный API-контракт должен определяться на уровне API Gateway. Gateway принимает клиентский HTTP-запрос, выполняет или делегирует authorization/authentication check, формирует доверенный контекст пользователя и проксирует запрос в целевой backend-сервис.

Route manifest API Gateway является единым source of truth для публичного route mapping. Из него генерируются nginx-конфигурация gateway и gateway-level OpenAPI specification, чтобы runtime routing и публичный API contract не расходились.

gRPC остается только внутренним service-to-service transport. Клиенты не обращаются к gRPC напрямую, а gateway не должен превращать gRPC в публичный API transport.

### Критерии приемки

- API Gateway является единственной внешней REST/HTTP точкой входа для клиентских приложений.
- Прямой внешний доступ к внутренним сервисам `symfony`, `catalog-service` и `cart-service` закрыт или не публикуется наружу.
- Публичные gateway routes явно сопоставлены с внутренними service endpoints.
- nginx-конфигурация gateway генерируется из route manifest.
- Gateway-level OpenAPI specification генерируется из того же route manifest и включает только публичные routes.
- Auth check выполняется на gateway layer или делегируется основному Symfony/Auth service до проксирования защищенных запросов.
- Внутренние сервисы получают доверенный identity context через согласованные headers, например `X-User-Id` и `X-User-Role`.
- Внутренние OpenAPI endpoints сервисов не считаются внешним публичным API-контрактом.
- Ошибки upstream-сервисов корректно преобразуются или проксируются в ожидаемый публичный REST/HTTP response.
- gRPC остается внутренним service-to-service transport и не публикуется как внешний клиентский transport.

### Технический подход

- Описать публичные route groups API Gateway и их соответствие backend-сервисам в `api-gateway/routes.json`.
- Генерировать nginx reverse proxy configuration из route manifest.
- Генерировать gateway-level OpenAPI specification из route manifest и внутренних service OpenAPI sources.
- Настроить nginx reverse proxy для маршрутизации запросов в основной Symfony service, Catalog Service и Cart/Order Service.
- Для защищенных routes добавить предварительный auth request или другой согласованный auth-check mechanism через основной Symfony/Auth service.
- Передавать во внутренние сервисы только доверенные identity headers, сформированные gateway layer.
- Исключить возможность подмены trusted headers клиентом.
- Разделить public gateway routes и internal service routes в документации и конфигурации.
- После реализации обновить gateway-level OpenAPI/README описание внешнего API, не смешивая его с внутренними таблицами endpoints сервисов.

### Как тестировать

- Проверить успешный публичный запрос через API Gateway к каждому backend-сервису.
- Проверить, что защищенный route без валидной авторизации не проксируется во внутренний сервис.
- Проверить, что валидная авторизация приводит к проксированию с корректными trusted identity headers.
- Проверить, что клиент не может подменить `X-User-Id`, `X-User-Role` или другие trusted headers.
- Проверить, что прямой доступ к внутренним сервисам извне недоступен.
- Проверить корректную маршрутизацию gateway routes в `symfony`, `catalog-service` и `cart-service`.
- Проверить, что `npm run gateway:generate` обновляет nginx config и gateway OpenAPI из одного route manifest.
- Проверить, что gateway OpenAPI содержит только публичные routes и не смешивает их с внутренними service-only endpoints.
- Проверить обработку upstream errors: not found, validation errors, authorization errors и service unavailable.
- Проверить, что gRPC endpoints не доступны снаружи как клиентский API.

### Примечания

- Эта задача описывает внешний API Gateway scope, а не изменение бизнес-логики внутренних сервисов.
- Внутренние OpenAPI-таблицы в README остаются полезными для service-level документации, но не заменяют gateway-level публичный API contract.
- Детальный публичный OpenAPI-контракт gateway формируется как generated artifact и может быть подключен к Swagger UI.

## EN

### Title

Implement the external API Gateway for the public REST/HTTP contract.

### Task Description

Implement the external nginx-based API Gateway for the project's public REST/HTTP API. The gateway must become the only external entrypoint for client applications and proxy requests to backend services through their internal REST endpoints.

This task continues Task 7: the main backend services have already been split out, their internal endpoints are described by each service OpenAPI contract, and the external public API must be defined and implemented separately at the gateway level.

### Goal

Separate internal service endpoints from the external public API contract, so clients work only through the API Gateway and `symfony`, `catalog-service`, and `cart-service` are not directly accessible from outside.

### Architecture Context

Internal REST endpoints remain the contract of each backend service. They are needed for service-level implementation, local verification, and generated internal API endpoint tables in the README.

The external public API contract must be defined at the API Gateway level. The gateway receives the client HTTP request, performs or delegates the authorization/authentication check, builds the trusted user context, and proxies the request to the target backend service.

The API Gateway route manifest is the single source of truth for public route mapping. It generates both the gateway nginx configuration and the gateway-level OpenAPI specification, so runtime routing and the public API contract stay aligned.

gRPC remains internal service-to-service transport only. Clients do not call gRPC directly, and the gateway must not expose gRPC as a public API transport.

### Acceptance Criteria

- API Gateway is the only external REST/HTTP entrypoint for client applications.
- Direct external access to internal services `symfony`, `catalog-service`, and `cart-service` is closed or not published externally.
- Public gateway routes are explicitly mapped to internal service endpoints.
- The gateway nginx configuration is generated from the route manifest.
- The gateway-level OpenAPI specification is generated from the same route manifest and includes only public routes.
- Auth checks happen at the gateway layer or are delegated to the main Symfony/Auth service before protected requests are proxied.
- Internal services receive trusted identity context through agreed headers, for example `X-User-Id` and `X-User-Role`.
- Internal service OpenAPI endpoints are not treated as the external public API contract.
- Upstream service errors are correctly transformed or proxied into the expected public REST/HTTP response.
- gRPC remains internal service-to-service transport and is not published as an external client transport.

### Technical Approach

- Describe public API Gateway route groups and their mapping to backend services in `api-gateway/routes.json`.
- Generate nginx reverse proxy configuration from the route manifest.
- Generate the gateway-level OpenAPI specification from the route manifest and internal service OpenAPI sources.
- Configure nginx reverse proxy routing to the main Symfony service, Catalog Service, and Cart/Order Service.
- Add a preliminary auth request or another agreed auth-check mechanism through the main Symfony/Auth service for protected routes.
- Pass only trusted identity headers generated by the gateway layer to internal services.
- Prevent clients from spoofing trusted headers.
- Separate public gateway routes from internal service routes in documentation and configuration.
- After implementation, update the gateway-level OpenAPI/README description of the external API without mixing it with internal service endpoint tables.

### How To Test

- Verify a successful public request through API Gateway to each backend service.
- Verify that a protected route without valid authorization is not proxied to an internal service.
- Verify that valid authorization proxies the request with correct trusted identity headers.
- Verify that a client cannot spoof `X-User-Id`, `X-User-Role`, or other trusted headers.
- Verify that direct access to internal services is unavailable from outside.
- Verify correct gateway route mapping to `symfony`, `catalog-service`, and `cart-service`.
- Verify that `npm run gateway:generate` updates nginx config and gateway OpenAPI from one route manifest.
- Verify that gateway OpenAPI includes only public routes and does not mix them with internal service-only endpoints.
- Verify upstream error handling: not found, validation errors, authorization errors, and service unavailable.
- Verify that gRPC endpoints are not externally available as a client API.

### Notes

- This task describes the external API Gateway scope, not business logic changes inside internal services.
- Internal OpenAPI tables in the README remain useful for service-level documentation, but they do not replace the gateway-level public API contract.
- The detailed public gateway OpenAPI contract is produced as a generated artifact and can be connected to Swagger UI.
