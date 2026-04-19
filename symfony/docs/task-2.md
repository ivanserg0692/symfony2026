# Task 2

## RU

### Название
Добавление JWT-аутентификации и ручек авторизации

### Описание задачи
На этом этапе требуется реализовать базовую аутентификацию API через JWT, чтобы клиентское приложение могло проходить авторизацию и работать с защищенными маршрутами.

В минимальный объем задачи войдут:
- настройка JWT-аутентификации в Symfony
- реализация ручки логина для получения access token
- реализация ручки refresh для перевыпуска access token
- реализация ручки для получения данных текущего авторизованного пользователя
- защита приватных API-маршрутов через bearer token
- обработка ошибок авторизации и невалидного токена
- описание ручек авторизации в Swagger/OpenAPI
- поддержка `access token` и `refresh token` через `HttpOnly` cookie для браузерных клиентов
- ограничение неуспешных попыток логина
- хранение refresh token через Doctrine
- подготовка инструкции по тестированию same-origin и cross-origin сценариев

### Цель
Подготовить базовый механизм авторизации для API, чтобы дальнейшие бизнес-ручки можно было безопасно открывать только для аутентифицированных пользователей.

### Технический стек
- `symfony/security-bundle` для firewall, `access_control` и кастомного аутентификатора логина
- `lexik/jwt-authentication-bundle` `v3.2.0` для выпуска и проверки access JWT
- `gesdinet/jwt-refresh-token-bundle` `v2.0.0` для refresh token, их ротации и хранения через Doctrine
- `symfony/rate-limiter` `v8.0.8` для ограничения неуспешных попыток логина
- `symfony/validator` для валидации DTO логина
- `pixelopen/cloudflare-turnstile-bundle` для проверки Cloudflare Turnstile token на логине
- `nelmio/cors-bundle` `v2.6.1` для cross-origin cookie и CORS-заголовков
- `doctrine/orm` для хранения refresh token в БД
- `nelmio/api-doc-bundle` и `zircote/swagger-php` для Swagger/OpenAPI документации auth-ручек

### Критерии приемки
- `POST /api/v1/auth/login` принимает `email`, `password` и `turnstileToken`
- `POST /api/v1/auth/refresh` перевыпускает access token по refresh token
- при успешной аутентификации сервер выдает access JWT и refresh token
- access JWT доступен для API как через `Authorization: Bearer <token>`, так и через `HttpOnly` cookie
- refresh token хранится на сервере через Doctrine
- `GET /api/v1/auth/me` возвращает текущего аутентифицированного пользователя
- невалидные учетные данные приводят к ошибке аутентификации без утечки лишних деталей
- на логин действует ограничение неуспешных попыток
- для cross-origin браузерного сценария задокументированы требования к CORS и cookie

### Как тестировать
#### Same-Origin
- открыть Swagger UI на том же origin, что и API
- выполнить `POST /api/v1/auth/login` с валидным `turnstileToken`
- убедиться, что ответ успешный и содержит `Set-Cookie` для access token и refresh token
- выполнить `POST /api/v1/auth/refresh` и убедиться, что access token перевыпускается
- выполнить `GET /api/v1/auth/me` и убедиться, что пользователь определяется корректно

#### Cross-Origin
- указать origin фронта в `FRONTEND_ORIGIN`
- для браузерных запросов использовать `credentials: 'include'`
- проверить, что ответы API содержат `Access-Control-Allow-Origin` с точным origin фронта
- проверить, что ответы API содержат `Access-Control-Allow-Credentials: true`
- убедиться, что access cookie выставляется с `HttpOnly`, `Secure`, `SameSite=None`
- убедиться, что refresh cookie выставляется как `HttpOnly` и доступна для маршрута refresh
- проверить, что истекшие refresh token очищаются отдельной консольной командой

#### Примеры запросов
Логин:

```bash
curl -i -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password123","turnstileToken":"<turnstile_token>"}'
```

Проверка текущего пользователя по bearer token:

```bash
curl -i http://localhost:8000/api/v1/auth/me \
  -H 'Authorization: Bearer <token>'
```

Обновление access token по refresh cookie:

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

Проверка CORS-заголовков для cross-origin запроса:

```bash
curl -i -X OPTIONS http://localhost:8000/api/v1/auth/login \
  -H 'Origin: http://localhost:3000' \
  -H 'Access-Control-Request-Method: POST' \
  -H 'Access-Control-Request-Headers: content-type'
```

### Ограничения и замечания
- для cross-origin cookie-сценария `SameSite=None` требует `Secure=true`
- refresh token должен храниться на сервере, поэтому после установки `gesdinet/jwt-refresh-token-bundle` нужно создать и применить Doctrine migration
- таблицу refresh token нужно периодически чистить командой `gesdinet:jwt:clear`, иначе она будет расти
- на локальном `http` браузер может не принять такую cookie, поэтому полноценную проверку лучше выполнять на `https` или в окружении, которое браузер считает secure
- отсутствие CORS-заголовков в same-origin Swagger-сценарии не считается ошибкой

## EN

### Title
JWT authentication and authorization endpoints

### Task Description
At this stage, the API needs a baseline JWT-based authentication flow so that client applications can authenticate and access protected endpoints.

The initial scope includes:
- JWT authentication setup in Symfony
- a login endpoint for issuing an access token
- a refresh endpoint for issuing a new access token
- an endpoint for retrieving the currently authenticated user
- protection of private API routes with a bearer token
- handling authorization errors and invalid tokens
- Swagger/OpenAPI documentation for the authentication endpoints
- support for issuing both access and refresh tokens through `HttpOnly` cookies for browser clients
- throttling of failed login attempts
- Doctrine-based refresh token storage
- testing notes for same-origin and cross-origin scenarios

### Goal
Establish a baseline API authorization mechanism so that upcoming business endpoints can be exposed only to authenticated users.

### Technical Stack
- `symfony/security-bundle` for firewalls, `access_control`, and the custom login authenticator
- `lexik/jwt-authentication-bundle` `v3.2.0` for issuing and validating access JWTs
- `gesdinet/jwt-refresh-token-bundle` `v2.0.0` for refresh tokens, token rotation, and Doctrine-backed storage
- `symfony/rate-limiter` `v8.0.8` for failed-login throttling
- `symfony/validator` for login DTO validation
- `pixelopen/cloudflare-turnstile-bundle` for Cloudflare Turnstile token validation on login
- `nelmio/cors-bundle` `v2.6.1` for cross-origin cookie delivery and CORS headers
- `doctrine/orm` for refresh-token persistence
- `nelmio/api-doc-bundle` and `zircote/swagger-php` for Swagger/OpenAPI documentation of the auth endpoints

### Acceptance Criteria
- `POST /api/v1/auth/login` accepts `email`, `password`, and `turnstileToken`
- `POST /api/v1/auth/refresh` issues a new access token using a refresh token
- a successful login issues both an access JWT and a refresh token
- the access JWT can be used both through `Authorization: Bearer <token>` and an `HttpOnly` cookie
- the refresh token is stored server-side through Doctrine
- `GET /api/v1/auth/me` returns the currently authenticated user
- invalid credentials trigger an authentication error without exposing unnecessary details
- failed login attempts are throttled
- cross-origin browser requirements for CORS and cookie delivery are documented

### How To Test
#### Same-Origin
- open Swagger UI on the same origin as the API
- execute `POST /api/v1/auth/login` with a valid `turnstileToken`
- verify that the response is successful and includes `Set-Cookie` for both access and refresh tokens
- execute `POST /api/v1/auth/refresh` and verify that the access token is re-issued
- execute `GET /api/v1/auth/me` and verify that the current user is resolved correctly

#### Cross-Origin
- set the frontend origin through `FRONTEND_ORIGIN`
- use `credentials: 'include'` for browser requests
- verify that API responses contain `Access-Control-Allow-Origin` with the exact frontend origin
- verify that API responses contain `Access-Control-Allow-Credentials: true`
- verify that the access cookie is issued with `HttpOnly`, `Secure`, and `SameSite=None`
- verify that the refresh cookie is `HttpOnly` and scoped for the refresh route
- verify that expired refresh tokens can be cleaned up with a dedicated console command

#### Request Examples
Login:

```bash
curl -i -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password123","turnstileToken":"<turnstile_token>"}'
```

Current user with a bearer token:

```bash
curl -i http://localhost:8000/api/v1/auth/me \
  -H 'Authorization: Bearer <token>'
```

Refresh the access token with a refresh cookie:

```bash
curl -i -X POST http://localhost:8000/api/v1/auth/refresh \
  -H 'Cookie: refresh_token=<refresh_token>'
```

Clear invalid refresh tokens:

```bash
docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console gesdinet:jwt:clear
```

Example cron entry for periodic cleanup:

```cron
0 * * * * cd /home/ivan/symfony2026 && docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console gesdinet:jwt:clear
```

Cross-origin CORS preflight check:

```bash
curl -i -X OPTIONS http://localhost:8000/api/v1/auth/login \
  -H 'Origin: http://localhost:3000' \
  -H 'Access-Control-Request-Method: POST' \
  -H 'Access-Control-Request-Headers: content-type'
```

### Notes
- for a cross-origin cookie flow, `SameSite=None` requires `Secure=true`
- because refresh tokens are stored through Doctrine, a migration must be created and applied after installing `gesdinet/jwt-refresh-token-bundle`
- the refresh token table should be cleaned periodically with `gesdinet:jwt:clear`, otherwise it will keep growing over time
- on local plain `http`, browsers may reject such cookies, so full verification is better done over `https` or another browser-secure local setup
- missing CORS headers in a same-origin Swagger flow is expected and not an error
