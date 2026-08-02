# Лог Результата MR Task 7.3

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Обзор](#%D0%BE%D0%B1%D0%B7%D0%BE%D1%80)
- [Scope](#scope)
- [Public API Boundary](#public-api-boundary)
- [Security Notes](#security-notes)
- [Проверка](#%D0%BF%D1%80%D0%BE%D0%B2%D0%B5%D1%80%D0%BA%D0%B0)
- [Known Follow-Up](#known-follow-up)
- [Вне Scope](#%D0%B2%D0%BD%D0%B5-scope)

<!-- END doctoc -->

## Обзор

Этот документ описывает видимый результат merge request для Task 7.3.

Merge request: TBD

Файл задачи: [task-7.3.md](task-7.3.md)

Merge request добавляет внешний REST/HTTP API Gateway на базе nginx. Gateway становится отдельным публичным proxy layer для клиентских `/api/v1/...` routes и проксирует запросы во внутренние Symfony, Catalog и Cart services по их текущим service-level REST endpoints.

## Scope

Реализовано:

- добавлен Docker Compose service `api-gateway` на базе nginx;
- добавлена nginx-конфигурация gateway с upstreams для `symfony-web`, `catalog-web` и `cart-web`;
- публичные gateway routes описаны в `api-gateway/routes.json`;
- nginx routes генерируются в `docker/nginx/snippets/generated-api-gateway-routes.conf`;
- gateway-level OpenAPI генерируется в `symfony/public/api-gateway/openapi.json`;
- базовая OpenAPI-шапка вынесена в `api-gateway/openapi-header.json`;
- общие nginx proxy/auth настройки вынесены в snippets;
- внешние `/api/v1/catalog/...`, `/api/v1/stores`, `/api/v1/cart` и `/api/v1/orders` routes сопоставлены с текущими внутренними routes Catalog и Cart services;
- защищенные cart/order routes проходят через nginx `auth_request`.

## Public API Boundary

Gateway публикует клиентский REST/HTTP контракт под `/api/v1/...`.

Ключевые route mappings:

- `/api/v1/auth/...` -> Symfony `/api/v1/auth/...`;
- `/api/v1/news...` -> Symfony `/api/v1/news...`;
- `/api/v1/notification...` -> Symfony `/api/v1/notification...`;
- `/api/v1/catalog/elements...` -> Catalog `/api/catalog/elements...`;
- `/api/v1/catalog/sections...` -> Catalog `/api/catalog/sections...`;
- `/api/v1/stores...` -> Catalog `/api/stores...`;
- `/api/v1/cart...` -> Cart `/api/cart...`;
- `/api/v1/orders...` -> Cart `/api/orders...`.

Gateway OpenAPI contract доступен как generated artifact: `/api-gateway/openapi.json`.

## Security Notes

Для защищенных cart/order routes gateway выполняет internal auth check через `/_auth`, который проксируется в Symfony `/api/v1/auth/me`.

После успешной проверки gateway берет `X-User-Id` из ответа auth endpoint и передает его во внутренний Cart service как trusted header.

Клиентский `X-User-Id` не считается доверенным:

- для public routes gateway очищает `X-User-Id`;
- для auth-check запроса gateway также очищает `X-User-Id`;
- для protected routes gateway выставляет `X-User-Id` из `$auth_user_id`, а не из клиентского header;
- `X-User-Id`, возвращаемый Symfony `/api/v1/auth/me`, скрывается от frontend через `proxy_hide_header`.

`X-User-Roles` и `X-User-Email` пока намеренно не добавлялись.

## Проверка

В рамках реализации проверено:

- `node --check scripts/generate-api-gateway.mjs`;
- `docker compose config --quiet`;
- `npm run gateway:generate`.

При генерации OpenAPI команда завершилась успешно. В выводе PHP-контейнеров остается существующее предупреждение `Cannot load Xdebug - it was already loaded`.

## Known Follow-Up

В текущем dev Docker Compose прямые порты внутренних web-сервисов все еще опубликованы наружу:

- Symfony web: `8000:8000`;
- Catalog web: `8010:8000`;
- Cart web: `8020:8000`.

Для полного production-like security boundary следующим шагом нужно перевести внутренние web-сервисы с `ports` на internal `expose` и оставить публичным REST entrypoint только `api-gateway`.

## Вне Scope

В рамках этого MR не менялась бизнес-логика внутренних сервисов.

Не добавлялись role headers, email headers или отдельный lightweight internal auth-check endpoint в Symfony. Текущая интеграция переиспользует `/api/v1/auth/me`.
