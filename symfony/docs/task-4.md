# Task 4

## RU

### Название
Добавление уведомлений через email и внутренние notifications

### Описание задачи
На этом этапе требуется добавить механизм уведомлений, который будет поддерживать два канала доставки: email и внутренние уведомления в приложении.

Внутренние уведомления должны храниться в системе и быть доступны пользователю через API. Пользователь сможет получить список своих сообщений и удалить ненужные уведомления.

Также требуется добавить первое простое бизнес-уведомление для администраторов: когда новость переходит в статус `on_moderation`, администраторы получают внутреннее сообщение о том, что новость требует модерации.

В минимальный объем задачи войдут:
- проектирование базовой модели внутреннего уведомления
- добавление API-ручки для получения списка внутренних уведомлений пользователя
- добавление API-ручки для удаления внутреннего уведомления
- ограничение доступа так, чтобы пользователь видел и удалял только свои уведомления
- добавление email-канала как отдельного способа отправки уведомлений
- отправка внутреннего уведомления администраторам при появлении новости в статусе `on_moderation`
- обновление Swagger/OpenAPI для новых notification-ручек

### Цель
Подготовить расширяемую основу для уведомлений, чтобы бизнес-события могли отправлять сообщения через разные каналы, а пользователи могли работать со своими внутренними уведомлениями через API.

### Критерии приемки
- в системе есть модель внутреннего уведомления
- пользователь может получить список своих внутренних уведомлений через API
- пользователь может удалить свое внутреннее уведомление через API
- пользователь не может получить или удалить чужое внутреннее уведомление
- email-канал выделен как отдельный канал отправки уведомлений
- при переводе новости в статус `on_moderation` создается внутреннее уведомление для администраторов
- новые API-ручки защищены авторизацией
- Swagger/OpenAPI содержит описание новых notification-ручек и требований авторизации

### Технический подход
- внутренние уведомления можно хранить в отдельной Doctrine entity
- отправку уведомлений стоит отделить от места возникновения бизнес-события через сервис
- для email-канала можно использовать Symfony Mailer
- для проверки доступа к внутренним уведомлениям можно использовать voter или явную проверку владельца в прикладном слое
- событие перехода новости в `on_moderation` должно создавать уведомления только для администраторов

### Как тестировать
- создать несколько уведомлений для разных пользователей
- выполнить запрос списка уведомлений от имени пользователя и убедиться, что возвращаются только его сообщения
- удалить свое уведомление и убедиться, что оно больше не возвращается в списке
- попытаться удалить чужое уведомление и убедиться, что сервер возвращает `403 Forbidden` или `404 Not Found`
- перевести новость в статус `on_moderation` и убедиться, что администраторы получили внутреннее уведомление
- проверить, что Swagger/OpenAPI показывает новые notification-ручки и требования авторизации

### Примечания
- на первом этапе достаточно простого внутреннего уведомления без сложного read/unread workflow, если он не требуется для приемки
- email-уведомления можно подключать к конкретным бизнес-событиям постепенно
- если список уведомлений будет расти, позже стоит добавить пагинацию

## EN

### Title
Add email and internal notifications

### Task Description
At this stage, the project needs a notification mechanism that supports two delivery channels: email and internal in-app notifications.

Internal notifications should be stored in the system and exposed to the user through the API. A user should be able to fetch their message list and delete notifications they no longer need.

The task also introduces the first simple business notification for administrators: when a news item moves to the `on_moderation` status, administrators receive an internal message saying that the news item requires moderation.

The initial scope includes:
- designing a baseline internal notification model
- adding an API endpoint for fetching the current user's internal notifications
- adding an API endpoint for deleting an internal notification
- restricting access so that users can only read and delete their own notifications
- adding the email channel as a separate notification delivery channel
- sending an internal notification to administrators when a news item appears in the `on_moderation` status
- updating Swagger/OpenAPI for the new notification endpoints

### Goal
Build an extensible foundation for notifications so that business events can deliver messages through different channels and users can manage their internal notifications through the API.

### Acceptance Criteria
- the system has an internal notification model
- a user can fetch their own internal notifications through the API
- a user can delete their own internal notification through the API
- a user cannot fetch or delete another user's internal notification
- the email channel is separated as its own notification delivery channel
- when a news item moves to `on_moderation`, an internal notification is created for administrators
- the new API endpoints are protected by authorization
- Swagger/OpenAPI describes the new notification endpoints and authorization requirements

### Technical Approach
- internal notifications can be stored in a dedicated Doctrine entity
- notification dispatch should be separated from the business event source through a service
- Symfony Mailer can be used for the email channel
- access to internal notifications can be checked through a voter or an explicit owner check in the application layer
- the news transition to `on_moderation` should create notifications only for administrators

### How To Test
- create several notifications for different users
- call the notification list endpoint as a user and verify that only their messages are returned
- delete the user's own notification and verify that it no longer appears in the list
- try to delete another user's notification and verify that the server returns `403 Forbidden` or `404 Not Found`
- move a news item to the `on_moderation` status and verify that administrators receive an internal notification
- verify that Swagger/OpenAPI exposes the new notification endpoints and authorization requirements

### Notes
- the first iteration can keep internal notifications simple without a full read/unread workflow if it is not required for acceptance
- email notifications can be connected to concrete business events gradually
- if the notification list grows, pagination should be added later
