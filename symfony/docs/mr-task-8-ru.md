# Лог Результата MR Task 8

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Обзор](#%D0%BE%D0%B1%D0%B7%D0%BE%D1%80)
- [Scope](#scope)
- [Monitoring Scope](#monitoring-scope)
- [Load Testing Scope](#load-testing-scope)
- [План проверки](#%D0%BF%D0%BB%D0%B0%D0%BD-%D0%BF%D1%80%D0%BE%D0%B2%D0%B5%D1%80%D0%BA%D0%B8)
- [Вне Scope](#%D0%B2%D0%BD%D0%B5-scope)
- [2026-08-19 - Завершение задачи](#2026-08-19---%D0%B7%D0%B0%D0%B2%D0%B5%D1%80%D1%88%D0%B5%D0%BD%D0%B8%D0%B5-%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B8)

<!-- END doctoc -->

## Обзор

Этот документ описывает планируемый результат merge request для Task 8.

Merge request: TBD

Файл задачи: [task-8.md](task-8.md)

Планируемый merge request внедряет мониторинг и нагрузочное тестирование интернет-магазина. Цель - сделать основные runtime-компоненты наблюдаемыми, выполнить реалистичные нагрузочные сценарии и определить стабильную пропускную способность, точки деградации latency, ошибки под нагрузкой и вероятные bottlenecks.

## Scope

Планируется:

- внедрить сбор метрик Prometheus для основных наблюдаемых компонентов;
- добавить визуализацию Grafana для application, gateway, database и system metrics;
- добавить k6-сценарии нагрузочного тестирования, имитирующие реальное поведение пользователей интернет-магазина;
- покрыть anonymous и authenticated user flows;
- измерять RPS, latency, p50, p95, p99, HTTP/gRPC errors, CPU, RAM, PostgreSQL activity и API Gateway metrics;
- связывать результаты нагрузочных тестов с service-level и resource-level метриками.

## Monitoring Scope

Monitoring scope должен покрывать:

- Nginx/API Gateway metrics;
- Symfony HTTP metrics основного Symfony-сервиса;
- Symfony HTTP metrics Catalog Service;
- Symfony HTTP metrics Cart/Order Service;
- отдельные Prometheus metrics для внутренних gRPC-вызовов;
- PostgreSQL connection и activity metrics;
- CPU, RAM и другие необходимые system metrics;
- нагрузку и ошибки отдельных сервисов.

## Load Testing Scope

k6-сценарии должны моделировать реальное поведение интернет-магазина, а не только изолированные проверки endpoints.

Планируемые сценарии:

- просмотр каталога;
- поиск товаров;
- просмотр карточки товара;
- операции с корзиной;
- checkout/order creation flows;
- действия авторизованных пользователей с разными пользователями.

Load profiles должны постепенно увеличивать трафик, чтобы определить:

- максимальный стабильный RPS;
- момент начала деградации latency;
- количество ошибок под нагрузкой;
- компонент, который становится bottleneck;
- использование ресурсов сервера при разных уровнях нагрузки.

## План проверки

Реализацию нужно будет проверять по следующим признакам:

- для каждого важного компонента определен источник метрик;
- Prometheus собирает application, gateway, database, gRPC и system metrics;
- Grafana dashboards позволяют сопоставлять трафик, latency, ошибки и использование ресурсов;
- k6-сценарии покрывают реалистичные user flows интернет-магазина;
- load profiles позволяют определить стабильную пропускную способность и точки деградации;
- результаты тестов позволяют назвать вероятный bottleneck для каждого уровня нагрузки.

## Вне Scope

Эта планируемая задача пока не фиксирует конкретные команды установки, Docker-конфигурацию, Prometheus scrape configuration, Grafana dashboard JSON или k6 scripts.

Изменения бизнес-логики сервисов находятся вне scope, если они не будут отдельно согласованы во время реализации.

## 2026-08-19 - Завершение задачи

Мониторинг и нагрузочное тестирование реализованы. В Docker Compose добавлены Prometheus, Grafana, Grafana Image Renderer, Nginx VTS exporter, Node Exporter и отдельные PostgreSQL exporters для основной базы данных, Catalog Service и Cart/Order Service.

Prometheus собирает метрики следующих наблюдаемых узлов:

- API Gateway и Nginx;
- основной Symfony-сервис;
- Catalog Service;
- Cart/Order Service;
- основная, catalog и cart PostgreSQL-базы данных;
- ресурсы хоста: CPU, RAM, swap, filesystem, disk I/O и network.

В Grafana построен общий дашборд со следующими группами панелей:

- входящий RPS API Gateway, RPS Symfony-сервисов и RPS отдельных endpoints;
- HTTP responses по классам статусов и процент ошибок API Gateway;
- latency p50, p95 и p99, а также p95 по отдельным endpoints;
- CPU, RAM, swap, system load, uptime и PSI resource pressure;
- использование filesystem, disk throughput, IOPS и disk utilization;
- входящий и исходящий network traffic и network saturation;
- PostgreSQL tuple operations и приблизительное среднее время выполнения tuple operation.

Добавлены k6-сценарии просмотра каталога, shopping flow, checkout и смешанной нагрузки. Сценарии поддерживают авторизованных пользователей и позволяют сопоставлять результаты нагрузочного теста с метриками Grafana.

Промежуточный результат нагрузочного тестирования составляет около **80 RPS**. Это текущий замер, а не зафиксированный максимальный стабильный предел системы. Дальнейшая работа по повышению RPS и поиску bottleneck продолжается отдельно от завершенной задачи мониторинга.

На скриншоте дашборда показан тестовый интервал нагрузки: входящий трафик через API Gateway и Symfony-сервисы сопоставляется с latency, HTTP errors, активностью PostgreSQL и использованием системных ресурсов. Такой набор панелей позволяет определить, на каком уровне начинается деградация при дальнейшем увеличении нагрузки.

![Grafana dashboard for monitoring and load testing](../../docs/images/task-8-monitoring-dashboard.png)
