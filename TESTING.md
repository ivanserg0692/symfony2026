# Testing

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [English](#english)
  - [Docker](#docker)
  - [Catalog Service](#catalog-service)
  - [Cart Service](#cart-service)
  - [Protobuf Generation Before Tests](#protobuf-generation-before-tests)
  - [Known Notes](#known-notes)
- [Русский](#%D1%80%D1%83%D1%81%D1%81%D0%BA%D0%B8%D0%B9)
  - [Docker](#docker-1)
  - [Catalog Service](#catalog-service-1)
  - [Cart Service](#cart-service-1)
  - [Генерация protobuf перед тестами](#%D0%B3%D0%B5%D0%BD%D0%B5%D1%80%D0%B0%D1%86%D0%B8%D1%8F-protobuf-%D0%BF%D0%B5%D1%80%D0%B5%D0%B4-%D1%82%D0%B5%D1%81%D1%82%D0%B0%D0%BC%D0%B8)
  - [Известные особенности](#%D0%B8%D0%B7%D0%B2%D0%B5%D1%81%D1%82%D0%BD%D1%8B%D0%B5-%D0%BE%D1%81%D0%BE%D0%B1%D0%B5%D0%BD%D0%BD%D0%BE%D1%81%D1%82%D0%B8)

<!-- END doctoc -->

## English

### Docker

Run PHP and Composer commands through the project Docker services.

Run commands from the repository root, the directory that contains `.git`.

### Catalog Service

Create the test database if it does not exist:

```bash
docker compose run --rm catalog-cli php bin/console doctrine:database:create --env=test --if-not-exists
```

Run the Catalog service tests:

```bash
docker compose run --rm -e SYMFONY_DEPRECATIONS_HELPER=weak catalog-cli vendor/bin/simple-phpunit -c phpunit.dist.xml
```

The `SYMFONY_DEPRECATIONS_HELPER=weak` flag keeps known indirect Doctrine deprecations from failing the test command while still printing them.

### Cart Service

Run the Cart service tests:

```bash
docker compose run --rm cart-cli vendor/bin/simple-phpunit -c phpunit.dist.xml
```

If `simple-phpunit` is not available, install the Symfony PHPUnit Bridge in the service through Docker:

```bash
docker compose run --rm cart-cli composer require --dev symfony/phpunit-bridge
```

### Protobuf Generation Before Tests

When `inventory.proto` changes, regenerate the PHP protobuf classes before running tests that use generated gRPC code.

Run protobuf commands from the `grpc-contracts` directory inside the repository:

```bash
cd grpc-contracts
buf lint
buf generate --template buf.gen.catalog-server.yaml
buf generate --template buf.gen.cart-client.yaml
```

Then return to the repository root and refresh Composer autoloads:

```bash
cd ..
docker compose run --rm catalog-cli composer dump-autoload
docker compose run --rm cart-cli composer dump-autoload
```

### Known Notes

The Catalog tests currently run through `vendor/bin/simple-phpunit`, not `bin/phpunit`.

Without `SYMFONY_DEPRECATIONS_HELPER=weak`, the Catalog test command may return a non-zero exit code because Doctrine reports indirect deprecations for existing many-to-many mapping metadata.

## Русский

### Docker

PHP и Composer команды запускаются через Docker-сервисы проекта.

Команды выполняются из корня репозитория, то есть из директории, где лежит `.git`.

### Catalog Service

Создать тестовую базу, если её ещё нет:

```bash
docker compose run --rm catalog-cli php bin/console doctrine:database:create --env=test --if-not-exists
```

Запустить тесты Catalog service:

```bash
docker compose run --rm -e SYMFONY_DEPRECATIONS_HELPER=weak catalog-cli vendor/bin/simple-phpunit -c phpunit.dist.xml
```

Флаг `SYMFONY_DEPRECATIONS_HELPER=weak` не даёт известным indirect deprecations Doctrine валить команду тестов, но оставляет их в выводе.

### Cart Service

Запустить тесты Cart service:

```bash
docker compose run --rm cart-cli vendor/bin/simple-phpunit -c phpunit.dist.xml
```

Если `simple-phpunit` недоступен, установи Symfony PHPUnit Bridge в сервис через Docker:

```bash
docker compose run --rm cart-cli composer require --dev symfony/phpunit-bridge
```

### Генерация protobuf перед тестами

Если менялся `inventory.proto`, перед тестами, которые используют сгенерированный gRPC-код, нужно перегенерировать PHP protobuf-классы.

Protobuf-команды выполняются из директории `grpc-contracts` внутри репозитория:

```bash
cd grpc-contracts
buf lint
buf generate --template buf.gen.catalog-server.yaml
buf generate --template buf.gen.cart-client.yaml
```

После этого вернуться в корень репозитория и обновить Composer autoload:

```bash
cd ..
docker compose run --rm catalog-cli composer dump-autoload
docker compose run --rm cart-cli composer dump-autoload
```

### Известные особенности

Тесты Catalog сейчас запускаются через `vendor/bin/simple-phpunit`, а не через `bin/phpunit`.

Без `SYMFONY_DEPRECATIONS_HELPER=weak` команда тестов Catalog может завершаться с ненулевым кодом, потому что Doctrine сообщает indirect deprecations для существующего many-to-many mapping metadata.
