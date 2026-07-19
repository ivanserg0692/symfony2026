# gRPC Contracts

## English

### Purpose

This directory contains shared gRPC contracts for project services.

The current contract is:

- `catalog/v1/inventory.proto` - stock availability API exposed by `catalog-service`.

### Generated PHP Files

PHP files are generated separately for each service role:

- catalog server files: `../catalog-service/src/Grpc/Generated/`
- cart client files: `../cart-service/src/Grpc/Generated/`

The generated files are not placed in this directory. This directory stores only source contracts and Buf generation configs.

### Generate Catalog Server Files

Run from this directory:

```bash
cd app/grpc-contracts
buf generate --template buf.gen.catalog-server.yaml
```

This generates protobuf message classes and the RoadRunner gRPC server interface for `catalog-service`.

### Generate Cart Client Files

Run from this directory:

```bash
cd app/grpc-contracts
buf generate --template buf.gen.cart-client.yaml
```

This generates protobuf message classes and the native PHP gRPC client stub for `cart-service`.

### Validate Contracts

Run from this directory:

```bash
cd app/grpc-contracts
buf lint
```

### Refresh Composer Autoload

After generating PHP files, refresh Composer autoload in the affected service.

For catalog:

```bash
cd app
docker compose run --rm catalog-cli composer dump-autoload
```

For cart:

```bash
cd app
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

### Сгенерированные PHP-файлы

PHP-файлы генерируются отдельно под роль конкретного сервиса:

- серверные файлы каталога: `../catalog-service/src/Grpc/Generated/`
- клиентские файлы корзины: `../cart-service/src/Grpc/Generated/`

Сгенерированные файлы не хранятся в этой директории. Здесь лежат только исходные контракты и Buf-конфиги для генерации.

### Генерация Серверных Файлов Каталога

Запускать из этой директории:

```bash
cd app/grpc-contracts
buf generate --template buf.gen.catalog-server.yaml
```

Команда генерирует protobuf message-классы и серверный RoadRunner gRPC-интерфейс для `catalog-service`.

### Генерация Клиентских Файлов Корзины

Запускать из этой директории:

```bash
cd app/grpc-contracts
buf generate --template buf.gen.cart-client.yaml
```

Команда генерирует protobuf message-классы и нативный PHP gRPC client stub для `cart-service`.

### Проверка Контрактов

Запускать из этой директории:

```bash
cd app/grpc-contracts
buf lint
```

### Обновление Composer Autoload

После генерации PHP-файлов нужно обновить Composer autoload в затронутом сервисе.

Для каталога:

```bash
cd app
docker compose run --rm catalog-cli composer dump-autoload
```

Для корзины:

```bash
cd app
docker compose run --rm cart-cli composer dump-autoload
```

### Нужный Autoload Mapping

Каждый сервис, который использует сгенерированные catalog-классы, должен иметь такой PSR-4 mapping в `composer.json`:

```json
"Grpc\\Catalog\\V1\\": "src/Grpc/Generated/Grpc/Catalog/V1/"
```
