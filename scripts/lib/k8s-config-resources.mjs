// Resolve project configuration and compute Helm values or cluster-only Secrets.
import { spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { resolve } from 'node:path';

let namespace;

export function run(command, args, input) {
  const env = command === 'docker'
    ? { ...process.env, COMPOSE_ENV_FILES: '.env,.env.local' }
    : process.env;
  const result = spawnSync(command, args, {
    input,
    encoding: 'utf8',
    maxBuffer: 16 * 1024 * 1024,
    windowsHide: true,
    env,
  });
  if (result.status !== 0) {
    // Compose and kubectl errors can include expanded secret values.
    throw new Error(`${command} ${args[0]} failed (exit ${result.status ?? 'unknown'}). Check CLI credentials and configuration.`);
  }
  return result.stdout;
}

function loadComposeConfig() {
  const compose = JSON.parse(run('docker', ['compose', 'config', '--format', 'json']));
  const env = (service) => compose.services[service]?.environment ?? {};

  return {
    auth: env('symfony-web'),
    catalog: env('catalog-web'),
    cart: env('cart-web'),
    database: env('database'),
    catalogDb: env('catalog-db'),
    cartDb: env('cart-db'),
    rabbitmq: env('rabbitmq'),
    minio: env('minio'),
    elasticsearch: env('elasticsearch'),
    kibana: env('elasticsearch-setup'),
  };
}

// Symfony Dotenv runs inside the matching built image. Compose's resolved
// environment is placed in PHP's process environment before bootEnv(), so it
// keeps precedence over .env, .env.local and environment-specific files.
function loadSymfonyEnvironment(serviceDirectory, image, composeEnvironment) {
  const php = `
    require '/workspace/vendor/autoload.php';
    $incoming = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    foreach ($incoming as $key => $value) {
        if (!is_string($value)) continue;
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = getenv('APP_ENV') ?: 'prod';
    (new Symfony\\Component\\Dotenv\\Dotenv())->bootEnv('/source/.env', 'prod');
    echo json_encode($_SERVER, JSON_THROW_ON_ERROR);
  `;
  const path = resolve(serviceDirectory);
  const stdout = run('docker', [
    'run', '--rm', '-i', '--mount', `type=bind,src=${path},dst=/source,readonly`,
    image, 'php', '-r', php,
  ], JSON.stringify(composeEnvironment));
  return JSON.parse(stdout);
}

export function loadConfiguration(imageProfile) {
  const compose = loadComposeConfig();
  return {
    ...compose,
    auth: loadSymfonyEnvironment('symfony', `symfony-auth:k8s-${imageProfile}`, compose.auth),
    catalog: loadSymfonyEnvironment('catalog-service', `symfony-catalog:k8s-${imageProfile}`, compose.catalog),
    cart: loadSymfonyEnvironment('cart-service', `symfony-cart:k8s-${imageProfile}`, compose.cart),
  };
}

function required(source, key) {
  const value = source[key];
  if (typeof value !== 'string' || value.length === 0) {
    throw new Error(`Required ${key} is missing from the resolved project configuration.`);
  }
  return value;
}

function priorAuthSecret() {
  const raw = run('kubectl', ['-n', namespace, 'get', 'secret', 'auth-config-secret', '--ignore-not-found', '-o', 'json']);
  if (!raw.trim()) return null;
  const data = JSON.parse(raw).data ?? {};
  return data.APP_SECRET ? Buffer.from(data.APP_SECRET, 'base64').toString('utf8') : null;
}

function secretManifest(name, data) {
  return {
    apiVersion: 'v1',
    kind: 'Secret',
    metadata: { name, namespace },
    stringData: data,
    type: 'Opaque',
  };
}

function commonConfig(auth) {
  return {
    PHP_FPM_MAX_CHILDREN: auth.PHP_FPM_MAX_CHILDREN || '10',
    NGINX_WORKER_COUNT: auth.NGINX_WORKER_COUNT || '1',
  };
}

function authConfigData({ auth }, common) {
  return {
    ...common,
    CACHE_DSN: 'redis://redis-symfony:6379/0?persistent=1',
    AUTH_SESSION_DSN: 'redis://redis-symfony:6379/1',
    PROM_METRICS_DSN: 'redis://redis-metrics:6379/0?persistent_connections=true',
    DEFAULT_URI: process.env.K8S_PUBLIC_BASE_URL || 'http://localhost:8001',
    CATALOG_GRPC_DSN: auth.CATALOG_GRPC_DSN || 'catalog-grpc:9001',
    CATALOG_SERVICE_BASE_URL: auth.CATALOG_SERVICE_BASE_URL || 'http://catalog-web:8000',
    CSRF_TRUSTED_ORIGINS: auth.CSRF_TRUSTED_ORIGINS || 'http://localhost:8001',
    CORS_ALLOW_ORIGIN: auth.CORS_ALLOW_ORIGIN || '^https?://(localhost|127\\.0\\.0\\.1)(:[0-9]+)?$',
    MAILER_FROM: auth.MAILER_FROM || 'dev@example.com',
    MINIO_ENDPOINT: auth.MINIO_ENDPOINT || 'http://minio:9000',
    MINIO_REGION: auth.MINIO_REGION || 'us-east-1',
    MINIO_BUCKET: auth.MINIO_BUCKET || 'app',
    TURNSTILE_KEY: auth.TURNSTILE_KEY || '3x00000000000000000000FF',
    CSRF_STATELESS_TOKEN_IDS: auth.CSRF_STATELESS_TOKEN_IDS || 'authenticate,refresh,logout,api_mutation',
    CSRF_COOKIE_NAME: auth.CSRF_COOKIE_NAME || 'csrf-token',
    CSRF_HEADER_NAME: auth.CSRF_HEADER_NAME || 'X-CSRF-Token',
    CSRF_COOKIE_PATH: auth.CSRF_COOKIE_PATH || '/api/v1',
    CSRF_COOKIE_SAMESITE: auth.CSRF_COOKIE_SAMESITE || 'lax',
    APP_LOCALE: auth.APP_LOCALE || 'en',
    JWT_REFRESH_TTL: auth.JWT_REFRESH_TTL || '2592000',
    APP_ADMIN_LOGIN: auth.APP_ADMIN_LOGIN || 'admin@example.com',
    JWT_SECRET_KEY: '/workspace/config/jwt/private.pem',
    JWT_PUBLIC_KEY: '/workspace/config/jwt/public.pem',
  };
}

function authSecret({ auth, minio }) {
  return secretManifest('auth-config-secret', {
    APP_SECRET: auth.APP_SECRET || priorAuthSecret() || randomBytes(32).toString('hex'),
    DATABASE_URL: required(auth, 'DATABASE_URL'),
    MAILER_DSN: auth.MAILER_DSN || 'smtp://mailpit:1025',
    MESSENGER_TRANSPORT_DSN: required(auth, 'MESSENGER_TRANSPORT_DSN'),
    MINIO_ROOT_USER: required(minio, 'MINIO_ROOT_USER'),
    MINIO_ROOT_PASSWORD: required(minio, 'MINIO_ROOT_PASSWORD'),
    TURNSTILE_SECRET: auth.TURNSTILE_SECRET || '',
    APP_ADMIN_PASSWORD: auth.APP_ADMIN_PASSWORD || '',
    LOAD_TEST_USER_PASSWORD: auth.LOAD_TEST_USER_PASSWORD || '',
  });
}

function catalogConfigData({ auth, catalog }, common) {
  return {
    ...common,
    CORS_ALLOW_ORIGIN: catalog.CORS_ALLOW_ORIGIN || auth.CORS_ALLOW_ORIGIN || '^https?://(localhost|127\\.0\\.0\\.1)(:[0-9]+)?$',
    CACHE_DSN: 'redis://redis-catalog:6379/0?persistent=1',
    PROM_METRICS_DSN: 'redis://redis-metrics:6379/1?persistent_connections=true',
    DEFAULT_URI: 'http://catalog-web:8000',
    CATALOG_READ_MODEL: catalog.CATALOG_READ_MODEL || 'doctrine',
    CATALOG_PAGINATION_USE_HAS_NEXT_PAGE: catalog.CATALOG_PAGINATION_USE_HAS_NEXT_PAGE || 'false',
    CATALOG_SECTIONS_CACHE_TTL_SECONDS: catalog.CATALOG_SECTIONS_CACHE_TTL_SECONDS || '600',
    ELASTICSEARCH_USERNAME: catalog.ELASTICSEARCH_USERNAME || 'elastic',
    PRODUCT_SEARCH_INDEX_PREFIX: catalog.PRODUCT_SEARCH_INDEX_PREFIX || 'products',
    PRODUCT_SEARCH_INDEX_ALIAS: catalog.PRODUCT_SEARCH_INDEX_ALIAS || 'products',
    PRODUCT_SEARCH_RESULT_WINDOW: catalog.PRODUCT_SEARCH_RESULT_WINDOW || '10000',
    PRODUCT_SEARCH_BATCH_SIZE: catalog.PRODUCT_SEARCH_BATCH_SIZE || '500',
    PRODUCT_SEARCH_REINDEX_LOCK_ID: catalog.PRODUCT_SEARCH_REINDEX_LOCK_ID || '1633906547',
    PRODUCT_SEARCH_INCREMENTAL_WORKER_PAUSED: '0',
    ROADRUNNER_NUM_WORKERS: catalog.ROADRUNNER_NUM_WORKERS || '1',
    APP_TRACING_ENABLED: catalog.APP_TRACING_ENABLED || '0',
  };
}

function catalogSecret({ catalog }) {
  return secretManifest('catalog-config-secret', {
    APP_SECRET: required(catalog, 'APP_SECRET'),
    DATABASE_URL: required(catalog, 'DATABASE_URL'),
    ELASTICSEARCH_URL: catalog.ELASTICSEARCH_URL || 'http://elasticsearch:9200',
    ELASTICSEARCH_PASSWORD: required(catalog, 'ELASTICSEARCH_PASSWORD'),
    CATALOG_SEARCH_MESSENGER_TRANSPORT_DSN: required(catalog, 'CATALOG_SEARCH_MESSENGER_TRANSPORT_DSN'),
  });
}

function cartConfigData({ auth, cart }, common) {
  return {
    ...common,
    CORS_ALLOW_ORIGIN: cart.CORS_ALLOW_ORIGIN || auth.CORS_ALLOW_ORIGIN || '^https?://(localhost|127\\.0\\.0\\.1)(:[0-9]+)?$',
    CACHE_DSN: 'redis://redis-cart:6379/0?persistent=1',
    PROM_METRICS_DSN: 'redis://redis-metrics:6379/2?persistent_connections=true',
    DEFAULT_URI: 'http://cart-web:8000',
    CATALOG_GRPC_DSN: cart.CATALOG_GRPC_DSN || 'catalog-grpc:9001',
    CATALOG_SERVICE_BASE_URL: cart.CATALOG_SERVICE_BASE_URL || 'http://catalog-web:8000',
    MAIN_SERVICE_BASE_URL: cart.MAIN_SERVICE_BASE_URL || 'http://symfony-web:8000',
    APP_TRACING_ENABLED: cart.APP_TRACING_ENABLED || '0',
  };
}

function cartSecret({ cart }) {
  return secretManifest('cart-config-secret', {
    APP_SECRET: required(cart, 'APP_SECRET'),
    DATABASE_URL: required(cart, 'DATABASE_URL'),
    MESSENGER_TRANSPORT_DSN: cart.MESSENGER_TRANSPORT_DSN || 'doctrine://default?auto_setup=0',
  });
}

function infrastructureSecret({ database, catalogDb, cartDb, rabbitmq, minio, elasticsearch, kibana }) {
  return secretManifest('infra-config-secret', {
    POSTGRES_DB: database.POSTGRES_DB || 'app',
    POSTGRES_USER: database.POSTGRES_USER || 'app',
    POSTGRES_PASSWORD: required(database, 'POSTGRES_PASSWORD'),
    CATALOG_POSTGRES_DB: catalogDb.POSTGRES_DB || 'catalog',
    CATALOG_POSTGRES_USER: catalogDb.POSTGRES_USER || 'catalog',
    CATALOG_POSTGRES_PASSWORD: required(catalogDb, 'POSTGRES_PASSWORD'),
    CART_POSTGRES_DB: cartDb.POSTGRES_DB || 'cart',
    CART_POSTGRES_USER: cartDb.POSTGRES_USER || 'cart',
    CART_POSTGRES_PASSWORD: required(cartDb, 'POSTGRES_PASSWORD'),
    RABBITMQ_DEFAULT_USER: required(rabbitmq, 'RABBITMQ_DEFAULT_USER'),
    RABBITMQ_DEFAULT_PASS: required(rabbitmq, 'RABBITMQ_DEFAULT_PASS'),
    ELASTIC_PASSWORD: required(elasticsearch, 'ELASTIC_PASSWORD'),
    KIBANA_PASSWORD: required(kibana, 'KIBANA_PASSWORD'),
    KIBANA_PASSWORD_JSON: JSON.stringify(required(kibana, 'KIBANA_PASSWORD')),
    MINIO_ROOT_USER: required(minio, 'MINIO_ROOT_USER'),
    MINIO_ROOT_PASSWORD: required(minio, 'MINIO_ROOT_PASSWORD'),
    MINIO_BUCKET: minio.MINIO_BUCKET || 'app',
  });
}

function dbExporterConfigData({ database, catalogDb, cartDb }) {
  return {
    AUTH_DB_URI: `database:5432/${database.POSTGRES_DB || 'app'}?sslmode=disable`,
    CATALOG_DB_URI: `catalog-db:5432/${catalogDb.POSTGRES_DB || 'catalog'}?sslmode=disable`,
    CART_DB_URI: `cart-db:5432/${cartDb.POSTGRES_DB || 'cart'}?sslmode=disable`,
  };
}

export function buildGeneratedValues(config) {
  const common = commonConfig(config.auth);
  return {
    generated: {
      postgresConnectionReserve: required(config.database, 'POSTGRES_CONNECTION_RESERVE'),
      config: {
        auth: authConfigData(config, common),
        catalog: catalogConfigData(config, common),
        cart: cartConfigData(config, common),
        dbExporter: dbExporterConfigData(config),
      },
    },
  };
}

function priorGrafana(key) {
  const raw = run('kubectl', ['-n', 'monitoring', 'get', 'secret', 'grafana-config-secret', '--ignore-not-found', '-o', 'json']);
  if (!raw.trim()) return null;
  const value = JSON.parse(raw).data?.[key];
  return value ? Buffer.from(value, 'base64').toString('utf8') : null;
}

function monitoringSecretManifest(name, data) {
  return {
    ...secretManifest(name, data),
    metadata: { name, namespace: 'monitoring' },
  };
}

function monitoringSecret() {
  return monitoringSecretManifest('grafana-config-secret', {
    'renderer-token': priorGrafana('renderer-token') || randomBytes(32).toString('hex'),
    'admin-password': priorGrafana('admin-password') || randomBytes(24).toString('hex'),
  });
}

export function buildSecrets(config, targetNamespace) {
  namespace = targetNamespace;
  return [
    authSecret(config),
    catalogSecret(config),
    cartSecret(config),
    infrastructureSecret(config),
    monitoringSecret(),
  ];
}
