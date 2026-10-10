# Task 11

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [RU](#ru)
  - [Статус](#%D1%81%D1%82%D0%B0%D1%82%D1%83%D1%81)
  - [Название](#%D0%BD%D0%B0%D0%B7%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5)
  - [Описание задачи](#%D0%BE%D0%BF%D0%B8%D1%81%D0%B0%D0%BD%D0%B8%D0%B5-%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B8)
  - [Цель](#%D1%86%D0%B5%D0%BB%D1%8C)
  - [Архитектурный контекст](#%D0%B0%D1%80%D1%85%D0%B8%D1%82%D0%B5%D0%BA%D1%82%D1%83%D1%80%D0%BD%D1%8B%D0%B9-%D0%BA%D0%BE%D0%BD%D1%82%D0%B5%D0%BA%D1%81%D1%82)
  - [Критерии приёмки](#%D0%BA%D1%80%D0%B8%D1%82%D0%B5%D1%80%D0%B8%D0%B8-%D0%BF%D1%80%D0%B8%D1%91%D0%BC%D0%BA%D0%B8)
  - [Технический подход](#%D1%82%D0%B5%D1%85%D0%BD%D0%B8%D1%87%D0%B5%D1%81%D0%BA%D0%B8%D0%B9-%D0%BF%D0%BE%D0%B4%D1%85%D0%BE%D0%B4)
  - [Как тестировать](#%D0%BA%D0%B0%D0%BA-%D1%82%D0%B5%D1%81%D1%82%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D1%82%D1%8C)
  - [Примечания](#%D0%BF%D1%80%D0%B8%D0%BC%D0%B5%D1%87%D0%B0%D0%BD%D0%B8%D1%8F)
- [EN](#en)
  - [Status](#status)
  - [Title](#title)
  - [Task Description](#task-description)
  - [Goal](#goal)
  - [Architecture Context](#architecture-context)
  - [Acceptance Criteria](#acceptance-criteria)
  - [Technical Approach](#technical-approach)
  - [How To Test](#how-to-test)
  - [Notes](#notes)

<!-- END doctoc -->

## RU

### Статус

**В работе.** Kubernetes-мониторинг и три дашборда добавлены; результаты проверки HPA и сравнения k6-прогонов ещё не зафиксированы.

### Название

Запуск сервисов в Kubernetes и проверка горизонтального масштабирования Symfony/PHP-FPM.

### Описание задачи

Добавить Kubernetes как второй, независимый способ запуска проекта. Существующий Docker Compose workflow должен продолжать работать без Kubernetes. Развернуть Auth, Catalog и Cart так, чтобы их HTTP-экземпляры можно было масштабировать независимо, а Catalog gRPC и Messenger workers запускались как отдельные роли.

Подготовить общее состояние и конфигурацию, необходимые нескольким Pod: JWT-ключи и секреты Auth, пользовательские сессии, подключения к PostgreSQL, Redis, RabbitMQ и Elasticsearch. Сохранить Prometheus/Grafana, обеспечить метрики PHP-FPM для каждого Pod и проверить масштабирование существующими k6-сценариями. Архитектура должна работать как на одной Kubernetes node, так и на нескольких без изменений приложения.

### Цель

Подтвердить, что увеличение числа Pod Auth, Catalog и Cart повышает доступную производительность соответствующего сервиса без нарушения авторизации, сессий, фоновой обработки и достоверности метрик.

### Архитектурный контекст

- Docker Compose и Kubernetes используют один проект, но имеют независимые конфигурации развёртывания.
- Каждый Pod приложения получает собственный PHP-FPM; Pod одного сервиса не должны зависеть от локальных файлов друг друга.
- Auth Pod используют одну пару JWT-ключей, один `APP_SECRET`, один `JWT_PASSPHRASE` и общее хранилище сессий для stateful admin-сценариев. Изменения обработки сессий должны сохранять текущий запуск через Compose.
- Catalog HTTP, Catalog gRPC и Messenger workers запускаются и масштабируются независимо, с сохранением текущих команд и поведения.
- PostgreSQL, Redis, RabbitMQ и Elasticsearch остаются общими зависимостями приложений. Их кластеризация и высокая доступность не являются частью Task 11.
- PHP-FPM exporter относится к конкретному экземпляру PHP-FPM. Symfony application metrics, хранящиеся в общем Redis, собираются один раз на логический сервис, чтобы реплики не умножали показатели.

### Критерии приёмки

- Auth, Catalog и Cart запускаются в Kubernetes из образов без bind mounts исходного кода.
- Существующий Docker Compose запуск и его Grafana dashboard работают как до изменений.
- Каждый из трёх HTTP-сервисов работает с несколькими Pod и масштабируется независимо; решение не привязано к числу Kubernetes nodes или именам Pod.
- Все Auth Pod получают одну и ту же пару JWT-ключей и необходимые общие секреты. Новые Pod не генерируют ключи повторно.
- Пользовательская сессия stateful части Auth работает при попадании последовательных запросов в разные Pod; JWT-сценарии также работают через разные Pod.
- Catalog HTTP, gRPC и workers запускаются как отдельные роли. Поведение очередей и индексации сохраняется.
- Prometheus собирает PHP-FPM metrics отдельно для каждого Pod без смешивания экземпляров. Общие Symfony metrics не учитываются многократно.
- Для Kubernetes созданы три независимых Grafana dashboard: основной Kubernetes, порт исходного Docker dashboard и объединённый без бессмысленных дублей. Выбор сервиса поддерживает Auth/Catalog/Cart и All. Основной dashboard показывает active/idle PHP-FPM processes и listen queue по Pod, `SUM` и `MAX` очереди по сервису, CPU/RAM по Pod и сервису, число активных и ready Pod и историю изменения числа Pod. Дополнительно доступны Top 15 Pod по CPU и использование памяти относительно лимита по сервису, Pod и паре service/container. Запросы используют Kubernetes/Prometheus labels.
- Проверены ручное изменение числа Pod и работа HPA под нагрузкой существующих k6-сценариев; зафиксированы RPS, latency, error rate, ресурсы и изменение числа ready Pod.

### Технический подход

1. **Подготовка проекта.** Добавить самодостаточные образы Auth, Catalog и Cart, сохранив существующие Compose targets и bind mounts. Проверить локальное состояние приложений; изменить Symfony-код только там, где без этого несколько реплик работать не могут. Для Auth предусмотреть общее хранилище сессий в Kubernetes (сейчас сессии файловые) при сохранении текущего Compose workflow.
2. **Bootstrap JWT.** Добавить npm-команду и bootstrap script для первоначального развёртывания. Скрипт использует существующие `private.pem` и `public.pem`, если присутствует полная пара; при их отсутствии запускает существующий `bin/init-jwt` в окружении Symfony-контейнера. `JWT_PASSPHRASE` берётся из текущей конфигурации без интерактивного ввода. Пара ключей и passphrase сохраняются в Kubernetes Secret: ключи монтируются как файлы во все Auth Pod, passphrase передаётся через переменную окружения. Неполная пара считается ошибкой. Существующий Compose JWT workflow не меняется.
3. **Конфигурация Kubernetes.** Добавить отдельные manifests и bootstrap/deploy scripts для приложений, их ролей, конфигурации, секретов и подключений к общим зависимостям. Не переносить Kubernetes-специфичные настройки в Compose без технической необходимости.
4. **Мониторинг.** Размещать PHP-FPM exporter рядом с соответствующим PHP-FPM. Сохранить Compose dashboard неизменным и поддерживать три отдельных Kubernetes dashboard: основной, порт исходного Compose dashboard и объединённый. Привязать запросы к фактическим discovery labels и проверить их в работающем кластере.
5. **Масштабирование и измерение.** Проверить несколько Pod каждого HTTP-сервиса, затем настроить HPA и провести сопоставимые k6-прогоны до и после масштабирования.

### Как тестировать

- Проверить работу образов без bind mounts и запуск каждой роли Catalog.
- Проверить первоначальный JWT bootstrap с готовой парой и без неё, а также отсутствие повторной генерации при создании Pod.
- Проверить Auth-сессию и JWT при запросах через разные Auth Pod.
- Масштабировать Auth, Catalog и Cart по отдельности; проверить маршрутизацию, готовность Pod, ошибки и работу зависимых сценариев.
- Сопоставить число PHP-FPM metric series с числом Pod; проверить значения Symfony metrics при добавлении реплик.
- Проверить панели Kubernetes dashboard при появлении и удалении Pod.
- Запустить существующие k6-сценарии, сравнить метрики при одном и нескольких Pod и проверить реакцию HPA.
- Обязательно повторить regression-проверку Docker Compose запуска и существующего Compose dashboard.

### Примечания

- Kubernetes manifests и три dashboard уже добавлены. Подтверждение HPA и сравнение k6-прогонов остаются открытыми пунктами Task 11.
- VPA, Redis Cluster и PostgreSQL HA не входят в Task 11.
- Реальные значения секретов не фиксируются в документации или Kubernetes manifests.

## EN

### Status

**In progress.** Kubernetes monitoring and three dashboards are present; HPA verification and comparative k6 results have not yet been recorded.

### Title

Run services in Kubernetes and verify horizontal scaling of Symfony/PHP-FPM.

### Task Description

Add Kubernetes as an independent deployment option while preserving the existing Docker Compose workflow. Run Auth, Catalog, and Cart so that their HTTP replicas can scale independently, with separate Catalog gRPC and Messenger worker roles. Preserve monitoring and verify scaling with the existing k6 scenarios. The application architecture must work with one or multiple Kubernetes nodes.

### Goal

Demonstrate that adding Auth, Catalog, or Cart Pods increases the capacity of the selected service without breaking authentication, sessions, background processing, or metric accuracy.

### Architecture Context

- Compose and Kubernetes remain independent deployment paths for the same project.
- Application Pods do not rely on another Pod’s local files. Auth replicas share JWT material, application secrets, and stateful admin sessions.
- Catalog HTTP, gRPC, and workers remain separate execution roles.
- PostgreSQL, Redis, RabbitMQ, and Elasticsearch are shared dependencies; their high availability is outside this task.
- PHP-FPM metrics are collected per Pod. Symfony application metrics backed by shared Redis are collected once per logical service.

### Acceptance Criteria

- Auth, Catalog, and Cart run from images without source-code bind mounts; the Compose workflow and its dashboard still work.
- Each HTTP service runs with multiple Pods and scales independently, without relying on Pod names or a fixed node count.
- All Auth replicas use the same JWT key pair and required secrets; newly created Pods do not regenerate keys. Stateful admin sessions and JWT requests work across replicas.
- Catalog HTTP, gRPC, and workers run independently without changing queue or indexing behavior.
- Prometheus exposes separate PHP-FPM metrics per Pod without multiplying shared Symfony metrics.
- Three independent Kubernetes Grafana dashboards are available: the primary Kubernetes dashboard, a port of the original Docker dashboard, and a combined dashboard without redundant panels. Service selection supports Auth/Catalog/Cart and All. The primary dashboard provides PHP-FPM active/idle processes and listen queue per Pod; service-level queue `SUM` and `MAX`; CPU/RAM per Pod and service; active and ready Pod counts; and Pod-count history. Additional panels show the Top 15 Pods by CPU and memory usage relative to limits by service, Pod, and service/container.
- Manual scaling and HPA are verified under existing k6 workloads, with performance and readiness results recorded.

### Technical Approach

1. Prepare self-contained service images and resolve confirmed local-state constraints while retaining Compose compatibility.
2. Add a one-time npm-driven JWT bootstrap: reuse an existing complete key pair or run `bin/init-jwt` using the current `JWT_PASSPHRASE`; create a Kubernetes Secret and mount it into all Auth Pods.
3. Add separate Kubernetes manifests and deployment scripts for the application roles and their configuration.
4. Pair each PHP-FPM instance with its exporter. Keep the Compose dashboard unchanged and maintain three separate Kubernetes dashboards: primary, original dashboard port, and combined. Match queries to actual discovery labels and verify them in the running cluster.
5. Verify independent manual scaling, configure HPA, and compare k6 runs.

### How To Test

- Start each image without bind mounts and exercise each Catalog role.
- Test both JWT bootstrap paths and verify that new Pods reuse the same keys.
- Exercise Auth sessions and JWT across replicas.
- Scale services independently and check routing, readiness, errors, and dependent workflows.
- Verify exporter series per Pod and stable shared application metrics.
- Check dashboard behavior as Pods appear and disappear.
- Compare one-Pod and multi-Pod k6 results and HPA behavior.
- Run Docker Compose regression checks, including its existing dashboard.

### Notes

- Kubernetes manifests and three dashboards are now present. HPA validation and comparative k6 results remain open Task 11 items.
- VPA, Redis Cluster, and PostgreSQL HA are outside Task 11.
- Real secret values must not be committed to documentation or manifests.
