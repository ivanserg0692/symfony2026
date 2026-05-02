# Symfony
<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [English](#english)
  - [Overview](#overview)
  - [Current Status](#current-status)
  - [Tech Stack and Component Roles](#tech-stack-and-component-roles)
  - [Tasks](#tasks)
    - [`Task 1` - done](#task-1---done)
    - [`Task 2` - done](#task-2---done)
    - [`Task 3` - done](#task-3---done)
    - [`Task 4` - done](#task-4---done)
  - [Run With Docker Compose](#run-with-docker-compose)
  - [Doctrine Database Setup](#doctrine-database-setup)
  - [API Documentation](#api-documentation)
  - [JWT Authentication](#jwt-authentication)
  - [Changelog](#changelog)
    - [2026-04-16](#2026-04-16)
    - [2026-04-10](#2026-04-10)
  - [Source Directory](#source-directory)
  - [Project Structure](#project-structure)
  - [Included Tools](#included-tools)
  - [Git Identity](#git-identity)
- [Русский](#%D1%80%D1%83%D1%81%D1%81%D0%BA%D0%B8%D0%B9)
  - [Обзор](#%D0%BE%D0%B1%D0%B7%D0%BE%D1%80)
  - [Текущий статус](#%D1%82%D0%B5%D0%BA%D1%83%D1%89%D0%B8%D0%B9-%D1%81%D1%82%D0%B0%D1%82%D1%83%D1%81)
  - [Технологический стек и назначение компонентов](#%D1%82%D0%B5%D1%85%D0%BD%D0%BE%D0%BB%D0%BE%D0%B3%D0%B8%D1%87%D0%B5%D1%81%D0%BA%D0%B8%D0%B9-%D1%81%D1%82%D0%B5%D0%BA-%D0%B8-%D0%BD%D0%B0%D0%B7%D0%BD%D0%B0%D1%87%D0%B5%D0%BD%D0%B8%D0%B5-%D0%BA%D0%BE%D0%BC%D0%BF%D0%BE%D0%BD%D0%B5%D0%BD%D1%82%D0%BE%D0%B2)
  - [Задачи](#%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B8)
    - [`Task 1` - done](#task-1---done-1)
    - [`Task 2` - done](#task-2---done-1)
    - [`Task 3` - done](#task-3---done-1)
    - [`Task 4` - done](#task-4---done-1)
  - [Запуск через Docker Compose](#%D0%B7%D0%B0%D0%BF%D1%83%D1%81%D0%BA-%D1%87%D0%B5%D1%80%D0%B5%D0%B7-docker-compose)
  - [Настройка Doctrine и базы данных](#%D0%BD%D0%B0%D1%81%D1%82%D1%80%D0%BE%D0%B9%D0%BA%D0%B0-doctrine-%D0%B8-%D0%B1%D0%B0%D0%B7%D1%8B-%D0%B4%D0%B0%D0%BD%D0%BD%D1%8B%D1%85)
  - [API Documentation](#api-documentation-1)
  - [JWT Authentication](#jwt-authentication-1)
  - [История изменений](#%D0%B8%D1%81%D1%82%D0%BE%D1%80%D0%B8%D1%8F-%D0%B8%D0%B7%D0%BC%D0%B5%D0%BD%D0%B5%D0%BD%D0%B8%D0%B9)
    - [2026-04-16](#2026-04-16-1)
    - [2026-04-10](#2026-04-10-1)
  - [Каталог исходников](#%D0%BA%D0%B0%D1%82%D0%B0%D0%BB%D0%BE%D0%B3-%D0%B8%D1%81%D1%85%D0%BE%D0%B4%D0%BD%D0%B8%D0%BA%D0%BE%D0%B2)
  - [Структура проекта](#%D1%81%D1%82%D1%80%D1%83%D0%BA%D1%82%D1%83%D1%80%D0%B0-%D0%BF%D1%80%D0%BE%D0%B5%D0%BA%D1%82%D0%B0)
  - [Включенные инструменты](#%D0%B2%D0%BA%D0%BB%D1%8E%D1%87%D0%B5%D0%BD%D0%BD%D1%8B%D0%B5-%D0%B8%D0%BD%D1%81%D1%82%D1%80%D1%83%D0%BC%D0%B5%D0%BD%D1%82%D1%8B)
  - [Git-идентичность](#git-%D0%B8%D0%B4%D0%B5%D0%BD%D1%82%D0%B8%D1%87%D0%BD%D0%BE%D1%81%D1%82%D1%8C)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

## English

This is my Symfony learning project.

The Symfony codebase itself lives in `symfony`.

![Hello in Symfony](symfony/docs/images/hello-in-symfony.png)


### Overview

The project is currently focused on setting up the API foundation in Symfony.

The current implementation path is:
- launch Swagger/OpenAPI for `api/v1`
- stabilize the API entry point and documentation
- build a baseline API for working with news

### Current Status

- Docker-based local environment is configured
- Swagger UI is available for `api/v1`
- OpenAPI specification is generated automatically at runtime
- Baseline JWT authentication endpoints are prepared

### Tech Stack and Component Roles

| Component | Purpose |
| --- | --- |
| PHP `8.4+` | Runtime for the Symfony application. |
| Symfony Framework Bundle `8.0.8` | Core application framework, service container integration, routing, and configuration. |
| Symfony Runtime `8.0.8` | Boots the application through Symfony Runtime and keeps entry points consistent. |
| Symfony Console `8.0.8` | Provides CLI commands for maintenance, migrations, fixtures, and project tooling. |
| Symfony Asset `8.0.8` | Generates paths for static assets used by the admin UI and documentation pages. |
| Symfony Dotenv `8.0.8` | Loads local environment variables from `.env` files. |
| Symfony YAML `8.0.8` | Reads YAML configuration files. |
| Symfony Security Bundle `8.0.8` | Provides firewalls, access control, authenticators, voters, and user authorization. |
| Symfony Validator `8.0.8` | Validates request DTOs and entity/form input. |
| Symfony Serializer `8.0.8` | Serializes API responses and controls output through serialization groups. |
| Symfony Rate Limiter `8.0.8` | Limits repeated login attempts. |
| Symfony Property Access `8.0.8` | Reads and writes object properties for forms, serializers, and framework integrations. |
| Symfony Property Info `8.0.8` | Extracts property type metadata for serializers, validators, and API documentation. |
| Symfony Monolog Bundle `4.0.2` | Integrates Monolog logging with Symfony. |
| Symfony Messenger `8.0.8` | Handles application messages and asynchronous processing. |
| Symfony AMQP Messenger `8.0.6` | Adds AMQP transport support for Messenger queues. |
| Symfony Mailer `8.0.8` | Sends application email messages. |
| Symfony Notifier `8.0.8` | Sends notifications through configured channels. |
| Doctrine ORM `3.6.3` | Maps entities and persists application data. |
| Doctrine Bundle `3.2.2` | Integrates Doctrine ORM and DBAL into Symfony. |
| Doctrine Migrations Bundle `4.0.0` | Manages database schema migrations. |
| Doctrine Fixtures Bundle `4.3.1` | Loads development and test seed data. |
| PostgreSQL | Main relational database. |
| EasyAdmin Bundle `5.0.6` | Provides the admin panel for users, groups, news, statuses, and related entities. |
| LexikJWTAuthenticationBundle `3.2.0` | Issues and validates access JWTs for the API. |
| GesdinetJWTRefreshTokenBundle `2.0.0` | Stores, rotates, refreshes, revokes, and cleans up refresh tokens. |
| PixelOpen Cloudflare Turnstile Bundle `0.5.0` | Validates Cloudflare Turnstile tokens before login continues. |
| NelmioCorsBundle `2.6.1` | Adds CORS headers for browser clients using cross-origin API requests. |
| Nelmio ApiDoc Bundle `5.9.5` | Generates API documentation from routes, attributes, and schemas. |
| Swagger-PHP `5.8.3` | Provides OpenAPI attributes used by the API documentation. |
| Pagerfanta `4.8.0` | Handles paginated API responses. |
| Pagerfanta Doctrine ORM Adapter `4.7+` | Connects Doctrine queries to Pagerfanta pagination. |
| Gedmo Doctrine Extensions `3.22.0` | Provides reusable Doctrine behaviors. |
| StofDoctrineExtensionsBundle `1.15.3` | Integrates Gedmo Doctrine Extensions with Symfony. |
| phpDocumentor Reflection DocBlock `6.0.3` | Reads PHPDoc metadata used by framework and documentation tooling. |
| PHPStan PHPDoc Parser `2.3.2` | Parses PHPDoc types for metadata and documentation support. |
| Twig CSS Inliner Extra `3.24.0` | Inlines CSS in Twig-rendered HTML, mainly for email templates. |
| Twig Inky Extra `3.24.0` | Adds Inky email markup support to Twig templates. |
| FakerPHP Faker `1.24.1` | Generates fake data for fixtures and local development. |
| Symfony Maker Bundle `1.67.0` | Generates Symfony boilerplate during development. |
| Symfony Debug Bundle `8.0.8` | Adds debugging helpers in development. |
| Symfony Web Profiler Bundle `8.0.8` | Provides the Symfony profiler toolbar and request diagnostics in development. |
| Symfony Stopwatch `8.0.8` | Measures code execution time for profiling and diagnostics. |
| Symfony Flex `2.10.0` | Manages Symfony recipes during dependency installation and updates. |
| Docker Compose | Runs the local development services. |

### Tasks

#### `Task 1` - done
- Task file: [symfony/docs/task-1.md](symfony/docs/task-1.md)
- Merge Request 1: <https://github.com/ivanserg0692/symfony2026/pull/1>
#### `Task 2` - done
- Merge Request 2: <https://github.com/ivanserg0692/symfony2026/pull/2>
- Task file: [symfony/docs/task-2.md](symfony/docs/task-2.md)
- MR result: [symfony/docs/mr-task-2.md](symfony/docs/mr-task-2.md)
#### `Task 3` - done
- Merge Request 3: <https://github.com/ivanserg0692/symfony2026/pull/3>
- Task file: [symfony/docs/task-3.md](symfony/docs/task-3.md)
- MR result (EN): [symfony/docs/mr-task-3-en.md](symfony/docs/mr-task-3-en.md)
- MR result (RU): [symfony/docs/mr-task-3-ru.md](symfony/docs/mr-task-3-ru.md)
### `Task 4` - done
- Merge Request 4: <https://github.com/ivanserg0692/symfony2026/pull/4>
- Task file: [symfony/docs/task-4.md](symfony/docs/task-4.md)
- MR result (EN): [symfony/docs/mr-task-4-en.md](symfony/docs/mr-task-4-en.md)
- MR result (RU): [symfony/docs/mr-task-4-ru.md](symfony/docs/mr-task-4-ru.md)

### Run With Docker Compose

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

Sync the bootstrap admin user from environment variables:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console app:user:sync-admin
```

### Doctrine Database Setup

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

To stream production-mode logs from the Symfony local web server inside `symfony-web`, use:

```bash
docker compose -f app/docker-compose.yml exec -it symfony-web sh -lc "/root/.symfony5/bin/symfony server:log"
```

### API Documentation

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

### JWT Authentication

JWT authentication is configured for the API and uses key files stored in `symfony/config/jwt`.

Login attempts are rate-limited: up to `5` failed requests per `15 minutes` for `POST /api/v1/auth/login`.

Browser clients can receive both the access JWT and the refresh token through `HttpOnly` cookies. The frontend origin for cross-origin requests is configured through `FRONTEND_ORIGIN`.

Authentication stack used in this project:
- `symfony/security-bundle` provides the firewall system, access control, user provider integration, and the custom login authenticator entry point
- `lexik/jwt-authentication-bundle` `v3.2.0` issues and validates access JWTs, reads them from the `Authorization` header or `AUTH_TOKEN` cookie, and can automatically set the access-token cookie
- `gesdinet/jwt-refresh-token-bundle` `v2.0.0` implements the refresh-token flow, stores refresh tokens through Doctrine, rotates them, and exposes console commands for cleanup and revoke
- `symfony/rate-limiter` `v8.0.8` is used by `login_throttling` to limit failed login attempts
- `symfony/validator` validates the login DTO fields such as `email`, `password`, and `turnstileToken`
- `pixelopen/cloudflare-turnstile-bundle` validates the Cloudflare Turnstile token before password authentication continues
- `nelmio/cors-bundle` `v2.6.1` adds CORS headers for cross-origin frontend requests with `credentials: include`
- `doctrine/orm` persists refresh tokens in the database and lets them be managed through migrations and cleanup commands
- `nelmio/api-doc-bundle` and `zircote/swagger-php` document the authentication endpoints in Swagger/OpenAPI

Before generating the keypair, set `JWT_PASSPHRASE` in `app/.env.local`:

```env
JWT_PASSPHRASE=!ChangeMe!
```

For cross-origin frontend requests, also set:

```env
FRONTEND_ORIGIN=http://localhost:3000
```

For the first admin bootstrap, set:

```env
APP_ADMIN_LOGIN=admin@example.com
APP_ADMIN_PASSWORD=!ChangeMeAdmin!
```

Then synchronize the admin user:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console app:user:sync-admin
```

How it works:
- if the user with `APP_ADMIN_LOGIN` does not exist, the command creates it with `ROLE_ADMIN`
- if the user already exists, the command keeps the account and resets the password from `APP_ADMIN_PASSWORD`
- the login value is stored in the `email` field because the current security flow authenticates by email and password

Then initialize the JWT keypair:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli bash bin/init-jwt
```

Available authentication endpoints:

```text
POST http://localhost:8000/api/v1/auth/login
POST http://localhost:8000/api/v1/auth/refresh
GET  http://localhost:8000/api/v1/auth/me
```

Login request example:

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password123","turnstileToken":"<turnstile_token>"}'
```

Refresh request example:

```bash
curl -i -X POST http://localhost:8000/api/v1/auth/refresh \
  -H 'Cookie: refresh_token=<refresh_token>'
```

Cleanup invalid refresh tokens:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console gesdinet:jwt:clear
```

Example cron entry for periodic cleanup:

```cron
0 * * * * cd /home/ivan/symfony2026 && docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console gesdinet:jwt:clear
```

Cross-origin CORS preflight example:

```bash
curl -i -X OPTIONS http://localhost:8000/api/v1/auth/login \
  -H 'Origin: http://localhost:3000' \
  -H 'Access-Control-Request-Method: POST' \
  -H 'Access-Control-Request-Headers: content-type'
```

What to verify:
- the login request includes a valid `turnstileToken` obtained from Cloudflare Turnstile on the frontend
- the login response includes `Set-Cookie` for both `AUTH_TOKEN` and the refresh token cookie
- the refresh response issues a new access token and rotates the refresh token
- expired and invalid refresh tokens can be removed with `gesdinet:jwt:clear`
- cross-origin responses include `Access-Control-Allow-Origin` with the exact frontend origin
- cross-origin responses include `Access-Control-Allow-Credentials: true`
- browser requests use `credentials: 'include'`
- same-origin Swagger requests may work without any CORS headers, which is expected
- on local plain `http`, browsers may reject `SameSite=None` + `Secure` cookies

### Changelog

#### 2026-04-16

- Added `GET /api/v1/news` list endpoint with pagination and sorting
- Added `ListQueryDto` for query mapping and Swagger schema description
- Added `Pagerfanta` for paginated News list responses
- Updated News query to join author data and serialize fields with `news:read` and `user:read` groups
- Expanded Swagger documentation for News list query parameters and response structure
- Added JWT authentication configuration and `/api/v1/auth/login`, `/api/v1/auth/me` endpoints
- Added `bin/init-jwt` bootstrap command for JWT key generation

#### 2026-04-10

- Added Swagger/OpenAPI support for `api/v1`
- Fixed the current API documentation stack in this README
- Added task description for Swagger launch and baseline News API preparation

### Source Directory

Project files are mounted from `symfony` into `/workspace` inside the container.

### Project Structure

- `symfony` contains the Symfony application codebase
- `docker` stores Docker-related files
- `docker-compose.yml` defines the local development container setup

### Included Tools

The runner image includes `symfony`, `php`, and `composer`.

### Git Identity

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

### Обзор

Сейчас проект сфокусирован на подготовке базового API-слоя на Symfony.

Текущий план реализации:
- запустить Swagger/OpenAPI для `api/v1`
- зафиксировать и стабилизировать точку входа в API и документацию
- реализовать базовую API для работы с новостями

### Текущий статус

- Настроено локальное окружение на Docker
- Swagger UI доступен для `api/v1`
- OpenAPI-спецификация генерируется автоматически во время запроса
- Подготовлены базовые JWT-ручки авторизации

### Технологический стек и назначение компонентов

| Компонент | Назначение |
| --- | --- |
| PHP `8.4+` | Среда выполнения Symfony-приложения. |
| Symfony Framework Bundle `8.0.8` | Ядро приложения, контейнер сервисов, маршрутизация и конфигурация. |
| Symfony Runtime `8.0.8` | Загружает приложение через Symfony Runtime и выравнивает точки входа. |
| Symfony Console `8.0.8` | Дает CLI-команды для обслуживания проекта, миграций, фикстур и служебных задач. |
| Symfony Asset `8.0.8` | Генерирует пути к статическим ресурсам для админки и страниц документации. |
| Symfony Dotenv `8.0.8` | Загружает локальные переменные окружения из `.env` файлов. |
| Symfony YAML `8.0.8` | Читает YAML-конфигурацию. |
| Symfony Security Bundle `8.0.8` | Отвечает за firewalls, access control, authenticators, voters и авторизацию пользователей. |
| Symfony Validator `8.0.8` | Валидирует DTO запросов и данные entity/form. |
| Symfony Serializer `8.0.8` | Сериализует API-ответы и управляет выводом через serialization groups. |
| Symfony Rate Limiter `8.0.8` | Ограничивает повторные попытки логина. |
| Symfony Property Access `8.0.8` | Читает и записывает свойства объектов для форм, сериализаторов и интеграций Symfony. |
| Symfony Property Info `8.0.8` | Извлекает информацию о типах свойств для сериализации, валидации и API-документации. |
| Symfony Monolog Bundle `4.0.2` | Интегрирует Monolog-логирование в Symfony. |
| Symfony Messenger `8.0.8` | Обрабатывает сообщения приложения и асинхронные задачи. |
| Symfony AMQP Messenger `8.0.6` | Добавляет AMQP-транспорт для очередей Messenger. |
| Symfony Mailer `8.0.8` | Отправляет email-сообщения приложения. |
| Symfony Notifier `8.0.8` | Отправляет уведомления через настроенные каналы. |
| Doctrine ORM `3.6.3` | Маппит entity и сохраняет данные приложения. |
| Doctrine Bundle `3.2.2` | Интегрирует Doctrine ORM и DBAL в Symfony. |
| Doctrine Migrations Bundle `4.0.0` | Управляет миграциями схемы базы данных. |
| Doctrine Fixtures Bundle `4.3.1` | Загружает seed-данные для разработки и тестов. |
| PostgreSQL | Основная реляционная база данных. |
| EasyAdmin Bundle `5.0.6` | Дает административную панель для пользователей, групп, новостей, статусов и связанных сущностей. |
| LexikJWTAuthenticationBundle `3.2.0` | Выпускает и проверяет access JWT для API. |
| GesdinetJWTRefreshTokenBundle `2.0.0` | Хранит, ротирует, обновляет, отзывает и очищает refresh tokens. |
| PixelOpen Cloudflare Turnstile Bundle `0.5.0` | Проверяет Cloudflare Turnstile token перед продолжением логина. |
| NelmioCorsBundle `2.6.1` | Добавляет CORS-заголовки для браузерных клиентов с cross-origin API запросами. |
| Nelmio ApiDoc Bundle `5.9.5` | Генерирует API-документацию из маршрутов, атрибутов и схем. |
| Swagger-PHP `5.8.3` | Дает OpenAPI-атрибуты, используемые в API-документации. |
| Pagerfanta `4.8.0` | Отвечает за пагинированные API-ответы. |
| Pagerfanta Doctrine ORM Adapter `4.7+` | Связывает Doctrine-запросы с пагинацией Pagerfanta. |
| Gedmo Doctrine Extensions `3.22.0` | Дает переиспользуемые Doctrine-поведения. |
| StofDoctrineExtensionsBundle `1.15.3` | Интегрирует Gedmo Doctrine Extensions в Symfony. |
| phpDocumentor Reflection DocBlock `6.0.3` | Читает PHPDoc-метаданные для framework- и documentation-инструментов. |
| PHPStan PHPDoc Parser `2.3.2` | Парсит PHPDoc-типы для метаданных и поддержки документации. |
| Twig CSS Inliner Extra `3.24.0` | Встраивает CSS в HTML, отрендеренный Twig, в основном для email-шаблонов. |
| Twig Inky Extra `3.24.0` | Добавляет поддержку Inky-разметки для email-шаблонов Twig. |
| FakerPHP Faker `1.24.1` | Генерирует фейковые данные для фикстур и локальной разработки. |
| Symfony Maker Bundle `1.67.0` | Генерирует Symfony boilerplate во время разработки. |
| Symfony Debug Bundle `8.0.8` | Добавляет отладочные инструменты в dev-окружении. |
| Symfony Web Profiler Bundle `8.0.8` | Дает Symfony profiler toolbar и диагностику запросов в dev-окружении. |
| Symfony Stopwatch `8.0.8` | Измеряет время выполнения кода для профилирования и диагностики. |
| Symfony Flex `2.10.0` | Управляет Symfony recipes при установке и обновлении зависимостей. |
| Docker Compose | Запускает локальные сервисы разработки. |

### Задачи

#### `Task 1` - done
- Файл задачи: [symfony/docs/task-1.md](symfony/docs/task-1.md)
- Merge Request 1: <https://github.com/ivanserg0692/symfony2026/pull/1>
#### `Task 2` - done
-  Merge Request 2: <https://github.com/ivanserg0692/symfony2026/pull/2>
- Файл задачи: [symfony/docs/task-2.md](symfony/docs/task-2.md)
- Результат MR: [symfony/docs/mr-task-2.md](symfony/docs/mr-task-2.md)
#### `Task 3` - done
- Merge Request 3: <https://github.com/ivanserg0692/symfony2026/pull/3>
- Файл задачи: [symfony/docs/task-3.md](symfony/docs/task-3.md)
- Результат MR (EN): [symfony/docs/mr-task-3-en.md](symfony/docs/mr-task-3-en.md)
- Результат MR (RU): [symfony/docs/mr-task-3-ru.md](symfony/docs/mr-task-3-ru.md)
### `Task 4` - done
- Merge Request 4: <https://github.com/ivanserg0692/symfony2026/pull/4>
- Файл задачи: [symfony/docs/task-4.md](symfony/docs/task-4.md)
- Результат MR (EN): [symfony/docs/mr-task-4-en.md](symfony/docs/mr-task-4-en.md)
- Результат MR (RU): [symfony/docs/mr-task-4-ru.md](symfony/docs/mr-task-4-ru.md)

### Запуск через Docker Compose

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

### Настройка Doctrine и базы данных

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

Чтобы смотреть логи production-режима из локального Symfony web server внутри `symfony-web`, используйте:

```bash
docker compose -f app/docker-compose.yml exec -it symfony-web sh -lc "/root/.symfony5/bin/symfony server:log"
```

### API Documentation

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

### JWT Authentication

JWT-аутентификация настроена для API и использует файлы ключей в `symfony/config/jwt`.

Для логина включено ограничение запросов: не более `5` неуспешных попыток за `15 минут` на `POST /api/v1/auth/login`.

Для браузерных клиентов access JWT и refresh token могут выдаваться через `HttpOnly` cookie. Origin фронта для cross-origin запросов задается через `FRONTEND_ORIGIN`.

Стек, который используется для аутентификации:
- `symfony/security-bundle` дает firewall-механику, `access_control`, интеграцию с user provider и точку входа для кастомного аутентификатора логина
- `lexik/jwt-authentication-bundle` `v3.2.0` отвечает за выпуск и проверку access JWT, чтение токена из `Authorization` header или `AUTH_TOKEN` cookie, а также за автоматическую установку access-cookie
- `gesdinet/jwt-refresh-token-bundle` `v2.0.0` реализует refresh-flow, хранит refresh token через Doctrine, делает ротацию токенов и дает консольные команды для очистки и revoke
- `symfony/rate-limiter` `v8.0.8` используется через `login_throttling` для ограничения неуспешных попыток логина
- `symfony/validator` валидирует DTO логина по полям `email`, `password` и `turnstileToken`
- `pixelopen/cloudflare-turnstile-bundle` проверяет Cloudflare Turnstile token до перехода к проверке пароля
- `nelmio/cors-bundle` `v2.6.1` добавляет CORS-заголовки для cross-origin фронта с `credentials: include`
- `doctrine/orm` хранит refresh token в базе и позволяет управлять ими через миграции и cleanup-команды
- `nelmio/api-doc-bundle` и `zircote/swagger-php` документируют auth-ручки в Swagger/OpenAPI

Перед генерацией ключей задайте `JWT_PASSPHRASE` в `app/.env.local`:

```env
JWT_PASSPHRASE=!ChangeMe!
```

Для фронта на другом домене также задайте:

```env
FRONTEND_ORIGIN=http://localhost:3000
```

Затем инициализируйте JWT keypair:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli bash bin/init-jwt
```

Доступные ручки авторизации:

```text
POST http://localhost:8000/api/v1/auth/login
POST http://localhost:8000/api/v1/auth/refresh
GET  http://localhost:8000/api/v1/auth/me
```

Пример запроса на логин:

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password123","turnstileToken":"<turnstile_token>"}'
```

Пример refresh-запроса:

```bash
curl -i -X POST http://localhost:8000/api/v1/auth/refresh \
  -H 'Cookie: refresh_token=<refresh_token>'
```

Очистка невалидных refresh token:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console gesdinet:jwt:clear
```

Пример cron для периодической очистки:

```cron
0 * * * * cd /home/ivan/symfony2026 && docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console gesdinet:jwt:clear
```

Пример CORS preflight для cross-origin сценария:

```bash
curl -i -X OPTIONS http://localhost:8000/api/v1/auth/login \
  -H 'Origin: http://localhost:3000' \
  -H 'Access-Control-Request-Method: POST' \
  -H 'Access-Control-Request-Headers: content-type'
```

Что проверять:
- в ответе логина есть `Set-Cookie` для `AUTH_TOKEN` и refresh cookie
- refresh-ответ перевыпускает access token и ротирует refresh token
- истекшие и невалидные refresh token можно очистить командой `gesdinet:jwt:clear`
- в cross-origin ответах есть `Access-Control-Allow-Origin` с точным origin фронта
- в cross-origin ответах есть `Access-Control-Allow-Credentials: true`
- браузерные запросы идут с `credentials: 'include'`
- same-origin запросы из Swagger могут работать без CORS-заголовков, это нормально
- на локальном plain `http` браузер может отклонять cookie с `SameSite=None` и `Secure`

### История изменений

#### 2026-04-16

- Добавлен endpoint `GET /api/v1/news` со списком новостей, пагинацией и сортировкой
- Добавлен `ListQueryDto` для маппинга query-параметров и описания схемы в Swagger
- Подключен `Pagerfanta` для пагинированного ответа списка News
- Обновлен запрос News: добавлен join автора и сериализация полей через группы `news:read` и `user:read`
- Расширена Swagger-документация для query-параметров и структуры ответа списка News
- Добавлен rate limit на `POST /api/v1/auth/login`

#### 2026-04-10

- Добавлена поддержка Swagger/OpenAPI для `api/v1`
- Текущий стек API-документации зафиксирован в этом README
- Добавлено описание задачи по запуску Swagger и подготовке базового News API

### Каталог исходников

Файлы проекта монтируются из каталога `symfony` в `/workspace` внутри контейнера.

### Структура проекта

- `symfony` содержит кодовую базу приложения Symfony
- `docker` хранит Docker-файлы проекта
- `docker-compose.yml` описывает локальную контейнерную среду разработки

### Включенные инструменты

Образ содержит `symfony`, `php` и `composer`.

### Git-идентичность

Укажите git-идентичность в `.env`, если планируете git-операции внутри контейнера:

```env
GIT_AUTHOR_NAME="Your Name"
GIT_AUTHOR_EMAIL="you@example.com"
GIT_COMMITTER_NAME="Your Name"
GIT_COMMITTER_EMAIL="you@example.com"
```
