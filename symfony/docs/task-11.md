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
  - [Зафиксированные результаты нагрузочного тестирования и мониторинга](#%D0%B7%D0%B0%D1%84%D0%B8%D0%BA%D1%81%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D0%BD%D0%BD%D1%8B%D0%B5-%D1%80%D0%B5%D0%B7%D1%83%D0%BB%D1%8C%D1%82%D0%B0%D1%82%D1%8B-%D0%BD%D0%B0%D0%B3%D1%80%D1%83%D0%B7%D0%BE%D1%87%D0%BD%D0%BE%D0%B3%D0%BE-%D1%82%D0%B5%D1%81%D1%82%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D0%BD%D0%B8%D1%8F-%D0%B8-%D0%BC%D0%BE%D0%BD%D0%B8%D1%82%D0%BE%D1%80%D0%B8%D0%BD%D0%B3%D0%B0)
    - [Реализованный HPA и наблюдения под нагрузкой](#%D1%80%D0%B5%D0%B0%D0%BB%D0%B8%D0%B7%D0%BE%D0%B2%D0%B0%D0%BD%D0%BD%D1%8B%D0%B9-hpa-%D0%B8-%D0%BD%D0%B0%D0%B1%D0%BB%D1%8E%D0%B4%D0%B5%D0%BD%D0%B8%D1%8F-%D0%BF%D0%BE%D0%B4-%D0%BD%D0%B0%D0%B3%D1%80%D1%83%D0%B7%D0%BA%D0%BE%D0%B9)
    - [Централизованные HTTP-логи и дашборд Kibana](#%D1%86%D0%B5%D0%BD%D1%82%D1%80%D0%B0%D0%BB%D0%B8%D0%B7%D0%BE%D0%B2%D0%B0%D0%BD%D0%BD%D1%8B%D0%B5-http-%D0%BB%D0%BE%D0%B3%D0%B8-%D0%B8-%D0%B4%D0%B0%D1%88%D0%B1%D0%BE%D1%80%D0%B4-kibana)
  - [Следующая задача: Performance Bottleneck Investigation](#%D1%81%D0%BB%D0%B5%D0%B4%D1%83%D1%8E%D1%89%D0%B0%D1%8F-%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B0-performance-bottleneck-investigation)
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
  - [Recorded load testing and monitoring results](#recorded-load-testing-and-monitoring-results)
    - [Implemented HPA and load observations](#implemented-hpa-and-load-observations)
    - [Centralized HTTP logs and Kibana dashboard](#centralized-http-logs-and-kibana-dashboard)
  - [Next task: Performance Bottleneck Investigation](#next-task-performance-bottleneck-investigation)
  - [Notes](#notes)

<!-- END doctoc -->

## RU

### Статус

**Документирование завершено.** HPA для трёх HTTP-сервисов и централизованный анализ HTTP-логов реализованы в Task 11. Конфигурация и наблюдавшиеся нагрузочные интервалы зафиксированы ниже. Сопоставимый отчёт k6 для одного и нескольких Pod и история решений HPA не сохранены; количественный эффект автомасштабирования по ним не доказан.

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

### Зафиксированные результаты нагрузочного тестирования и мониторинга

#### Реализованный HPA и наблюдения под нагрузкой

В рамках Task 11 в Helm созданы три независимых `autoscaling/v2` HPA для Auth (`symfony-web`), Catalog (`catalog-web`) и Cart (`cart-web`). PHP-FPM exporter каждого HTTP Pod предоставляет `phpfpm_listen_queue`; Prometheus собирает серии с labels namespace, Pod и service, а Prometheus Adapter публикует их через `pods/phpfpm_listen_queue` в Custom Metrics API. Adapter берёт максимум очереди в окне **30 секунд** для каждого Pod; HPA сравнивает среднее значение по Pod соответствующего Deployment с целью **10 ожидающих запросов на Pod**. Текущие Helm values включают все три HPA с границами **1–20 реплик**, `scaleUp.stabilizationWindowSeconds: 0` и `scaleDown.stabilizationWindowSeconds: 300`. При включённом HPA Deployment не задаёт `spec.replicas`. Активные и свободные процессы PHP-FPM помогают диагностировать насыщение пула, но не входят в правило HPA. Catalog gRPC и workers этим HPA не управляются. [Конфигурация и команды проверки](../../kubernetes/README.md#%D0%B3%D0%BE%D1%80%D0%B8%D0%B7%D0%BE%D0%BD%D1%82%D0%B0%D0%BB%D1%8C%D0%BD%D0%BE%D0%B5-%D0%BC%D0%B0%D1%81%D1%88%D1%82%D0%B0%D0%B1%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5-http-%D1%81%D0%B5%D1%80%D0%B2%D0%B8%D1%81%D0%BE%D0%B2) описаны отдельно.

В Kubernetes доступны три [Grafana dashboard](../../kubernetes/README.md#monitoring): основной, перенос исходного Compose dashboard и объединённый. Prometheus собирает Gateway RPS и статусы, application latency, метрики PHP-FPM по Pod (active/idle processes и listen queue), число running/ready Pod, CPU, память и системные показатели. На сохранённом снимке объединённого dashboard во время двух нагрузочных интервалов видны рост очередей и числа running/ready HTTP Pod, затем возврат числа Pod к исходному уровню; линии по Pod показывают загрузку PHP-FPM, CPU и память. Это демонстрирует масштабирование развёртываний при нагрузке и доступность метрик для его анализа. На снимке нет `desiredReplicas`, событий HPA и журнала ручных действий, поэтому приписать каждое изменение числа Pod именно HPA или оценить выигрыш в RPS нельзя. В рассматриваемом окне общие ресурсы стенда не выглядят полностью исчерпанными; отдельные очереди PHP-FPM всё же требуют проверки по Pod и времени.

![Task 11: Grafana, два нагрузочных интервала и метрики Kubernetes](<images/11 task Symfony services _ Kubernetes _ Combined-1793541598970.png>)

#### Централизованные HTTP-логи и дашборд Kibana

В рамках Task 11 для Kubernetes `prod` и `load-test` реализован путь **Nginx Gateway и приложений → Filebeat DaemonSet → отдельный Elasticsearch Logs → Kibana Logs**. Filebeat читает access/error потоки контейнера Gateway `nginx` и access потоки Nginx внутри application `php` Pod, добавляет Kubernetes metadata и сервис, разбирает существующие форматы Gateway и JSON-формат приложения, затем пишет в alias `nginx-logs`. Отдельный кластер Catalog Elasticsearch/Kibana не участвует в сборе логов; профиль `dev` этот стек не разворачивает. В индекс попадают `@timestamp`, `environment`, `service.name`, Pod/контейнер, `request_id`, HTTP-метод и `http.response.status_code`, `request_time`, адрес и времена upstream. Вместо исходного URI сохраняются `url.path` и `url.original` с маскировкой чувствительных query-параметров; сырые request line/URI, debug tokens и другие чувствительные поля удаляются перед индексацией. Подробности конфигурации, ILM и доставки находятся в [руководстве Kubernetes](../../kubernetes/README.md#nginx-request-logs).

На сохранённом снимке созданного дашборда Kibana видны четыре графика:

| Панель | Что показывает и для чего используется |
| --- | --- |
| `api-gateway RPS` | Число access-записей Gateway за секунду; поиск разгона, плато и кратких просадок потока завершённых запросов. |
| `latency distribution` | Распределение `request_time` по всем записям выбранного окна и фильтра; оценка основной массы запросов и длинного хвоста. |
| `max latency` | Максимальный `request_time` по источникам рядом с числом записей/с; локализация всплесков медленных запросов по Gateway/Auth/Catalog/Cart. |
| `http statuses` | Частоты отдельных кодов ответа по времени, включая 2xx, 4xx и 5xx; выделение конкретной ошибки и сравнение её частоты с RPS. |

Верхняя строка Kibana задаёт временное окно и KQL-фильтр. Для расследования выберите на графике секунды просадки RPS, задайте `service.name: "api-gateway"` и фильтр по `http.response.status_code` (например, конкретный 502/503 или диапазон 4xx/5xx), затем сравните частоту ответа с общей линией RPS. В Discover можно раскрыть запись по `request_id`, `url.path`, `request_time`, `upstream.response_time` и соседнюю запись приложения. Для медленных запросов выберите интервал всплеска `max latency`, отсортируйте `request_time` по убыванию и сравните upstream timings. Агрегаты Prometheus показывают тренд и использование ресурсов; HTTP-логи дают конкретный код, путь, длительность и `request_id` отдельного завершённого запроса. Совпадение по времени не устанавливает причину просадки.

Скриншот Kibana за **1 ноября 2026 года, 18:31–18:44** показывает после разгона примерно с 18:34 интенсивность Gateway около **1400 access-log записей в секунду** на большей части нагрузочного интервала; линия колеблется и имеет короткие провалы. Панели показывают распределение длительности, максимальную длительность запроса по источникам и число ответов по кодам, включая 200, 201, 204, 400, 409, 502 и 503. В окне отображается 792 463 записи в панели распределения latency, но это число записей выбранного фильтра и интервала, а не количество HTTP-запросов завершённого k6-прогона. По снимку нельзя восстановить точное число VU, профиль сценария, итоговый средний RPS, k6 p95 или долю ошибок за весь прогон.

![Task 11: Kibana, HTTP access-log RPS, latency и статусы](<images/11 task kuber RPS.png>)

Снимок показывает наличие 4xx/5xx, но не доказывает, что они вызвали провалы RPS. Nginx пишет access log после завершения запроса, поэтому таймаут k6 не обязан немедленно появиться в Kibana.

Предыдущая [калибровка ресурсов](../../docs/performance/kubernetes-resource-calibration.md) описывает **другой** интервал: приблизительно 255 RPS в среднем и 302 RPS на пике по `nginx_vts_server_requests_total`, а также CPU usage и throttling контейнеров. Эти показатели нельзя объединять с ноябрьскими скриншотами как один прогон. Новый Grafana снимок показывает, что ресурсы стенда в рассматриваемых интервалах не были полностью исчерпаны; однако отдельные очереди PHP-FPM и ранее замеченный throttling требуют проверки по времени и Pod. Причина ограничения дальнейшего роста RPS не установлена. На наблюдение могли влиять Windows/WSL2, конкурирующие процессы, сеть или отдельные компоненты приложения; **1400 RPS не является доказанным максимумом приложения**.

### Следующая задача: Performance Bottleneck Investigation

Отдельным этапом нужно исследовать загрузку и очереди PHP-FPM и RoadRunner gRPC, CPU throttling и pressure, ограничения контейнеров, сетевые задержки и инфраструктуру WSL2. Следует синхронно сопоставить RPS, latency, 4xx/5xx, очереди и использование ресурсов, сохранить k6 summary и историю решений уже реализованного HPA для количественной оценки scale-up/scale-down. Затем повторить тот же профиль на выделенном Linux-стенде без Windows/WSL2. Это направления исследования, а не реализация HPA или логирования: обе системы уже входят в Task 11.

### Примечания

- Kubernetes manifests, три Grafana dashboard, три HPA и централизованный стек Nginx-логов реализованы; количественный эффект HPA и сравнение k6-прогонов остаются неподтверждёнными пунктами исходных критериев.
- VPA, Redis Cluster и PostgreSQL HA не входят в Task 11.
- Реальные значения секретов не фиксируются в документации или Kubernetes manifests.

## EN

### Status

**Documentation complete.** HPA for the three HTTP services and centralized HTTP log analysis were implemented in Task 11. The configuration and observed load intervals are recorded below. A comparable one-Pod versus multi-Pod k6 report and HPA decision history were not saved, so the quantitative scaling benefit remains unverified.

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

### Recorded load testing and monitoring results

#### Implemented HPA and load observations

Task 11 added three independent `autoscaling/v2` HPAs for Auth (`symfony-web`), Catalog (`catalog-web`), and Cart (`cart-web`). Each HTTP Pod's PHP-FPM exporter exposes `phpfpm_listen_queue`; Prometheus scrapes separate namespace, Pod, and service series, and Prometheus Adapter publishes `pods/phpfpm_listen_queue` through the Custom Metrics API. The Adapter takes the maximum queue sample over **30 seconds** per Pod. Each HPA compares the average across Pods of its Deployment with a target of **10 queued requests per Pod**. Current Helm values enable all three HPAs with **1–20 replicas**, zero scale-up stabilization, and **300 seconds** of scale-down stabilization. Helm omits Deployment `spec.replicas` while HPA is enabled. Active/idle PHP-FPM workers help diagnose pool saturation but do not drive HPA; Catalog gRPC and workers have no PHP-FPM queue HPA. See the [Kubernetes autoscaling guide](../../kubernetes/README.md#http-horizontal-autoscaling) for configuration and checks.

Kubernetes has three [Grafana dashboards](../../kubernetes/README.md#monitoring): the primary view, a port of the Compose dashboard, and a combined view. Prometheus provides Gateway RPS and statuses, application latency, per-Pod PHP-FPM active/idle processes and listen queue, running/ready Pod counts, CPU, memory, and system metrics. In two captured load intervals, the combined view shows queue growth and more running/ready HTTP Pods, followed by a return to the initial Pod count; per-Pod lines show PHP-FPM, CPU, and memory use. This records Deployment scaling during load and the metrics used to inspect it. The image does not show HPA `desiredReplicas`, controller events, or manual-action history, so it cannot attribute each replica change to HPA or quantify an RPS benefit. Overall test-environment resources do not appear fully exhausted in this window, while individual PHP-FPM queues still require time-aligned inspection.

![Task 11 Grafana: two load intervals and Kubernetes metrics](<images/11 task Symfony services _ Kubernetes _ Combined-1793541598970.png>)

#### Centralized HTTP logs and Kibana dashboard

Task 11 implemented the **Gateway/application Nginx → Filebeat DaemonSet → dedicated Elasticsearch Logs → Kibana Logs** path for Kubernetes `prod` and `load-test`. Filebeat reads Gateway `nginx` access/error streams and application Nginx access streams from `php` Pods, adds Kubernetes and service metadata, parses the Gateway formats and application JSON format, and writes to the `nginx-logs` alias. Catalog's Elasticsearch/Kibana remain separate; `dev` does not deploy this log stack. Indexed data includes `@timestamp`, `environment`, `service.name`, Pod/container, `request_id`, HTTP method and `http.response.status_code`, `request_time`, upstream address and timings. The raw URI is replaced with `url.path` and a `url.original` whose sensitive query values are redacted; raw request lines/URIs, debug tokens, and other sensitive access fields are dropped before indexing. See the [Kubernetes logging guide](../../kubernetes/README.md#nginx-request-logs) for the configuration, ILM, and delivery limits.

The saved Kibana dashboard capture has four panels:

| Panel | Purpose |
| --- | --- |
| `api-gateway RPS` | Gateway access records per second, revealing ramp-up, plateaus, and brief drops in completed-request flow. |
| `latency distribution` | Distribution of `request_time` for all records selected by time and filters, showing the main body and long tail. |
| `max latency` | Maximum `request_time` by source alongside records/s, locating slow-request spikes in Gateway/Auth/Catalog/Cart. |
| `http statuses` | Per-second counts for individual 2xx, 4xx, and 5xx codes, allowing a specific error to be compared with RPS. |

The top Kibana controls set the time window and KQL filter. To investigate a dip, select its seconds on the RPS chart, filter `service.name: "api-gateway"` and `http.response.status_code` for a specific 502/503 or a 4xx/5xx range, then compare the code's rate with overall RPS. In Discover, open an individual record by `request_id`, `url.path`, `request_time`, and `upstream.response_time`, then inspect the matching application record. For slow requests, select a `max latency` spike, sort `request_time` descending, and compare upstream timings. Prometheus aggregates expose trends and resource use; HTTP logs retain the specific code, path, duration, and request ID of a completed request. Temporal correlation alone does not prove the cause of a dip.

The saved Kibana capture for **1 November 2026, 18:31–18:44** shows roughly **1,400 Gateway access-log records/s** during much of the load interval after the ramp near 18:34, with fluctuations and brief dips. Its panels show a latency distribution, maximum request duration by source, and response counts by status, including 200, 201, 204, 400, 409, 502, and 503. The latency panel displays 792,463 records in its selected window; that is not a completed k6 request total. The screenshot does not establish the VU count, scenario mix, final average k6 RPS, k6 p95, or whole-run error rate.

![Task 11 Kibana: access-log RPS, latency, and statuses](<images/11 task kuber RPS.png>)

The capture does not establish that the visible errors caused the RPS dips. Nginx writes an access record only when a request finishes, so a k6 timeout may not immediately appear in Kibana.

The earlier [resource calibration](../../docs/performance/kubernetes-resource-calibration.md) covers a **different** interval: approximately 255 average and 302 peak RPS from `nginx_vts_server_requests_total`, plus container CPU usage and throttling. These measurements must not be merged with the November screenshots as one run. The newer Grafana capture indicates that the test environment's resources were not fully exhausted in the observed intervals, while per-Pod queues and previously observed throttling warrant time-aligned inspection. The factor limiting further RPS growth is unknown. Windows/WSL2, competing processes, networking, or an application component may contribute. **1,400 RPS is not an established application maximum.**

### Next task: Performance Bottleneck Investigation

In a separate task, inspect PHP-FPM and RoadRunner gRPC utilization and queues, CPU throttling and pressure, container limits, network delays, and WSL2 infrastructure. Correlate RPS, latency, 4xx/5xx, queues, and resource usage in the same time window; save the k6 summary and decision history of the already implemented HPA to quantify scale-up/down. Repeat the same workload on a dedicated Linux host without Windows/WSL2. These are future investigations; HPA and logging implementation belong to Task 11.

### Notes

- Kubernetes manifests, three Grafana dashboards, three HPAs, and centralized Nginx logging are implemented; HPA's quantitative benefit and comparative k6 runs remain unverified original criteria.
- VPA, Redis Cluster, and PostgreSQL HA are outside Task 11.
- Real secret values must not be committed to documentation or manifests.
