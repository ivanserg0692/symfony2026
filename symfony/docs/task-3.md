# Task 3

## RU

### Название
Добавление проверки доступа к News API

### Описание задачи
На этом этапе требуется добавить слой авторизации для API новостей, чтобы доступ к `news`-ручкам регулировался в зависимости от статуса аутентификации и роли пользователя.

Задача строится поверх уже реализованной JWT-аутентификации и должна определить, какие маршруты новостей остаются публичными, а какие доступны только после успешной авторизации.

В минимальный объем задачи войдут:
- настройка проверки доступа к `news`-маршрутам через Symfony Security
- определение правил доступа для чтения, создания, обновления и удаления новостей
- использование ролей для разграничения прав доступа
- возврат корректных ошибок `401 Unauthorized` и `403 Forbidden`
- интеграция правил доступа с текущей JWT-аутентификацией
- обновление Swagger/OpenAPI для `news`-ручек с учетом требований авторизации

### Цель
Подготовить прозрачный и предсказуемый механизм доступа к News API, чтобы публичные и защищенные сценарии были явно разделены, а бизнес-ручки не могли использоваться без нужных прав.

### Критерии приемки
- правила доступа к `news`-ручкам зафиксированы на уровне Symfony Security
- публичные `news`-маршруты доступны без аутентификации, если они предусмотрены бизнес-логикой
- защищенные `news`-маршруты требуют валидную аутентификацию
- пользователь без аутентификации получает `401 Unauthorized` при обращении к защищенным `news`-маршрутам
- пользователь с валидной аутентификацией, но без достаточных прав, получает `403 Forbidden`
- пользователь с подходящей ролью получает доступ к разрешенным операциям
- текущий JWT flow продолжает работать как для `Authorization: Bearer <token>`, так и для browser/cookie-сценария
- Swagger/OpenAPI отражает, какие `news`-ручки требуют авторизацию

### Технический подход
- базовая защита маршрутов может быть настроена через `access_control`
- разграничение доступа может опираться на `ROLE_USER` и `ROLE_ADMIN`
- если для новостей появятся более тонкие бизнес-правила, допускается расширение через voter
- на текущем этапе приоритетом остается простое и явное route-level разграничение, если этого достаточно для задачи

### Как тестировать
- выполнить публичный `GET`-запрос к news endpoint и убедиться, что он доступен без токена, если маршрут должен быть публичным
- выполнить запрос к защищенному `news` endpoint без токена и убедиться, что сервер возвращает `401`
- выполнить запрос к защищенному `news` endpoint с валидным JWT пользователя без нужной роли и убедиться, что сервер возвращает `403`
- выполнить запрос к защищенному `news` endpoint с валидным JWT пользователя с нужной ролью и убедиться, что доступ разрешен
- проверить, что те же правила работают для bearer token и cookie-based сценария
- убедиться, что Swagger/OpenAPI корректно показывает security requirements для защищенных `news`-ручек

### Примечания
- если на текущем этапе правила доступа ограничиваются только ролями и маршрутом, voter может не понадобиться
- если позже появятся ownership rules, статус публикации, черновики или редакторские сценарии, access control, скорее всего, потребуется расширить

## EN

### Title
Add access control for the News API

### Task Description
At this stage, the News API needs an authorization layer so that access to `news` endpoints is controlled according to the user's authentication state and role.

This task builds on top of the existing JWT authentication flow and defines which news routes stay public and which ones require successful authorization.

The initial scope includes:
- configuring access checks for `news` routes through Symfony Security
- defining access rules for reading, creating, updating, and deleting news
- using roles to separate permissions
- returning proper `401 Unauthorized` and `403 Forbidden` responses
- integrating the access rules with the current JWT authentication flow
- updating Swagger/OpenAPI for `news` endpoints to reflect authorization requirements

### Goal
Establish a clear and predictable access model for the News API so that public and protected scenarios are explicitly separated and business endpoints cannot be used without the required permissions.

### Acceptance Criteria
- access rules for `news` endpoints are defined at the Symfony Security level
- public `news` routes remain accessible without authentication when allowed by business rules
- protected `news` routes require valid authentication
- an unauthenticated user receives `401 Unauthorized` when calling protected `news` routes
- an authenticated user without sufficient permissions receives `403 Forbidden`
- a user with the required role can access the allowed operations
- the existing JWT flow continues to work for both `Authorization: Bearer <token>` and browser/cookie-based scenarios
- Swagger/OpenAPI clearly indicates which `news` endpoints require authorization

### Technical Approach
- baseline route protection can be configured through `access_control`
- permission separation can rely on `ROLE_USER` and `ROLE_ADMIN`
- if finer-grained business rules are introduced for news, the design can be extended with a voter
- at this stage, simple and explicit route-level access control is preferred if it is sufficient for the task

### How To Test
- call a public `GET` news endpoint and verify that it is available without a token when the route is intended to be public
- call a protected `news` endpoint without a token and verify that the server returns `401`
- call a protected `news` endpoint with a valid JWT for a user without the required role and verify that the server returns `403`
- call a protected `news` endpoint with a valid JWT for a user with the required role and verify that access is granted
- verify that the same rules work for both bearer-token and cookie-based scenarios
- verify that Swagger/OpenAPI correctly exposes security requirements for protected `news` endpoints

### Notes
- if access rules are limited to route-level and role-based checks, a voter may not be necessary yet
- if ownership rules, publication states, drafts, or editorial workflows appear later, the access-control layer will likely need to be extended
