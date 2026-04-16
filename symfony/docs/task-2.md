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

### Цель
Подготовить базовый механизм авторизации для API, чтобы дальнейшие бизнес-ручки можно было безопасно открывать только для аутентифицированных пользователей.

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

### Goal
Establish a baseline API authorization mechanism so that upcoming business endpoints can be exposed only to authenticated users.
