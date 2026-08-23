# Лог Результата MR Task 9

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Обзор](#%D0%BE%D0%B1%D0%B7%D0%BE%D1%80)
- [Scope](#scope)
- [PHP-окружение](#php-%D0%BE%D0%BA%D1%80%D1%83%D0%B6%D0%B5%D0%BD%D0%B8%D0%B5)
- [Проверка производительности](#%D0%BF%D1%80%D0%BE%D0%B2%D0%B5%D1%80%D0%BA%D0%B0-%D0%BF%D1%80%D0%BE%D0%B8%D0%B7%D0%B2%D0%BE%D0%B4%D0%B8%D1%82%D0%B5%D0%BB%D1%8C%D0%BD%D0%BE%D1%81%D1%82%D0%B8)
- [План проверки](#%D0%BF%D0%BB%D0%B0%D0%BD-%D0%BF%D1%80%D0%BE%D0%B2%D0%B5%D1%80%D0%BA%D0%B8)
- [Вне Scope](#%D0%B2%D0%BD%D0%B5-scope)

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
