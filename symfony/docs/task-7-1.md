# Task 7.1

## RU

### Название
Разработка backend-сервисов Catalog Service и Product Service

### Описание задачи
Подзадача детализирует часть архитектурной задачи `Task 7` и описывает разработку отдельных backend-сервисов для каталога и товаров.

Необходимо спроектировать и реализовать сервисы `Catalog Service` и `Product Service` как самостоятельные backend-компоненты, которые получают внешний REST/HTTP-трафик через `nginx` и используют gRPC только для внутреннего взаимодействия между сервисами.

### Цель
Выделить ответственность каталога и товаров в отдельные сервисы, чтобы основной Symfony-сервис не разрастался за пределы своих зон ответственности: Auth, админка, новости и текущая асинхронная обработка уведомлений.

### Архитектурный контекст
`nginx` остается внешним API Gateway. Клиент отправляет REST/HTTP-запросы в `nginx`, `nginx` выполняет проверку авторизации через основной Symfony-сервис и проксирует исходный REST/HTTP-запрос в нужный backend-сервис с доверенными заголовками:

- `X-User-Id`
- `X-User-Role`

`Catalog Service` отвечает за структуру каталога:

- категории;
- иерархию и навигацию каталога;
- публичные разделы каталога;
- подборки и витрины, если они относятся к структуре каталога;
- REST/HTTP API для внешнего доступа через `nginx`;
- gRPC-контракты для внутреннего взаимодействия с другими сервисами.

`Product Service` отвечает за данные товаров:

- карточки товаров;
- атрибуты и характеристики;
- цены и остатки в рамках выбранной модели владения данными;
- поиск и фильтрацию товаров;
- REST/HTTP API для внешнего доступа через `nginx`;
- gRPC-контракты для внутреннего взаимодействия с другими сервисами.

gRPC не является внешним транспортом для клиентов. Он используется только между backend-сервисами, например когда корзине, заказам или каталогу нужны данные товаров без прямого доступа к чужой базе данных.

Каждый сервис должен владеть своей моделью данных и не читать таблицы другого сервиса напрямую.

### Критерии приемки
- Описаны границы ответственности `Catalog Service` и `Product Service`.
- Определено, какие REST/HTTP endpoints нужны для внешнего доступа через `nginx`.
- Определено, какие gRPC-методы нужны для внутреннего взаимодействия между сервисами.
- Зафиксировано владение данными и отсутствие прямого доступа к базе данных другого сервиса.
- Учтена передача доверенного пользовательского контекста через `X-User-Id` и `X-User-Role`.
- Для долгоживущих RoadRunner/gRPC worker-процессов предусмотрена reset-логика после обработки request.
- Подготовлены минимальные тесты для HTTP API, gRPC-контрактов и сервисной бизнес-логики.

### Технический подход
- Начать с контрактов: описать REST/HTTP endpoints и внутренние gRPC RPC-методы.
- Разделить модели данных каталога и товаров по владельцам.
- Не связывать сервисы через общую базу данных.
- Использовать `nginx` как внешний REST/HTTP gateway с предварительной авторизацией через основной Symfony-сервис.
- Использовать gRPC только для backend-to-backend вызовов.
- Для Symfony-based сервисов использовать Symfony Runtime и RoadRunner runner-подход, согласованный в предыдущем контексте по gRPC worker.
- После каждого gRPC request сбрасывать состояние долгоживущего worker через Symfony reset-механизм.

### Как тестировать
- Проверить REST/HTTP endpoints сервисов через `nginx` с доверенными identity headers.
- Проверить, что сервисы не принимают пользовательскую идентичность из недоверенного внешнего источника напрямую.
- Проверить gRPC-вызовы между сервисами на уровне контрактов.
- Проверить, что `Catalog Service` не обращается напрямую к базе `Product Service`, и наоборот.
- Проверить reset-поведение worker после нескольких последовательных gRPC request.

### Примечания
- Это подзадача к `Task 7`, а не отдельная новая верхнеуровневая архитектурная задача.
- Основной Symfony-сервис остается владельцем Auth, админки, новостей и текущей локальной event-driven обработки уведомлений.
- RabbitMQ в текущем контексте остается локальной асинхронной инфраструктурой основного Symfony-сервиса; межсервисную event-driven шину отдельно не внедряем в рамках этой подзадачи.

## EN

### Title
Develop Catalog Service and Product Service backend services

### Task Description
This subtask details part of `Task 7` and describes the development of separate backend services for catalog and product responsibilities.

The goal is to design and implement `Catalog Service` and `Product Service` as standalone backend components. They receive external REST/HTTP traffic through `nginx` and use gRPC only for internal service-to-service communication.

### Goal
Separate catalog and product responsibilities into dedicated services so the main Symfony service does not grow beyond its intended scope: Auth, admin, news, and the current asynchronous notification processing.

### Architecture Context
`nginx` remains the external API Gateway. The client sends REST/HTTP requests to `nginx`, `nginx` performs authorization through the main Symfony service, and then proxies the original REST/HTTP request to the target backend service with trusted headers:

- `X-User-Id`
- `X-User-Role`

`Catalog Service` owns catalog structure:

- categories;
- catalog hierarchy and navigation;
- public catalog sections;
- collections and storefront blocks when they belong to catalog structure;
- REST/HTTP API for external access through `nginx`;
- gRPC contracts for internal service-to-service communication.

`Product Service` owns product data:

- product cards;
- attributes and specifications;
- prices and stock according to the selected data ownership model;
- product search and filtering;
- REST/HTTP API for external access through `nginx`;
- gRPC contracts for internal service-to-service communication.

gRPC is not an external client transport. It is used only between backend services, for example when cart, order, or catalog flows need product data without direct access to another service database.

Each service must own its data model and must not read another service database tables directly.

### Acceptance Criteria
- `Catalog Service` and `Product Service` responsibility boundaries are documented.
- Required REST/HTTP endpoints for external access through `nginx` are defined.
- Required gRPC methods for internal service-to-service communication are defined.
- Data ownership is documented, including the rule against direct access to another service database.
- Trusted user context propagation through `X-User-Id` and `X-User-Role` is accounted for.
- Long-running RoadRunner/gRPC workers have reset logic after request handling.
- Minimal tests are planned for HTTP API, gRPC contracts, and service business logic.

### Technical Approach
- Start from contracts: define REST/HTTP endpoints and internal gRPC RPC methods.
- Split catalog and product data models by ownership.
- Do not couple services through a shared database.
- Use `nginx` as the external REST/HTTP gateway with preliminary authorization through the main Symfony service.
- Use gRPC only for backend-to-backend calls.
- For Symfony-based services, use Symfony Runtime and the RoadRunner runner approach agreed in the previous gRPC worker context.
- Reset long-running worker state after each gRPC request through the Symfony reset mechanism.

### How To Test
- Verify service REST/HTTP endpoints through `nginx` with trusted identity headers.
- Verify services do not accept user identity directly from an untrusted external source.
- Verify gRPC calls between services at the contract level.
- Verify `Catalog Service` does not directly access the `Product Service` database, and vice versa.
- Verify worker reset behavior after multiple sequential gRPC requests.

### Notes
- This is a subtask of `Task 7`, not a separate top-level architecture task.
- The main Symfony service remains responsible for Auth, admin, news, and the current local event-driven notification processing.
- RabbitMQ remains local asynchronous infrastructure of the main Symfony service in the current context; an inter-service event-driven bus is not introduced in this subtask.
