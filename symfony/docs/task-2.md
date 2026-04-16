# Task 2

## RU

### Название
Добавление JWT-аутентификации и ручек авторизации

### Описание задачи
На этом этапе требуется реализовать базовую аутентификацию API через JWT, чтобы клиентское приложение могло проходить авторизацию и работать с защищенными маршрутами.

В минимальный объем задачи войдут:
- настройка JWT-аутентификации в Symfony
- реализация ручки логина для получения access token
- реализация ручки для получения данных текущего авторизованного пользователя
- защита приватных API-маршрутов через bearer token
- обработка ошибок авторизации и невалидного токена
- описание ручек авторизации в Swagger/OpenAPI
- поддержка выдачи JWT через `HttpOnly` cookie для браузерных клиентов
- ограничение неуспешных попыток логина
- подготовка инструкции по тестированию same-origin и cross-origin сценариев

### Цель
Подготовить базовый механизм авторизации для API, чтобы дальнейшие бизнес-ручки можно было безопасно открывать только для аутентифицированных пользователей.

### Критерии приемки
- `POST /api/v1/auth/login` принимает `email` и `password`
- при успешной аутентификации сервер выдает JWT
- JWT доступен для API как через `Authorization: Bearer <token>`, так и через `HttpOnly` cookie
- `GET /api/v1/auth/me` возвращает текущего аутентифицированного пользователя
- невалидные учетные данные приводят к ошибке аутентификации без утечки лишних деталей
- на логин действует ограничение неуспешных попыток
- для cross-origin браузерного сценария задокументированы требования к CORS и cookie

### Как тестировать
#### Same-Origin
- открыть Swagger UI на том же origin, что и API
- выполнить `POST /api/v1/auth/login`
- убедиться, что ответ успешный и содержит `Set-Cookie` с JWT
- выполнить `GET /api/v1/auth/me` и убедиться, что пользователь определяется корректно

#### Cross-Origin
- указать origin фронта в `FRONTEND_ORIGIN`
- для браузерных запросов использовать `credentials: 'include'`
- проверить, что ответы API содержат `Access-Control-Allow-Origin` с точным origin фронта
- проверить, что ответы API содержат `Access-Control-Allow-Credentials: true`
- убедиться, что cookie выставляется с `HttpOnly`, `Secure`, `SameSite=None`

#### Примеры запросов
Логин:

```bash
curl -i -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password123"}'
```

Проверка текущего пользователя по bearer token:

```bash
curl -i http://localhost:8000/api/v1/auth/me \
  -H 'Authorization: Bearer <token>'
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
- an endpoint for retrieving the currently authenticated user
- protection of private API routes with a bearer token
- handling authorization errors and invalid tokens
- Swagger/OpenAPI documentation for the authentication endpoints
- support for issuing JWT through an `HttpOnly` cookie for browser clients
- throttling of failed login attempts
- testing notes for same-origin and cross-origin scenarios

### Goal
Establish a baseline API authorization mechanism so that upcoming business endpoints can be exposed only to authenticated users.

### Acceptance Criteria
- `POST /api/v1/auth/login` accepts `email` and `password`
- a successful login issues a JWT
- the JWT can be used both through `Authorization: Bearer <token>` and an `HttpOnly` cookie
- `GET /api/v1/auth/me` returns the currently authenticated user
- invalid credentials trigger an authentication error without exposing unnecessary details
- failed login attempts are throttled
- cross-origin browser requirements for CORS and cookie delivery are documented

### How To Test
#### Same-Origin
- open Swagger UI on the same origin as the API
- execute `POST /api/v1/auth/login`
- verify that the response is successful and includes a JWT `Set-Cookie`
- execute `GET /api/v1/auth/me` and verify that the current user is resolved correctly

#### Cross-Origin
- set the frontend origin through `FRONTEND_ORIGIN`
- use `credentials: 'include'` for browser requests
- verify that API responses contain `Access-Control-Allow-Origin` with the exact frontend origin
- verify that API responses contain `Access-Control-Allow-Credentials: true`
- verify that the cookie is issued with `HttpOnly`, `Secure`, and `SameSite=None`

#### Request Examples
Login:

```bash
curl -i -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"password123"}'
```

Current user with a bearer token:

```bash
curl -i http://localhost:8000/api/v1/auth/me \
  -H 'Authorization: Bearer <token>'
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
- on local plain `http`, browsers may reject such cookies, so full verification is better done over `https` or another browser-secure local setup
- missing CORS headers in a same-origin Swagger flow is expected and not an error
