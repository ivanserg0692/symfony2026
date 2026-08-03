# Лог Результата MR Task 7

## Обзор

Этот документ будет описывать видимый результат merge request 7.

Merge request: https://github.com/ivanserg0692/symfony2026/pull/7

Файл задачи: [task-7.md](task-7.md)

Задача описывает и подготавливает целевую архитектуру backend-сервисов, где nginx является внешним REST/HTTP API Gateway, существующее Symfony-приложение является основным сервисом для Auth, админки, новостей и уведомлений, а gRPC используется только для внутренних service-to-service взаимодействий.

## Планируемый результат

Ожидаемый результат включает:
- nginx как внешний REST/HTTP gateway
- auth check от nginx к Symfony Main Service
- trusted identity header `X-User-Id`
- проксирование REST/HTTP запросов от nginx в downstream backend-сервисы
- внутреннее gRPC-взаимодействие между backend-сервисами там, где оно нужно
- отдельное владение базой данных каждым сервисом
- локальную async/event-driven обработку внутри Symfony Main Service через RabbitMQ
- зафиксированный будущий путь для расширения уведомлений и event-driven интеграций

## Архитектурная диаграмма

<!-- plantuml src="plantuml/backend-architecture/services.puml" alt="Backend service architecture" out="images/plantuml/backend-architecture/services.png" -->
![Backend service architecture](images/plantuml/backend-architecture/services.png)
<!-- /plantuml -->

## Скриншоты

Не применимо для архитектурной задачи по документации.

## Обновления

Заметки по реализации добавляются сюда отдельными датированными секциями.

### 2026-07-19 - Созданы service applications

В текущей реализации появились два отдельных Symfony service applications, которые соответствуют схеме архитектуры Task 7:

- `catalog-service` для границы Catalog Service;
- `cart-service` для границы Order/Cart Service.

На этом этапе они фиксируются как сервисная основа под дальнейшую реализацию REST/HTTP endpoints, внутренних gRPC-интеграций и собственного владения данными.

### 2026-08-03 - Архитектурный scope завершен

Task 7 теперь завершена по реализованному архитектурному направлению:

- Catalog Service и Cart Service существуют как отдельные Symfony applications со своими service boundaries;
- Cart/Order сценарии используют внутреннюю Catalog gRPC-интеграцию там, где нужны product validation или snapshots;
- nginx API Gateway является целевой публичной REST/HTTP точкой входа и публикует внешний contract `/api/v1/...`;
- Gateway routes, nginx snippets и публичный Gateway OpenAPI contract генерируются из `api-gateway/routes.json` и `api-gateway/openapi-header.json`;
- защищенные Gateway routes валидируют текущего пользователя через Symfony Main Service и передают downstream-сервисам доверенный header `X-User-Id`;
- client-supplied identity headers очищаются или перезаписываются на Gateway перед proxying;
- общие CORS values, internal service URLs и database maintenance targets вынесены в root `.env`;
- общие DB maintenance scripts покрывают создание баз, миграции, status checks и fixtures для всех настроенных Symfony services.

В development Docker Compose setup отдельные сервисные порты могут оставаться опубликованными для отладки. В production-like окружении прямой внешний доступ к этим портам должен закрываться perimeter firewall, а Gateway остается публичным REST/HTTP contract.
