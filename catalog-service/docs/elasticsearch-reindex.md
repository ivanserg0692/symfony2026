# Elasticsearch Product Catalog Reindex

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

Use the production Docker Compose context from the repository root:

```bash
set -a
. ./.env.compose
set +a
unset COMPOSE_PROJECT_NAME COMPOSE_FILE

docker compose run --rm catalog-cli php bin/console app:elasticsearch:reindex
```

The command exits successfully only when every bulk item succeeded, the target count equals the successful indexing count, and the alias switch completed.

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

Из корня репозитория активируйте production Docker Compose context и выполните:

```bash
set -a
. ./.env.compose
set +a
unset COMPOSE_PROJECT_NAME COMPOSE_FILE

docker compose run --rm catalog-cli php bin/console app:elasticsearch:reindex
```

Команда завершится успешно только если все элементы bulk-запросов проиндексированы, count целевого индекса совпал с числом успешных операций и alias был переключён.

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
