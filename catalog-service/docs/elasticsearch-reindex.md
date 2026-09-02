# Elasticsearch Product Catalog Reindex

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [English](#english)
  - [Purpose](#purpose)
  - [Configuration](#configuration)
  - [Run](#run)
  - [Verified result](#verified-result)
  - [Verify in Elasticsearch](#verify-in-elasticsearch)
  - [Failure and rollback](#failure-and-rollback)
  - [Concurrent changes and incremental indexing](#concurrent-changes-and-incremental-indexing)
- [Русский](#%D1%80%D1%83%D1%81%D1%81%D0%BA%D0%B8%D0%B9)
  - [Назначение](#%D0%BD%D0%B0%D0%B7%D0%BD%D0%B0%D1%87%D0%B5%D0%BD%D0%B8%D0%B5)
  - [Конфигурация](#%D0%BA%D0%BE%D0%BD%D1%84%D0%B8%D0%B3%D1%83%D1%80%D0%B0%D1%86%D0%B8%D1%8F)
  - [Запуск](#%D0%B7%D0%B0%D0%BF%D1%83%D1%81%D0%BA)
  - [Подтверждённый результат](#%D0%BF%D0%BE%D0%B4%D1%82%D0%B2%D0%B5%D1%80%D0%B6%D0%B4%D1%91%D0%BD%D0%BD%D1%8B%D0%B9-%D1%80%D0%B5%D0%B7%D1%83%D0%BB%D1%8C%D1%82%D0%B0%D1%82)
  - [Проверка в Elasticsearch](#%D0%BF%D1%80%D0%BE%D0%B2%D0%B5%D1%80%D0%BA%D0%B0-%D0%B2-elasticsearch)
  - [Ошибки и rollback](#%D0%BE%D1%88%D0%B8%D0%B1%D0%BA%D0%B8-%D0%B8-rollback)
  - [Конкурентные изменения и incremental indexing](#%D0%BA%D0%BE%D0%BD%D0%BA%D1%83%D1%80%D0%B5%D0%BD%D1%82%D0%BD%D1%8B%D0%B5-%D0%B8%D0%B7%D0%BC%D0%B5%D0%BD%D0%B5%D0%BD%D0%B8%D1%8F-%D0%B8-incremental-indexing)

<!-- END doctoc -->

## English

### Purpose

The Catalog Service rebuilds a derived product search read model from PostgreSQL. PostgreSQL remains the source of truth. Each run creates a new versioned index, bulk-indexes bounded ID-keyset batches, validates the indexed document count, and only then atomically points the `products` alias to the new index.

The old index is retained for rollback and manual cleanup. A failed or partially failed rebuild never changes the alias.

### Configuration

Keep real credentials in the repository-root `.env.local`:

```dotenv
ELASTICSEARCH_USERNAME=elastic
ELASTIC_PASSWORD=replace-with-local-password
KIBANA_PASSWORD=replace-with-local-password
```

The following non-secret settings are configurable through the environment and have project defaults:

```dotenv
ELASTICSEARCH_URL=http://elasticsearch:9200
PRODUCT_SEARCH_INDEX_PREFIX=products
PRODUCT_SEARCH_INDEX_ALIAS=products
PRODUCT_SEARCH_BATCH_SIZE=500
```

### Run

Run the rebuild from the repository root in the currently active Docker Compose context:

```bash
npm run catalog:elasticsearch:reindex
```

The operational script uses a batch size of `100`, a PHP memory limit of `512M`, and Symfony's production environment without debug mode. Override these values when needed:

```bash
PRODUCT_SEARCH_BATCH_SIZE=50 \
PRODUCT_SEARCH_REINDEX_MEMORY_LIMIT=1G \
npm run catalog:elasticsearch:reindex
```

The command exits successfully only when every bulk item succeeded, the target count equals the successful indexing count, and the alias switch completed.

### Verified result

The full rebuild was verified on a catalog containing one million products:

- processed: `1,000,000`;
- indexed: `1,000,000`;
- failed: `0`;
- average rate: approximately `1,792 docs/s`;
- elapsed time: `00:09:18`;
- alias switched: `yes`.

![Elasticsearch full reindex of one million products](images/reindex-result.png)

### Verify in Elasticsearch

```bash
docker compose exec -T elasticsearch sh -lc \
  'curl --fail --silent --show-error --user "elastic:${ELASTIC_PASSWORD}" "http://localhost:9200/_cat/aliases/products?v"'

docker compose exec -T elasticsearch sh -lc \
  'curl --fail --silent --show-error --user "elastic:${ELASTIC_PASSWORD}" "http://localhost:9200/products/_count?pretty"'

docker compose exec -T elasticsearch sh -lc \
  'curl --fail --silent --show-error --user "elastic:${ELASTIC_PASSWORD}" "http://localhost:9200/products/_search?size=1&pretty"'
```

In Kibana Dev Tools at `http://127.0.0.1:5601`, run:

```http
GET /_cat/aliases/products?v
GET /products/_count
GET /products/_search
{
  "size": 1,
  "query": { "match_all": {} }
}
```

### Failure and rollback

Bulk item failures are logged with the catalog element ID and Elasticsearch error. The summary reports processed, indexed, and failed counts. On any failure the old alias remains active and the incomplete versioned index is retained for diagnosis.

To roll back after a successful switch, atomically remove the alias from the current index and add it to the retained previous index using Elasticsearch `_aliases`. Delete old versioned indices only after confirming that rollback is no longer required.

### Concurrent changes and incremental indexing

A product can change after the rebuild has already read it but before the alias switch. If an incremental handler writes only to the current alias, switching to the new index can temporarily restore the older product version.

The incremental phase should capture a monotonic event/version high-water mark before rebuilding, index the PostgreSQL snapshot, replay events newer than that mark into the target index, and switch the alias only after the replay catches up. External versioning or another monotonic product version should reject stale out-of-order writes. The future Messenger handler must reuse `ProductSearchDocumentBuilder` and `ProductSearchIndexGatewayInterface`.

## Русский

### Назначение

Catalog Service восстанавливает производную поисковую read-model товаров из PostgreSQL. PostgreSQL остаётся источником истины. Каждый запуск создаёт новый versioned index, загружает ограниченные batch по keyset `id`, проверяет число документов и только после этого атомарно переключает alias `products`.

Старый индекс сохраняется для rollback и ручной очистки. Неуспешный или частично успешный rebuild никогда не меняет alias.

### Конфигурация

Реальные credentials должны находиться в `.env.local` в корне репозитория:

```dotenv
ELASTICSEARCH_USERNAME=elastic
ELASTIC_PASSWORD=replace-with-local-password
KIBANA_PASSWORD=replace-with-local-password
```

Остальные настройки не являются секретами, передаются через environment и имеют проектные значения по умолчанию:

```dotenv
ELASTICSEARCH_URL=http://elasticsearch:9200
PRODUCT_SEARCH_INDEX_PREFIX=products
PRODUCT_SEARCH_INDEX_ALIAS=products
PRODUCT_SEARCH_BATCH_SIZE=500
```

### Запуск

Запустите rebuild из корня репозитория в текущем активном Docker Compose context:

```bash
npm run catalog:elasticsearch:reindex
```

Эксплуатационный скрипт использует batch size `100`, PHP memory limit `512M` и production-окружение Symfony без debug mode. При необходимости значения можно переопределить:

```bash
PRODUCT_SEARCH_BATCH_SIZE=50 \
PRODUCT_SEARCH_REINDEX_MEMORY_LIMIT=1G \
npm run catalog:elasticsearch:reindex
```

Команда завершится успешно только если все элементы bulk-запросов проиндексированы, count целевого индекса совпал с числом успешных операций и alias был переключён.

### Подтверждённый результат

Полный rebuild проверен на каталоге из одного миллиона товаров:

- обработано: `1 000 000`;
- проиндексировано: `1 000 000`;
- ошибок: `0`;
- средняя скорость: около `1 792 docs/s`;
- время выполнения: `00:09:18`;
- alias переключён: `yes`.

![Полная индексация одного миллиона товаров в Elasticsearch](images/reindex-result.png)

### Проверка в Elasticsearch

```bash
docker compose exec -T elasticsearch sh -lc \
  'curl --fail --silent --show-error --user "elastic:${ELASTIC_PASSWORD}" "http://localhost:9200/_cat/aliases/products?v"'

docker compose exec -T elasticsearch sh -lc \
  'curl --fail --silent --show-error --user "elastic:${ELASTIC_PASSWORD}" "http://localhost:9200/products/_count?pretty"'

docker compose exec -T elasticsearch sh -lc \
  'curl --fail --silent --show-error --user "elastic:${ELASTIC_PASSWORD}" "http://localhost:9200/products/_search?size=1&pretty"'
```

В Kibana Dev Tools по адресу `http://127.0.0.1:5601`:

```http
GET /_cat/aliases/products?v
GET /products/_count
GET /products/_search
{
  "size": 1,
  "query": { "match_all": {} }
}
```

### Ошибки и rollback

Для каждой ошибки bulk-индексации логируются ID элемента каталога и ответ Elasticsearch. Итоговая таблица показывает processed, indexed и failed. При любой ошибке старый alias остаётся активным, а незавершённый versioned index сохраняется для диагностики.

Для rollback после успешного переключения нужно одним запросом `_aliases` снять alias с текущего индекса и назначить сохранённому предыдущему индексу. Старые индексы следует удалять только после окончания периода rollback.

### Конкурентные изменения и incremental indexing

Товар может измениться после того, как rebuild его прочитал, но до переключения alias. Если incremental handler пишет только в текущий alias, после переключения в новом индексе временно окажется более старая версия товара.

На следующем этапе нужно зафиксировать монотонный high-water mark событий/версий перед rebuild, построить snapshot из PostgreSQL, воспроизвести в новый индекс события после этой отметки и переключить alias только после catch-up. External versioning или другая монотонная версия товара должна отклонять устаревшие события, пришедшие не по порядку. Будущий Messenger handler должен повторно использовать `ProductSearchDocumentBuilder` и `ProductSearchIndexGatewayInterface`.
