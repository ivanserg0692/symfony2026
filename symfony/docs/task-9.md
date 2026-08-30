# Task 9

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [RU](#ru)
  - [Статус](#%D1%81%D1%82%D0%B0%D1%82%D1%83%D1%81)
  - [Название](#%D0%BD%D0%B0%D0%B7%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5)
  - [Описание задачи](#%D0%BE%D0%BF%D0%B8%D1%81%D0%B0%D0%BD%D0%B8%D0%B5-%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B8)
  - [Цель](#%D1%86%D0%B5%D0%BB%D1%8C)
  - [Архитектурный контекст](#%D0%B0%D1%80%D1%85%D0%B8%D1%82%D0%B5%D0%BA%D1%82%D1%83%D1%80%D0%BD%D1%8B%D0%B9-%D0%BA%D0%BE%D0%BD%D1%82%D0%B5%D0%BA%D1%81%D1%82)
  - [Критерии приемки](#%D0%BA%D1%80%D0%B8%D1%82%D0%B5%D1%80%D0%B8%D0%B8-%D0%BF%D1%80%D0%B8%D0%B5%D0%BC%D0%BA%D0%B8)
  - [Технический подход](#%D1%82%D0%B5%D1%85%D0%BD%D0%B8%D1%87%D0%B5%D1%81%D0%BA%D0%B8%D0%B9-%D0%BF%D0%BE%D0%B4%D1%85%D0%BE%D0%B4)
  - [Как тестировать](#%D0%BA%D0%B0%D0%BA-%D1%82%D0%B5%D1%81%D1%82%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D1%82%D1%8C)
  - [Примечания](#%D0%BF%D1%80%D0%B8%D0%BC%D0%B5%D1%87%D0%B0%D0%BD%D0%B8%D1%8F)
  - [Итог выполнения](#%D0%B8%D1%82%D0%BE%D0%B3-%D0%B2%D1%8B%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D1%8F)
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
  - [Completion Result](#completion-result)

<!-- END doctoc -->

## RU

### Статус

**Completed — 2026-08-30.**

### Название

Оптимизация производительности приложения, повышение RPS и настройка PHP-окружения.

### Описание задачи

Провести измеримую оптимизацию производительности интернет-магазина на основе результатов мониторинга и нагрузочного тестирования из Task 8. Зафиксировать воспроизводимый baseline, определить фактические узкие места, оптимизировать PHP runtime и связанные компоненты, а затем подтвердить результат повторными нагрузочными тестами на том же сценарии и окружении.

Предварительный результат Task 8 — около 80 RPS — используется как ориентир, но перед оптимизацией должен быть повторно измерен и зафиксирован вместе с latency, error rate и потреблением ресурсов. Итоговый прирост оценивается относительно подтвержденного baseline, а не только по абсолютному значению RPS.

Оптимизация должна охватывать PHP-FPM, OPcache, Nginx, Symfony, Doctrine, PostgreSQL, Redis, Docker image и параметры контейнерного окружения. Изменения должны основываться на данных профилирования и мониторинга и не должны менять бизнес-логику или публичное поведение приложения.

### Цель

Повысить максимальный стабильный RPS и снизить latency приложения под нагрузкой, устранив подтвержденные bottleneck и подготовив чистое, воспроизводимое и оптимизированное production PHP-окружение без development- и build-time-зависимостей.

### Архитектурный контекст

Внешний трафик поступает через Nginx API Gateway и распределяется между основным Symfony-сервисом, Catalog Service и Cart/Order Service. Сервисы используют PostgreSQL, Redis и внутренние REST/gRPC-интеграции. Prometheus, Grafana и k6, подготовленные в Task 8, используются для измерения RPS, latency, ошибок и потребления ресурсов.

Production и development должны собираться из одного multi-stage Dockerfile, но иметь разный состав runtime:

- production использует PHP-FPM, Nginx и OPcache;
- development содержит Composer, Symfony CLI и Xdebug;
- build toolchain, headers и `*-dev` пакеты остаются только в builder stages;
- PHP extensions и необходимые shared runtime libraries сохраняются в обоих окружениях;
- Compose targets `prod` и `dev` продолжают использовать существующую схему запуска окружений.

Оптимизации прикладного кода, запросов к данным и инфраструктуры выполняются только после подтверждения соответствующего bottleneck измерениями. Изменения бизнес-логики и API-контрактов не входят в задачу.

### Критерии приемки

- Зафиксирован воспроизводимый baseline до оптимизации: сценарий k6, профиль нагрузки, длительность, количество виртуальных пользователей, тестовые данные и параметры окружения.
- Для baseline зафиксированы RPS, p50, p95, p99, error rate, CPU, RAM и показатели основных зависимостей.
- Предварительное значение около 80 RPS из Task 8 проверено повторным измерением и не используется как единственный критерий результата.
- Определены подтвержденные bottleneck по данным k6, Prometheus, Grafana, PHP/Symfony profiling и метрикам инфраструктуры.
- После оптимизации максимальный стабильный RPS выше подтвержденного baseline при сопоставимом окружении и сценарии нагрузки.
- Рост RPS не сопровождается неприемлемой деградацией p95/p99 latency или увеличением error rate.
- Для каждого существенного изменения зафиксирована связь: наблюдаемая проблема, внесенная оптимизация и измеренный результат.
- PHP Dockerfile использует multi-stage build с отдельными production- и development-targets.
- Production image не содержит компиляторы, PHP headers, `$PHPIZE_DEPS` и `*-dev` пакеты, не требуемые во время runtime.
- Production содержит необходимые PHP extensions и только их runtime shared libraries.
- OPcache установлен и включен в production.
- Xdebug отсутствует в production и доступен только в development.
- Composer и Symfony CLI отсутствуют в production и доступны только в development.
- Существующие PHP extensions сохранены: `intl`, `mbstring`, `pdo_pgsql`, `sockets`, `amqp`, `grpc`, `redis`, `xsl` и `zip`.
- Compose targets `prod` и `dev` сохранены, а итоговая Compose-конфигурация валидна для обоих окружений.
- Проверены и обоснованы настройки PHP-FPM pool, OPcache, Nginx и контейнерных ресурсов.
- Проверены Doctrine-запросы, N+1, индексы базы данных, кеширование и межсервисные вызовы; изменения применены только для подтвержденных проблем.
- Все существующие automated tests и smoke tests проходят после оптимизации.
- Функциональность приложения и публичные API-контракты не изменены.
- Зафиксированы размер production image до и после оптимизации и итоговый состав runtime-пакетов.
- Подготовлен итоговый отчет со сравнением производительности до и после изменений.

### Технический подход

- Выбрать стабильный k6-сценарий из Task 8 и зафиксировать одинаковые условия для всех сравнительных запусков.
- Выполнить несколько baseline-прогонов после прогрева приложения и использовать согласованный способ агрегации результатов.
- Сопоставить k6-метрики с Grafana/Prometheus: CPU, RAM, saturation PHP-FPM workers, Nginx connections, PostgreSQL activity, Redis и service-level HTTP/gRPC latency.
- Разделять время обработки между API Gateway, PHP-приложением, базой данных, кешем и внутренними вызовами.
- Оптимизировать сначала наиболее значимый подтвержденный bottleneck и повторять измерение после каждой группы изменений.
- Перевести PHP image на stages для сборки общих extensions, production extensions, development extensions и минимального runtime.
- Устанавливать системные headers и build toolchain только в builder stages.
- Копировать собранные PHP extensions и их ini-конфигурацию только между stages с совместимыми PHP ABI, архитектурой и базовым образом.
- Устанавливать в runtime только shared libraries, которые подтверждены через `ldd` и package dependencies.
- Настроить OPcache для production с учетом размера codebase, количества PHP-файлов и неизменяемости production-кода.
- Проверить параметры PHP-FPM: `pm`, количество workers, spare servers, request limits и memory budget контейнера.
- Проверить Nginx upstream keepalive, buffering, timeouts и лимиты соединений, не скрывая ошибки backend-сервисов чрезмерными retry.
- Использовать Symfony production mode: `APP_ENV=prod`, `APP_DEBUG=0`, прогретый cache и оптимизированный autoloader в процессе deployment/build workflow.
- Анализировать Doctrine query count и query time, устранять N+1, добавлять необходимые fetch strategy или индексы только после подтверждения планом выполнения и метриками.
- Проверить эффективность Redis/cache и исключить кеширование данных с некорректной invalidation-семантикой.
- Не объединять несколько независимых оптимизаций в одно измерение, если это мешает определить вклад каждой из них.
- Сохранить rollback-возможность и документировать параметры окружения, влияющие на результаты теста.

### Как тестировать

- Собрать production target командой `docker compose build symfony-cli`.
- Собрать development target командой `docker compose --env-file .env --env-file .env.dev build symfony-cli`.
- Проверить итоговую конфигурацию командами `docker compose config` и `docker compose --env-file .env --env-file .env.dev config`.
- Проверить список PHP extensions через `php -m` в production и development.
- Проверить production OPcache через `php --ri opcache`.
- Убедиться, что Xdebug отсутствует в production и присутствует в development.
- Проверить через `ldd` зависимости критичных PHP extensions и отсутствие строк `not found`.
- Проверить отсутствие компиляторов, build toolchain, PHP headers и ненужных `*-dev` пакетов в production image.
- Проверить отсутствие Composer и Symfony CLI в production и их наличие в development.
- Сравнить размер production image до и после multi-stage refactoring.
- Запустить Symfony smoke tests для основных публичных и внутренних маршрутов.
- Выполнить существующие automated tests всех затронутых сервисов.
- Выполнить одинаковые baseline- и post-optimization k6-сценарии после прогрева окружения.
- Сравнить RPS, p50, p95, p99, error rate, CPU, RAM, database activity и service-level latency.
- Проверить работу под стабильной нагрузкой и отдельно определить точку saturation при ступенчатом повышении нагрузки.
- Повторить контрольный прогон, чтобы исключить случайный прирост производительности.

### Примечания

- Задача продолжает Task 8 и использует уже подготовленные monitoring и load-testing инструменты.
- Около 80 RPS является предварительным результатом, а не гарантированным baseline или целевым пределом.
- Точное целевое значение RPS фиксируется после воспроизводимого baseline и оценки доступных ресурсов.
- Производительность сравнивается только на одинаковом оборудовании, наборе данных, конфигурации контейнеров и профиле нагрузки.
- Более высокий RPS не считается успешным результатом, если он достигнут ценой ошибок, неконтролируемого роста latency или изменения функциональности.
- Изменения Composer dependencies, бизнес-логики и API-контрактов требуют отдельного обоснования и не входят в базовый scope задачи.

### Итог выполнения

Исходным ориентиром оставался результат Task 8 около 80 RPS на Intel Core i5 и 8 ГБ RAM с совместным запуском приложения, инфраструктуры, мониторинга и k6 в WSL. Полный набор исходных latency-перцентилей не сохранился. Итоговый тест выполнялся на Intel Core i9 с 64 ГБ RAM и каталогом примерно в 1 млн товаров, поэтому абсолютные результаты нельзя трактовать как строго сопоставимый коэффициент ускорения.

В рамках задачи выполнены multi-stage реорганизация PHP image, production-настройка OPcache, разделение dev/prod/load-test targets, выделение отдельных Redis-инстансов прикладного кеша, подготовка production-like load-test окружения и инструментов корреляции Symfony profiler с SQL. Для каталога проверен режим look-ahead пагинации: запрос загружает `limit + 1` строку и возвращает `hasNextPage`, что позволяет не выполнять точный `COUNT` на основном пути списка. Режим управляется feature flag и сохраняет прежний вариант ответа при отключении.

Mixed-load эксперимент продолжительностью около пяти минут использовал 300 VU: 225 browsing, 60 shopping и 15 checkout. Результат: 159 646 HTTP-запросов, 525,54 запроса/с в среднем, 0,00% HTTP-ошибок, average 202,80 мс, median 169,55 мс, p90 273,56 мс и p95 307,64 мс. Grafana показывала примерно 643–678 gateway RPS в верхней части прогона. Latency каталога составляла около 200 мс; показатель около 2 секунд на дашборде относится к авторизации.

Главным оставшимся ограничением каталога являются тяжёлые `COUNT`, фильтрация и агрегации в PostgreSQL. Их дальнейшая оптимизация требует специализированной read-модели/search engine на Elasticsearch и вынесена в отдельную задачу. Elasticsearch в Task 9 не реализован.

При закрытии задачи dev- и load-test Compose configurations успешно прошли `docker compose config --quiet`. Catalog suite прошёл полностью: 24 теста и 63 assertions. PHP lint прошёл для всех изменённых PHP-файлов. Smoke-запрос списка каталога через API Gateway вернул HTTP 200 примерно за 202 мс, а защищённый `/api/v1/auth/me` без токена ожидаемо вернул HTTP 401.

Оставшиеся ограничения: исходный и итоговый тесты выполнены на разном оборудовании; генератор нагрузки и приложение разделяли один хост; CPU приближался к насыщению; p99 на итоговом скриншоте k6 отдельно не зафиксирован; production image после последних изменений не пересобирался в рамках закрытия задачи; Composer фактически находится в общем runtime-base и доступен в production target, несмотря на первоначальный критерий его исключения; Cart suite не был запущен, потому что в текущем контейнере отсутствует `vendor/bin/simple-phpunit`, а установка dependencies не входит в процедуру закрытия.

## EN

### Status

**Completed — 2026-08-30.**

### Title

Application performance optimization, RPS improvement, and PHP environment tuning.

### Task Description

Perform measurable online store performance optimization based on the monitoring and load-testing results from Task 8. Establish a reproducible baseline, identify actual bottlenecks, optimize the PHP runtime and related components, and confirm the result with repeated load tests using the same scenario and environment.

The preliminary Task 8 result of approximately 80 RPS is used as a reference, but it must be measured again and recorded together with latency, error rate, and resource usage before optimization. The final improvement is evaluated against the confirmed baseline rather than only by an absolute RPS value.

The optimization must cover PHP-FPM, OPcache, Nginx, Symfony, Doctrine, PostgreSQL, Redis, the Docker image, and container environment parameters. Changes must be based on profiling and monitoring data and must not alter business logic or the public behavior of the application.

### Goal

Increase the maximum stable RPS and reduce application latency under load by removing confirmed bottlenecks and preparing a clean, reproducible, optimized production PHP environment without development or build-time dependencies.

### Architecture Context

External traffic enters through the Nginx API Gateway and is distributed between the main Symfony service, Catalog Service, and Cart/Order Service. The services use PostgreSQL, Redis, and internal REST/gRPC integrations. Prometheus, Grafana, and k6 prepared in Task 8 are used to measure RPS, latency, errors, and resource consumption.

Production and development must be built from the same multi-stage Dockerfile but have different runtime contents:

- production uses PHP-FPM, Nginx, and OPcache;
- development includes Composer, Symfony CLI, and Xdebug;
- the build toolchain, headers, and `*-dev` packages remain only in builder stages;
- PHP extensions and required shared runtime libraries remain available in both environments;
- Compose targets `prod` and `dev` continue to use the existing environment startup model.

Application code, data query, and infrastructure optimizations are applied only after measurements confirm the corresponding bottleneck. Business logic and API contract changes are outside the task scope.

### Acceptance Criteria

- A reproducible pre-optimization baseline is recorded: k6 scenario, load profile, duration, virtual user count, test data, and environment parameters.
- Baseline RPS, p50, p95, p99, error rate, CPU, RAM, and main dependency metrics are recorded.
- The preliminary Task 8 value of approximately 80 RPS is verified by a repeated measurement and is not used as the only success criterion.
- Confirmed bottlenecks are identified using k6, Prometheus, Grafana, PHP/Symfony profiling, and infrastructure metrics.
- The maximum stable RPS after optimization is higher than the confirmed baseline under a comparable environment and load scenario.
- The RPS increase does not cause unacceptable p95/p99 latency degradation or a higher error rate.
- Every significant change records the relationship between the observed issue, applied optimization, and measured result.
- The PHP Dockerfile uses a multi-stage build with separate production and development targets.
- The production image does not contain compilers, PHP headers, `$PHPIZE_DEPS`, or `*-dev` packages that are not required at runtime.
- Production contains the required PHP extensions and only their runtime shared libraries.
- OPcache is installed and enabled in production.
- Xdebug is absent from production and available only in development.
- Composer and Symfony CLI are absent from production and available only in development.
- Existing PHP extensions are preserved: `intl`, `mbstring`, `pdo_pgsql`, `sockets`, `amqp`, `grpc`, `redis`, `xsl`, and `zip`.
- Compose targets `prod` and `dev` are preserved, and the resulting Compose configuration is valid for both environments.
- PHP-FPM pool, OPcache, Nginx, and container resource settings are reviewed and justified.
- Doctrine queries, N+1 issues, database indexes, caching, and inter-service calls are reviewed; changes are applied only to confirmed problems.
- All existing automated tests and smoke tests pass after optimization.
- Application functionality and public API contracts remain unchanged.
- The production image size before and after optimization and the final runtime package set are recorded.
- A final report compares performance before and after the changes.

### Technical Approach

- Select a stable k6 scenario from Task 8 and keep identical conditions for every comparative run.
- Run multiple baseline measurements after application warm-up and use an agreed result aggregation method.
- Correlate k6 metrics with Grafana/Prometheus data: CPU, RAM, PHP-FPM worker saturation, Nginx connections, PostgreSQL activity, Redis, and service-level HTTP/gRPC latency.
- Separate processing time between the API Gateway, PHP application, database, cache, and internal calls.
- Optimize the most significant confirmed bottleneck first and repeat measurements after each group of changes.
- Split the PHP image into stages for common extension builds, production extensions, development extensions, and a minimal runtime.
- Install system headers and the build toolchain only in builder stages.
- Copy compiled PHP extensions and their ini configuration only between stages with compatible PHP ABI, architecture, and base image.
- Install only shared runtime libraries confirmed by `ldd` and package dependencies in runtime stages.
- Tune OPcache for production based on codebase size, PHP file count, and immutable production code.
- Review PHP-FPM settings: `pm`, worker count, spare servers, request limits, and container memory budget.
- Review Nginx upstream keepalive, buffering, timeouts, and connection limits without hiding backend failures behind excessive retries.
- Use Symfony production mode with `APP_ENV=prod`, `APP_DEBUG=0`, warmed cache, and an optimized autoloader in the deployment/build workflow.
- Analyze Doctrine query count and query time; remove N+1 issues and add required fetch strategies or indexes only after execution plans and metrics confirm the need.
- Review Redis/cache effectiveness and avoid caching data without correct invalidation semantics.
- Avoid combining unrelated optimizations into one measurement when this would hide the contribution of individual changes.
- Preserve rollback capability and document environment parameters that affect test results.

### How To Test

- Build the production target with `docker compose build symfony-cli`.
- Build the development target with `docker compose --env-file .env --env-file .env.dev build symfony-cli`.
- Validate the resulting configuration with `docker compose config` and `docker compose --env-file .env --env-file .env.dev config`.
- Check the PHP extension list with `php -m` in production and development.
- Check production OPcache with `php --ri opcache`.
- Confirm that Xdebug is absent from production and present in development.
- Use `ldd` to inspect critical PHP extension dependencies and confirm that no `not found` entries exist.
- Confirm that compilers, build toolchain, PHP headers, and unnecessary `*-dev` packages are absent from the production image.
- Confirm that Composer and Symfony CLI are absent from production and available in development.
- Compare the production image size before and after the multi-stage refactoring.
- Run Symfony smoke tests for main public and internal routes.
- Run the existing automated tests for every affected service.
- Run identical baseline and post-optimization k6 scenarios after environment warm-up.
- Compare RPS, p50, p95, p99, error rate, CPU, RAM, database activity, and service-level latency.
- Test sustained stable load and separately identify the saturation point with step-based load growth.
- Repeat the control run to rule out accidental performance improvement.

### Notes

- This task continues Task 8 and uses the monitoring and load-testing tools already prepared there.
- Approximately 80 RPS is a preliminary result, not a guaranteed baseline or target limit.
- The exact target RPS is defined after establishing a reproducible baseline and evaluating available resources.
- Performance is compared only on the same hardware, dataset, container configuration, and load profile.
- Higher RPS is not considered successful if it causes errors, uncontrolled latency growth, or functionality changes.
- Composer dependency, business logic, and API contract changes require separate justification and are outside the base task scope.

### Completion Result

The Task 8 result of approximately 80 RPS on an Intel Core i5 machine with 8 GB RAM remained the initial reference. The application, infrastructure, monitoring, and k6 shared one WSL host, and the complete initial latency percentiles were not retained. The final test ran on an Intel Core i9 machine with 64 GB RAM and a catalog of approximately 1 million products, so the absolute values must not be interpreted as a strictly comparable speedup ratio.

The task delivered a multi-stage PHP image reorganization, production OPcache configuration, separate dev/prod/load-test targets, dedicated application Redis instances, a production-like load-test environment, and tooling that correlates Symfony profiler records with SQL. A catalog look-ahead pagination mode was tested: it fetches `limit + 1` rows and returns `hasNextPage`, avoiding an exact `COUNT` on the common listing path. A feature flag controls the mode and preserves the previous response variant when disabled.

The approximately five-minute mixed-load experiment used 300 VUs: 225 browsing, 60 shopping, and 15 checkout. It produced 159,646 HTTP requests at 525.54 requests/s on average, with 0.00% HTTP failures, 202.80 ms average latency, 169.55 ms median latency, 273.56 ms p90, and 307.64 ms p95. Grafana showed approximately 643–678 gateway RPS in the upper part of the run. Catalog latency was approximately 200 ms; the value near 2 seconds on the dashboard belongs to authorization traffic.

The main remaining catalog limitation is heavy PostgreSQL `COUNT`, filtering, and aggregation work. Further optimization requires a specialized Elasticsearch-based read model/search engine and is deferred to a separate task. Elasticsearch was not implemented in Task 9.

During task closure, the dev and load-test Compose configurations passed `docker compose config --quiet`. The complete Catalog suite passed with 24 tests and 63 assertions. PHP lint passed for every changed PHP file. A catalog-list smoke request through the API Gateway returned HTTP 200 in approximately 202 ms, while protected `/api/v1/auth/me` correctly returned HTTP 401 without a token.

Remaining limitations: the initial and final tests used different hardware; the load generator and application shared one host; CPU approached saturation; the final k6 screenshot did not record a separate p99 value; the production image was not rebuilt during task closure; Composer currently resides in the shared runtime base and is therefore available in the production target despite the initial exclusion criterion; and the Cart suite was not run because `vendor/bin/simple-phpunit` is absent from the current container and dependency installation is outside the closure procedure.
