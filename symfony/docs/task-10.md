# Task 10

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

**Planned.**

### Название

Elasticsearch read-модель каталога для поиска, фильтрации, агрегаций и пресетов.

### Описание задачи

Внедрить специализированную Elasticsearch read-модель для операций чтения каталога, которые после Task 9 остаются главным ограничением производительности: полнотекстового поиска, сложной фильтрации, точных `COUNT`, фасетов и агрегаций по каталогу примерно из 1 млн товаров.

PostgreSQL должен остаться единственным источником истины и местом выполнения операций записи. Elasticsearch должен содержать производное денормализованное представление товаров и связанных с ними поисковых структур, оптимизированное для чтения. Каталожные запросы должны переводиться на Elasticsearch только в пределах явно определённого read-контракта и с контролируемой eventual consistency.

Одновременно необходимо привести фильтрацию к стабильному публичному контракту: определить разрешённые поля и операторы, поддержать комбинации условий и добавить системные именованные пресеты — заранее заданные конфигурации фильтров, сортировки и поисковых параметров для типовых каталоговых сценариев.

### Цель

Снять с PostgreSQL тяжёлые каталоговые `COUNT`, фильтрацию и агрегации, обеспечить быстрый и предсказуемый поиск по большому каталогу и подготовить расширяемую модель фильтров и пресетов без изменения PostgreSQL как источника истины.

### Архитектурный контекст

Task 9 показала, что после оптимизации PHP runtime, кешей и основного пути пагинации главным оставшимся bottleneck каталога являются тяжёлые запросы PostgreSQL для `COUNT`, фильтрации и агрегаций. При каталоге примерно в 1 млн товаров простой путь списка работает с latency около 200 мс, однако точные подсчёты, комбинации фильтров и построение агрегатов продолжают создавать существенную нагрузку на базу данных и плохо масштабируются вместе с усложнением поиска.

Целевая схема разделяет ответственность:

- PostgreSQL хранит канонические товары, связи и транзакционные данные;
- Elasticsearch хранит версионированную денормализованную read-модель каталога;
- Catalog Service формирует единый поисковый контракт и не раскрывает клиентам Elasticsearch DSL;
- изменения данных доставляются в индекс асинхронно с retry, контролем ошибок и возможностью повторной обработки;
- первичная загрузка и полная переиндексация выполняются отдельно от инкрементальной синхронизации;
- переключение между версиями индекса выполняется через alias без остановки чтения;
- рассинхронизация допустима только в пределах явно определённого и измеряемого окна eventual consistency.

Синхронная двойная запись в PostgreSQL и Elasticsearch не должна использоваться как механизм консистентности. Конкретный способ гарантированной публикации изменений — например transactional outbox с дальнейшей доставкой через существующую очередь — должен быть подтверждён при проектировании интеграции.

Elasticsearch в этой задаче является поисковой read-моделью и хранилищем производных поисковых структур, а не заменой PostgreSQL и не универсальным кешем приложения. Документы индекса могут включать нормализованные значения фильтров, данные для сортировки, фасетов и агрегаций, если их происхождение, версия и правила обновления однозначно определены.

### Критерии приемки

- Elasticsearch добавлен как отдельный инфраструктурный компонент для Catalog Service и доступен в необходимых окружениях проекта.
- PostgreSQL остаётся источником истины; создание и изменение товаров не зависят от синхронной записи в Elasticsearch.
- Определены versioned mapping и settings индекса, включая типы полей, анализаторы, normalizers и поля для сортировки, фильтрации, фасетов и агрегаций.
- Реализована идемпотентная первичная индексация каталога примерно из 1 млн товаров с контролем прогресса, ошибок и возможностью безопасного повторного запуска.
- Реализована надёжная инкрементальная синхронизация созданий, изменений и удалений товаров без необратимой потери событий.
- Для доставки изменений предусмотрены retry, dead-letter/error handling, наблюдаемость и способ повторной обработки.
- Реализована полная переиндексация в новый versioned index с атомарным переключением alias и возможностью rollback.
- Catalog Service выполняет через read-модель полнотекстовый поиск, фильтрацию, сортировку, пагинацию, точный подсчёт результатов, фасеты и необходимые агрегации.
- Публичный API не принимает произвольный Elasticsearch DSL; разрешённые поля, операторы, сортировки и лимиты контролируются приложением.
- Фильтры поддерживают как минимум точное совпадение, множественный выбор, диапазоны, проверку наличия значения и безопасные комбинации условий для разрешённых полей.
- Добавлены системные именованные пресеты фильтрации с версионируемой схемой, валидацией и однозначными правилами объединения с явными параметрами запроса.
- Определено ожидаемое окно eventual consistency и добавлена метрика задержки между изменением в PostgreSQL и доступностью изменения в Elasticsearch.
- Предусмотрено контролируемое поведение при недоступности Elasticsearch; оно не должно приводить к незаметной выдаче некорректных результатов.
- Запросы Elasticsearch, ошибки, latency, hit count, состояние индекса, отставание синхронизации и длительность переиндексации доступны в monitoring.
- Зафиксирован воспроизводимый PostgreSQL baseline для поиска, тяжёлых фильтров, `COUNT`, фасетов и агрегаций на одинаковом наборе примерно из 1 млн товаров.
- После внедрения выполнено сравнение PostgreSQL и Elasticsearch на одинаковом оборудовании, данных и профиле нагрузки с фиксацией RPS, p50, p95, p99, error rate, CPU, RAM и размера индекса.
- Для согласованного набора тяжёлых поисковых сценариев Elasticsearch показывает измеримое улучшение p95 и снижает database load без ухудшения корректности результатов; конкретный целевой порог фиксируется вместе с baseline до начала оптимизации запросов.
- Результаты поиска, фильтрации, подсчётов и агрегаций проверены на функциональную эквивалентность с каноническими данными PostgreSQL в пределах заявленного окна consistency.
- Существующая функциональность каталога и интеграции с Cart/Order Service не нарушены.
- Подготовлена эксплуатационная документация: первоначальное заполнение, инкрементальная синхронизация, reindex, alias switch, rollback, диагностика lag и восстановление после сбоя.

### Технический подход

- Зафиксировать набор репрезентативных запросов: поиск по тексту, одиночные и комбинированные фильтры, диапазоны, сортировки, фасеты, точный `COUNT` и тяжёлые агрегации.
- Снять baseline этих запросов на текущей PostgreSQL-реализации и сохранить параметры оборудования, объём данных, профиль нагрузки и распределение значений.
- Спроектировать поисковый документ вокруг read-сценариев, не копируя реляционную модель один к одному.
- Явно разделить analyzed text fields для поиска и keyword/numeric/date fields для фильтрации, сортировки и агрегаций.
- Версионировать mapping, индекс и формат событий, чтобы изменения схемы выполнялись через переиндексацию, а не через небезопасные изменения существующего индекса.
- Реализовать bulk-индексацию пакетами с ограничением нагрузки, checkpoint/progress tracking и повторным запуском с безопасной точки.
- Использовать устойчивую публикацию изменений после успешной транзакции PostgreSQL; предпочтительный вариант — outbox/event pipeline без синхронной dual write.
- Сделать обработчики индексации идемпотентными и устойчивыми к повторной доставке и изменению порядка событий.
- Использовать alias для чтения и переключать его только после проверки полноты и корректности нового индекса.
- Ввести внутренние DTO/value objects для нормализованного поискового запроса, разрешённых фильтров и выбранного пресета; преобразование в Elasticsearch DSL оставить внутри infrastructure-адаптера.
- Определить каталог доступных фильтров: публичное имя, тип, поддерживаемые операторы, mapping-поле, правила нормализации и допустимые значения.
- Хранить системные пресеты как версионируемую конфигурацию приложения или отдельную управляемую сущность после обоснования жизненного цикла; не хранить в preset произвольный клиентский DSL.
- Зафиксировать приоритет параметров: базовый preset формирует исходный запрос, а разрешённые явные параметры могут уточнять его только по документированным правилам.
- Проверять корректность read-модели выборочной и автоматизированной сверкой Elasticsearch с PostgreSQL.
- Добавить dashboards и alerts для indexing lag, rejected operations, failed events, cluster/index health, search latency и reindex progress.
- Проводить оптимизацию mapping и запросов по результатам profiler/monitoring и нагрузочных экспериментов, фиксируя влияние каждого существенного изменения.

### Как тестировать

- Подготовить воспроизводимый dataset примерно из 1 млн товаров с реалистичным распределением категорий, атрибутов, цен и поискового текста.
- Выполнить baseline-тесты текущих PostgreSQL-запросов после прогрева окружения.
- Запустить первичную индексацию и проверить количество документов, ошибки, длительность, нагрузку и возможность безопасного повторного запуска.
- Сравнить выборку документов Elasticsearch с PostgreSQL, включая связанные и денормализованные поля.
- Проверить create, update и delete товара, повторную доставку одного события и доставку событий с задержкой.
- Измерить и проверить заявленное окно eventual consistency.
- Искусственно вызвать временную недоступность Elasticsearch и проверить retry, error handling, monitoring и последующее восстановление синхронизации.
- Выполнить reindex в новую версию, проверить полноту данных, переключить alias и проверить rollback на предыдущий индекс.
- Покрыть контракт фильтрации unit- и integration-тестами для каждого типа оператора, комбинаций, некорректных полей и лимитов.
- Проверить применение каждого системного пресета, правила объединения с явными фильтрами и обратную совместимость публичного ответа.
- Сверить результаты поиска, фильтров, `COUNT`, фасетов и агрегаций с контрольными PostgreSQL-запросами.
- Выполнить одинаковые нагрузочные сценарии для PostgreSQL baseline и Elasticsearch read-модели после прогрева.
- Сравнить RPS, p50, p95, p99, error rate, CPU, RAM, PostgreSQL load, Elasticsearch resource usage и размер индекса.
- Запустить существующие automated и smoke tests каталога, API Gateway и зависимых Cart/Order сценариев.

### Примечания

- Task 10 является отдельным продолжением Task 9 и закрывает зафиксированный там PostgreSQL bottleneck каталога.
- Персональные сохранённые фильтры пользователей не входят в базовый scope. Под пресетами понимаются системные именованные конфигурации типовых каталоговых запросов.
- Рекомендации, персонализация, autocomplete, typo tolerance, semantic/vector search и сложное ранжирование не входят в обязательный scope и требуют отдельных критериев.
- Elasticsearch не должен становиться источником данных для транзакционных проверок остатков, цен в заказе или других операций, где требуются канонические данные.
- Необходимо заранее определить ресурсные лимиты и политику хранения индексов, чтобы ускорение чтения не привело к неконтролируемому расходу RAM и disk.
- Конкретные mapping, analyzers, библиотека клиента, transport и механизм доставки событий выбираются во время реализации после проверки совместимости с текущим стеком.

## EN

### Status

**Planned.**

### Title

Elasticsearch catalog read model for search, filtering, aggregations, and presets.

### Task Description

Introduce a specialized Elasticsearch read model for the catalog read operations that remain the main performance limitation after Task 9: full-text search, complex filtering, exact `COUNT`, facets, and aggregations over a catalog of approximately one million products.

PostgreSQL must remain the only source of truth and the write store. Elasticsearch must contain a derived denormalized representation of products and related search structures optimized for reads. Catalog queries must move to Elasticsearch only within an explicitly defined read contract and with controlled eventual consistency.

The task must also establish a stable public filtering contract: define allowed fields and operators, support condition combinations, and add system-defined named presets—predefined filter, sorting, and search configurations for common catalog scenarios.

### Goal

Remove heavy catalog `COUNT`, filtering, and aggregation workloads from PostgreSQL, provide fast and predictable search over a large catalog, and establish an extensible filter and preset model without changing PostgreSQL as the source of truth.

### Architecture Context

Task 9 demonstrated that after optimizing the PHP runtime, caches, and the main pagination path, heavy PostgreSQL queries for `COUNT`, filtering, and aggregations remain the primary catalog bottleneck. With approximately one million products, the simple listing path has latency around 200 ms, but exact counts, filter combinations, and aggregate construction continue to place significant load on the database and scale poorly as search complexity grows.

The target architecture separates responsibilities:

- PostgreSQL stores canonical products, relations, and transactional data;
- Elasticsearch stores a versioned, denormalized catalog read model;
- Catalog Service owns a unified search contract and does not expose Elasticsearch DSL to clients;
- data changes are delivered to the index asynchronously with retries, error handling, and replay capability;
- initial loading and full reindexing are separate from incremental synchronization;
- index versions are switched through an alias without stopping reads;
- synchronization delay is allowed only within an explicitly defined and measurable eventual-consistency window.

Synchronous dual writes to PostgreSQL and Elasticsearch must not be used as the consistency mechanism. The guaranteed change-publication method—for example, a transactional outbox followed by delivery through the existing queue—must be confirmed during integration design.

In this task, Elasticsearch is a search read model and a store for derived search structures, not a PostgreSQL replacement or a general-purpose application cache. Index documents may include normalized filter values and data for sorting, facets, and aggregations when their origin, version, and update rules are unambiguous.

### Acceptance Criteria

- Elasticsearch is added as a separate infrastructure component for Catalog Service and is available in the required project environments.
- PostgreSQL remains the source of truth; creating or updating a product does not depend on a synchronous Elasticsearch write.
- Versioned index mappings and settings are defined, including field types, analyzers, normalizers, and fields for sorting, filtering, facets, and aggregations.
- Idempotent initial indexing of approximately one million products is implemented with progress tracking, error handling, and safe restart support.
- Reliable incremental synchronization handles product creation, updates, and deletion without irreversible event loss.
- Change delivery includes retry, dead-letter/error handling, observability, and replay capability.
- Full reindexing into a new versioned index is implemented with an atomic alias switch and rollback capability.
- Catalog Service uses the read model for full-text search, filtering, sorting, pagination, exact result counts, facets, and required aggregations.
- The public API does not accept arbitrary Elasticsearch DSL; allowed fields, operators, sorting options, and limits are controlled by the application.
- Filters support at least exact match, multi-select, ranges, value existence, and safe combinations for allowed fields.
- System-defined named filter presets are added with a versioned schema, validation, and unambiguous merging rules for explicit request parameters.
- The expected eventual-consistency window is defined, and a metric reports the delay between a PostgreSQL change and its availability in Elasticsearch.
- Controlled behavior is defined for Elasticsearch unavailability and must not silently return incorrect results.
- Elasticsearch queries, errors, latency, hit count, index state, synchronization lag, and reindex duration are available in monitoring.
- A reproducible PostgreSQL baseline is recorded for search, heavy filters, `COUNT`, facets, and aggregations over the same dataset of approximately one million products.
- PostgreSQL and Elasticsearch are compared on identical hardware, data, and load profiles, recording RPS, p50, p95, p99, error rate, CPU, RAM, and index size.
- For the agreed heavy search scenarios, Elasticsearch provides a measurable p95 improvement and reduces database load without reducing result correctness; the concrete target threshold is recorded with the baseline before query optimization starts.
- Search, filtering, counts, and aggregation results are verified against canonical PostgreSQL data within the declared consistency window.
- Existing catalog functionality and Cart/Order Service integrations remain intact.
- Operational documentation covers initial indexing, incremental synchronization, reindexing, alias switching, rollback, lag diagnosis, and failure recovery.

### Technical Approach

- Define a representative query set covering text search, single and combined filters, ranges, sorting, facets, exact `COUNT`, and heavy aggregations.
- Measure those queries against the current PostgreSQL implementation and preserve hardware parameters, dataset size, load profile, and value distribution.
- Design search documents around read scenarios instead of mirroring the relational model one-to-one.
- Separate analyzed text fields for search from keyword, numeric, and date fields used for filtering, sorting, and aggregations.
- Version mappings, indices, and event formats so schema changes use reindexing instead of unsafe mutations of an existing index.
- Implement bounded bulk indexing with checkpoints, progress tracking, and restart from a safe point.
- Publish changes reliably after a successful PostgreSQL transaction; the preferred direction is an outbox/event pipeline without synchronous dual writes.
- Make indexing handlers idempotent and resilient to duplicate or out-of-order delivery.
- Use an alias for reads and switch it only after the new index passes completeness and correctness checks.
- Introduce internal DTOs/value objects for normalized search queries, allowed filters, and selected presets; keep Elasticsearch DSL conversion inside the infrastructure adapter.
- Define a filter catalog containing the public name, type, supported operators, mapped index field, normalization rules, and allowed values.
- Store system presets as versioned application configuration or as a separately managed entity after its lifecycle is justified; never store arbitrary client DSL in a preset.
- Define parameter precedence: a base preset creates the initial query, while allowed explicit parameters may refine it only according to documented rules.
- Verify read-model correctness through sampled and automated comparisons between Elasticsearch and PostgreSQL.
- Add dashboards and alerts for indexing lag, rejected operations, failed events, cluster/index health, search latency, and reindex progress.
- Optimize mappings and queries based on profiling, monitoring, and load experiments, recording the effect of every substantial change.

### How To Test

- Prepare a reproducible dataset of approximately one million products with realistic category, attribute, price, and search-text distributions.
- Run baseline tests for current PostgreSQL queries after warming up the environment.
- Run initial indexing and verify document count, failures, duration, load, and safe restart behavior.
- Compare sampled Elasticsearch documents with PostgreSQL, including related and denormalized fields.
- Test product creation, update, and deletion, duplicate event delivery, and delayed event delivery.
- Measure and verify the declared eventual-consistency window.
- Simulate temporary Elasticsearch unavailability and verify retries, error handling, monitoring, and subsequent synchronization recovery.
- Reindex into a new version, verify completeness, switch the alias, and test rollback to the previous index.
- Cover the filtering contract with unit and integration tests for every operator type, combinations, invalid fields, and limits.
- Test every system preset, explicit-filter merging rules, and public-response backward compatibility.
- Compare search, filter, `COUNT`, facet, and aggregation results with control PostgreSQL queries.
- Run identical load scenarios against the PostgreSQL baseline and the Elasticsearch read model after warm-up.
- Compare RPS, p50, p95, p99, error rate, CPU, RAM, PostgreSQL load, Elasticsearch resource usage, and index size.
- Run existing automated and smoke tests for Catalog Service, the API Gateway, and dependent Cart/Order scenarios.

### Notes

- Task 10 is a separate continuation of Task 9 and addresses the PostgreSQL catalog bottleneck recorded there.
- User-saved personal filters are outside the base scope. Presets mean system-defined named configurations for common catalog queries.
- Recommendations, personalization, autocomplete, typo tolerance, semantic/vector search, and advanced ranking are outside the required scope and need separate acceptance criteria.
- Elasticsearch must not become the data source for transactional stock checks, order prices, or other operations requiring canonical data.
- Resource limits and index-retention policies must be defined in advance so faster reads do not cause uncontrolled RAM or disk usage.
- Concrete mappings, analyzers, client library, transport, and event-delivery mechanism are selected during implementation after compatibility with the current stack is verified.
