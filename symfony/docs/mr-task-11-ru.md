# Лог результата MR Task 11

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Обзор](#%D0%BE%D0%B1%D0%B7%D0%BE%D1%80)
- [Планируемый объём](#%D0%BF%D0%BB%D0%B0%D0%BD%D0%B8%D1%80%D1%83%D0%B5%D0%BC%D1%8B%D0%B9-%D0%BE%D0%B1%D1%8A%D1%91%D0%BC)
- [План проверки](#%D0%BF%D0%BB%D0%B0%D0%BD-%D0%BF%D1%80%D0%BE%D0%B2%D0%B5%D1%80%D0%BA%D0%B8)
- [Вне задачи](#%D0%B2%D0%BD%D0%B5-%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B8)
- [Задача](#%D0%B7%D0%B0%D0%B4%D0%B0%D1%87%D0%B0)

<!-- END doctoc -->

## Обзор

Документ фиксирует планируемый объём [PR #15](https://github.com/ivanserg0692/symfony2026/pull/15) по [Task 11](task-11.md). Сейчас в репозитории есть описание задачи; развёртывание в Kubernetes и масштабирование ещё не подтверждены как выполненные.

Цель — сохранить Docker Compose и Kubernetes как два независимых способа запуска одного проекта и проверить независимое горизонтальное масштабирование Auth, Catalog и Cart без привязки к числу Kubernetes nodes.

## Планируемый объём

- Подготовить самодостаточные образы Auth, Catalog и Cart для запуска без bind mounts исходного кода, сохранив существующий Compose workflow.
- Добавить однократный bootstrap JWT для Auth: использовать полную существующую пару ключей или создать её через `bin/init-jwt` с текущим `JWT_PASSPHRASE`. Все Auth Pod будут получать одинаковые ключи, необходимые секреты и общее состояние сессий.
- Запускать Catalog HTTP, gRPC и Messenger workers как независимые роли без ненужных изменений поведения приложения.
- Добавить Kubernetes-конфигурацию и ресурсы развёртывания отдельно от Docker Compose.
- Собирать PHP-FPM metrics отдельно по Pod. Symfony application metrics из общего Redis собирать один раз на логический сервис.
- Добавить отдельный Kubernetes Grafana dashboard по discovery labels с представлением по сервисам и Pod; Compose dashboard сохранить без изменений.
- Проверить ручное масштабирование и HPA существующими k6-сценариями: готовность, ошибки, throughput, latency, ресурсы и историю числа Pod.

## План проверки

- Запустить образы сервисов без bind mounts и проверить каждую роль Catalog.
- Проверить bootstrap JWT с готовыми ключами и без них, а также сессии и JWT-запросы через разные Auth Pod.
- Проверить PHP-FPM series по каждому Pod и отсутствие умножения общих Symfony metrics на число реплик.
- Сравнить k6-прогоны с одним и несколькими Pod и проверить масштабирование в Kubernetes dashboard.
- Повторно проверить существующий запуск Docker Compose и его dashboard.

## Вне задачи

- Замена Docker Compose или изменение его Grafana dashboard.
- VPA, Redis Cluster и PostgreSQL HA.
- Утверждение о результатах Kubernetes или HPA до реализации и измерения.

## Задача

Файл задачи: [task-11.md](task-11.md).
