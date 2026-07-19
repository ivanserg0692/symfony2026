# Task 7

## RU

### Название

Архитектура backend-сервисов с nginx API Gateway, основным Symfony-сервисом и внутренним gRPC.

### Описание задачи

Описать и подготовить архитектурную основу backend-системы, где внешней точкой входа является nginx, а основной сервис в папке `symfony` отвечает за Auth, админку и новости.

Клиенты обращаются к nginx по REST/HTTP. nginx проверяет авторизацию через Auth endpoint основного Symfony-сервиса, получает identity headers и проксирует исходный REST/HTTP запрос в нужный backend-сервис. gRPC используется только для внутренних service-to-service вызовов между backend-сервисами, а не как внешний gateway transport.

### Цель

Зафиксировать целевую архитектуру backend-сервисов перед реализацией, чтобы дальнейшая разработка основного Symfony-сервиса, Catalog Service, Order/Cart Service и внутренних интеграций шла по единой модели входящего HTTP, авторизации через nginx и внутреннего gRPC-взаимодействия.

### Архитектурный контекст

Система состоит из следующих компонентов:

- nginx API Gateway - внешняя REST/HTTP точка входа для frontend/client-приложений.
- Symfony Main Service - основной сервис в папке `symfony`, который отвечает за Auth, админку и новости.
- Catalog Service - отдельный backend-сервис каталога.
- Order/Cart Service - отдельный backend-сервис корзины и заказов.

Текущий статус реализации:

- `catalog-service` создан как отдельное Symfony-приложение для Catalog Service;
- `cart-service` создан как отдельное Symfony-приложение для Order/Cart Service;
- оба сервиса пока фиксируются как инфраструктурная основа под дальнейшую реализацию REST/HTTP endpoints, внутренних gRPC-интеграций и собственного владения данными.

nginx API Gateway отвечает за:

- прием REST/HTTP запросов от frontend/client-приложений;
- выполнение auth check через Auth endpoint основного Symfony-сервиса;
- получение identity headers от Auth/Symfony сервиса;
- проксирование исходного REST/HTTP запроса в нужный backend-сервис;
- передачу downstream-сервисам доверенных headers `X-User-Id` и `X-User-Role`;
- запрет прямого внешнего доступа к внутренним сервисам в обход gateway.

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
4. nginx формирует trusted identity headers `X-User-Id` и `X-User-Role`.
5. nginx проксирует исходный REST/HTTP запрос в нужный backend-сервис вместе с trusted identity headers.

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
- Зафиксирована передача trusted headers `X-User-Id` и `X-User-Role` от gateway к backend-сервисам.
- Зафиксировано, что Catalog Service и Order/Cart Service получают внешний трафик от nginx по REST/HTTP.
- Зафиксировано, что gRPC используется для внутренних service-to-service вызовов, а не как внешний gateway transport.
- Зафиксирована локальная async/event-driven архитектура основного Symfony-сервиса через RabbitMQ.
- Описано владение базой данных каждым сервисом.
- Подготовлена основа для последующей реализации nginx routing, auth endpoint, identity headers, REST endpoints и внутренних gRPC-контрактов.

### Технический подход

- Начать с описания nginx routes и правил auth check для защищенных endpoint groups.
- В Symfony Main Service реализовать Auth endpoint, который nginx сможет использовать для проверки токена.
- Не принимать `X-User-Id` и `X-User-Role` от внешнего клиента как доверенные headers.
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
- Проверить, что при валидном токене downstream-сервис получает `X-User-Id` и `X-User-Role`.
- Проверить, что при невалидном токене nginx не проксирует request в downstream-сервис.
- Проверить, что внешний клиент не может подделать trusted identity headers.
- Проверить, что Catalog Service и Order/Cart Service доступны снаружи только через nginx routing.
- Проверить, что внутренние service-to-service сценарии используют gRPC-контракты там, где это требуется.
- Проверить, что async/event-driven обработка уведомлений основного Symfony-сервиса использует RabbitMQ локально внутри этого сервиса.

### Примечания

- В этой архитектуре nginx является основным API Gateway, но не содержит бизнес-логику авторизации; он делегирует проверку Auth/Symfony сервису.
- Symfony Main Service остается центральным сервисом для Auth, админки, новостей и текущих уведомлений по новостям.
- Сейчас event-driven подход уже используется внутри Symfony Main Service для асинхронной обработки через RabbitMQ в рамках одного сервиса. В будущих итерациях эту модель можно расширить до общей подсистемы уведомлений и межсервисных событий.
- gRPC следует рассматривать как внутренний транспорт между сервисами, а не как транспорт между клиентом и gateway.
- Для long-running gRPC workers сохраняется требование не переносить mutable state между requests.

## EN

### Title

Backend service architecture with nginx API Gateway, main Symfony service, and internal gRPC.

### Task Description

Describe and prepare the backend architecture foundation where nginx is the external entrypoint and the main service under the `symfony` directory owns Auth, admin, and news.

Clients call nginx over REST/HTTP. nginx validates authorization through an Auth endpoint exposed by the main Symfony service, receives identity headers, and proxies the original REST/HTTP request to the target backend service. gRPC is used only for internal service-to-service calls between backend services, not as the external gateway transport.

### Goal

Capture the target backend service architecture before implementation so that the main Symfony service, Catalog Service, Order/Cart Service, and internal integrations can be developed with a shared model for incoming HTTP, nginx-based authorization, and internal gRPC communication.

### Architecture Context

The system consists of the following components:

- nginx API Gateway - external REST/HTTP entrypoint for frontend/client applications.
- Symfony Main Service - the main service under `symfony`, responsible for Auth, admin, and news.
- Catalog Service - separate catalog backend service.
- Order/Cart Service - separate cart and order backend service.

Current implementation status:

- `catalog-service` has been created as a separate Symfony application for Catalog Service;
- `cart-service` has been created as a separate Symfony application for Order/Cart Service;
- both services are currently captured as the infrastructure foundation for future REST/HTTP endpoints, internal gRPC integrations, and independent data ownership.

nginx API Gateway is responsible for:

- accepting REST/HTTP requests from frontend/client applications;
- performing auth check through the Auth endpoint of the main Symfony service;
- receiving identity headers from the Auth/Symfony service;
- proxying the original REST/HTTP request to the target backend service;
- passing trusted `X-User-Id` and `X-User-Role` headers to downstream services;
- preventing direct external access to internal services bypassing the gateway.

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
4. nginx creates trusted `X-User-Id` and `X-User-Role` identity headers.
5. nginx proxies the original REST/HTTP request to the target backend service with trusted identity headers.

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
- Trusted `X-User-Id` and `X-User-Role` headers from gateway to backend services are documented.
- Catalog Service and Order/Cart Service are documented as receiving external traffic from nginx over REST/HTTP.
- gRPC is documented as internal service-to-service transport, not as the external gateway transport.
- Local async/event-driven architecture of the main Symfony service through RabbitMQ is documented.
- Database ownership per service is documented.
- The task provides a foundation for implementing nginx routing, auth endpoint, identity headers, REST endpoints, and internal gRPC contracts.

### Technical Approach

- Start by describing nginx routes and auth check rules for protected endpoint groups.
- Implement an Auth endpoint in Symfony Main Service that nginx can use for token validation.
- Do not treat `X-User-Id` and `X-User-Role` sent by an external client as trusted headers.
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
- Verify that with a valid token the downstream service receives `X-User-Id` and `X-User-Role`.
- Verify that with an invalid token nginx does not proxy the request to the downstream service.
- Verify that an external client cannot spoof trusted identity headers.
- Verify that Catalog Service and Order/Cart Service are externally reachable only through nginx routing.
- Verify that internal service-to-service scenarios use gRPC contracts where required.
- Verify that async/event-driven notification processing of the main Symfony service uses RabbitMQ locally inside that service.

### Notes

- In this architecture nginx is the main API Gateway, but it does not own authorization business logic; it delegates validation to the Auth/Symfony service.
- Symfony Main Service remains the central service for Auth, admin, news, and current news-related notifications.
- The event-driven approach is already used inside Symfony Main Service for asynchronous processing through RabbitMQ within one service. In future iterations, this model can be expanded into a general notification subsystem and inter-service events.
- gRPC should be treated as an internal service-to-service transport, not as client-to-gateway transport.
- Long-running gRPC workers still must not leak mutable state between requests.
