# gRPC Contracts

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [English](#english)
  - [Purpose](#purpose)
  - [Catalog Inventory Service](#catalog-inventory-service)
  - [Product Snapshot Access Rule](#product-snapshot-access-rule)
  - [Generated PHP Files](#generated-php-files)
  - [Generate Catalog Server Files](#generate-catalog-server-files)
  - [Generate Cart Client Files](#generate-cart-client-files)
  - [Validate Contracts](#validate-contracts)
  - [Refresh Composer Autoload](#refresh-composer-autoload)
  - [Required Autoload Mapping](#required-autoload-mapping)
- [Русский](#%D1%80%D1%83%D1%81%D1%81%D0%BA%D0%B8%D0%B9)
  - [Назначение](#%D0%BD%D0%B0%D0%B7%D0%BD%D0%B0%D1%87%D0%B5%D0%BD%D0%B8%D0%B5)
  - [Catalog Inventory Service](#catalog-inventory-service-1)
  - [Правило Доступа К Product Snapshots](#%D0%BF%D1%80%D0%B0%D0%B2%D0%B8%D0%BB%D0%BE-%D0%B4%D0%BE%D1%81%D1%82%D1%83%D0%BF%D0%B0-%D0%BA-product-snapshots)
  - [Сгенерированные PHP-файлы](#%D1%81%D0%B3%D0%B5%D0%BD%D0%B5%D1%80%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D0%BD%D0%BD%D1%8B%D0%B5-php-%D1%84%D0%B0%D0%B9%D0%BB%D1%8B)
  - [Генерация Серверных Файлов Каталога](#%D0%B3%D0%B5%D0%BD%D0%B5%D1%80%D0%B0%D1%86%D0%B8%D1%8F-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80%D0%BD%D1%8B%D1%85-%D1%84%D0%B0%D0%B9%D0%BB%D0%BE%D0%B2-%D0%BA%D0%B0%D1%82%D0%B0%D0%BB%D0%BE%D0%B3%D0%B0)
  - [Генерация Клиентских Файлов Корзины](#%D0%B3%D0%B5%D0%BD%D0%B5%D1%80%D0%B0%D1%86%D0%B8%D1%8F-%D0%BA%D0%BB%D0%B8%D0%B5%D0%BD%D1%82%D1%81%D0%BA%D0%B8%D1%85-%D1%84%D0%B0%D0%B9%D0%BB%D0%BE%D0%B2-%D0%BA%D0%BE%D1%80%D0%B7%D0%B8%D0%BD%D1%8B)
  - [Проверка Контрактов](#%D0%BF%D1%80%D0%BE%D0%B2%D0%B5%D1%80%D0%BA%D0%B0-%D0%BA%D0%BE%D0%BD%D1%82%D1%80%D0%B0%D0%BA%D1%82%D0%BE%D0%B2)
  - [Обновление Composer Autoload](#%D0%BE%D0%B1%D0%BD%D0%BE%D0%B2%D0%BB%D0%B5%D0%BD%D0%B8%D0%B5-composer-autoload)
  - [Нужный Autoload Mapping](#%D0%BD%D1%83%D0%B6%D0%BD%D1%8B%D0%B9-autoload-mapping)

<!-- END doctoc -->

## English

### Purpose

This directory contains shared gRPC contracts for project services.

The current contract is:

- `catalog/v1/inventory.proto` - stock availability API exposed by `catalog-service`.

### Catalog Inventory Service

`catalog.v1.InventoryService` is an internal service-to-service API exposed by `catalog-service` and consumed by `cart-service`.

The current RPC methods are:

- `CheckStock` - checks whether a requested quantity is available for one product and returns total stock plus per-store stock.
- `GetProductPrices` - returns current checkout prices for a batch of product ids.
- `DeductStocks` - deducts stock for one checkout operation and returns per-product deductions with created `product_snapshot_id` values.
- `GetProductSnapshots` - returns historical product snapshots by unique snapshot ids in one batch.

### Product Snapshot Access Rule

Product snapshots belong to `catalog-service`, but they are not exposed through a standalone public REST endpoint.

`GetProductSnapshots` must be called only after `cart-service` has verified access to the order:

1. authenticated owner;
2. owned `Order`;
3. `OrderItems` from that order;
4. referenced `productSnapshotId` values;
5. one batch `GetProductSnapshots` gRPC request.

`catalog-service` does not receive a user id and does not authorize orders. It only returns snapshots requested by trusted backend services.

Historical order responses must use snapshot data instead of current product data. Current product rename, update, disable, price change, or deletion must not change historical order product output. Order prices are stored in `OrderItem`, so product snapshots do not need to duplicate order price fields.

### Generated PHP Files

PHP files are generated separately for each service role:

- catalog server files: `../catalog-service/src/Grpc/Generated/`
- cart client files: `../cart-service/src/Grpc/Generated/`

The generated files are not placed in this directory. This directory stores only source contracts and Buf generation configs.

### Generate Catalog Server Files

Run from the repository root:

```bash
cd grpc-contracts
buf generate --template buf.gen.catalog-server.yaml
```

This generates protobuf message classes and the RoadRunner gRPC server interface for `catalog-service`.

### Generate Cart Client Files

Run from the repository root:

```bash
cd grpc-contracts
buf generate --template buf.gen.cart-client.yaml
```

This generates protobuf message classes and the native PHP gRPC client stub for `cart-service`.

### Validate Contracts

Run from the repository root:

```bash
cd grpc-contracts
buf lint
```

### Refresh Composer Autoload

After generating PHP files, refresh Composer autoload in the affected service.

For catalog:

```bash
docker compose run --rm catalog-cli composer dump-autoload
```

For cart:

```bash
docker compose run --rm cart-cli composer dump-autoload
```

### Required Autoload Mapping

Each service that uses generated catalog classes must include this PSR-4 mapping in its `composer.json`:

```json
"Grpc\\Catalog\\V1\\": "src/Grpc/Generated/Grpc/Catalog/V1/"
```

## Русский

### Назначение

Эта директория содержит общие gRPC-контракты для сервисов проекта.

Текущий контракт:

- `catalog/v1/inventory.proto` - API проверки остатков, который предоставляет `catalog-service`.

### Catalog Inventory Service

`catalog.v1.InventoryService` - внутренний service-to-service API, который предоставляет `catalog-service` и использует `cart-service`.

Текущие RPC-методы:

- `CheckStock` - проверяет доступность запрошенного количества для одного товара и возвращает общий остаток вместе с остатками по складам.
- `GetProductPrices` - возвращает текущие checkout-цены для batch списка product ids.
- `DeductStocks` - списывает остатки для одной checkout operation и возвращает итоговые списания по товарам вместе с созданными `product_snapshot_id`.
- `GetProductSnapshots` - возвращает исторические product snapshots по snapshot ids одним batch-запросом.

### Правило Доступа К Product Snapshots

Product snapshots принадлежат `catalog-service`, но не открываются через отдельный публичный REST endpoint.

`GetProductSnapshots` должен вызываться только после того, как `cart-service` проверил доступ к заказу:

1. authenticated owner;
2. owned `Order`;
3. `OrderItems` из этого заказа;
4. связанные `productSnapshotId`;
5. один batch gRPC request `GetProductSnapshots`.

`catalog-service` не получает user id и не авторизует заказы. Он только возвращает snapshots, которые запросил trusted backend service.

Исторический ответ заказа должен использовать snapshot data, а не текущие product data. Переименование, обновление, отключение, изменение цены или удаление текущего товара не должны менять исторический product output заказа. Цены заказа хранятся в `OrderItem`, поэтому product snapshots не обязаны дублировать price fields заказа.

### Сгенерированные PHP-файлы

PHP-файлы генерируются отдельно под роль конкретного сервиса:

- серверные файлы каталога: `../catalog-service/src/Grpc/Generated/`
- клиентские файлы корзины: `../cart-service/src/Grpc/Generated/`

Сгенерированные файлы не хранятся в этой директории. Здесь лежат только исходные контракты и Buf-конфиги для генерации.

### Генерация Серверных Файлов Каталога

Запускать из корня репозитория:

```bash
cd grpc-contracts
buf generate --template buf.gen.catalog-server.yaml
```

Команда генерирует protobuf message-классы и серверный RoadRunner gRPC-интерфейс для `catalog-service`.

### Генерация Клиентских Файлов Корзины

Запускать из корня репозитория:

```bash
cd grpc-contracts
buf generate --template buf.gen.cart-client.yaml
```

Команда генерирует protobuf message-классы и нативный PHP gRPC client stub для `cart-service`.

### Проверка Контрактов

Запускать из корня репозитория:

```bash
cd grpc-contracts
buf lint
```

### Обновление Composer Autoload

После генерации PHP-файлов нужно обновить Composer autoload в затронутом сервисе.

Для каталога:

```bash
docker compose run --rm catalog-cli composer dump-autoload
```

Для корзины:

```bash
docker compose run --rm cart-cli composer dump-autoload
```

### Нужный Autoload Mapping

Каждый сервис, который использует сгенерированные catalog-классы, должен иметь такой PSR-4 mapping в `composer.json`:

```json
"Grpc\\Catalog\\V1\\": "src/Grpc/Generated/Grpc/Catalog/V1/"
```
