# Testing

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
