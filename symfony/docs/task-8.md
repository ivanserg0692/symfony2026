# Task 8

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [RU](#ru)
  - [Название](#%D0%BD%D0%B0%D0%B7%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5)
  - [Описание задачи](#%D0%BE%D0%BF%D0%B8%D1%81%D0%B0%D0%BD%D0%B8%D0%B5-%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B8)
  - [Цель](#%D1%86%D0%B5%D0%BB%D1%8C)
  - [Архитектурный контекст](#%D0%B0%D1%80%D1%85%D0%B8%D1%82%D0%B5%D0%BA%D1%82%D1%83%D1%80%D0%BD%D1%8B%D0%B9-%D0%BA%D0%BE%D0%BD%D1%82%D0%B5%D0%BA%D1%81%D1%82)
  - [Критерии приемки](#%D0%BA%D1%80%D0%B8%D1%82%D0%B5%D1%80%D0%B8%D0%B8-%D0%BF%D1%80%D0%B8%D0%B5%D0%BC%D0%BA%D0%B8)
  - [Технический подход](#%D1%82%D0%B5%D1%85%D0%BD%D0%B8%D1%87%D0%B5%D1%81%D0%BA%D0%B8%D0%B9-%D0%BF%D0%BE%D0%B4%D1%85%D0%BE%D0%B4)
  - [Как тестировать](#%D0%BA%D0%B0%D0%BA-%D1%82%D0%B5%D1%81%D1%82%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D1%82%D1%8C)
  - [Примечания](#%D0%BF%D1%80%D0%B8%D0%BC%D0%B5%D1%87%D0%B0%D0%BD%D0%B8%D1%8F)
- [EN](#en)
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

### Название

Мониторинг и нагрузочное тестирование интернет-магазина.

### Описание задачи

Внедрить мониторинг и нагрузочное тестирование интернет-магазина: определить источники метрик, настроить сбор и визуализацию показателей, а также подготовить k6-сценарии, имитирующие реальную работу пользователей.

В рамках задачи Docker runtime разделен на production- и development-окружения. Production запускается по умолчанию через PHP-FPM и Nginx с OPcache, а development подключает Symfony CLI, Composer и Xdebug через отдельный Compose override.

### Цель

Внедрить наблюдаемость основных компонентов системы и провести нагрузочное тестирование, чтобы определить производительность интернет-магазина, максимальный стабильный RPS, latency, количество ошибок под нагрузкой и возможные узкие места архитектуры.

### Архитектурный контекст

Система состоит из API Gateway на базе Nginx, основного Symfony-сервиса, Catalog Service, Cart/Order Service, внутренних REST/HTTP endpoints, внутренних gRPC-интеграций, PostgreSQL-баз данных и вспомогательной инфраструктуры.

Мониторинг должен охватывать как пользовательский путь через API Gateway, так и состояние отдельных backend-сервисов. HTTP-метрики Symfony-сервисов должны показывать нагрузку, latency, статус-коды и ошибки REST endpoints. Для внутренних gRPC-вызовов нужны отдельные Prometheus metrics, чтобы видеть latency, ошибки и нагрузку service-to-service взаимодействия.

Prometheus должен использоваться для сбора и хранения метрик. Grafana должна использоваться для визуализации состояния системы, сравнения нагрузки между сервисами и анализа деградации под нагрузкой. k6 должен использоваться для нагрузочного тестирования с постепенным увеличением нагрузки и сценариями, похожими на реальную работу пользователей интернет-магазина.

Системные и exporter-метрики должны покрывать Nginx/API Gateway, PostgreSQL, CPU, RAM и другие необходимые компоненты окружения, чтобы результаты нагрузочного тестирования можно было связать с использованием ресурсов.

Production и development используют общую Compose-архитектуру, но разные PHP image targets и runtime-параметры. Базовый `.env` и `docker-compose.yml` описывают production, а `.env.dev` и `docker-compose.dev.yml` переключают ту же инфраструктуру в development-режим.

### Критерии приемки

- Описан общий monitoring scope для API Gateway, основного Symfony-сервиса, Catalog Service, Cart/Order Service, gRPC-интеграций, PostgreSQL и системных ресурсов.
- Зафиксировано использование Prometheus для сбора и хранения метрик.
- Зафиксировано использование Grafana для визуализации метрик и анализа состояния системы.
- Зафиксировано использование k6 для нагрузочного тестирования интернет-магазина.
- Определены ключевые показатели: RPS, latency, p50, p95, p99, HTTP errors, gRPC errors, CPU, RAM, PostgreSQL connections/activity, Nginx/API Gateway metrics и нагрузка отдельных сервисов.
- Учтены отдельные Prometheus metrics для Symfony HTTP endpoints.
- Учтены отдельные Prometheus metrics для внутренних gRPC-вызовов.
- Нагрузочное тестирование должно позволять постепенно увеличивать нагрузку и определять максимальный стабильный RPS.
- Нагрузочное тестирование должно выявлять момент начала деградации latency.
- Нагрузочное тестирование должно фиксировать количество ошибок под нагрузкой.
- Нагрузочное тестирование должно помогать определить, какой компонент становится bottleneck.
- k6-сценарии должны имитировать реальную работу пользователей интернет-магазина, включая работу разных авторизованных пользователей.
- Результаты нагрузочных тестов должны связываться с ресурсными метриками сервера и отдельных компонентов.
- Production по умолчанию использует target `prod`, PHP-FPM, Nginx, OPcache, `APP_ENV=prod` и `APP_DEBUG=0`.
- Development использует target `dev`, Symfony CLI, Composer, Xdebug, `APP_ENV=dev` и `APP_DEBUG=1`.
- Production и development используют разные Compose project names и могут запускаться без конфликта имен ресурсов.

### Технический подход

- Определить набор компонентов, для которых должны собираться метрики.
- Разделить метрики пользовательского HTTP-трафика через API Gateway и внутренние service-level метрики.
- Предусмотреть Symfony HTTP metrics для основного Symfony-сервиса, Catalog Service и Cart/Order Service.
- Предусмотреть отдельные gRPC metrics для внутренних service-to-service вызовов.
- Предусмотреть exporter-метрики для Nginx/API Gateway, PostgreSQL, CPU, RAM и других необходимых компонентов.
- Сформировать набор Grafana dashboards для анализа общего состояния системы, HTTP/gRPC latency, ошибок, PostgreSQL activity и использования ресурсов.
- Сформировать k6 load profiles с постепенным ростом нагрузки.
- Сформировать k6 user scenarios, отражающие типовые действия интернет-магазина: просмотр каталога, поиск, просмотр товара, работа с корзиной, оформление заказа и действия авторизованных пользователей.
- Сравнивать результаты нагрузочных тестов с метриками Prometheus/Grafana, чтобы находить bottleneck по сервисам и ресурсам.
- Использовать multi-stage PHP image с targets `prod` и `dev` для разделения production runtime и development-инструментов.
- Хранить production Compose-конфигурацию в `.env` и `docker-compose.yml`, а development overrides - в `.env.dev` и `docker-compose.dev.yml`.
- Не менять бизнес-логику сервисов в рамках подготовки мониторинга и нагрузочного тестирования без отдельного согласования.

### Как тестировать

- Проверить, что для каждого важного компонента системы определен источник метрик.
- Проверить, что список метрик позволяет измерять RPS, latency p50/p95/p99, HTTP/gRPC errors и ресурсную нагрузку.
- Проверить, что нагрузочные сценарии k6 покрывают пользовательские пути интернет-магазина.
- Проверить, что сценарии учитывают разных авторизованных пользователей, а не только анонимный трафик.
- Проверить, что load profile позволяет постепенно повышать нагрузку и наблюдать деградацию.
- Проверить, что результаты тестов позволяют определить максимальный стабильный RPS.
- Проверить, что результаты тестов позволяют определить момент роста latency и появления ошибок.
- Проверить, что Grafana dashboards позволяют сопоставить нагрузку, ошибки и использование ресурсов.
- Проверить, что итоговый анализ позволяет назвать вероятный bottleneck при разных уровнях нагрузки.
- Проверить production-запуск командой `docker compose up -d` и значения `APP_ENV=prod`, `APP_DEBUG=0`.
- Проверить development-запуск командой `docker compose --env-file .env --env-file .env.dev up -d` и значения `APP_ENV=dev`, `APP_DEBUG=1`.
- Проверить, что Compose project names различаются и окружения не конфликтуют.

### Примечания

- Мониторинг, Grafana dashboards, Prometheus scrape config, k6 scripts и разделение Docker-окружений реализованы в рамках задачи.
- Production остается окружением по умолчанию; development подключается только при явном указании `.env.dev`.
- Мониторинг помогает анализировать как внешний пользовательский трафик через API Gateway, так и внутренние REST/gRPC взаимодействия сервисов.
- Нагрузочные тесты используются не только для поиска максимального RPS, но и для понимания деградации системы и потребления ресурсов.

## EN

### Title

Monitoring and load testing for the online store.

### Task Description

Implement monitoring and load testing for the online store: define metric sources, configure metric collection and visualization, and prepare k6 scenarios that simulate realistic user behavior.

As part of the task, the Docker runtime is separated into production and development environments. Production runs by default through PHP-FPM and Nginx with OPcache, while development provides Symfony CLI, Composer, and Xdebug through a separate Compose override.

### Goal

Introduce observability for the main system components and run load testing to determine the online store performance, maximum stable RPS, latency, error count under load, and possible architectural bottlenecks.

### Architecture Context

The system consists of the Nginx-based API Gateway, the main Symfony service, Catalog Service, Cart/Order Service, internal REST/HTTP endpoints, internal gRPC integrations, PostgreSQL databases, and supporting infrastructure.

Monitoring must cover both the user path through API Gateway and the state of individual backend services. Symfony HTTP metrics should show request load, latency, status codes, and REST endpoint errors. Internal gRPC calls need separate Prometheus metrics to observe latency, errors, and service-to-service load.

Prometheus must be used to collect and store metrics. Grafana must be used to visualize system state, compare load across services, and analyze degradation under load. k6 must be used for load testing with gradual load increase and scenarios close to real online store user behavior.

System and exporter metrics must cover Nginx/API Gateway, PostgreSQL, CPU, RAM, and other required environment components so that load test results can be correlated with resource usage.

Production and development share the same Compose architecture but use different PHP image targets and runtime parameters. The base `.env` and `docker-compose.yml` describe production, while `.env.dev` and `docker-compose.dev.yml` switch the same infrastructure to development mode.

### Acceptance Criteria

- The monitoring scope is described for API Gateway, the main Symfony service, Catalog Service, Cart/Order Service, gRPC integrations, PostgreSQL, and system resources.
- Prometheus is selected for metrics collection and storage.
- Grafana is selected for metrics visualization and system state analysis.
- k6 is selected for online store load testing.
- Key indicators are defined: RPS, latency, p50, p95, p99, HTTP errors, gRPC errors, CPU, RAM, PostgreSQL connections/activity, Nginx/API Gateway metrics, and per-service load.
- Dedicated Prometheus metrics for Symfony HTTP endpoints are included.
- Dedicated Prometheus metrics for internal gRPC calls are included.
- Load testing must support gradual load increase and identify the maximum stable RPS.
- Load testing must identify when latency degradation starts.
- Load testing must record the number of errors under load.
- Load testing must help determine which component becomes the bottleneck.
- k6 scenarios must simulate realistic online store usage, including different authenticated users.
- Load test results must be correlated with server and component resource metrics.
- Production uses the `prod` target, PHP-FPM, Nginx, OPcache, `APP_ENV=prod`, and `APP_DEBUG=0` by default.
- Development uses the `dev` target, Symfony CLI, Composer, Xdebug, `APP_ENV=dev`, and `APP_DEBUG=1`.
- Production and development use different Compose project names and can run without resource-name conflicts.

### Technical Approach

- Define the set of components that must expose or provide metrics.
- Separate user-facing HTTP traffic metrics through API Gateway from internal service-level metrics.
- Plan Symfony HTTP metrics for the main Symfony service, Catalog Service, and Cart/Order Service.
- Plan dedicated gRPC metrics for internal service-to-service calls.
- Plan exporter metrics for Nginx/API Gateway, PostgreSQL, CPU, RAM, and other required components.
- Define Grafana dashboards for overall system state, HTTP/gRPC latency, errors, PostgreSQL activity, and resource usage.
- Define k6 load profiles with gradual load growth.
- Define k6 user scenarios that represent typical online store actions: catalog browsing, search, product view, cart operations, checkout, and authenticated user actions.
- Compare load test results with Prometheus/Grafana metrics to identify bottlenecks by service and resource.
- Use a multi-stage PHP image with `prod` and `dev` targets to separate the production runtime from development tooling.
- Keep the production Compose configuration in `.env` and `docker-compose.yml`, and development overrides in `.env.dev` and `docker-compose.dev.yml`.
- Do not change service business logic as part of monitoring and load testing preparation without separate approval.

### How To Test

- Verify that every important system component has a defined metrics source.
- Verify that the metric list can measure RPS, p50/p95/p99 latency, HTTP/gRPC errors, and resource usage.
- Verify that k6 load scenarios cover online store user paths.
- Verify that scenarios include different authenticated users, not only anonymous traffic.
- Verify that the load profile can gradually increase load and observe degradation.
- Verify that test results can identify the maximum stable RPS.
- Verify that test results can identify when latency grows and errors appear.
- Verify that Grafana dashboards can correlate load, errors, and resource usage.
- Verify that the final analysis can name the likely bottleneck at different load levels.
- Verify the production startup with `docker compose up -d` and confirm `APP_ENV=prod` and `APP_DEBUG=0`.
- Verify the development startup with `docker compose --env-file .env --env-file .env.dev up -d` and confirm `APP_ENV=dev` and `APP_DEBUG=1`.
- Verify that the Compose project names differ and the environments do not conflict.

### Notes

- Monitoring, Grafana dashboards, Prometheus scrape configuration, k6 scripts, and Docker environment separation are implemented as part of this task.
- Production remains the default environment; development is enabled only when `.env.dev` is explicitly selected.
- Monitoring helps analyze both external user traffic through API Gateway and internal REST/gRPC service interactions.
- Load tests are used not only to find maximum RPS, but also to understand system degradation and resource usage.
