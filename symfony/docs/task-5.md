# Task 5

## RU

### Название
Экспорт новостей в S3 через очередь

### Описание задачи
На этом этапе требуется добавить асинхронный экспорт новостей в файл с сохранением результата в S3-compatible storage.

Для локальной разработки нужно поднять MinIO как S3-совместимое хранилище. Экспорт должен запускаться из приложения, передаваться в очередь через Symfony Messenger и обрабатываться worker'ом через уже подключенный RabbitMQ.

В минимальный объем задачи войдут:
- добавление MinIO в Docker Compose
- настройка bucket, endpoint, access key и secret key через переменные окружения
- добавление механизма запуска экспорта новостей
- постановка задачи экспорта в очередь через Symfony Messenger
- обработка сообщения worker'ом и формирование файла экспорта
- сохранение готового файла в S3-compatible storage
- фиксация состояния экспорта, чтобы можно было проверить результат или ошибку
- обновление Swagger/OpenAPI, если запуск или просмотр экспорта выполняется через API

### Цель
Подготовить надежный асинхронный сценарий для тяжелых операций экспорта, чтобы пользовательский запрос не ждал формирования файла, а результат сохранялся во внешнем файловом хранилище.

### Критерии приемки
- MinIO добавлен в локальное Docker Compose окружение
- параметры подключения к S3-compatible storage вынесены в переменные окружения
- в MinIO создается или используется отдельный bucket для экспортов
- пользователь с нужными правами может запустить экспорт новостей
- запуск экспорта создает задачу и отправляет сообщение в очередь RabbitMQ
- worker Symfony Messenger обрабатывает сообщение экспорта
- результат экспорта сохраняется в MinIO/S3
- состояние экспорта можно проверить после запуска
- ошибки экспорта фиксируются в состоянии задачи и не теряются
- повторный запуск worker'а не должен создавать неконсистентное состояние для уже обработанной задачи
- Swagger/OpenAPI отражает новые export-ручки и требования авторизации, если экспорт доступен через API

### Технический подход
- для локального S3-compatible storage использовать MinIO
- для работы с S3 можно использовать AWS SDK for PHP или Flysystem S3 adapter
- экспорт лучше хранить как отдельную Doctrine entity со статусами `pending`, `processing`, `completed` и `failed`
- сообщение Messenger должно передавать идентификатор export-записи, а не весь набор новостей
- worker должен загружать данные из базы в момент обработки сообщения
- файл экспорта можно сформировать в формате JSON или CSV, если бизнес-формат не задан отдельно
- ключ объекта в S3 стоит делать стабильным и уникальным, например с использованием export id
- доступ к запуску экспорта должен быть ограничен авторизованными пользователями с подходящей ролью

### Как тестировать
- запустить локальное окружение с RabbitMQ и MinIO
- убедиться, что MinIO Management UI доступен и bucket для экспортов существует
- запустить экспорт новостей через API или консольную команду
- проверить, что после запуска создана запись экспорта в статусе `pending`
- проверить в RabbitMQ Management UI, что сообщение попадает в очередь
- запустить Symfony Messenger worker и дождаться обработки сообщения
- убедиться, что статус экспорта изменился на `completed`
- проверить в MinIO, что файл экспорта создан в нужном bucket
- скачать файл из MinIO и проверить, что он содержит ожидаемые новости
- смоделировать ошибку S3 или обработки и убедиться, что экспорт переходит в статус `failed`

### Примечания
- RabbitMQ уже подключен в рамках предыдущей задачи, поэтому здесь нужно использовать существующий транспорт, а не добавлять новый брокер
- на первом этапе достаточно одного формата экспорта, если несколько форматов не требуются для приемки
- если экспорт будет доступен обычным пользователям, нужно отдельно определить, какие новости они имеют право выгружать
- для больших объемов данных позже может потребоваться потоковая запись файла, пагинация выборки и ограничение количества одновременных экспортов

## EN

### Title
Export news to S3 through a queue

### Task Description
At this stage, the project needs asynchronous news export into a file with the result stored in S3-compatible storage.

For local development, MinIO should be added as the S3-compatible storage. The export should be started from the application, sent to a queue through Symfony Messenger, and processed by a worker through the already configured RabbitMQ broker.

The initial scope includes:
- adding MinIO to Docker Compose
- configuring the bucket, endpoint, access key, and secret key through environment variables
- adding a mechanism for starting a news export
- dispatching the export job to the queue through Symfony Messenger
- processing the message in a worker and generating the export file
- storing the generated file in S3-compatible storage
- tracking the export state so the result or error can be inspected
- updating Swagger/OpenAPI if export creation or inspection is exposed through the API

### Goal
Build a reliable asynchronous flow for heavy export operations so that the user request does not wait for file generation and the result is stored in external file storage.

### Acceptance Criteria
- MinIO is added to the local Docker Compose environment
- S3-compatible storage connection settings are moved to environment variables
- a dedicated export bucket is created or used in MinIO
- a user with the required permissions can start a news export
- starting an export creates a job and sends a message to RabbitMQ
- the Symfony Messenger worker processes the export message
- the export result is stored in MinIO/S3
- the export state can be checked after startup
- export errors are captured in the job state and are not lost
- restarting the worker must not create inconsistent state for an already processed job
- Swagger/OpenAPI reflects the new export endpoints and authorization requirements if the export is exposed through the API

### Technical Approach
- use MinIO for local S3-compatible storage
- use AWS SDK for PHP or the Flysystem S3 adapter for S3 access
- store exports as a dedicated Doctrine entity with `pending`, `processing`, `completed`, and `failed` statuses
- the Messenger message should pass the export record id instead of the full news dataset
- the worker should load data from the database when processing the message
- the export file can be generated as JSON or CSV unless a specific business format is defined separately
- the S3 object key should be stable and unique, for example based on the export id
- access to export creation should be limited to authenticated users with the required role

### How To Test
- start the local environment with RabbitMQ and MinIO
- verify that the MinIO Management UI is available and the export bucket exists
- start a news export through the API or a console command
- verify that an export record is created with the `pending` status
- verify in RabbitMQ Management UI that the message reaches the queue
- start the Symfony Messenger worker and wait for the message to be processed
- verify that the export status changes to `completed`
- verify in MinIO that the export file is created in the expected bucket
- download the file from MinIO and verify that it contains the expected news items
- simulate an S3 or processing error and verify that the export moves to the `failed` status

### Notes
- RabbitMQ was already integrated in the previous task, so this task should use the existing transport instead of adding a new broker
- the first iteration can support a single export format if multiple formats are not required for acceptance
- if export is available to regular users, the allowed news scope must be defined separately
- for large datasets, streaming file writes, paginated reads, and limits on concurrent exports may be needed later
