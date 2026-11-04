# Kubernetes Resource Calibration

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [English](#english)
  - [Purpose and sources](#purpose-and-sources)
  - [Test environment](#test-environment)
  - [Server benchmark](#server-benchmark)
  - [Load test](#load-test)
  - [CPU and Kubernetes configuration](#cpu-and-kubernetes-configuration)
    - [Where the settings are defined](#where-the-settings-are-defined)
  - [Assessing requests, limits, and throttling](#assessing-requests-limits-and-throttling)
  - [Portability limits](#portability-limits)
  - [Recommendations for future runs](#recommendations-for-future-runs)
- [Русский](#%D1%80%D1%83%D1%81%D1%81%D0%BA%D0%B8%D0%B9)
  - [Назначение и источники](#%D0%BD%D0%B0%D0%B7%D0%BD%D0%B0%D1%87%D0%B5%D0%BD%D0%B8%D0%B5-%D0%B8-%D0%B8%D1%81%D1%82%D0%BE%D1%87%D0%BD%D0%B8%D0%BA%D0%B8)
  - [Тестовое окружение](#%D1%82%D0%B5%D1%81%D1%82%D0%BE%D0%B2%D0%BE%D0%B5-%D0%BE%D0%BA%D1%80%D1%83%D0%B6%D0%B5%D0%BD%D0%B8%D0%B5)
  - [Бенчмарк сервера](#%D0%B1%D0%B5%D0%BD%D1%87%D0%BC%D0%B0%D1%80%D0%BA-%D1%81%D0%B5%D1%80%D0%B2%D0%B5%D1%80%D0%B0)
  - [Нагрузочное тестирование](#%D0%BD%D0%B0%D0%B3%D1%80%D1%83%D0%B7%D0%BE%D1%87%D0%BD%D0%BE%D0%B5-%D1%82%D0%B5%D1%81%D1%82%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5)
  - [CPU и конфигурация Kubernetes](#cpu-%D0%B8-%D0%BA%D0%BE%D0%BD%D1%84%D0%B8%D0%B3%D1%83%D1%80%D0%B0%D1%86%D0%B8%D1%8F-kubernetes)
    - [Где заданы параметры](#%D0%B3%D0%B4%D0%B5-%D0%B7%D0%B0%D0%B4%D0%B0%D0%BD%D1%8B-%D0%BF%D0%B0%D1%80%D0%B0%D0%BC%D0%B5%D1%82%D1%80%D1%8B)
  - [Оценка requests, limits и throttling](#%D0%BE%D1%86%D0%B5%D0%BD%D0%BA%D0%B0-requests-limits-%D0%B8-throttling)
  - [Ограничения переносимости](#%D0%BE%D0%B3%D1%80%D0%B0%D0%BD%D0%B8%D1%87%D0%B5%D0%BD%D0%B8%D1%8F-%D0%BF%D0%B5%D1%80%D0%B5%D0%BD%D0%BE%D1%81%D0%B8%D0%BC%D0%BE%D1%81%D1%82%D0%B8)
  - [Рекомендации для следующих прогонов](#%D1%80%D0%B5%D0%BA%D0%BE%D0%BC%D0%B5%D0%BD%D0%B4%D0%B0%D1%86%D0%B8%D0%B8-%D0%B4%D0%BB%D1%8F-%D1%81%D0%BB%D0%B5%D0%B4%D1%83%D1%8E%D1%89%D0%B8%D1%85-%D0%BF%D1%80%D0%BE%D0%B3%D0%BE%D0%BD%D0%BE%D0%B2)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

## English

### Purpose and sources

This document connects the machine characteristics, synthetic CPU benchmark, observed Kubernetes load, and current CPU `requests`/`limits`. These values describe one specific environment and provide a starting point for future calibration; they are not a universal profile for every server.

Primary sources:

- Grafana panel screenshot: [kuber requests.png](../images/kuber%20requests.png).
- Helm settings and desired replicas: [`values.yaml`](../../kubernetes/helm/symfony2026/values.yaml).
- Deployment and StatefulSet templates: [`templates/`](../../kubernetes/helm/symfony2026/templates/).
- Phoronix methodology and raw results: [Hardware Performance Reference](hardware-benchmark.md), [`sysbench.xml`](benchmark-results/sysbench.xml), [`sysbench-2-threads.xml`](benchmark-results/sysbench-2-threads.xml).
- Kubernetes metrics and panels: [Kubernetes README](../../kubernetes/README.md#resource-diagnosis-and-load-testing).

### Test environment

| Parameter | Recorded value |
|---|---|
| Kubernetes node | `desktop-s6v2euu`, single-node k3s |
| Namespace | `symfony2026-load-test` |
| Runtime | Kubernetes/k3s; workloads observed in Grafana/Prometheus |
| Desired HTTP service replicas | `symfony-web`: 1; `catalog-web`: 1; `cart-web`: 1; `api-gateway`: 1 |
| HPA | Not configured during this historical measurement; the chart now contains queue-based HPA for HTTP workloads |
| CPU benchmark host | Intel Core i9-13900KF, 16 physical cores / 32 logical threads as reported by Phoronix |
| Benchmark environment memory | 32 GB visible to the Phoronix WSL2 environment; Windows reported about 63.8 GiB of physical RAM |
| Benchmark environment OS | Ubuntu 22.04 in WSL2, ext4, kernel `6.18.40.1-microsoft-standard-WSL2` |

Replica counts in the table are the chart's `replicas` values and the observed steady-state configuration. Historical Auth metrics contained two Pod UIDs, which is consistent with Pod replacement or temporary overlap and does not prove HPA scaling. The maximum number of web Deployment Pods in the interval could also include Pod replacement; the desired replica count remained 1.

### Server benchmark

The previously saved CPU benchmark used Phoronix Test Suite 10.8.6, the `system/sysbench-1.0.0` profile, and Sysbench 1.0.20. For the primary result, PTS allowed `NUM_CPU_CORES=32`; each CPU run used `--threads=32 --time=90`.

| Mode | Sysbench threads | CPU result | Purpose |
|---|---:|---:|---|
| Full machine | 32 | 78,338.14 events/s | Primary result for this machine |
| 2-vCPU comparison | 2 | 3,367.69 events/s | Separately measured result for comparison with 2-vCPU environments |

This is synthetic Sysbench throughput. It does not translate directly into application RPS: results depend on application code and request mix, PHP-FPM, databases, Elasticsearch, networking, container limits, and CPU contention. The two-thread result must not be replaced by a linearly estimated share of the 32-thread result.

The Phoronix results report 32 GB available to the benchmark environment; Windows reports about 63.8 GiB of physical RAM. These are observations at different system layers, so the memory figure must not be treated as memory guaranteed to the Kubernetes node. See [Hardware Performance Reference](hardware-benchmark.md) for the full raw results and methodology.

### Load test

The supplied Grafana screenshot selects namespace `symfony2026-load-test` and shows an interval of approximately `2026-10-14 22:00–22:16` in the browser's time zone (about `14:00–14:16 UTC`). A hover at `22:13:15` reports `0.744` CPU cores for one `catalog-web` Pod; the visual peak estimate is approximately `0.8–0.9` cores.

Saved Prometheus metrics for this window indicate an API Gateway rate of approximately **255 RPS on average** and **302 RPS at peak**. This is derived from the rate of change in `nginx_vts_server_requests_total`, not from the final k6 summary. It aggregates Gateway requests and can include different routes and response statuses.

The screenshot window and Prometheus sampling interval do not establish the exact duration of the k6 scenario, number of VUs, or request profile. These values could not be confirmed from the available material for this screenshot. RPS is therefore included only as context for the observed window. Previously recorded Compose/k6 results (for example, about 1,750 RPS in a different run) belong to separate profiles and must not be conflated with this Kubernetes measurement.

### CPU and Kubernetes configuration

Usage values are the average and maximum over the specified window, calculated from a five-minute rate series aggregated by the corresponding container/service. They estimate CPU cores, not percentages of the host. Requests and limits are for the primary container; auxiliary exporter containers are not included in the table.

| Service / container | Average CPU | Peak CPU | `requests.cpu` | `limits.cpu` | Replicas |
|---|---:|---:|---:|---:|---:|
| API Gateway / Nginx | ~0.063 | ~0.075 | 25m | not set | 1 |
| Symfony / PHP-FPM | no confirmed value for this window | no confirmed value | 300m | 500m | 1 |
| Auth / PHP-FPM | ~0.22 per Pod | ~0.37 per Pod | 300m | 500m | 1 desired |
| Catalog / PHP-FPM | ~0.676 | ~0.797 | 900m | 1 | 1 |
| Catalog / gRPC | ~0.024 | ~0.034 | 100m | 1 | 1 |
| Catalog / outbox worker | ~0.002 | ~0.002 | 50m | 500m | 1 |
| Catalog / index worker | ~0.004 | ~0.005 | 50m | 500m | 1 |
| Cart / PHP-FPM | ~0.256 | ~0.301 | 300m | 500m | 1 |
| Elasticsearch | ~0.263 | ~0.344 | 250m | not set | 1 |
| Auth / async worker | less than 0.001 | less than 0.001 | 50m | 500m | 1 |

Auxiliary exporters used only a few millicores each in this window. Confirmed CPU usage for Symfony PHP-FPM, internal databases, Redis, RabbitMQ, and some infrastructure workloads is not available for this time slice. Their settings exist in the chart, but that does not establish actual consumption. Read resource settings from the chart and corresponding workload templates; omitted CPU limits remain unset.

#### Where the settings are defined

The main blocks are in [`values.yaml`](../../kubernetes/helm/symfony2026/values.yaml): `resources.api-gateway.nginx`, `resources.symfony-web.php`, `resources.catalog-web.php`, `resources.cart-web.php`, `resources.catalog-grpc.grpc`, worker blocks, and `resources.elasticsearch.elasticsearch`. Replicas are set in `replicas` in the same file.

Helm passes these values to [`app-workloads.yaml`](../../kubernetes/helm/symfony2026/templates/app-workloads.yaml) and [`app-gateway.yaml`](../../kubernetes/helm/symfony2026/templates/app-gateway.yaml) (Deployments), and [`infra-stateful.yaml`](../../kubernetes/helm/symfony2026/templates/infra-stateful.yaml) (Elasticsearch StatefulSet). The chart settings themselves were not changed as part of this documentation.

### Assessing requests, limits, and throttling

`requests.cpu` is used for Pod placement and defines the CPU share a container can expect under contention. Usage exceeding the request is acceptable by itself when CPU is available. `limits.cpu` sets a quota and can restrict a container through CFS throttling when reached. If no CPU limit is set, Kubernetes does not apply that quota to the container.

- Catalog PHP-FPM averaged about 0.676 cores against a 0.9 request and peaked near 0.797 against a 1-core limit. Headroom remained within this window, but this does not prove the settings are sufficient for other load profiles.
- Cart PHP-FPM averaged about 0.256 cores against a 0.3 request and reached about 0.301 against a 0.5 limit. The observed load was close to the request but not the limit.
- Auth PHP-FPM used about 0.22 cores on average and up to 0.37 per Pod against a 0.3 request and 0.5 limit. The peak share of CFS periods with throttling reached about 18.7%.
- For Catalog PHP-FPM, the peak share of CFS periods with throttling reached about 58.2%; for Cart PHP-FPM it reached about 2.8%. This is the percentage of CFS periods in which throttling was recorded, not the percentage of CPU time lost or the share of requests with increased latency.
- Elasticsearch averaged slightly above its 0.25-core request (about 0.263) and peaked near 0.344. This is not an error: a request is not an upper limit. Elasticsearch has no CPU limit set.

The observed throttling is a signal to correlate with latency, PHP-FPM queue depth, and errors in future controlled runs. One graph cannot establish that throttling harmed user-facing performance or that the resources are guaranteed to be sufficient. Node-wide over-reservation also cannot be assessed from usage in one window: that requires total Pod requests, node allocatable CPU, and workload contention.

### Portability limits

The same Kubernetes CPU value does not provide the same application performance on different processors. A CPU unit represents a share of compute capacity, while PHP, Elasticsearch, and other workloads depend on microarchitecture, frequency, available core count, virtualization, power profile, and competing load.

The current settings reflect observations on `desktop-s6v2euu` under one specific load profile. Sysbench helps describe a machine and compare systems using the same test, but it does not provide a coefficient for automatically recalculating requests/limits. When hardware, runtime, or request mix changes, repeat application calibration on the target system.

### Recommendations for future runs

For the next comparable run, record together:

1. Exact chart/image versions and hardware configuration, including CPU quota and memory visible to WSL/k3s.
2. The k6 scenario, VU count, ramp-up, duration, target routes, actual RPS, and latency/error summary.
3. Ready Pod counts and replacement/restart events.
4. Average and peak CPU by container, requests/limits, throttled periods, and PHP-FPM queue depth.
5. A Prometheus/Grafana snapshot with the time zone and exact interval.

Assess CPU alongside latency, errors, and queues. Change requests/limits and repeat the run as separate controlled steps so the effect can be tied to the changed setting. This record preserves the current baseline; it does not recommend changing values without new measurements.

## Русский

### Назначение и источники

Документ связывает характеристики машины, синтетический CPU-бенчмарк, наблюдавшуюся нагрузку Kubernetes и текущие CPU `requests`/`limits`. Значения фиксируют конкретное окружение и служат исходной точкой для дальнейшей калибровки, а не универсальным профилем для любого сервера.

Основные источники:

- Снимок панели Grafana: [kuber requests.png](../images/kuber%20requests.png).
- Параметры и желаемые реплики Helm: [`values.yaml`](../../kubernetes/helm/symfony2026/values.yaml).
- Шаблоны Deployment и StatefulSet: [`templates/`](../../kubernetes/helm/symfony2026/templates/).
- Методика и исходные результаты Phoronix: [Hardware Performance Reference](hardware-benchmark.md), [`sysbench.xml`](benchmark-results/sysbench.xml), [`sysbench-2-threads.xml`](benchmark-results/sysbench-2-threads.xml).
- Описание Kubernetes-метрик и панелей: [Kubernetes README](../../kubernetes/README.md#resource-diagnosis-and-load-testing).

### Тестовое окружение

| Параметр | Зафиксированное значение |
|---|---|
| Узел Kubernetes | `desktop-s6v2euu`, один узел k3s |
| Namespace | `symfony2026-load-test` |
| Runtime | Kubernetes/k3s, workloads наблюдались в Grafana/Prometheus |
| Желаемые реплики HTTP-сервисов | `symfony-web`: 1; `catalog-web`: 1; `cart-web`: 1; `api-gateway`: 1 |
| HPA | На момент этих замеров не был настроен; сейчас chart содержит HPA HTTP-сервисов по очереди |
| CPU benchmark host | Intel Core i9-13900KF, 16 физических ядер / 32 логических потока по Phoronix |
| Память benchmark-среды | 32 GB видимой Phoronix WSL2-среде; Windows сообщала около 63.8 GiB физической RAM |
| ОС benchmark-среды | Ubuntu 22.04 в WSL2, ext4, kernel `6.18.40.1-microsoft-standard-WSL2` |

Число реплик в таблице — значение `replicas` из chart и наблюдаемая штатная конфигурация. В исторических метриках у Auth присутствовали два Pod UID, что соответствует замене Pod/временному перекрытию, а не доказательству масштабирования HPA. Для веб Deployment максимальное число Pod в интервале также могло включать замену Pod; желаемое число реплик оставалось равным 1.

### Бенчмарк сервера

Ранее сохранённый CPU-бенчмарк выполнен Phoronix Test Suite 10.8.6 с профилем `system/sysbench-1.0.0` и Sysbench 1.0.20. Для основного результата PTS разрешил `NUM_CPU_CORES=32`; каждый CPU run запускался с `--threads=32 --time=90`.

| Режим | Потоки Sysbench | Результат CPU | Назначение |
|---|---:|---:|---|
| Полная машина | 32 | 78,338.14 events/s | Основной результат этой машины |
| Сравнение 2 vCPU | 2 | 3,367.69 events/s | Отдельный измеренный результат для сравнения с 2-vCPU средами |

Это синтетическая пропускная способность Sysbench. Она не переводится напрямую в RPS приложения: на результат влияют код и профиль запросов, PHP-FPM, база данных, Elasticsearch, сеть, лимиты контейнеров и конкуренция за CPU. Двухпоточный результат нельзя заменять линейно рассчитанной долей от 32-поточного результата.

В Phoronix результатах указано 32 GB доступной benchmark-среде; Windows сообщает около 63.8 GiB физической RAM. Это разные уровни наблюдения, поэтому объём памяти нельзя трактовать как гарантированную память Kubernetes node. Подробные исходные результаты и методика приведены в [Hardware Performance Reference](hardware-benchmark.md).

### Нагрузочное тестирование

На приложенном Grafana-снимке выбран namespace `symfony2026-load-test`; отображённый интервал приблизительно `2026-10-14 22:00–22:16` по часовому поясу браузера (около `14:00–14:16 UTC`). Hover в `22:13:15` показывает `0.744` CPU cores для одного Pod `catalog-web`; по графику пользовательская оценка пика составляет примерно `0.8–0.9` cores.

Для этого временного окна сохранённые метрики Prometheus дают ориентировочную интенсивность API Gateway около **255 RPS в среднем** и **302 RPS на пике**. Это расчёт по скорости изменения `nginx_vts_server_requests_total`, а не итоговая статистика k6. Значение агрегирует запросы на Gateway и может включать разные маршруты и статусы.

Окно снимка и интервал выборки Prometheus не подтверждают точную длительность самого k6-сценария, число VUs или профиль запросов. Эти значения в доступных материалах для данного снимка не установлены. Поэтому RPS ниже используется только как контекст наблюдавшегося окна. Ранее зафиксированные Compose/k6 результаты (например, около 1,750 RPS в другом прогоне) относятся к отдельным профилям и не должны смешиваться с этим Kubernetes-измерением.

### CPU и конфигурация Kubernetes

Значения usage — среднее и максимум за указанное окно из 5-минутного rate ряда, агрегированного по соответствующему контейнеру/сервису. Это оценки CPU cores, а не проценты от host. Requests и limits приведены для основного контейнера; вспомогательные exporter-контейнеры в таблице не суммируются.

| Сервис / контейнер | Среднее CPU | Пик CPU | `requests.cpu` | `limits.cpu` | Реплики |
|---|---:|---:|---:|---:|---:|
| API Gateway / Nginx | ~0.063 | ~0.075 | 25m | не задан | 1 |
| Symfony / PHP-FPM | нет подтверждённого значения для этого окна | нет подтверждённого значения | 300m | 500m | 1 |
| Auth / PHP-FPM | ~0.22 на Pod | ~0.37 на Pod | 300m | 500m | 1 желаемая |
| Catalog / PHP-FPM | ~0.676 | ~0.797 | 900m | 1 | 1 |
| Catalog / gRPC | ~0.024 | ~0.034 | 100m | 1 | 1 |
| Catalog / outbox worker | ~0.002 | ~0.002 | 50m | 500m | 1 |
| Catalog / index worker | ~0.004 | ~0.005 | 50m | 500m | 1 |
| Cart / PHP-FPM | ~0.256 | ~0.301 | 300m | 500m | 1 |
| Elasticsearch | ~0.263 | ~0.344 | 250m | не задан | 1 |
| Auth / async worker | менее 0.001 | менее 0.001 | 50m | 500m | 1 |

Вспомогательные exporters в этом окне потребляли единицы millicores каждый. Для Symfony PHP-FPM, внутренних баз данных, Redis, RabbitMQ и части инфраструктурных workloads подтверждённые значения CPU usage для этого временного среза не приведены; их параметры заданы в chart, но это не позволяет восстановить фактическое потребление. Значения ресурсов следует читать из chart и соответствующих workload templates, так как omitted CPU limits остаются неустановленными.

#### Где заданы параметры

Основные блоки находятся в [`values.yaml`](../../kubernetes/helm/symfony2026/values.yaml): `resources.api-gateway.nginx`, `resources.symfony-web.php`, `resources.catalog-web.php`, `resources.cart-web.php`, `resources.catalog-grpc.grpc`, worker-блоки и `resources.elasticsearch.elasticsearch`. Реплики задаются в `replicas` того же файла.

Helm передаёт эти значения в [`app-workloads.yaml`](../../kubernetes/helm/symfony2026/templates/app-workloads.yaml) и [`app-gateway.yaml`](../../kubernetes/helm/symfony2026/templates/app-gateway.yaml) (Deployment), а также в [`infra-stateful.yaml`](../../kubernetes/helm/symfony2026/templates/infra-stateful.yaml) (StatefulSet Elasticsearch). Сами настройки chart в рамках этой документации не менялись.

### Оценка requests, limits и throttling

`requests.cpu` участвует в размещении Pod и задаёт долю CPU, на которую контейнер может рассчитывать при конкуренции. Превышение usage над request допустимо само по себе, если CPU доступен. `limits.cpu` задаёт quota и при достижении может ограничивать контейнер через CFS throttling. Если CPU limit не задан, Kubernetes не накладывает эту quota на контейнер.

- Catalog PHP-FPM в среднем использовал около 0.676 cores при request 0.9, пик около 0.797 при limit 1 core. В пределах этого окна запас до limit оставался, но это не доказывает достаточность параметров для иных профилей нагрузки.
- Cart PHP-FPM в среднем использовал около 0.256 cores при request 0.3 и достигал около 0.301 при limit 0.5. Наблюдавшаяся нагрузка была близка к request, но не к limit.
- Auth PHP-FPM показывал примерно 0.22 среднего и до 0.37 cores на Pod при request 0.3 и limit 0.5. В метрике CFS peak доля throttled periods достигала около 18.7%.
- Для Catalog PHP-FPM peak доля CFS periods с throttling достигала около 58.2%; для Cart PHP-FPM — около 2.8%. Эта доля означает процент периодов CFS, в которых фиксировалось throttling, а не процент потерянного CPU-времени и не долю запросов с задержкой.
- Elasticsearch в среднем немного превышал request 0.25 cores (около 0.263), а пик достигал около 0.344. Это не ошибка: request не является верхним пределом. CPU limit для Elasticsearch не задан.

Зафиксированное throttling — сигнал для корреляции с latency, PHP-FPM queue и ошибками в будущих контролируемых прогонах. По одному графику нельзя заключить, что throttling ухудшил пользовательские показатели, или что ресурсы гарантированно достаточны. Нельзя также оценить избыточное резервирование всей ноды только по usage одного окна: для этого нужны суммарные requests всех Pod, доступные CPU node и конкуренция workloads.

### Ограничения переносимости

Одинаковое Kubernetes-значение CPU не означает одинаковую производительность приложения на разных процессорах. CPU unit задаёт долю вычислительного ресурса, а скорость выполнения PHP, Elasticsearch и других процессов зависит от микроархитектуры, частоты, числа доступных ядер, виртуализации, энергопрофиля и конкурирующей нагрузки.

Текущие параметры отражают наблюдения на `desktop-s6v2euu` и конкретном профиле нагрузки. Sysbench помогает описать машину и сравнить её с другой системой при одинаковом тесте, но не даёт коэффициент для автоматического пересчёта requests/limits. При смене оборудования, runtime или профиля запросов нужно повторить калибровку приложения на целевой системе.

### Рекомендации для следующих прогонов

Для следующего сравнимого прогона сохранять вместе:

1. точные версии chart/image и конфигурацию оборудования, включая CPU quota/memory, видимые WSL/k3s;
2. сценарий k6, число VUs, ramp-up, длительность, целевые маршруты, фактические RPS и summary latency/error rate;
3. число Ready Pod и события замен/рестартов;
4. средний и пиковый CPU по контейнерам, requests/limits, throttled periods и PHP-FPM очередь;
5. Prometheus/Grafana снимок с часовым поясом и точным временным интервалом.

Сначала следует оценивать CPU вместе с latency, ошибками и очередями. Изменение requests/limits и повторный прогон должны быть отдельными контролируемыми шагами, чтобы можно было связать эффект с изменённым параметром. Эта запись сохраняет текущую точку отсчёта и не является рекомендацией менять значения без нового измерения.
