# Kubernetes deployment

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [English](#english)
  - [Runtime and images](#runtime-and-images)
  - [First deployment and subsequent deployments](#first-deployment-and-subsequent-deployments)
  - [Access, maintenance, and scaling](#access-maintenance-and-scaling)
  - [Monitoring](#monitoring)
- [Русский](#%D1%80%D1%83%D1%81%D1%81%D0%BA%D0%B8%D0%B9)
  - [Runtime и образы](#runtime-%D0%B8-%D0%BE%D0%B1%D1%80%D0%B0%D0%B7%D1%8B)
  - [Первое и последующие развёртывания](#%D0%BF%D0%B5%D1%80%D0%B2%D0%BE%D0%B5-%D0%B8-%D0%BF%D0%BE%D1%81%D0%BB%D0%B5%D0%B4%D1%83%D1%8E%D1%89%D0%B8%D0%B5-%D1%80%D0%B0%D0%B7%D0%B2%D1%91%D1%80%D1%82%D1%8B%D0%B2%D0%B0%D0%BD%D0%B8%D1%8F)
  - [Доступ, обслуживание и масштабирование](#%D0%B4%D0%BE%D1%81%D1%82%D1%83%D0%BF-%D0%BE%D0%B1%D1%81%D0%BB%D1%83%D0%B6%D0%B8%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5-%D0%B8-%D0%BC%D0%B0%D1%81%D1%88%D1%82%D0%B0%D0%B1%D0%B8%D1%80%D0%BE%D0%B2%D0%B0%D0%BD%D0%B8%D0%B5)
  - [Мониторинг](#%D0%BC%D0%BE%D0%BD%D0%B8%D1%82%D0%BE%D1%80%D0%B8%D0%BD%D0%B3)

<!-- END doctoc -->

## English

### Runtime and images

The manifests under `kubernetes/` are ordinary Kubernetes resources. They contain no kind node names, kind networking, or host source mounts. The application namespace comes from `K8S_NAMESPACE` in the selected environment; `monitoring` is a separate namespace. A default StorageClass is required for the stateful services. Docker Compose remains an independent workflow with its existing bind mounts.

- Local development (`npm run set:dev` or `npm run set:load-test`): Docker, Docker Compose, kubectl, OpenSSL, and kind. `npm run prerequisites:check` checks them. `npm run docker:k8s:build` builds the PHP base, three image profiles for each of Auth, Catalog, and Cart, plus gateway, Prometheus, Grafana, MinIO, and mc. `npm run k8s:cluster:init` creates/selects the kind cluster before Secret synchronization; `npm run k8s:deploy` also selects it, loads the selected profile images, and deploys the stack.
- Existing k3s cluster (`K8S_RUNTIME=k3s` in the selected environment): Docker, Docker Compose, kubectl, OpenSSL, and k3s CLI on the deployment machine. Check with `npm run prerequisites:check`.
- Other Kubernetes cluster (default `prod` configuration): Docker, Docker Compose, kubectl, and OpenSSL. Check with `npm run prerequisites:check`.

The root `.env` selects `K8S_RUNTIME=kubernetes`, `K8S_NAMESPACE=symfony2026`, and `K8S_IMAGE_PROFILE=prod` by default. The existing `set:dev` and `set:load-test` commands select `local`, their own namespaces, and matching image profiles. Change `K8S_RUNTIME` in the selected environment file to `k3s` for a k3s target. For k3s or another Kubernetes cluster, set `K8S_IMAGE_REGISTRY` to the repository prefix and `K8S_IMAGE_TAG` to an immutable release tag, run `npm run docker:k8s:build`, then manually tag and push the selected Auth, Catalog, and Cart images as `<release-tag>-<K8S_IMAGE_PROFILE>` and the gateway, Prometheus, Grafana, MinIO, and mc images as `<release-tag>`. Run `npm run k8s:cluster:init` before synchronizing Secrets. This checks the existing kubectl context; it does not create a remote cluster. Deploy with `npm run k8s:deploy`. `AUTH_JWT_BOOTSTRAP_IMAGE` is set to the Auth image from that registry. The registry must be reachable by both the deployment machine's Docker daemon (for JWT bootstrap) and cluster nodes. Registry credentials, when needed, are provided through normal Docker login and Kubernetes imagePullSecrets for the target cluster. The MinIO and mc source release tags are pinned in `docker/minio/Dockerfile`; both Compose and Kubernetes use these project-built images.

### First deployment and subsequent deployments

For the first local deployment, activate `npm run set:dev`, then run these commands from the repository root in order:

```bash
# Build the PHP base, all three application profiles, and the shared images.
npm run docker:k8s:build

# Generate reviewable ConfigMap YAML from the current project configuration.
npm run k8s:config:sync

# Verify that the generated ConfigMap YAML matches its sources.
npm run k8s:config:sync -- --check

# Create/select the local kind cluster before sending Secrets to its API server.
npm run k8s:cluster:init

# Send secret values directly to Kubernetes Secrets without writing secret YAML.
npm run k8s:secrets:sync

# Deploy the generated configuration, infrastructure, applications, and monitoring.
npm run k8s:deploy
```

After building the images, run `npm run k8s:config:sync` and review `kubernetes/generated/<K8S_NAMESPACE>/configmaps.yaml` in Git diff. The command resolves Compose environment plus each Symfony service's `.env` hierarchy, writes only non-secret ConfigMaps, and never contacts Kubernetes. `npm run k8s:config:sync -- --check` fails if the generated file is stale. Run `npm run k8s:cluster:init` before `npm run k8s:secrets:sync`; Secret synchronization requires a reachable Kubernetes API server. The cluster command only prepares access to the cluster, while Secret synchronization creates the application and monitoring namespaces when needed and sends secret values directly to Kubernetes Secrets without writing secret YAML. All Kubernetes commands use `K8S_RUNTIME` and `K8S_NAMESPACE` from the same selected environment. Resynchronize ConfigMaps when their sources change; synchronize Secrets for each new cluster and when secret sources change.

`k8s:deploy` applies the previously generated ConfigMaps, requires the application, infrastructure and Grafana Secrets to exist, prepares the `auth-jwt` Secret, deploys persistent infrastructure, runs setup and migration Jobs, then deploys HTTP/gRPC services, workers, exporters, Prometheus and Grafana. It does not regenerate ConfigMaps or synchronize application Secrets. It also performs the first Catalog full reindex once, after the workers are running. The JWT bootstrap reuses a complete local PEM pair or calls the existing `bin/init-jwt` if both files are absent; a half pair is an error. An existing Kubernetes `auth-jwt` Secret is reused. Auth Pods only mount that Secret. All Auth replicas use one APP_SECRET and one Redis session store.

The same deploy command can run again. Completed setup/migration Jobs are recreated and Doctrine migrations are idempotent. PersistentVolumeClaims and JWT keys are not deleted. Explicit secret synchronization preserves a generated Auth APP_SECRET and Grafana credentials from their Kubernetes Secrets. If the cluster already contains application data, plan a separate backup/restore before pointing this deployment at it; Compose volumes are not migrated automatically. Keep the Kubernetes Secrets and PVCs backed up.

Synchronization reads the root `.env` and `.env.local` through Compose and the service-local `.env`, `.env.local`, `.env.<APP_ENV>`, and `.env.<APP_ENV>.local` through Symfony Dotenv inside the selected profile images. Compose process values take precedence. `K8S_IMAGE_PROFILE` selects the Kubernetes PHP images: `dev` sets `APP_ENV=dev`, `APP_DEBUG=1`, and enables Turnstile; `prod` sets `APP_ENV=prod`, `APP_DEBUG=0`, and enables Turnstile; `load-test` uses prod Symfony settings with Turnstile disabled. The Kubernetes dev image keeps the PHP-FPM/Nginx server and includes Composer dev dependencies; the Compose dev server and bind mounts remain unchanged. The commands do not modify source env files. Real credentials remain in the root `.env.local` and Kubernetes Secrets. Update `K8S_PUBLIC_BASE_URL` when the gateway is published under a nonlocal URL. The default local URL is `http://localhost:8001` via port-forward. After changing ConfigMaps or Secrets, restart affected Deployments; existing Pods do not automatically reload environment variables or subPath mounted files.

### Access, maintenance, and scaling

Use `kubectl -n symfony2026 port-forward service/api-gateway 8001:80` for the gateway and `kubectl -n monitoring port-forward service/grafana 3000:3000` for Grafana. Replace `symfony2026` with the selected `K8S_NAMESPACE` when it differs. Prometheus is available through `kubectl -n monitoring port-forward service/prometheus 9090:9090`.

The Catalog outbox and index consumers are independent Deployments. Full reindex is initiated with `npm run k8s:catalog:reindex`; the script takes an API-server lock, records the index worker replica count, scales only that worker to zero, waits for its Pods to stop, runs a Job using the current Catalog image, then restores the worker. The outbox relay stays active; RabbitMQ keeps incremental messages until the index worker drains them. If the operator process is killed and the lock remains, inspect the Job and use `npm run k8s:catalog:reindex -- recover` to stop the Job, restore the recorded worker count, and release the lock. Do not run the Compose reindex script against Kubernetes.

For a manual scale check, run `kubectl -n symfony2026 scale deployment/symfony-web deployment/catalog-web deployment/cart-web --replicas=3`, wait for each rollout, inspect `kubectl -n symfony2026 get pods -l component=http -o wide` and `kubectl -n symfony2026 get endpointslices -l kubernetes.io/service-name=symfony-web` (repeat for Catalog and Cart). Send repeated requests through the gateway and verify that each Pod's `phpfpm_accepted_connections` series increases. Test one Auth login with requests alternating between Auth Pods to validate Redis sessions, and check JWT verification on every Pod. Restore replica counts as needed. k6/HPA checks are a later stage.

### Monitoring

The Compose Prometheus config and Grafana dashboards are unchanged. Kubernetes has its own Prometheus config and Grafana dashboard. PHP-FPM exporter runs beside each HTTP PHP-FPM instance and is discovered by Pod labels; application metrics in shared Redis are scraped once through each service's ClusterIP. PostgreSQL exporters remain separate, Nginx exporter runs beside the gateway, and node exporter is a DaemonSet. kube-state-metrics and cAdvisor feed Pod count, readiness, CPU and RAM panels. Validate the dashboard queries against the actual cluster's labels and cAdvisor availability after first deployment.

Kubernetes Prometheus and Grafana configuration files live beside their Dockerfiles under `docker/prometheus/k8s/` and `docker/grafana/k8s/` and are copied into the respective images. The Prometheus entrypoint fills in the application namespace supplied by the deployment manifest. Changes to these files require rebuilding and redeploying the monitoring images; `k8s:config:sync` only updates application and infrastructure ConfigMaps. Grafana credentials remain in `grafana-config-secret`.

## Русский

### Runtime и образы

Манифесты в `kubernetes/` не зависят от kind, числа node и файлов исходников на host. Namespace приложения берётся из `K8S_NAMESPACE` выбранного окружения; мониторинг живёт в отдельном namespace `monitoring`. Для StatefulSet нужен default StorageClass. Docker Compose остаётся отдельным способом запуска с прежними bind mounts.

- Локально (`npm run set:dev` или `npm run set:load-test`): Docker, Docker Compose, kubectl, OpenSSL и kind. Проверка: `npm run prerequisites:check`. Команда `npm run docker:k8s:build` собирает PHP base, по три профиля образов Auth, Catalog и Cart, а также gateway, Prometheus, Grafana, MinIO и mc. `npm run k8s:cluster:init` создаёт/выбирает kind cluster до синхронизации Secret; `npm run k8s:deploy` также выбирает его, загружает образы выбранного профиля и разворачивает проект.
- В существующем k3s cluster (`K8S_RUNTIME=k3s` в выбранном окружении): те же общие CLI и k3s CLI на машине развёртывания. Проверка: `npm run prerequisites:check`.
- В другом Kubernetes cluster (конфигурация `prod` по умолчанию): общие CLI без kind/k3s. Проверка: `npm run prerequisites:check`.

Корневой `.env` по умолчанию задаёт `K8S_RUNTIME=kubernetes`, `K8S_NAMESPACE=symfony2026` и `K8S_IMAGE_PROFILE=prod`. Существующие `set:dev` и `set:load-test` выбирают `local`, отдельные namespaces и соответствующие профили образов. Для k3s измените `K8S_RUNTIME` выбранного env-файла на `k3s`. Для k3s/обычного Kubernetes задайте `K8S_IMAGE_REGISTRY` (префикс репозитория) и `K8S_IMAGE_TAG` (неизменяемый release tag), запустите `npm run docker:k8s:build`, затем вручную назначьте выбранным образам Auth, Catalog и Cart тег `<release-tag>-<K8S_IMAGE_PROFILE>`, а gateway, Prometheus, Grafana, MinIO и mc — `<release-tag>`, после чего отправьте их в registry. До синхронизации Secret выполните `npm run k8s:cluster:init`. Команда проверяет текущий kubectl context, но не создаёт удалённый кластер. Для развёртывания используйте `npm run k8s:deploy`. Docker на машине запуска должен получить Auth image из registry для JWT bootstrap; nodes тоже должны иметь доступ к registry. При закрытом registry настройте Docker login и Kubernetes imagePullSecrets. Версии исходников MinIO и mc закреплены в `docker/minio/Dockerfile`; Compose и Kubernetes используют эти образы проекта.

### Первое и последующие развёртывания

Для первого локального развёртывания активируйте `npm run set:dev`, затем выполните из корня репозитория по порядку:

```bash
# Собрать PHP base, три профиля приложений и общие образы.
npm run docker:k8s:build

# Создать проверяемый YAML ConfigMap из текущей конфигурации проекта.
npm run k8s:config:sync

# Проверить, что созданный YAML ConfigMap соответствует его источникам.
npm run k8s:config:sync -- --check

# Создать/выбрать локальный kind cluster до отправки Secret в Kubernetes API.
npm run k8s:cluster:init

# Передать секретные значения напрямую в Kubernetes Secret без YAML-файла.
npm run k8s:secrets:sync

# Развернуть конфигурацию, инфраструктуру, приложения и мониторинг.
npm run k8s:deploy
```

После сборки образов запустите `npm run k8s:config:sync` и проверьте `kubernetes/generated/<K8S_NAMESPACE>/configmaps.yaml` через Git diff. Команда учитывает Compose environment и иерархию `.env` каждого Symfony-сервиса, записывает только ConfigMap и не обращается к Kubernetes. `npm run k8s:config:sync -- --check` сообщает об устаревшем файле. Перед `npm run k8s:secrets:sync` запустите `npm run k8s:cluster:init`: для синхронизации Secret нужен доступный Kubernetes API. Команда подготовки обеспечивает доступ к кластеру, а синхронизация Secret при необходимости создаёт namespaces приложения и мониторинга и передаёт значения напрямую в Kubernetes Secret без файла с секретами. Все Kubernetes-команды получают `K8S_RUNTIME` и `K8S_NAMESPACE` из одного выбранного окружения. Повторно синхронизируйте ConfigMap при изменении источников, а Secret — для каждого нового кластера и при изменении секретных значений.

`k8s:deploy` применяет уже подготовленные ConfigMap, проверяет наличие Secret приложения, инфраструктуры и Grafana, готовит `auth-jwt`, запускает инфраструктуру, setup/migration Jobs, HTTP/gRPC, workers, exporters, Prometheus и Grafana. Он не перегенерирует ConfigMap и не синхронизирует Secret приложения. После запуска workers он один раз выполняет первоначальный полный Catalog reindex. JWT bootstrap использует полную существующую пару PEM; если обоих файлов нет, вызывает `bin/init-jwt`; один файл вместо пары — ошибка. Уже созданный Secret `auth-jwt` не перезаписывается. Auth Pod только монтируют этот Secret; у всех реплик общий APP_SECRET и Redis sessions.

Повторный deploy переиспользует JWT, PVC и Kubernetes credentials, а setup/migration Jobs создаёт заново. При явной повторной синхронизации Secret сохраняются сгенерированные APP_SECRET Auth и credentials Grafana. Doctrine migrations остаются идемпотентными. Данные из Compose volumes автоматически не переносятся: для них нужна отдельная процедура backup/restore. Secret и PVC следует резервировать.

Синхронизация читает корневые `.env` и `.env.local` через Compose и сервисные `.env`, `.env.local`, `.env.<APP_ENV>`, `.env.<APP_ENV>.local` через Symfony Dotenv внутри образов выбранного профиля. Значения process environment из Compose имеют приоритет. `K8S_IMAGE_PROFILE` выбирает Kubernetes PHP images: `dev` задаёт `APP_ENV=dev`, `APP_DEBUG=1` и включает Turnstile; `prod` задаёт `APP_ENV=prod`, `APP_DEBUG=0` и включает Turnstile; `load-test` использует prod-настройки Symfony с выключенным Turnstile. Kubernetes dev image сохраняет PHP-FPM/Nginx и содержит dev-зависимости Composer; Compose dev server и bind mounts не меняются. Исходные env-файлы не меняются. Реальные credentials остаются в корневом `.env.local` и Kubernetes Secret. Для внешнего URL gateway задайте `K8S_PUBLIC_BASE_URL`; локально по умолчанию используется `http://localhost:8001` через port-forward. После обновления ConfigMap/Secret перезапускайте соответствующие Deployments: env и subPath mounts уже запущенных Pod автоматически не обновятся.

### Доступ, обслуживание и масштабирование

Gateway: `kubectl -n symfony2026 port-forward service/api-gateway 8001:80`. Grafana: `kubectl -n monitoring port-forward service/grafana 3000:3000`. Prometheus: `kubectl -n monitoring port-forward service/prometheus 9090:9090`. Если выбран другой `K8S_NAMESPACE`, замените `symfony2026`.

Catalog outbox и index consumers — отдельные Deployments. Полный reindex запускается через `npm run k8s:catalog:reindex`: скрипт создаёт блокировку в API server, запоминает число реплик index worker, останавливает именно его, дожидается удаления Pod, выполняет Job на текущем Catalog image и возвращает worker. Outbox остаётся активным, а RabbitMQ сохраняет накопившиеся сообщения до возобновления обработки. После аварийного прерывания проверьте Job и запустите `npm run k8s:catalog:reindex -- recover`, чтобы остановить Job, восстановить число workers и снять блокировку.

Для ручной проверки задайте три реплики через `kubectl -n symfony2026 scale deployment/symfony-web deployment/catalog-web deployment/cart-web --replicas=3`, дождитесь rollout и проверьте Pod и EndpointSlice каждого Service. Отправьте серию запросов через gateway и убедитесь, что `phpfpm_accepted_connections` растёт отдельно у каждого Pod. Проверьте Auth login/session при чередовании Pod и JWT verification на всех Pod. HPA и k6 относятся к следующему этапу.

### Мониторинг

Compose Prometheus и dashboards не меняются. Kubernetes получает отдельный Prometheus config и dashboard. PHP-FPM exporter работает рядом с каждым HTTP PHP-FPM, а Prometheus находит его по Pod labels. Symfony metrics из общего Redis снимаются один раз через Service. PostgreSQL exporters остаются отдельными, Nginx exporter работает рядом с gateway, node exporter — DaemonSet. kube-state-metrics и cAdvisor дают число/готовность Pod, CPU и RAM. После первого запуска нужно сверить запросы dashboard с реальными labels и доступностью cAdvisor в выбранном cluster.

Kubernetes-конфигурации Prometheus и Grafana лежат рядом со своими Dockerfile в `docker/prometheus/k8s/` и `docker/grafana/k8s/` и копируются в images. Entrypoint Prometheus подставляет namespace приложения, переданный через deployment manifest. Для изменения этих файлов нужно пересобрать и повторно развернуть monitoring images; `k8s:config:sync` обновляет только ConfigMap приложения и инфраструктуры. Credentials Grafana остаются в `grafana-config-secret`.
