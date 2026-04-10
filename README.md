# Symfony

## English

This is my Symfony learning project.

The Symfony codebase itself lives in `symfony`.

![Hello in Symfony](symfony/docs/images/hello-in-symfony.png)

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

Запуск локального Symfony-сервера:

```bash
docker compose up --build symfony-web
```

Откройте приложение в браузере:

```text
http://localhost:8000
```

## Документация API

Документация для `api/v1` генерируется приложением Symfony автоматически во время запроса.

- Формат спецификации OpenAPI: `3.0.0`
- Symfony bundle: `nelmio/api-doc-bundle` `v5.9.5`
- Парсер атрибутов: `zircote/swagger-php` `5.8.3`
- UI для отображения: `Swagger UI` `v7.0.0`

Доступные адреса:

```text
http://localhost:8000/api/v1/doc
http://localhost:8000/api/v1/doc.json
```

В документацию попадают только маршруты, которые соответствуют шаблону `^/api/v1`.

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
