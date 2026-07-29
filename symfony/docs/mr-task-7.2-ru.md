# Лог Результата MR Task 7.2

## Обзор

Этот документ описывает видимый результат merge request 10.

Merge request: https://github.com/ivanserg0692/symfony2026/pull/10

Файл задачи: [task-7.2.md](task-7.2.md)

Merge request продолжает Task 7.2 и фокусируется на endpoints Cart/Order Service, которым нужна внутренняя gRPC-интеграция с Catalog Service.

## Scope

Ожидаемый результат включает:

- gRPC-интеграцию с Catalog Service для Cart/Order flows;
- только внутреннее service-to-service взаимодействие;
- внешний REST/HTTP доступ через nginx/API layer;
- неизменное поведение endpoints, которые уже готовы без gRPC.

## Целевые endpoints

В рамках задачи покрываются:

- `POST /api/cart/items` - проверка существования товара, доступности, цены и остатков перед добавлением позиции в корзину.
- `PATCH /api/cart/items/{itemId}` - проверка запрошенного количества через Catalog Service перед обновлением позиции корзины.
- `GET /api/orders/{orderId}` - сначала проверка ownership заказа, затем подгрузка нужных catalog details/snapshots через gRPC.

## Вне Scope

Следующие endpoints отслеживаются отдельно:

- `POST /api/orders` - требует отдельного RabbitMQ/event-driven flow.
- `POST /api/orders/{orderId}/cancel` - требует отдельного cancel flow.

Уже готовые endpoints без gRPC остаются вне scope этой реализации:

- `GET /api/cart`;
- `DELETE /api/cart/items/{itemId}`;
- `DELETE /api/cart`;
- `GET /api/orders`.

## Security Notes

`GET /api/orders/{orderId}` не должен вызывать Catalog Service до проверки, что заказ принадлежит текущему пользователю. Это защищает от подгрузки или раскрытия catalog-related данных для чужого заказа.

## Скриншоты

Catalog gRPC performance profiler:

![Catalog gRPC performance profiler](../../catalog-service/docs/images/grpc-performance.png)

Order product snapshot gRPC flow:

<!-- plantuml src="plantuml/grpc-contracts/order-snapshot-flow.puml" alt="Order product snapshot gRPC flow" out="images/plantuml/grpc-contracts/order-snapshot-flow.png" -->
![Order product snapshot gRPC flow](images/plantuml/grpc-contracts/order-snapshot-flow.png)
<!-- /plantuml -->

## Обновления

Заметки по реализации будут добавляться сюда отдельными датированными секциями.

### 2026-07-29

Task 7.2 доведена до состояния готового backend MR scope.

Добавлено:

- Cart/Order Service вызывает Catalog Service по gRPC для cart add/update validation и order detail snapshot loading.
- `GET /api/orders/{orderId}` сначала проверяет ownership заказа, затем собирает `productSnapshotId` только из `OrderItems` разрешенного заказа и делает один batch-вызов `GetProductSnapshots`.
- `GET /api/orders` остается lightweight paginated list без полной подгрузки items и snapshots.
- Product snapshot data возвращается через order detail response и не открывается через standalone public REST endpoint.
- Исторический ответ заказа строится из snapshot data; текущий Product не используется для восстановления product fields заказа.
- Цены заказа остаются в `OrderItem`, поэтому отсутствие price fields в snapshot не является проблемой.
- Для Catalog gRPC добавлены performance logging и profiler view, чтобы видеть handler time, profiler save time и total processing time.
- Cart add/update и checkout paths усилены transaction/locking behavior вокруг операций, которые читают и меняют состояние корзины.

Связанная документация:

- gRPC contracts: [grpc-contracts/README.md](../../grpc-contracts/README.md).
- Proto source: [inventory.proto](../../grpc-contracts/catalog/v1/inventory.proto).
