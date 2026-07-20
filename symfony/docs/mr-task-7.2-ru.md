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

Не применимо для backend gRPC integration work.

## Обновления

Заметки по реализации будут добавляться сюда отдельными датированными секциями.
