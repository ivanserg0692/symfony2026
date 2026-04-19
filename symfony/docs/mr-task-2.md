# Merge Request: Task 2

## English

### Title
Browser-oriented JWT authentication, CSRF protection, refresh-token flow, and admin bootstrap

### Summary
This merge request introduces the authentication layer for the API based on JWT access tokens and database-backed refresh tokens.

The implementation is oriented to browser-based frontend clients that work on a separate domain and authenticate through `HttpOnly` cookies.
The flow now includes explicit CSRF protection for state-changing authentication endpoints, login protection with Cloudflare Turnstile, refresh-token persistence, and bootstrap synchronization of the system administrator from environment variables.

The authentication flow now covers:
- CSRF token issuing for frontend clients
- login by `email`, `password`, and Cloudflare Turnstile token
- access-token verification for protected routes
- refresh-token rotation
- logout from the authenticated browser session
- `HttpOnly` cookie support for browser-based clients
- login throttling for failed authentication attempts
- current-user endpoint for authenticated requests
- bootstrap admin synchronization from environment variables
- Swagger/OpenAPI documentation updates

### What Was Added
- `GET /api/v1/auth/csrf` endpoint for issuing a CSRF token for browser clients
- custom login authenticator for `POST /api/v1/auth/login`
- login request DTO with validation for `email`, `password`, and `turnstileToken`
- `GET /api/v1/auth/me` endpoint for the current authenticated user
- refresh-token support through `gesdinet/jwt-refresh-token-bundle`
- refresh-token persistence through Doctrine
- `POST /api/v1/auth/logout` endpoint for ending the authenticated session
- rate limiting for failed login attempts
- CORS configuration for cross-origin frontend requests with cookies
- JWT key initialization script
- bootstrap admin command:
  - `php bin/console app:user:sync-admin`

### Technical Notes
- `lexik/jwt-authentication-bundle` is used for access JWT issuing and validation
- `gesdinet/jwt-refresh-token-bundle` is used for refresh-token storage, rotation, and cleanup
- `pixelopen/cloudflare-turnstile-bundle` is used to validate the Cloudflare Turnstile token during login
- CSRF protection is applied to the authentication flow through a dedicated CSRF endpoint and `X-CSRF-Token` header checks on protected auth endpoints
- access JWT is not stored in the database and is verified statelessly
- refresh token is stored in the database through Doctrine
- access and refresh tokens are configured for `HttpOnly` cookie delivery
- refresh tokens are stored in the database and should be cleaned periodically with:
  - `php bin/console gesdinet:jwt:clear`
- the first admin user is synchronized from:
  - `APP_ADMIN_LOGIN`
  - `APP_ADMIN_PASSWORD`

### Authentication Flow
1. Frontend requests `GET /api/v1/auth/csrf` to receive a CSRF token.
2. Frontend calls `POST /api/v1/auth/login` with `email`, `password`, `turnstileToken`, and `X-CSRF-Token`.
3. On successful authentication, the backend issues `HttpOnly` cookies for the access token and refresh token.
4. Frontend uses `POST /api/v1/auth/refresh` with the existing cookies and CSRF header to rotate or renew the auth session.
5. Frontend uses `POST /api/v1/auth/logout` to clear the authenticated browser session.

### Configuration
The authentication module depends on the following environment values:
- `JWT_PASSPHRASE`
- `FRONTEND_ORIGIN` or trusted frontend-domain configuration used by CSRF/CORS
- `APP_ADMIN_LOGIN`
- `APP_ADMIN_PASSWORD`
- Cloudflare Turnstile site key and secret key

### Operational Notes
- JWT keys must be generated before first use.
- Refresh-token cleanup should be scheduled periodically.
- The bootstrap admin command is idempotent: it creates the system admin if it does not exist and updates its credentials if it already exists.

### API Endpoints
- `GET /api/v1/auth/csrf`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`

### How To Test
1. Set `JWT_PASSPHRASE`, frontend-origin/CSRF environment values, Cloudflare Turnstile keys, `APP_ADMIN_LOGIN`, and `APP_ADMIN_PASSWORD`.
2. Generate the JWT keypair:
   `docker compose -f app/docker-compose.yml exec -T symfony-cli bash bin/init-jwt`
3. Synchronize the bootstrap admin:
   `docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console app:user:sync-admin`
4. Execute `GET /api/v1/auth/csrf` and verify that the response returns a CSRF token.
5. Execute `POST /api/v1/auth/login` with a valid `turnstileToken` and `X-CSRF-Token`.
6. Verify that the login response includes cookie headers for the access token and refresh token.
7. Execute `GET /api/v1/auth/me` and verify that the authenticated user is returned from the issued auth cookies.
8. Execute `POST /api/v1/auth/refresh` with auth cookies and `X-CSRF-Token` and verify that the access token is re-issued.
9. Execute `POST /api/v1/auth/logout` and verify that the auth cookies are cleared and the authenticated session is terminated.

### Screenshot
![JWT auth endpoints](images/jwt-auth-endpoints.png)

## Русский

### Заголовок
JWT-аутентификация для браузерного клиента, CSRF-защита, refresh-token flow и bootstrap админа

### Краткое описание
В этом merge request реализован слой аутентификации API на основе JWT access token и refresh token, который хранится в базе.

Реализация ориентирована на browser-based frontend-клиент на отдельном домене, который работает через `HttpOnly` cookies.
Теперь auth flow включает явную CSRF-защиту state-changing auth-ручек, защиту логина через Cloudflare Turnstile, хранение refresh token в Doctrine и синхронизацию системного администратора из переменных окружения.

Теперь auth flow покрывает:
- выдачу CSRF token для frontend-клиента
- логин по `email`, `password` и Cloudflare Turnstile token
- проверку access token на защищенных маршрутах
- ротацию refresh token
- logout из авторизованной браузерной сессии
- поддержку `HttpOnly` cookie для браузерных клиентов
- ограничение неуспешных попыток логина
- ручку текущего пользователя
- синхронизацию bootstrap-админа через переменные окружения
- обновленную Swagger/OpenAPI документацию

### Что Было Добавлено
- ручка `GET /api/v1/auth/csrf` для выдачи CSRF token браузерному клиенту
- кастомный аутентификатор логина для `POST /api/v1/auth/login`
- DTO логина с валидацией `email`, `password` и `turnstileToken`
- ручка `GET /api/v1/auth/me` для текущего авторизованного пользователя
- поддержка refresh token через `gesdinet/jwt-refresh-token-bundle`
- хранение refresh token через Doctrine
- ручка `POST /api/v1/auth/logout` для завершения авторизованной сессии
- rate limiting для неуспешных попыток логина
- CORS-конфигурация для cross-origin frontend-запросов с cookie
- скрипт инициализации JWT-ключей
- команда bootstrap-админа:
  - `php bin/console app:user:sync-admin`

### Технические детали
- `lexik/jwt-authentication-bundle` используется для выпуска и проверки access JWT
- `gesdinet/jwt-refresh-token-bundle` используется для хранения, ротации и очистки refresh token
- `pixelopen/cloudflare-turnstile-bundle` используется для проверки Cloudflare Turnstile token во время логина
- CSRF-защита встроена в auth flow через отдельную CSRF-ручку и проверки `X-CSRF-Token` header на защищенных auth-эндпоинтах
- access JWT не хранится в базе данных и проверяется stateless
- refresh token хранится в базе данных через Doctrine
- access token и refresh token настроены на доставку через `HttpOnly` cookie
- refresh token хранится в базе данных и должен периодически очищаться командой:
  - `php bin/console gesdinet:jwt:clear`
- первый админ синхронизируется из переменных:
  - `APP_ADMIN_LOGIN`
  - `APP_ADMIN_PASSWORD`

### Поток Аутентификации
1. Frontend вызывает `GET /api/v1/auth/csrf` и получает CSRF token.
2. Frontend вызывает `POST /api/v1/auth/login`, передавая `email`, `password`, `turnstileToken` и `X-CSRF-Token`.
3. При успешной аутентификации backend выставляет `HttpOnly` cookies для access token и refresh token.
4. Frontend вызывает `POST /api/v1/auth/refresh` с существующими cookies и CSRF header для продления или ротации сессии.
5. Frontend вызывает `POST /api/v1/auth/logout`, чтобы завершить авторизованную браузерную сессию.

### Конфигурация
Модуль аутентификации зависит от следующих переменных окружения:
- `JWT_PASSPHRASE`
- `FRONTEND_ORIGIN` или конфигурации доверенных frontend-доменов, используемой CSRF/CORS-логикой
- `APP_ADMIN_LOGIN`
- `APP_ADMIN_PASSWORD`
- Cloudflare Turnstile site key и secret key

### Эксплуатационные заметки
- JWT-ключи должны быть сгенерированы до первого использования.
- Очистку refresh token стоит запускать периодически по расписанию.
- Команда bootstrap-админа идемпотентна: она создаёт системного админа, если его нет, и обновляет его credentials, если он уже существует.

### API Ручки
- `GET /api/v1/auth/csrf`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`

### Как Проверить
1. Задать `JWT_PASSPHRASE`, переменные frontend-origin/CSRF, Cloudflare Turnstile keys, `APP_ADMIN_LOGIN` и `APP_ADMIN_PASSWORD`.
2. Сгенерировать JWT keypair:
   `docker compose -f app/docker-compose.yml exec -T symfony-cli bash bin/init-jwt`
3. Синхронизировать bootstrap-админа:
   `docker compose -f app/docker-compose.yml exec -T symfony-cli php bin/console app:user:sync-admin`
4. Выполнить `GET /api/v1/auth/csrf` и проверить, что ответ возвращает CSRF token.
5. Выполнить `POST /api/v1/auth/login` с валидным `turnstileToken` и `X-CSRF-Token`.
6. Проверить, что в ответе на логин есть cookie для access token и refresh token.
7. Выполнить `GET /api/v1/auth/me` и проверить, что по выданным auth cookies возвращается текущий авторизованный пользователь.
8. Выполнить `POST /api/v1/auth/refresh` с auth cookies и `X-CSRF-Token` и проверить перевыпуск access token.
9. Выполнить `POST /api/v1/auth/logout` и проверить, что auth cookies очищаются, а авторизованная сессия завершается.

### Скриншот
![JWT auth endpoints](images/jwt-auth-endpoints.png)
