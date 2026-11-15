# Catalog Fixture Photographs

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [English](#english)
  - [Library and provenance](#library-and-provenance)
  - [Local files and preparation](#local-files-and-preparation)
  - [Storage and fixture workflow](#storage-and-fixture-workflow)
  - [Configuration and environment isolation](#configuration-and-environment-isolation)
  - [Catalog API](#catalog-api)
  - [Verification](#verification)
- [Русский](#%D1%80%D1%83%D1%81%D1%81%D0%BA%D0%B8%D0%B9)
  - [Библиотека и источники](#%D0%B1%D0%B8%D0%B1%D0%BB%D0%B8%D0%BE%D1%82%D0%B5%D0%BA%D0%B0-%D0%B8-%D0%B8%D1%81%D1%82%D0%BE%D1%87%D0%BD%D0%B8%D0%BA%D0%B8)
  - [Подготовка файлов](#%D0%BF%D0%BE%D0%B4%D0%B3%D0%BE%D1%82%D0%BE%D0%B2%D0%BA%D0%B0-%D1%84%D0%B0%D0%B9%D0%BB%D0%BE%D0%B2)
  - [Загрузка и fixtures](#%D0%B7%D0%B0%D0%B3%D1%80%D1%83%D0%B7%D0%BA%D0%B0-%D0%B8-fixtures)
  - [Окружения и API](#%D0%BE%D0%BA%D1%80%D1%83%D0%B6%D0%B5%D0%BD%D0%B8%D1%8F-%D0%B8-api)

<!-- END doctoc -->

## English

### Library and provenance

The fixture library contains 160 downloaded photographs (143 Unsplash, 17 Wikimedia Commons) and 480 WebP derivatives covering all 70 fixture leaf categories. [Browse the photograph library](fixture-media-preview.html), including sources and credits, in light or dark mode. `fixtures/media/sources.json` records the selection, author, original download URL, source page, license URL, retrieval date and SHA-256 of each source. `manifest.json` adds the derived object keys and checksums. Category coverage can be checked against `AppFixtures::mediaCategorySlugs()`.

Only photographs with recorded free-use licenses are included. Preserve attribution, source and license information when distributing the library. CC BY/CC BY-SA derivatives retain the source license; the manifests record resizing, padding and conversion as modifications. Unsplash photographs use the Unsplash License. The API includes credit fields so the storefront can display them; consumers of CC-licensed photographs must preserve the required attribution and license links. Do not assume that an image-search result alone establishes its license.

### Local files and preparation

```text
catalog-service/fixtures/media/
  sources.json
  manifest.json
  sources/<category>/<asset>/<source-sha256>.{jpg,png}
  variants/<asset>/<version>/{thumb,card,main}.webp
```

Prepare the committed sources without external network access:

```bash
npm run catalog:media:prepare
```

Restore missing sources only from their pinned manifest URLs, checking their recorded checksums:

```bash
npm run catalog:media:prepare -- --download
```

The script uses the root npm dependency `sharp`. It auto-orients photographs, fits them into a square neutral canvas, strips embedded metadata and writes WebP at 320, 640 and 1600 px. It does not crop away the product. Review contact sheets before accepting new photographs; query matches do not guarantee a suitable product image. A source must have a longest dimension of at least 1600 px. Source and variant bytes together determine the immutable derivative version key. Source objects use their source-content hash, so changing the encoder does not duplicate an unchanged original. A different encoder or preparation setting may produce a new derivative version.

Previous manifest entries are retained in `retiredAssets`; they remain resolvable and are excluded from new fixture selection. Keep their local files and S3 objects while products or snapshots can reference them. Preparation and import never delete old versions.

### Storage and fixture workflow

`App\DataFixtures\Media\FixtureMediaLibrary` owns the fixture manifest, category lookup and deterministic selection. `FixtureMediaImporter` coordinates local verification and uploads only. It never creates shared media rows. The runtime `App\Media\Infrastructure\CatalogMediaStorage` only knows S3 keys and files; it has no fixture dependency. Categories are data, not separate strategy classes.

`App\Search\Product\Infrastructure\CatalogResponseFactory` builds product and section response DTOs for both readers and the section controller. ORM readers use the `picture` associations already loaded by fetch joins; Elasticsearch readers use the embedded `picture` and `sections[].picture` metadata. `App\Media\Infrastructure\CatalogPictureResponseFactory` constructs image responses and receives `CATALOG_MEDIA_PUBLIC_BASE_URL` through constructor injection, with the environment binding kept in `config/services.yaml`. Readers, controllers and DTOs do not receive or forward the URL setting. DTOs carry the response data; mapping performs no additional SQL or S3 calls.

`doctrine:fixtures:load` clears the database, including `catalog_media`, then loads `CatalogMediaFixtures`:

1. Checks that all 70 leaf categories have photographs.
2. Verifies all local source and derivative checksums.
3. Creates the missing derivative bucket with its default private access.
4. Uploads only missing WebP derivatives and verifies the SHA-256 metadata of existing objects. Originals remain on disk and are never uploaded to MinIO.
5. Loads `AppFixtures`, which creates a separate `CatalogMedia` row for each product and section.
6. Loads `ProductSnapshotFixtures`, which copies metadata into a new row for each snapshot product without copying files. The existing `OrderedFixtureInterface` mechanism preserves this order: media 5, products 10, snapshots 20. Loading only the `product_snapshots` group retains its existing behavior.

There is no Console listener, automatic purge exclusion, or preliminary import in the Compose/Kubernetes fixture scripts. These scripts use the same native fixtures workflow as direct console invocations. The database registry is rebuilt on each normal fixture load; MinIO objects remain and are reused without duplicate uploads. A failure can occur after database cleanup has started, so fixture loading must be treated as a destructive operation. Completed fixtures in other services are not rolled back. Existing product generation, prices, stock and reindexing remain unchanged.

With `--dry-run`, `CatalogMediaFixtures` checks coverage and local files without initializing the importer, accessing S3 or writing media metadata. It reads the CLI flag through an injected `ArgvInput`; programmatic callers should supply their own `InputInterface`. Doctrine executes the other selected fixtures inside a database transaction and rolls it back.

Apply the Doctrine migration before the first metadata import:

```bash
docker compose exec -T catalog-cli php bin/console doctrine:migrations:migrate --no-interaction
```

The schema changes are consolidated into `Version20261114173605`, generated with Doctrine `migrations:diff --filter-expression='~^(catalog_media|product|catalog_sections)$~'` against the schema before the media feature. It creates `catalog_media` directly with a generated `BIGINT` primary key and adds unique owner foreign keys. The `picture` associations use Doctrine’s default `picture_id` column name: a nullable `BIGINT` referencing `catalog_media.id`, distinct from the string file key `catalog_media.picture_id`. Rollback must remove the owner foreign keys before dropping `catalog_media`. The migration targets disposable test data: legacy owner `picture_id` columns are dropped without backfill. Rebuild data with normal fixtures after migration; rollback does not restore legacy image assignments.

An explicit file-only import is also available; repeating it creates neither media rows nor duplicate objects:

```bash
docker compose exec -T catalog-cli php bin/console catalog:media:import
```

Use this command in the already selected Compose environment. Do not run fixtures just to verify image import: fixtures clear the database and generate the configured large dataset.

One source is kept on disk and three WebP derivatives are stored in MinIO per unique photograph, independently of product count. Each owner stores a unique `picture_id` referencing its own media row with a generated `BIGINT id`. File keys, including `catalog_media.picture_id`, are deliberately nonunique; `getPictureId()` remains an internal main-object key accessor, including for the unchanged gRPC snapshot contract. Snapshots own independent metadata rows with the same immutable file keys. Replacing or removing one owner’s metadata never deletes shared MinIO objects. Object garbage collection is outside this workflow and must check all owners and snapshots before deleting files. Both ORM and bulk fixtures use the same library and pick a photograph together with its product-type noun. Existing Faker calls are still consumed in their original order, including the former UUID choice. The existing assignment of one to three categories is preserved; the first category determines the image.

### Configuration and environment isolation

| Setting | Purpose |
| --- | --- |
| `MINIO_ENDPOINT` | Existing shared internal endpoint; passed through Compose |
| `MINIO_REGION` | S3 region, default `us-east-1` |
| `MINIO_ROOT_USER`, `MINIO_ROOT_PASSWORD` | Existing credentials from the root `.env.local`; never commit their values |
| `CATALOG_MEDIA_BUCKET` | Private derivative bucket, default `catalog-media` |
| `CATALOG_MEDIA_PUBLIC_BASE_URL` | Browser-accessible Gateway route; default `http://localhost:8001/api/v1/catalog/media` |

The existing Compose projects, volumes and Kubernetes clusters isolate dev, prod and load-test MinIO instances. Each environment imports its own library and must not depend on another environment. The Kubernetes config/secret generator forwards these settings to Catalog. Set the public base URL for the actual browser-facing Gateway before generating Kubernetes configuration. CLI, web and gRPC images use the same catalog sources; rebuild/redeploy images after adding or replacing library files. Already running containers need recreation to adopt a rebuilt PHP runtime or changed environment.

No public bucket policy is required. Existing news-export storage remains separate. The same MinIO credentials are reused; new real credentials must also stay exclusively in the root `.env.local`.

### Catalog API

Catalog product list/search/detail and category REST responses expose only nullable `picture` (without `pictureId`) with `thumbnailUrl`, `cardUrl`, `fullUrl`, `alt`, `creator`, `sourceUrl`, `license`, `licenseUrl` and `attribution`. Product rows reference media through a nullable, unidirectional ORM `OneToOne` with a unique `picture_id`; sections use the same mapping. No inverse media or Product-to-CatalogElements association is added. Elasticsearch schema v3 stores `picture` and `sections[].picture` as objects with `enabled: false`: variant keys and alt/credit/license metadata remain only in `_source`, without `picture_id`, media IDs, checksums or environment URLs. The separate `catalog_media` table stores three derivative keys/checksums, alt text, author, source, license and attribution. Each owner has its own row even when keys match. Missing media returns `picture: null` for the storefront fallback. Full and incremental indexing share the document builder and existing three bounded ORM queries with media fetch joins. Replacing or removing a product/section picture queues affected documents through the outbox. Media metadata is changed by replacing its row. Moving to v3 requires a full rebuild from PostgreSQL and an alias switch; Elasticsearch `_reindex` cannot supply the new metadata. PostgreSQL OneToOne foreign keys and gRPC snapshots remain unchanged.

The Gateway forwards `/api/v1/catalog/media/` to Catalog `/api/catalog/media/`. Only registered `thumb.webp`, `card.webp` and `main.webp` derivatives are served; the HTTP path and its restrictions are preserved. The endpoint streams private S3 objects, returns `image/webp`, supports GET/HEAD, ETag/304 and immutable caching. It cannot serve originals or arbitrary S3 paths. Elasticsearch-backed Catalog JSON is built entirely from `_source` without opening a database connection. ORM product and section JSON uses the existing fetch joins and performs no separate metadata queries. Embedded metadata belongs to each owner, so shared file keys never merge independent alt text or credits. Doctrine product, section and snapshot read paths fetch join media without global eager loading; pagination remains unchanged. File delivery selects at most one row through the nonunique file-key index, rather than loading every owner. Bulk product and snapshot fixtures insert metadata in bounded batches within their existing transactions. Response factories never query the database. An absent picture is stored and returned as null, with no SQL fallback. URL construction performs no S3 requests. Image GET reads S3; HEAD checks object existence without downloading its body; a matching ETag returns 304 without S3 access. Runtime JSON and image endpoints never load `sources.json`, `manifest.json` or local photographs. Removing fixture files after import does not affect the API.

### Verification

```bash
docker compose run --rm --no-deps -e APP_ENV=test -e SYMFONY_DEPRECATIONS_HELPER=weak catalog-cli vendor/bin/simple-phpunit -c phpunit.dist.xml tests/Unit/Media
```

Tests cover shared category selection, URL/credit contracts, missing-category rejection, checksum failure before storage access, WebP-only uploads with originals retained on disk, native fixture ordering and isolated snapshot-group loading, fetch-joined pictures in Doctrine/section reads and embedded picture metadata in Elasticsearch reads without opening a database connection, runtime operation without a manifest, and repeat imports without duplicate uploads. PostgreSQL integration tests verify independent owner rows, duplicate `picture_id` rejection, shared file keys, snapshot isolation, idempotent snapshot fixtures, ORM/bulk product fixtures and constant media query counts. Integration tests rebuild the isolated `_test` database schema. Run the full Catalog suite with `docker compose run --rm --no-deps -e APP_ENV=test -e SYMFONY_DEPRECATIONS_HELPER=weak catalog-cli vendor/bin/simple-phpunit -c phpunit.dist.xml`. Verify real MinIO import separately without loading database fixtures. After changing Gateway routes, regenerate Gateway/OpenAPI and README endpoint tables with the existing project scripts.

## Русский

### Библиотека и источники

Библиотека содержит 160 готовых фотографий (143 Unsplash, 17 Wikimedia Commons) и 480 WebP-версий для всех 70 конечных категорий fixtures. [Просмотр библиотеки](fixture-media-preview.html) с авторами и лицензиями доступен в светлой и тёмной темах. Нейросетевая генерация не используется. В `fixtures/media/sources.json` сохранены автор, страница фотографии, URL скачивания, лицензия и ссылка на неё, дата получения, категория, тип товара и SHA-256. В `manifest.json` добавлены ключи и контрольные суммы производных файлов.

При распространении сохраняйте сведения об авторстве и лицензиях. Для CC BY/CC BY-SA производные версии сохраняют лицензию оригинала; изменение размера, поля и WebP-конвертация отмечены как обработка. API возвращает сведения для атрибуции: витрина должна показывать обязательные подписи и ссылки. Фотографии Unsplash используются по Unsplash License.

### Подготовка файлов

Исходники находятся в `sources/<category>/<asset>/<source-sha256>.jpg`, производные — в `variants/<asset>/<version>/`. Подготовка без сети: `npm run catalog:media:prepare`. Восстановление отсутствующих исходников по закреплённым URL с проверкой хешей: `npm run catalog:media:prepare -- --download`.

Используется установленный в корне npm-пакет `sharp`: коррекция ориентации, нейтральный квадратный фон, удаление встроенных метаданных, WebP 320/640/1600 px без обрезания товара. Исходник должен иметь не менее 1600 px по длинной стороне. Каждая фотография проходит визуальную проверку. Старые версии сохраняются в `retiredAssets` и остаются доступны для существующих товаров и снимков, но не выбираются для новых fixtures.

### Загрузка и fixtures

`doctrine:fixtures:load` очищает БД, включая `catalog_media`, затем запускает `CatalogMediaFixtures`: проверяет покрытие 70 конечных категорий и хеши файлов, создаёт отсутствующий приватный bucket для WebP, загружает отсутствующие WebP-версии и сверяет хеши существующих. Исходники остаются на диске и в MinIO не загружаются. Importer загружает только файлы; отдельные записи медиа создают fixtures владельцев. Существующий механизм `OrderedFixtureInterface` задаёт порядок: `CatalogMediaFixtures` (5) → `AppFixtures` (10) → `ProductSnapshotFixtures` (20). Отдельный запуск группы `product_snapshots` сохраняет прежнее поведение.

Console listener, автоматическое исключение из purge и предварительный импорт в Compose/Kubernetes-скриптах удалены. Скрипты используют тот же штатный механизм fixtures, что и прямой вызов команды. Реестр БД наполняется заново, а файлы MinIO сохраняются и переиспользуются без повторной загрузки. Ошибка может возникнуть уже после начала очистки БД; запуск fixtures является операцией с удалением данных. Завершённые fixtures других сервисов не откатываются. Генерация товаров, цены, остатки и переиндексация не изменены.

При `--dry-run` медиафикстура проверяет покрытие и локальные файлы, не создавая importer и не обращаясь к S3 или записи метаданных. Флаг командной строки читается через внедрённый `ArgvInput`; при программном вызове нужно передать соответствующий `InputInterface`. Остальные выбранные fixtures Doctrine выполняет в транзакции с последующим откатом.

Перед наполнением нужно применить миграции: `docker compose exec -T catalog-cli php bin/console doctrine:migrations:migrate --no-interaction`.

Отдельный импорт файлов без очистки БД и без создания строк медиа: `docker compose exec -T catalog-cli php bin/console catalog:media:import` в ранее выбранном окружении. Для проверки изображений не нужно запускать миллионные fixtures.

Манифест, выбор фотографий и проверка локальных файлов находятся только в `App\DataFixtures\Media`. `FixtureMediaImporter` загружает общие файлы без копирования и повторных загрузок. У каждой записи товара, раздела и товара-снимка собственная запись `CatalogMedia`: автогенерируемый `BIGINT id`, nullable однонаправленная ORM-связь `OneToOne`, уникальный `picture_id`. Ключи файлов и `catalog_media.picture_id` не уникальны. Снимок копирует метаданные в новую строку, сохраняя общие файловые ключи. Замена или удаление строки медиа не удаляет объекты MinIO. Перед будущим удалением файлов нужно проверять ссылки всех владельцев и снимков. При обычной загрузке fixtures таблица `catalog_media` очищается и наполняется заново; исключение из purge не добавляется.

Изменения объединены в одну миграцию `Version20261114173605`, сгенерированную штатным `doctrine:migrations:diff --filter-expression='~^(catalog_media|product|catalog_sections)$~'` от схемы до добавления медиа. Она сразу создаёт `catalog_media` с автогенерируемым `BIGINT` PRIMARY KEY и добавляет уникальные внешние ключи владельцев. Связи `picture` используют стандартное имя колонки Doctrine `picture_id`: nullable `BIGINT` со ссылкой на `catalog_media.id`, в отличие от строкового ключа файла `catalog_media.picture_id`. При откате внешние ключи должны удаляться до таблицы `catalog_media`. Переноса старых данных нет: старые колонки владельцев `picture_id` удаляются; тестовые данные нужно пересоздать через fixtures. Откат не восстанавливает прежние назначения изображений.

На фотографию сохраняется один исходник на диске и три WebP-версии в MinIO. Товары переиспользуют ключ `main.webp` во внутреннем строковом поле `catalog_media.picture_id`. ORM- и bulk-ветки выбирают фотографию вместе с типом товара по полному slug основной категории. Прежний порядок вызовов Faker сохранён. Цены, остатки, транзакции, число категорий и порядок переиндексации не меняются.

### Окружения и API

`App\Search\Product\Infrastructure\CatalogResponseFactory` собирает DTO ответов товаров и разделов для обоих readers и контроллера разделов: из ORM-сущностей с загруженными связями либо из Elasticsearch-документов со встроенными метаданными. `App\Media\Infrastructure\CatalogPictureResponseFactory` создаёт ответ изображения; только ей через конструктор внедряется `CATALOG_MEDIA_PUBLIC_BASE_URL`, а env-binding остаётся в `config/services.yaml`. Readers, контроллеры и DTO не получают и не передают настройку URL. DTO содержат данные ответа; преобразование не выполняет дополнительных SQL- или S3-запросов. `App\Media\Infrastructure\CatalogMediaStorage` отвечает за работу с S3 и не зависит от fixtures или манифеста.

Параметры перечислены в английской таблице выше. Один приватный bucket `catalog-media` содержит только готовые WebP-версии; исходники хранятся на диске. Секреты остаются только в корневом `.env.local`. Dev/prod/load-test используют собственные MinIO в существующих изолированных окружениях. Kubernetes-генератор передаёт настройки и credentials в Catalog. До генерации конфигурации задайте `CATALOG_MEDIA_PUBLIC_BASE_URL` с доступным браузеру адресом Gateway; внутренний `http://minio:9000` для этого не подходит.

REST-ответы товаров и категорий содержат только nullable `picture`, без отдельного `pictureId`: URL трёх размеров, описание, автор и сведения о лицензии. Связи OneToOne и числовые FK `picture_id` в PostgreSQL сохраняются. Строковый `catalog_media.picture_id` остаётся внутренним ключом файла; gRPC-снимки пока сохраняют прежний контракт.

Elasticsearch v3 хранит `picture` и `sections[].picture` как объекты с `enabled: false`: ключи вариантов `thumb/card/main`, alt и сведения об источнике и лицензии находятся только в `_source`. Отдельного `picture_id`, ID медиа, контрольных сумм и абсолютных URL в документе нет. `App\Media\Infrastructure\CatalogPictureResponseFactory` формирует URL через `CATALOG_MEDIA_PUBLIC_BASE_URL`; чтение списка и карточки из Elasticsearch не открывает соединение с БД и не обращается к S3. Для отсутствующей картинки хранится и возвращается null, без SQL fallback. Совпадение файловых ключей не объединяет метаданные владельцев.

ORM-списки и карточки используют уже загруженную связь `picture`. Полная и инкрементальная индексация используют общий построитель документов и три ограниченных пакетных ORM-запроса на загрузку товаров, разделов, цен и остатков; медиа загружаются существующими fetch joins без N+1. Замена или удаление `picture` товара либо раздела запускает обновление затронутых документов через outbox. Метаданные `CatalogMedia` изменяются заменой записи. Bulk-fixtures создают медиа внутри прежних транзакций; endpoint файла выбирает одну строку по индексу ключа. Для перехода на v3 нужна полная переиндексация из PostgreSQL с переключением alias, а не Elasticsearch `_reindex`.

Gateway передаёт `/api/v1/catalog/media/` в Catalog. Endpoint выдаёт только зарегистрированные в БД WebP-версии, поддерживает GET/HEAD, ETag/304 и immutable-кеширование. Исходники и произвольные S3-пути недоступны. GET читает файл из S3, HEAD проверяет наличие без скачивания тела; совпадение ETag возвращает 304 без S3-запроса. API не читает fixture-манифест и локальные фотографии: после импорта их отсутствие не влияет на выдачу. Публичная политика bucket не требуется. После добавления файлов в библиотеку пересоберите и разверните образы; запущенные контейнеры не обновляются автоматически.
