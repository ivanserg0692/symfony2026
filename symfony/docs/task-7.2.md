# Task 7.2

## RU

### Название

Доработка Cart/Order endpoints, которым нужны gRPC-интеграции.

### Описание задачи

Доработать оставшиеся endpoints Cart/Order Service, где для корректной бизнес-логики требуется обращение к Catalog Service через внутренний gRPC transport.

Задача является продолжением Task 7: базовая архитектура сервисов уже зафиксирована, `catalog-service` и `cart-service` созданы как отдельные Symfony service applications, а gRPC используется только для внутреннего service-to-service взаимодействия.

### Цель

Добавить gRPC-интеграции в Cart/Order Service только там, где сервису нужны данные Catalog Service для проверки товара, остатков, цены или безопасной подгрузки данных заказа после проверки доступа.

### Архитектурный контекст

Текущий статус endpoints:

- `GET /api/cart` - готово без gRPC.
- `POST /api/cart/items` - требует gRPC.
- `PATCH /api/cart/items/{itemId}` - требует gRPC.
- `DELETE /api/cart/items/{itemId}` - готово без gRPC.
- `DELETE /api/cart` - готово без gRPC.
- `POST /api/orders` - требует отдельной доработки RabbitMQ/event-driven flow.
- `GET /api/orders` - готово без gRPC.
- `GET /api/orders/{orderId}` - требует gRPC для подгрузки данных после проверки доступа.
- `POST /api/orders/{orderId}/cancel` - пока не реализовано, потенциально требует отдельного cancel flow и event-driven обработки.

gRPC scope для этой задачи:

- `POST /api/cart/items` должен обращаться в Catalog Service перед добавлением товара в корзину.
- `PATCH /api/cart/items/{itemId}` должен обращаться в Catalog Service перед изменением количества товара.
- `GET /api/orders/{orderId}` должен сначала проверить, что заказ принадлежит текущему пользователю, и только после этого выполнять gRPC-запросы для подгрузки нужных catalog details/snapshots.

Связанные, но отдельные направления:

- `POST /api/orders` требует отдельной проработки RabbitMQ/event-driven сценариев после успешного создания заказа.
- `POST /api/orders/{orderId}/cancel` требует отдельной реализации cancel use case, проверки статуса заказа и возможной публикации событий.

### Критерии приемки

- `POST /api/cart/items` использует gRPC для проверки существования товара, доступности, цены и остатков перед добавлением.
- `PATCH /api/cart/items/{itemId}` использует gRPC для проверки допустимого количества перед обновлением позиции корзины.
- `GET /api/orders/{orderId}` сначала проверяет доступ по текущему пользователю, затем подгружает catalog data через gRPC только для разрешенного заказа.
- Внешний REST/HTTP контракт endpoints остается за nginx/API layer; gRPC не становится внешним transport.
- Ошибки Catalog Service корректно преобразуются в REST/HTTP ошибки Cart/Order Service.
- Нельзя получить данные чужого заказа или связанные catalog details по чужому `orderId`.
- Уже готовые endpoints без gRPC не усложняются лишней межсервисной интеграцией.

### Технический подход

- Описать или переиспользовать proto-контракт Catalog Service для проверки товара и получения данных по product ids.
- В Cart/Order Service добавить gRPC client слой для Catalog Service.
- Для `POST /api/cart/items` перед сохранением cart item проверить product availability через gRPC.
- Для `PATCH /api/cart/items/{itemId}` перед обновлением quantity проверить актуальные ограничения через gRPC.
- Для `GET /api/orders/{orderId}` сначала найти заказ локально и проверить ownership по trusted `X-User-Id`.
- Выполнять gRPC-подгрузку данных заказа только после успешной access check.
- Разделить ошибки validation/business rules и ошибки недоступности Catalog Service.
- Не добавлять RabbitMQ/event-driven реализацию в рамках этой задачи, кроме фиксации будущего направления.

### Как тестировать

- Проверить успешное добавление товара в корзину при валидном ответе Catalog Service.
- Проверить отказ при добавлении несуществующего или недоступного товара.
- Проверить отказ при добавлении количества больше доступного остатка.
- Проверить успешное изменение quantity через `PATCH /api/cart/items/{itemId}`.
- Проверить отказ при недопустимом quantity.
- Проверить, что `GET /api/orders/{orderId}` возвращает данные только владельцу заказа.
- Проверить, что gRPC-запросы для деталей заказа не выполняются до успешной ownership/access check.
- Проверить mapping gRPC errors в корректные REST/HTTP responses.
- Проверить, что готовые endpoints без gRPC продолжают работать как раньше.

### Примечания

- Для исторических order snapshots источник истины должен быть локальным состоянием Order/Cart Service, если snapshot был сохранен при создании заказа.
- gRPC-подгрузка в `GET /api/orders/{orderId}` нужна только для тех данных, которые действительно должны приходить из Catalog Service.
- Security-sensitive порядок важен: access check сначала, внешняя подгрузка данных потом.
- RabbitMQ/event-driven flow для создания и отмены заказов лучше вынести в отдельную задачу.

## EN

### Title

Complete Cart/Order endpoints that require gRPC integrations.

### Task Description

Complete the remaining Cart/Order Service endpoints where correct business behavior requires calling Catalog Service through the internal gRPC transport.

This task continues Task 7: the base service architecture is already documented, `catalog-service` and `cart-service` have been created as separate Symfony service applications, and gRPC is used only for internal service-to-service communication.

### Goal

Add gRPC integrations to Cart/Order Service only where the service needs Catalog Service data to validate products, stock, price, or safely load order-related data after access checks.

### Architecture Context

Current endpoint status:

- `GET /api/cart` - done without gRPC.
- `POST /api/cart/items` - requires gRPC.
- `PATCH /api/cart/items/{itemId}` - requires gRPC.
- `DELETE /api/cart/items/{itemId}` - done without gRPC.
- `DELETE /api/cart` - done without gRPC.
- `POST /api/orders` - requires separate RabbitMQ/event-driven flow work.
- `GET /api/orders` - done without gRPC.
- `GET /api/orders/{orderId}` - requires gRPC to load data after access check.
- `POST /api/orders/{orderId}/cancel` - not implemented yet, likely requires a separate cancel flow and event-driven processing.

gRPC scope for this task:

- `POST /api/cart/items` must call Catalog Service before adding a product to the cart.
- `PATCH /api/cart/items/{itemId}` must call Catalog Service before changing item quantity.
- `GET /api/orders/{orderId}` must first verify that the order belongs to the current user, and only then perform gRPC calls to load required catalog details/snapshots.

Related but separate work:

- `POST /api/orders` requires separate RabbitMQ/event-driven scenarios after successful order creation.
- `POST /api/orders/{orderId}/cancel` requires a separate cancel use case, order status checks, and possible event publication.

### Acceptance Criteria

- `POST /api/cart/items` uses gRPC to validate product existence, availability, price, and stock before adding an item.
- `PATCH /api/cart/items/{itemId}` uses gRPC to validate allowed quantity before updating the cart item.
- `GET /api/orders/{orderId}` first checks access for the current user, then loads catalog data through gRPC only for an allowed order.
- The external REST/HTTP endpoint contract remains behind nginx/API layer; gRPC does not become an external transport.
- Catalog Service errors are mapped into correct REST/HTTP errors in Cart/Order Service.
- It is not possible to fetch another user's order data or related catalog details by using another user's `orderId`.
- Existing completed endpoints that do not need gRPC are not complicated with unnecessary service-to-service calls.

### Technical Approach

- Define or reuse the Catalog Service proto contract for product validation and product-id based data loading.
- Add a Catalog Service gRPC client layer in Cart/Order Service.
- For `POST /api/cart/items`, validate product availability through gRPC before persisting the cart item.
- For `PATCH /api/cart/items/{itemId}`, validate current quantity constraints through gRPC before updating quantity.
- For `GET /api/orders/{orderId}`, first load the order locally and check ownership through trusted `X-User-Id`.
- Perform gRPC order data loading only after the access check succeeds.
- Separate validation/business-rule errors from Catalog Service availability errors.
- Do not implement RabbitMQ/event-driven behavior in this task beyond documenting it as future related work.

### How To Test

- Verify successful cart item creation when Catalog Service returns valid product data.
- Verify rejection when adding a missing or unavailable product.
- Verify rejection when requested quantity is greater than available stock.
- Verify successful quantity update through `PATCH /api/cart/items/{itemId}`.
- Verify rejection for invalid quantity.
- Verify that `GET /api/orders/{orderId}` returns data only to the owner of the order.
- Verify that gRPC calls for order details are not executed before ownership/access check succeeds.
- Verify gRPC error mapping into correct REST/HTTP responses.
- Verify that already completed endpoints without gRPC keep working as before.

### Notes

- For historical order snapshots, the source of truth should be local Order/Cart Service state if the snapshot was persisted when the order was created.
- gRPC loading in `GET /api/orders/{orderId}` should be used only for data that must actually come from Catalog Service.
- Security-sensitive ordering matters: access check first, external data loading second.
- RabbitMQ/event-driven flow for order creation and cancellation should be handled in a separate task.
