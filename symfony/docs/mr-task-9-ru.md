# Лог Результата MR Task 9

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Обзор](#%D0%BE%D0%B1%D0%B7%D0%BE%D1%80)
- [Scope](#scope)
- [PHP-окружение](#php-%D0%BE%D0%BA%D1%80%D1%83%D0%B6%D0%B5%D0%BD%D0%B8%D0%B5)
- [Проверка производительности](#%D0%BF%D1%80%D0%BE%D0%B2%D0%B5%D1%80%D0%BA%D0%B0-%D0%BF%D1%80%D0%BE%D0%B8%D0%B7%D0%B2%D0%BE%D0%B4%D0%B8%D1%82%D0%B5%D0%BB%D1%8C%D0%BD%D0%BE%D1%81%D1%82%D0%B8)
- [План проверки](#%D0%BF%D0%BB%D0%B0%D0%BD-%D0%BF%D1%80%D0%BE%D0%B2%D0%B5%D1%80%D0%BA%D0%B8)
- [Вне Scope](#%D0%B2%D0%BD%D0%B5-scope)
- [2026-08-23 — Документация multi-stage сборки](#2026-08-23--%D0%B4%D0%BE%D0%BA%D1%83%D0%BC%D0%B5%D0%BD%D1%82%D0%B0%D1%86%D0%B8%D1%8F-multi-stage-%D1%81%D0%B1%D0%BE%D1%80%D0%BA%D0%B8)
- [2026-08-30 — Итоговые показатели и завершение задачи](#2026-08-30--%D0%B8%D1%82%D0%BE%D0%B3%D0%BE%D0%B2%D1%8B%D0%B5-%D0%BF%D0%BE%D0%BA%D0%B0%D0%B7%D0%B0%D1%82%D0%B5%D0%BB%D0%B8-%D0%B8-%D0%B7%D0%B0%D0%B2%D0%B5%D1%80%D1%88%D0%B5%D0%BD%D0%B8%D0%B5-%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B8)
- [2026-08-30 — Уточнение runtime-профилей](#2026-08-30--%D1%83%D1%82%D0%BE%D1%87%D0%BD%D0%B5%D0%BD%D0%B8%D0%B5-runtime-%D0%BF%D1%80%D0%BE%D1%84%D0%B8%D0%BB%D0%B5%D0%B9)
- [2026-08-30 — Команды активации окружений](#2026-08-30--%D0%BA%D0%BE%D0%BC%D0%B0%D0%BD%D0%B4%D1%8B-%D0%B0%D0%BA%D1%82%D0%B8%D0%B2%D0%B0%D1%86%D0%B8%D0%B8-%D0%BE%D0%BA%D1%80%D1%83%D0%B6%D0%B5%D0%BD%D0%B8%D0%B9)

<!-- END doctoc -->

## Обзор

Этот документ описывает планируемый результат merge request для Task 9.

Merge request: [PR #13](https://github.com/ivanserg0692/symfony2026/pull/13)

Файл задачи: [task-9.md](task-9.md)

Планируемый merge request фиксирует воспроизводимый performance baseline, оптимизирует production PHP-окружение, устраняет подтвержденные bottleneck приложения и инфраструктуры и проверяет итоговый рост RPS повторяемыми нагрузочными тестами.

Работа должна сохранить поведение приложения, публичные API-контракты, Composer dependencies и текущий набор PHP extensions. Оптимизация считается успешной только при наличии сопоставимых измерений до и после изменений без неприемлемой деградации latency или error rate.

## Scope

Планируется:

- зафиксировать повторяемый k6 baseline с RPS, p50, p95, p99, error rate, CPU, RAM и метриками зависимостей;
- сопоставить результаты нагрузочных тестов с метриками Prometheus и Grafana;
- определить bottleneck до внесения изменений в application, database, cache, gateway или runtime;
- настроить PHP-FPM, OPcache, Nginx, Symfony production mode, Doctrine, PostgreSQL, Redis и ресурсы контейнеров там, где изменение подтверждено измерениями;
- сравнить максимальный стабильный RPS и размер production image до и после оптимизации;
- сохранить существующие Docker Compose targets `prod` и `dev`.

## PHP-окружение

PHP image планируется как multi-stage build с разделением builder- и runtime-ответственности:

- build toolchain, headers и `*-dev` пакеты остаются в builder stages;
- production содержит PHP-FPM, OPcache, необходимые PHP extensions и только их runtime shared libraries;
- development дополнительно содержит Composer, Symfony CLI и Xdebug;
- Xdebug, Composer и Symfony CLI отсутствуют в production;
- собранные extensions копируются только между stages на основе одной PHP image family с совместимыми ABI, архитектурой и системными библиотеками.

Существующие extensions `intl`, `mbstring`, `pdo_pgsql`, `sockets`, `amqp`, `grpc`, `redis`, `xsl` и `zip` сохраняются и не должны удаляться.

## Проверка производительности

Результат должен сравниваться с подтвержденным baseline на одинаковом оборудовании, наборе данных, конфигурации контейнеров, процедуре прогрева и профиле нагрузки.

Проверка включает:

- повторяемые baseline- и post-optimization k6-запуски;
- сравнение максимального стабильного RPS и точки saturation;
- сравнение p50, p95 и p99 latency;
- сравнение HTTP/gRPC error rate;
- сравнение CPU, RAM, PHP-FPM workers, PostgreSQL, Redis, gateway и service-level metrics;
- фиксацию связи между каждым подтвержденным bottleneck, внесенным изменением и измеренным эффектом.

Предварительный результат Task 8 около 80 RPS является только ориентиром и должен быть измерен повторно перед использованием в качестве optimization baseline.

## План проверки

Реализацию нужно проверить по следующим признакам:

- production и development Docker targets успешно собираются;
- Docker Compose configuration остается валидной для обоих окружений;
- необходимые PHP extensions загружены в production и development;
- OPcache установлен и включен в production;
- Xdebug отсутствует в production и присутствует в development;
- Composer и Symfony CLI отсутствуют в production и присутствуют в development;
- `ldd` не показывает отсутствующие shared libraries для критичных PHP extensions;
- компиляторы, PHP headers, build toolchain и ненужные `*-dev` пакеты отсутствуют в production;
- Symfony smoke tests и существующие automated tests проходят;
- повторные нагрузочные тесты подтверждают измеренный результат без функциональной регрессии.

## Вне Scope

- изменение Composer dependencies без отдельного согласования;
- удаление существующих PHP extensions;
- изменение бизнес-логики или публичных API-контрактов;
- использование неподтвержденного абсолютного RPS как единственного критерия успеха;
- применение speculative optimization без подтверждения profiling- или monitoring-данными.

## 2026-08-23 — Документация multi-stage сборки

- Добавлена отдельная двуязычная документация multi-stage сборки PHP image.
- Описаны все builder- и runtime-stages, artifacts расширений, границы системных пакетов, требования ABI, Compose targets, поведение layer cache и команды проверки.
- Добавлена локализованная PlantUML-диаграмма stages, а документация сборки связана с основным README.
- Документация сборки: [php-multi-stage-build.md](php-multi-stage-build.md)

## 2026-08-30 — Итоговые показатели и завершение задачи

- Task 9 переведена в статус completed без реализации Elasticsearch и без дополнительных архитектурных изменений на этапе закрытия.
- Зафиксирован ориентир Task 8 около 80 RPS на Intel Core i5/8 ГБ RAM и указано, что он не является строго сопоставимым с итоговым запуском на Intel Core i9/64 ГБ RAM.
- Описан итоговый mixed-load профиль: около пяти минут, 300 VU (225 browsing, 60 shopping и 15 checkout), каталог примерно из 1 млн товаров.
- Зафиксированы 159 646 HTTP-запросов, 525,54 запроса/с в среднем, 0,00% HTTP-ошибок, average latency 202,80 мс, median 169,55 мс, p90 273,56 мс и p95 307,64 мс. В верхней части прогона Grafana показывала примерно 643–678 gateway RPS.
- Уточнено, что latency каталога составляла около 200 мс, а значение около 2 секунд относится к авторизации.
- Подведены итоги по multi-stage runtime, OPcache, разделению окружений, отдельным Redis-кешам, нагрузочным инструментам, корреляции profiler/SQL и опциональной пагинации `hasNextPage`.
- Подтверждено, что главным оставшимся bottleneck каталога являются тяжёлые PostgreSQL `COUNT`, фильтрация и агрегации. Специализированная read-модель/search engine на Elasticsearch вынесена в отдельную задачу и здесь не реализована.
- Проверены dev- и load-test Compose configurations, выполнен lint всех изменённых PHP-файлов, Catalog suite прошёл с результатом 24 теста и 63 assertions, а catalog smoke request вернул HTTP 200 примерно за 202 мс. Защищённая проверка авторизации без токена корректно вернула HTTP 401.
- Зафиксированы ограничения: разное baseline/final оборудование, общий хост приложения и генератора нагрузки, приближение CPU к saturation, отсутствие отдельного p99 в итоговом k6 capture, отсутствие пересборки production image при закрытии, наличие Composer в общем production runtime-base и недоступный Cart test runner (`vendor/bin/simple-phpunit`) в текущем контейнере.

## 2026-08-30 — Уточнение runtime-профилей

- Описаны три стандартных runtime-профиля: production по умолчанию, изолированный `load_test` для тестов производительности и development с подробными логами приложения, Symfony profiler и Xdebug.
- Уточнено, что итоговые показатели Task 9 получены в `load_test`, который сохраняет production-like поведение PHP без development-диагностики.

## 2026-08-30 — Команды активации окружений

- Зафиксированы `npm run set:prod`, `npm run set:dev` и `npm run set:load-test` как стандартные helper-команды для загрузки выбранного окружения в отдельном интерактивном shell.
- Уточнено, что команды выбирают Compose-контекст, но сами не собирают и не запускают контейнеры, а `exit` возвращает предыдущий контекст терминала.
