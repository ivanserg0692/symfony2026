# Symfony

## English

This is my Symfony learning project.

The Symfony codebase itself lives in `symfony`.

![Hello in Symfony](symfony/docs/images/hello-in-symfony.png)

## Overview

The project is currently focused on setting up the API foundation in Symfony.

The current implementation path is:
- launch Swagger/OpenAPI for `api/v1`
- stabilize the API entry point and documentation
- build a baseline API for working with news

## Current Status

- Docker-based local environment is configured
- Swagger UI is available for `api/v1`
- OpenAPI specification is generated automatically at runtime
- Baseline JWT authentication endpoints are prepared

## Tech Stack

- PHP `8.4+`
- Symfony `8.0`
- Doctrine ORM `3.6`
- Doctrine Migrations Bundle `4.0`
- Doctrine Fixtures Bundle `4.3`
- PostgreSQL
- Symfony Serializer
- Symfony Security
- Nelmio ApiDoc Bundle `5.9`
- Swagger-PHP `5.8`
- Pagerfanta `4.8`
- Gedmo Doctrine Extensions `3.22`
- StofDoctrineExtensionsBundle `1.15`
- FakerPHP Faker `1.24`
- Docker Compose

## Tasks

- `Task 1` - done
- Task file: [symfony/docs/task-1.md](symfony/docs/task-1.md)
- Merge Request 1: <https://github.com/ivanserg0692/symfony2026/pull/1>
- `Task 2` - in progress
- Task file: [symfony/docs/task-2.md](symfony/docs/task-2.md)

## Run With Docker Compose

Start the runner from the repository root.

```bash
cp .env.example .env
docker compose up --build -d
```

Open a shell inside the container:

```bash
docker compose exec symfony-cli bash
```

Run Symfony CLI commands directly:

```bash
docker compose run --rm symfony-cli symfony --help
docker compose run --rm symfony-cli symfony <command>
```

Run common project commands:

```bash
docker compose run --rm symfony-cli composer install
docker compose run --rm symfony-cli php bin/console about
docker compose run --rm symfony-cli php bin/console cache:clear
```

Initialize JWT keys after dependencies are installed:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli bash bin/init-jwt
```

## Doctrine Database Setup

Start PostgreSQL and create the database if it does not exist yet:

```bash
docker compose -f app/docker-compose.yml up -d database
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console doctrine:database:create --if-not-exists
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console doctrine:migrations:status
```

Generate and apply migrations:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console make:migration
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console doctrine:migrations:migrate --no-interaction
```

Quick database connection check:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console dbal:run-sql "SELECT 1"
```

Start the Symfony local web server:

```bash
docker compose up --build symfony-web
```

Open the app in your browser:

```text
http://localhost:8000
```

## API Documentation

API documentation for `api/v1` is generated automatically at runtime by the Symfony app.

- OpenAPI format: `3.0.0`
- Symfony bundle: `nelmio/api-doc-bundle` `v5.9.5`
- Attribute parser: `zircote/swagger-php` `5.8.3`
- UI renderer: `Swagger UI` `v7.0.0`

Available endpoints:

```text
http://localhost:8000/api/v1/doc
http://localhost:8000/api/v1/doc.json
```

The documentation includes only routes that match `^/api/v1`.

## JWT Authentication

JWT authentication is configured for the API and uses key files stored in `symfony/config/jwt`.

Login attempts are rate-limited: up to `5` failed requests per `15 minutes` for `POST /api/v1/auth/login`.

Before generating the keypair, set `JWT_PASSPHRASE` in `app/.env.local`:

```env
JWT_PASSPHRASE=!ChangeMe!
```

Then initialize the JWT keypair:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli bash bin/init-jwt
```

Available authentication endpoints:

```text
POST http://localhost:8000/api/v1/auth/login
GET  http://localhost:8000/api/v1/auth/me
```

Login request example:

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password123"}'
```

## Changelog

### 2026-04-16

- Added `GET /api/v1/news` list endpoint with pagination and sorting
- Added `ListQueryDto` for query mapping and Swagger schema description
- Added `Pagerfanta` for paginated News list responses
- Updated News query to join author data and serialize fields with `news:read` and `user:read` groups
- Expanded Swagger documentation for News list query parameters and response structure
- Added JWT authentication configuration and `/api/v1/auth/login`, `/api/v1/auth/me` endpoints
- Added `bin/init-jwt` bootstrap command for JWT key generation

### 2026-04-10

- Added Swagger/OpenAPI support for `api/v1`
- Fixed the current API documentation stack in this README
- Added task description for Swagger launch and baseline News API preparation

## Source Directory

Project files are mounted from `symfony` into `/workspace` inside the container.

## Project Structure

- `symfony` contains the Symfony application codebase
- `docker` stores Docker-related files
- `docker-compose.yml` defines the local development container setup

## Included Tools

The runner image includes `symfony`, `php`, and `composer`.

## Git Identity

Set your git identity in `.env` for git operations inside the container:

```env
GIT_AUTHOR_NAME="Your Name"
GIT_AUTHOR_EMAIL="you@example.com"
GIT_COMMITTER_NAME="Your Name"
GIT_COMMITTER_EMAIL="you@example.com"
```

## Русский

Это мой учебный проект на Symfony.

Исходный код проекта Symfony находится в каталоге `symfony`.

![Hello in Symfony](symfony/docs/images/hello-in-symfony.png)

## Обзор

Сейчас проект сфокусирован на подготовке базового API-слоя на Symfony.

Текущий план реализации:
- запустить Swagger/OpenAPI для `api/v1`
- зафиксировать и стабилизировать точку входа в API и документацию
- реализовать базовую API для работы с новостями

## Текущий статус

- Настроено локальное окружение на Docker
- Swagger UI доступен для `api/v1`
- OpenAPI-спецификация генерируется автоматически во время запроса
- Подготовлены базовые JWT-ручки авторизации

## Технологический стек

- PHP `8.4+`
- Symfony `8.0`
- Doctrine ORM `3.6`
- Doctrine Migrations Bundle `4.0`
- Doctrine Fixtures Bundle `4.3`
- PostgreSQL
- Symfony Serializer
- Symfony Security
- Nelmio ApiDoc Bundle `5.9`
- Swagger-PHP `5.8`
- Pagerfanta `4.8`
- Gedmo Doctrine Extensions `3.22`
- StofDoctrineExtensionsBundle `1.15`
- FakerPHP Faker `1.24`
- Docker Compose

## Задачи

- `Task 1` - done
- Файл задачи: [symfony/docs/task-1.md](symfony/docs/task-1.md)
- Merge Request 1: <https://github.com/ivanserg0692/symfony2026/pull/1>
- `Task 2` - in progress
- Файл задачи: [symfony/docs/task-2.md](symfony/docs/task-2.md)

## Запуск через Docker Compose

Запускайте контейнер из корня репозитория:

```bash
cp .env.example .env
docker compose up --build -d
```

Откройте shell внутри контейнера:

```bash
docker compose exec symfony-cli bash
```

Запускайте команды Symfony CLI напрямую:

```bash
docker compose run --rm symfony-cli symfony --help
docker compose run --rm symfony-cli symfony <command>
```

Запускайте типовые команды проекта:

```bash
docker compose run --rm symfony-cli composer install
docker compose run --rm symfony-cli php bin/console about
docker compose run --rm symfony-cli php bin/console cache:clear
```

После установки зависимостей инициализируйте JWT-ключи:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli bash bin/init-jwt
```

## Настройка Doctrine и базы данных

Поднимите PostgreSQL и создайте базу, если она еще не существует:

```bash
docker compose -f app/docker-compose.yml up -d database
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console doctrine:database:create --if-not-exists
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console doctrine:migrations:status
```

Сгенерируйте и примените миграции:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console make:migration
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console doctrine:migrations:migrate --no-interaction
```

Быстрая проверка подключения к базе:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console dbal:run-sql "SELECT 1"
```

Запустите локальный Symfony web server:

```bash
docker compose up --build symfony-web
```

Откройте приложение в браузере:

```text
http://localhost:8000
```

## API Documentation

Документация API для `api/v1` генерируется автоматически во время выполнения Symfony-приложения.

- Формат OpenAPI: `3.0.0`
- Symfony bundle: `nelmio/api-doc-bundle` `v5.9.5`
- Парсер атрибутов: `zircote/swagger-php` `5.8.3`
- UI renderer: `Swagger UI` `v7.0.0`

Доступные endpoints:

```text
http://localhost:8000/api/v1/doc
http://localhost:8000/api/v1/doc.json
```

В документацию попадают только маршруты, соответствующие `^/api/v1`.

## JWT Authentication

JWT-аутентификация настроена для API и использует файлы ключей в `symfony/config/jwt`.

Для логина включено ограничение запросов: не более `5` неуспешных попыток за `15 минут` на `POST /api/v1/auth/login`.

Перед генерацией ключей задайте `JWT_PASSPHRASE` в `app/.env.local`:

```env
JWT_PASSPHRASE=!ChangeMe!
```

Затем инициализируйте JWT keypair:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli bash bin/init-jwt
```

Доступные ручки авторизации:

```text
POST http://localhost:8000/api/v1/auth/login
GET  http://localhost:8000/api/v1/auth/me
```

Пример запроса на логин:

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password123"}'
```

## История изменений

### 2026-04-16

- Добавлен endpoint `GET /api/v1/news` со списком новостей, пагинацией и сортировкой
- Добавлен `ListQueryDto` для маппинга query-параметров и описания схемы в Swagger
- Подключен `Pagerfanta` для пагинированного ответа списка News
- Обновлен запрос News: добавлен join автора и сериализация полей через группы `news:read` и `user:read`
- Расширена Swagger-документация для query-параметров и структуры ответа списка News
- Добавлен rate limit на `POST /api/v1/auth/login`

### 2026-04-10

- Добавлена поддержка Swagger/OpenAPI для `api/v1`
- Текущий стек API-документации зафиксирован в этом README
- Добавлено описание задачи по запуску Swagger и подготовке базового News API

## Каталог исходников

Файлы проекта монтируются из каталога `symfony` в `/workspace` внутри контейнера.

## Структура проекта

- `symfony` содержит кодовую базу приложения Symfony
- `docker` хранит Docker-файлы проекта
- `docker-compose.yml` описывает локальную контейнерную среду разработки

## Включенные инструменты

Образ содержит `symfony`, `php` и `composer`.

## Git-идентичность

Укажите git-идентичность в `.env`, если планируете git-операции внутри контейнера:

```env
GIT_AUTHOR_NAME="Your Name"
GIT_AUTHOR_EMAIL="you@example.com"
GIT_COMMITTER_NAME="Your Name"
GIT_COMMITTER_EMAIL="you@example.com"
```
