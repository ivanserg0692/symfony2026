# Task 7

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [RU](#ru)
  - [Название](#%D0%BD%D0%B0%D0%B7%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5)
  - [Описание задачи](#%D0%BE%D0%BF%D0%B8%D1%81%D0%B0%D0%BD%D0%B8%D0%B5-%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B8)
  - [Цель](#%D1%86%D0%B5%D0%BB%D1%8C)
  - [Архитектурный контекст](#%D0%B0%D1%80%D1%85%D0%B8%D1%82%D0%B5%D0%BA%D1%82%D1%83%D1%80%D0%BD%D1%8B%D0%B9-%D0%BA%D0%BE%D0%BD%D1%82%D0%B5%D0%BA%D1%81%D1%82)
    - [7.1 Выделение сервисов](#71-%D0%B2%D1%8B%D0%B4%D0%B5%D0%BB%D0%B5%D0%BD%D0%B8%D0%B5-%D1%81%D0%B5%D1%80%D0%B2%D0%B8%D1%81%D0%BE%D0%B2)
    - [7.2 gRPC-интеграция Cart/Order](#72-grpc-%D0%B8%D0%BD%D1%82%D0%B5%D0%B3%D1%80%D0%B0%D1%86%D0%B8%D1%8F-cartorder)
    - [7.3 Публичный контракт API Gateway](#73-%D0%BF%D1%83%D0%B1%D0%BB%D0%B8%D1%87%D0%BD%D1%8B%D0%B9-%D0%BA%D0%BE%D0%BD%D1%82%D1%80%D0%B0%D0%BA%D1%82-api-gateway)
  - [Критерии приемки](#%D0%BA%D1%80%D0%B8%D1%82%D0%B5%D1%80%D0%B8%D0%B8-%D0%BF%D1%80%D0%B8%D0%B5%D0%BC%D0%BA%D0%B8)
  - [Технический подход](#%D1%82%D0%B5%D1%85%D0%BD%D0%B8%D1%87%D0%B5%D1%81%D0%BA%D0%B8%D0%B9-%D0%BF%D0%BE%D0%B4%D1%85%D0%BE%D0%B4)
  - [Как тестировать](#%D0%BA%D0%B0%D0%BA-%D1%82%D0%B5%D1%81%D1%82%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D1%82%D1%8C)
  - [Примечания](#%D0%BF%D1%80%D0%B8%D0%BC%D0%B5%D1%87%D0%B0%D0%BD%D0%B8%D1%8F)
- [EN](#en)
  - [Title](#title)
  - [Task Description](#task-description)
  - [Goal](#goal)
  - [Architecture Context](#architecture-context)
    - [7.1 Service split](#71-service-split)
    - [7.2 Cart/Order gRPC integration](#72-cartorder-grpc-integration)
    - [7.3 API Gateway public contract](#73-api-gateway-public-contract)
  - [Acceptance Criteria](#acceptance-criteria)
  - [Technical Approach](#technical-approach)
  - [How To Test](#how-to-test)
  - [Notes](#notes)

<!-- END doctoc -->

## RU

### Название

Архитектура backend-сервисов с nginx API Gateway, основным Symfony-сервисом и внутренним gRPC.

### Описание задачи

Описать и подготовить архитектурную основу backend-системы, где внешней точкой входа является nginx, а основной сервис в папке `symfony` отвечает за Auth, админку и новости.

Клиенты обращаются к nginx по REST/HTTP. nginx проверяет авторизацию через Auth endpoint основного Symfony-сервиса, получает identity header и проксирует исходный REST/HTTP запрос в нужный backend-сервис. gRPC используется только для внутренних service-to-service вызовов между backend-сервисами, а не как внешний gateway transport.

### Цель

Зафиксировать завершенную архитектурную основу backend-сервисов, чтобы дальнейшая разработка основного Symfony-сервиса, Catalog Service, Order/Cart Service и внутренних интеграций шла по единой модели входящего HTTP, авторизации через nginx и внутреннего gRPC-взаимодействия.

### Архитектурный контекст

Система состоит из следующих компонентов:

- nginx API Gateway - внешняя REST/HTTP точка входа для frontend/client-приложений.
- Symfony Main Service - основной сервис в папке `symfony`, который отвечает за Auth, админку и новости.
- Catalog Service - отдельный backend-сервис каталога.
- Order/Cart Service - отдельный backend-сервис корзины и заказов.

Текущий статус реализации:

- `catalog-service` создан как отдельное Symfony-приложение для Catalog Service;
- `cart-service` создан как отдельное Symfony-приложение для Order/Cart Service;
- REST/HTTP endpoints сервисов реализованы и опубликованы через nginx API Gateway под внешним namespace `/api/v1/...`;
- Gateway OpenAPI contract генерируется отдельно от внутренних OpenAPI specs сервисов;
- общие service env-настройки и DB maintenance scripts вынесены на уровень repository root.

#### 7.1 Выделение сервисов

Этап `7.1` фиксирует выделение Catalog Service и Order/Cart Service в отдельные Symfony applications. Main Symfony Service сохраняет Auth, admin, news и notifications, Catalog Service владеет catalog/store/stock data, а Order/Cart Service владеет cart и order data.

#### 7.2 gRPC-интеграция Cart/Order

Этап `7.2` фиксирует внутреннюю интеграцию Cart/Order с Catalog через gRPC: проверка цен, списание stock, создание product snapshots и последующее чтение snapshots для исторических order responses. Эти связи остаются service-to-service и не становятся публичным client API.

#### 7.3 Публичный контракт API Gateway

Этап `7.3` фиксирует внешний REST/HTTP contract через nginx API Gateway. Workflow должен быть описан route-level mappings, а не только общими стрелками:

- auth, news, notifications, users и ping: внешние `/api/v1/auth/...`, `/api/v1/news...`, `/api/v1/notification...`, `/api/v1/users`, `/api/v1/ping` -> Symfony Main Service с теми же internal `/api/v1/...` routes;
- catalog elements: внешние `/api/v1/catalog/elements...` -> Catalog Service `/api/catalog/elements...`;
- catalog sections: внешние `/api/v1/catalog/sections...` -> Catalog Service `/api/catalog/sections...`;
- stores: внешние `/api/v1/stores...` -> Catalog Service `/api/stores...`;
- cart: внешние `/api/v1/cart...` -> Cart Service `/api/cart...`;
- orders: внешние `/api/v1/orders...` -> Cart Service `/api/orders...`;
- protected routes: nginx вызывает internal `/_auth`, который проксируется в Symfony Main Service `GET /api/v1/auth/me`; после успешной проверки Gateway формирует trusted `X-User-Id`.

nginx API Gateway отвечает за:

- прием REST/HTTP запросов от frontend/client-приложений;
- выполнение auth check через Auth endpoint основного Symfony-сервиса;
- получение identity header от Auth/Symfony сервиса;
- проксирование исходного REST/HTTP запроса в нужный backend-сервис;
- передачу downstream-сервисам доверенного header `X-User-Id`;
- очистку или перезапись client-supplied identity headers перед проксированием request;
- целевую роль единой публичной REST/HTTP точки входа. В dev окружении сервисные порты могут оставаться опубликованными для отладки; внешний perimeter должен закрывать прямой доступ через firewall.

Symfony Main Service отвечает за:

- регистрацию пользователей;
- login flow;
- выпуск JWT и refresh token;
- проверку токенов для nginx auth check;
- роли и права пользователя;
- админку;
- новости;
- уведомления, связанные с новостями;
- локальную event-driven архитектуру внутри основного сервиса для асинхронной обработки через RabbitMQ;
- будущую общую подсистему уведомлений на базе event-driven подхода.

Catalog Service отвечает за:

- товары;
- категории;
- поиск;
- остатки;
- публичные данные каталога.

Order/Cart Service отвечает за:

- корзину;
- оформление заказа;
- статусы заказа;
- связь заказа с пользователем и товарами.

Внешний request flow:

1. Client отправляет REST/HTTP запрос в nginx.
2. nginx делает auth check в Symfony Main Service.
3. Symfony Main Service валидирует токен и возвращает результат авторизации.
4. nginx формирует trusted identity header `X-User-Id`.
5. nginx проксирует исходный REST/HTTP запрос в нужный backend-сервис вместе с trusted identity header.

Внутренний service-to-service flow:

- backend-сервисы могут вызывать друг друга через gRPC;
- gRPC не является внешним транспортом для frontend/client-приложений;
- proto-контракты должны описывать только внутренние интеграции между сервисами;
- RabbitMQ сейчас используется как локальная async/event-driven инфраструктура основного Symfony-сервиса;
- RabbitMQ пока не является межсервисным event bus между backend-сервисами;
- если сервис запускается как long-running gRPC worker через RoadRunner, он должен учитывать lifecycle worker и reset состояния после обработки request.

Каждый сервис владеет своей базой данных:

- Symfony Main Service -> Main/Auth/Admin/News DB;
- Catalog Service -> Catalog DB;
- Order/Cart Service -> Orders DB.

<!-- plantuml src="plantuml/backend-architecture/services.puml" alt="Backend service architecture" out="images/plantuml/backend-architecture/services.png" -->
![Backend service architecture](images/plantuml/backend-architecture/services.png)
<!-- /plantuml -->

### Критерии приемки

- Описана роль nginx как внешнего API Gateway для REST/HTTP запросов.
- Зафиксировано, что основной сервис в папке `symfony` отвечает за Auth, админку, новости и связанные уведомления.
- Зафиксировано, что `catalog-service` и `cart-service` уже созданы как отдельные service applications для текущего этапа реализации.
- Описан auth check от nginx к Symfony Main Service перед проксированием request.
- Зафиксирована передача trusted header `X-User-Id` от gateway к backend-сервисам.
- Зафиксировано, что Catalog Service и Order/Cart Service получают внешний трафик от nginx по REST/HTTP.
- Зафиксировано, что gRPC используется для внутренних service-to-service вызовов, а не как внешний gateway transport.
- Зафиксирована локальная async/event-driven архитектура основного Symfony-сервиса через RabbitMQ.
- Описано владение базой данных каждым сервисом.
- Подготовлена и реализована основа для nginx routing, auth endpoint, identity header, REST endpoints, внутренних gRPC-контрактов, gateway OpenAPI generation и общих maintenance scripts.

### Технический подход

- Начать с описания nginx routes и правил auth check для защищенных endpoint groups.
- В Symfony Main Service реализовать Auth endpoint, который nginx сможет использовать для проверки токена.
- Не принимать `X-User-Id` от внешнего клиента как доверенный header.
- Сбрасывать или перезаписывать identity headers на уровне nginx перед проксированием downstream request.
- Передавать downstream-сервисам только headers, сформированные после успешного auth check.
- Для внешнего трафика использовать REST/HTTP через nginx.
- Для внутренних интеграций между backend-сервисами использовать gRPC и proto-контракты.
- Для асинхронной обработки внутри Symfony Main Service использовать RabbitMQ как локальный message broker.
- Не считать RabbitMQ межсервисным event bus на текущем этапе архитектуры.
- Gateway не должен напрямую использовать базы данных backend-сервисов.
- Backend-сервисы не должны обращаться напрямую к чужим базам данных.

### Как тестировать

- Проверить, что client request попадает сначала в nginx.
- Проверить, что nginx перед защищенным route вызывает Auth endpoint Symfony Main Service.
- Проверить, что при валидном токене downstream-сервис получает `X-User-Id`.
- Проверить, что при невалидном токене nginx не проксирует request в downstream-сервис.
- Проверить, что внешний клиент не может подделать trusted identity headers.
- Проверить, что целевой публичный REST/HTTP contract доступен через nginx routing. В dev окружении опубликованные порты сервисов допустимы для отладки, если внешний доступ к ним закрывается perimeter firewall.
- Проверить, что внутренние service-to-service сценарии используют gRPC-контракты там, где это требуется.
- Проверить, что async/event-driven обработка уведомлений основного Symfony-сервиса использует RabbitMQ локально внутри этого сервиса.

### Примечания

- В этой архитектуре nginx является основным API Gateway, но не содержит бизнес-логику авторизации; он делегирует проверку Auth/Symfony сервису.
- Symfony Main Service остается центральным сервисом для Auth, админки, новостей и текущих уведомлений по новостям.
- Сейчас event-driven подход уже используется внутри Symfony Main Service для асинхронной обработки через RabbitMQ в рамках одного сервиса. В будущих итерациях эту модель можно расширить до общей подсистемы уведомлений и межсервисных событий.
- gRPC следует рассматривать как внутренний транспорт между сервисами, а не как транспорт между клиентом и gateway.
- Для long-running gRPC workers сохраняется требование не переносить mutable state между requests.
- `X-User-Roles` и `X-User-Email` намеренно не входят в текущий Gateway contract и могут быть добавлены отдельной задачей, когда downstream-сервисам реально понадобится эта identity metadata.

## EN

### Title

Backend service architecture with nginx API Gateway, main Symfony service, and internal gRPC.

### Task Description

Describe and prepare the backend architecture foundation where nginx is the external entrypoint and the main service under the `symfony` directory owns Auth, admin, and news.

Clients call nginx over REST/HTTP. nginx validates authorization through an Auth endpoint exposed by the main Symfony service, receives an identity header, and proxies the original REST/HTTP request to the target backend service. gRPC is used only for internal service-to-service calls between backend services, not as the external gateway transport.

### Goal

Capture the completed backend service architecture foundation so that the main Symfony service, Catalog Service, Order/Cart Service, and internal integrations can continue to evolve with a shared model for incoming HTTP, nginx-based authorization, and internal gRPC communication.

### Architecture Context

The system consists of the following components:

- nginx API Gateway - external REST/HTTP entrypoint for frontend/client applications.
- Symfony Main Service - the main service under `symfony`, responsible for Auth, admin, and news.
- Catalog Service - separate catalog backend service.
- Order/Cart Service - separate cart and order backend service.

Current implementation status:

- `catalog-service` has been created as a separate Symfony application for Catalog Service;
- `cart-service` has been created as a separate Symfony application for Order/Cart Service;
- service REST/HTTP endpoints are implemented and exposed through nginx API Gateway under the external `/api/v1/...` namespace;
- the Gateway OpenAPI contract is generated separately from internal service OpenAPI specs;
- shared service env settings and DB maintenance scripts are maintained at the repository root.

#### 7.1 Service split

Stage `7.1` captures the extraction of Catalog Service and Order/Cart Service into separate Symfony applications. Symfony Main Service keeps Auth, admin, news, and notifications, Catalog Service owns catalog/store/stock data, and Order/Cart Service owns cart and order data.

#### 7.2 Cart/Order gRPC integration

Stage `7.2` captures the internal Cart/Order integration with Catalog over gRPC: price checks, stock deduction, product snapshot creation, and later snapshot reads for historical order responses. These relations remain service-to-service integration points and do not become public client API.

#### 7.3 API Gateway public contract

Stage `7.3` captures the external REST/HTTP contract through nginx API Gateway. The workflow should be documented as route-level mappings, not only as generic arrows:

- auth, news, notifications, users, and ping: external `/api/v1/auth/...`, `/api/v1/news...`, `/api/v1/notification...`, `/api/v1/users`, `/api/v1/ping` -> Symfony Main Service with the same internal `/api/v1/...` routes;
- catalog elements: external `/api/v1/catalog/elements...` -> Catalog Service `/api/catalog/elements...`;
- catalog sections: external `/api/v1/catalog/sections...` -> Catalog Service `/api/catalog/sections...`;
- stores: external `/api/v1/stores...` -> Catalog Service `/api/stores...`;
- cart: external `/api/v1/cart...` -> Cart Service `/api/cart...`;
- orders: external `/api/v1/orders...` -> Cart Service `/api/orders...`;
- protected routes: nginx calls internal `/_auth`, which is proxied to Symfony Main Service `GET /api/v1/auth/me`; after successful validation, Gateway creates the trusted `X-User-Id`.

nginx API Gateway is responsible for:

- accepting REST/HTTP requests from frontend/client applications;
- performing auth check through the Auth endpoint of the main Symfony service;
- receiving an identity header from the Auth/Symfony service;
- proxying the original REST/HTTP request to the target backend service;
- passing the trusted `X-User-Id` header to downstream services;
- clearing or overwriting client-supplied identity headers before proxying the request;
- acting as the target single public REST/HTTP entrypoint. In the dev environment, service ports may remain published for debugging; the external perimeter must block direct access through firewall rules.

Symfony Main Service is responsible for:

- user registration;
- login flow;
- issuing JWT and refresh tokens;
- token validation for nginx auth check;
- user roles and permissions;
- admin area;
- news;
- notifications related to news;
- local event-driven architecture inside the main service for asynchronous processing through RabbitMQ;
- future general notification subsystem based on the event-driven approach.

Catalog Service is responsible for:

- products;
- categories;
- search;
- stock;
- public catalog data.

Order/Cart Service is responsible for:

- cart;
- checkout;
- order statuses;
- linking orders with users and products.

External request flow:

1. Client sends a REST/HTTP request to nginx.
2. nginx performs auth check against Symfony Main Service.
3. Symfony Main Service validates the token and returns the authorization result.
4. nginx creates the trusted `X-User-Id` identity header.
5. nginx proxies the original REST/HTTP request to the target backend service with the trusted identity header.

Internal service-to-service flow:

- backend services may call each other through gRPC;
- gRPC is not the external transport for frontend/client applications;
- proto contracts must describe only internal integrations between services;
- RabbitMQ is currently used as local async/event-driven infrastructure of the main Symfony service;
- RabbitMQ is not an inter-service event bus between backend services at this stage;
- if a service runs as a long-running gRPC worker through RoadRunner, it must account for worker lifecycle and reset state after request processing.

Each service owns its own database:

- Symfony Main Service -> Main/Auth/Admin/News DB;
- Catalog Service -> Catalog DB;
- Order/Cart Service -> Orders DB.

<!-- plantuml src="plantuml/backend-architecture/services.puml" alt="Backend service architecture" out="images/plantuml/backend-architecture/services.png" -->
![Backend service architecture](images/plantuml/backend-architecture/services.png)
<!-- /plantuml -->

### Acceptance Criteria

- nginx is documented as the external API Gateway for REST/HTTP requests.
- The main service under `symfony` is documented as responsible for Auth, admin, news, and related notifications.
- `catalog-service` and `cart-service` are documented as existing service applications created for the current implementation stage.
- nginx auth check against Symfony Main Service before request proxying is documented.
- The trusted `X-User-Id` header from gateway to backend services is documented.
- Catalog Service and Order/Cart Service are documented as receiving external traffic from nginx over REST/HTTP.
- gRPC is documented as internal service-to-service transport, not as the external gateway transport.
- Local async/event-driven architecture of the main Symfony service through RabbitMQ is documented.
- Database ownership per service is documented.
- The task provides and implements the foundation for nginx routing, auth endpoint, identity header, REST endpoints, internal gRPC contracts, gateway OpenAPI generation, and shared maintenance scripts.

### Technical Approach

- Start by describing nginx routes and auth check rules for protected endpoint groups.
- Implement an Auth endpoint in Symfony Main Service that nginx can use for token validation.
- Do not treat `X-User-Id` sent by an external client as a trusted header.
- Clear or overwrite identity headers at nginx before proxying downstream requests.
- Pass identity headers to downstream services only after successful auth check.
- Use REST/HTTP through nginx for external traffic.
- Use gRPC and proto contracts for internal integrations between backend services.
- Use RabbitMQ as a local message broker for asynchronous processing inside Symfony Main Service.
- Do not treat RabbitMQ as an inter-service event bus at the current architecture stage.
- Gateway must not directly use backend service databases.
- Backend services must not directly access databases owned by other services.

### How To Test

- Verify that client requests reach nginx first.
- Verify that nginx calls the Symfony Main Service Auth endpoint before a protected route is proxied.
- Verify that with a valid token the downstream service receives `X-User-Id`.
- Verify that with an invalid token nginx does not proxy the request to the downstream service.
- Verify that an external client cannot spoof trusted identity headers.
- Verify that the target public REST/HTTP contract is reachable through nginx routing. In the dev environment, published service ports are acceptable for debugging when external access to them is blocked by the perimeter firewall.
- Verify that internal service-to-service scenarios use gRPC contracts where required.
- Verify that async/event-driven notification processing of the main Symfony service uses RabbitMQ locally inside that service.

### Notes

- In this architecture nginx is the main API Gateway, but it does not own authorization business logic; it delegates validation to the Auth/Symfony service.
- Symfony Main Service remains the central service for Auth, admin, news, and current news-related notifications.
- The event-driven approach is already used inside Symfony Main Service for asynchronous processing through RabbitMQ within one service. In future iterations, this model can be expanded into a general notification subsystem and inter-service events.
- gRPC should be treated as an internal service-to-service transport, not as client-to-gateway transport.
- Long-running gRPC workers still must not leak mutable state between requests.
- `X-User-Roles` and `X-User-Email` are intentionally outside the current Gateway contract and can be added in a separate task once downstream services actually need this identity metadata.
