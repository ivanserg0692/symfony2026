#!/usr/bin/env node

import { existsSync, mkdirSync, readFileSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const WORKLOAD_NAMES = [
  'symfony-web',
  'auth-async-worker',
  'catalog-web',
  'catalog-grpc',
  'catalog-search-outbox-worker',
  'catalog-search-index-worker',
  'cart-web',
];
const DEFAULT_FPM_CHILDREN = 10;
const DEFAULT_GRPC_WORKERS = 1;
const EXTRA_POD_ALLOWANCE = 1;
const CONNECTIONS_PER_WORKER_POD = 2;

/**
 * Parsed YAML scalar or container. The parser intentionally supports only the
 * mapping/scalar subset used by the project values files.
 * @typedef {string | number | boolean | null | YamlValue[] | YamlMapping} YamlValue
 */

/** @typedef {{ [key: string]: YamlValue }} YamlMapping */

/**
 * @typedef {object} PostgresCapacityInputs
 * @property {Record<string, number>} replicas Effective replica count per workload: the greater of replicas and maxReplicas.
 * @property {number} fpmChildren PHP-FPM child processes per Symfony web pod.
 * @property {number} grpcWorkers RoadRunner workers per catalog gRPC pod.
 * @property {number} reserve Connections reserved for non-workload use.
 */

/**
 * @typedef {object} PostgresCapacity
 * @property {number} auth Maximum PostgreSQL connections for the auth database.
 * @property {number} catalog Maximum PostgreSQL connections for the catalog database.
 * @property {number} cart Maximum PostgreSQL connections for the cart database.
 */

/** @param {string} value @param {number} lineNumber @returns {YamlValue | undefined} */
function parseScalar(value, lineNumber) {
  const scalar = value.trim();
  if (scalar === '') return undefined;
  if (scalar === '{}') return {};
  if (scalar === '[]') return [];
  if (scalar.startsWith('"')) {
    try {
      return JSON.parse(scalar);
    } catch {
      throw new Error(`Invalid quoted YAML scalar near line ${lineNumber}.`);
    }
  }
  if (scalar.startsWith("'")) {
    if (!scalar.endsWith("'") || scalar.length < 2) {
      throw new Error(`Invalid quoted YAML scalar near line ${lineNumber}.`);
    }
    return scalar.slice(1, -1).replaceAll("''", "'");
  }
  if (/^-?\d+$/.test(scalar)) return Number(scalar);
  return scalar;
}

// Read the nested mappings used by the project values files. Reject YAML syntax
// outside this subset so changed input cannot silently produce partial values.
/** @param {string} source @returns {YamlMapping} */
export function parseValuesYaml(source) {
  const root = {};
  /** @type {{ indent: number, value: YamlMapping }[]} */
  const parents = [];

  for (const [lineNumber, line] of source.split(/\r?\n/).entries()) {
    if (!line.trim() || line.trimStart().startsWith('#')) continue;
    const match = line.match(/^( *)([^:#][^:]*):(?:\s+(.*))?\s*$/);
    if (!match) {
      throw new Error(`Unsupported YAML syntax near line ${lineNumber + 1}.`);
    }

    const indent = match[1].length;
    const key = match[2].trim();
    const value = parseScalar(match[3] ?? '', lineNumber + 1);
    while (parents.length && parents.at(-1).indent >= indent) parents.pop();
    if (indent > 0 && (!parents.length || parents.at(-1).indent >= indent)) {
      throw new Error(`Unexpected YAML indentation near line ${lineNumber + 1}.`);
    }
    const parent = parents.at(-1)?.value ?? root;
    if (Object.hasOwn(parent, key)) {
      throw new Error(`Duplicate YAML key "${key}" near line ${lineNumber + 1}.`);
    }
    if (value === undefined) {
      parent[key] = {};
      parents.push({ indent, value: parent[key] });
    } else {
      parent[key] = value;
    }
  }
  return root;
}

/** @param {YamlValue} value @param {string} name @param {{ positive?: boolean }} [options] @returns {number} */
function integer(value, name, { positive = true } = {}) {
  const parsed = typeof value === 'number' ? value : Number(value);
  const minimum = positive ? 1 : 0;
  if (!Number.isSafeInteger(parsed) || parsed < minimum) {
    throw new Error(`${name} must be ${positive ? 'a positive' : 'a non-negative'} integer.`);
  }
  return parsed;
}

/** @param {YamlMapping} values @param {string} path @returns {YamlValue} */
function requiredPath(values, path) {
  const result = path.split('.').reduce((value, part) => value?.[part], values);
  if (result === undefined || result === null || result === '') {
    throw new Error(`${path} is required.`);
  }
  return result;
}

/** @param {YamlMapping} base @param {YamlMapping} override @returns {YamlMapping} */
function mergeValues(base, override) {
  const merged = structuredClone(base);
  for (const [key, value] of Object.entries(override)) {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      const baseValue = merged[key] && typeof merged[key] === 'object' && !Array.isArray(merged[key])
        ? merged[key]
        : {};
      merged[key] = mergeValues(baseValue, value);
    } else {
      merged[key] = value;
    }
  }
  return merged;
}

/**
 * Merge Helm and generated application values, then validate the calculation inputs.
 * @param {YamlMapping} chartValues Helm chart values, including maxReplicas.
 * @param {YamlMapping} generatedValues Application values generated for this namespace.
 * @returns {PostgresCapacityInputs}
 */
function readCalculationInputs(chartValues, generatedValues) {
  const values = mergeValues(chartValues, generatedValues);
  const reserve = integer(
    requiredPath(values, 'generated.postgresConnectionReserve'),
    'generated.postgresConnectionReserve',
    { positive: false },
  );
  const fpmValue = values.generated?.config?.auth?.PHP_FPM_MAX_CHILDREN;
  const grpcValue = values.generated?.config?.catalog?.ROADRUNNER_NUM_WORKERS;
  const fpmChildren = integer(
    fpmValue === undefined ? DEFAULT_FPM_CHILDREN : fpmValue,
    'generated.config.auth.PHP_FPM_MAX_CHILDREN',
  );
  const grpcWorkers = integer(
    grpcValue === undefined ? DEFAULT_GRPC_WORKERS : grpcValue,
    'generated.config.catalog.ROADRUNNER_NUM_WORKERS',
  );
  const replicas = Object.fromEntries(WORKLOAD_NAMES.map((name) => {
    const configuredReplicas = integer(requiredPath(values, `replicas.${name}`), `replicas.${name}`);
    const maximumReplicas = integer(requiredPath(values, `maxReplicas.${name}`), `maxReplicas.${name}`);
    return [name, Math.max(configuredReplicas, maximumReplicas)];
  }));

  return { replicas, fpmChildren, grpcWorkers, reserve };
}

/**
 * Calculate each database's connection budget from per-pod workers and maximum replicas.
 * @param {YamlMapping} chartValues Helm chart values, including maxReplicas.
 * @param {YamlMapping} generatedValues Generated application values and connection reserve.
 * @returns {PostgresCapacity}
 */
export function calculatePostgresCapacity(chartValues, generatedValues) {
  const { replicas, fpmChildren, grpcWorkers, reserve } = readCalculationInputs(chartValues, generatedValues);
  const capacity = {
    auth: (replicas['symfony-web'] + EXTRA_POD_ALLOWANCE) * fpmChildren
      + (replicas['auth-async-worker'] + EXTRA_POD_ALLOWANCE) * CONNECTIONS_PER_WORKER_POD
      + reserve,
    catalog: (replicas['catalog-web'] + EXTRA_POD_ALLOWANCE) * fpmChildren
      + (replicas['catalog-grpc'] + EXTRA_POD_ALLOWANCE) * grpcWorkers
      + (replicas['catalog-search-outbox-worker'] + EXTRA_POD_ALLOWANCE) * CONNECTIONS_PER_WORKER_POD
      + (replicas['catalog-search-index-worker'] + EXTRA_POD_ALLOWANCE) * CONNECTIONS_PER_WORKER_POD
      + reserve,
    cart: (replicas['cart-web'] + EXTRA_POD_ALLOWANCE) * fpmChildren + reserve,
  };
  for (const [service, value] of Object.entries(capacity)) {
    if (!Number.isSafeInteger(value) || value < 1) {
      throw new Error(`Calculated PostgreSQL capacity for ${service} is outside the safe integer range.`);
    }
  }
  return capacity;
}

/** @param {PostgresCapacity} capacity @returns {string} */
function renderRuntimeValues(capacity) {
  return [
    'generated:',
    '  postgresCapacity:',
    `    auth: ${capacity.auth}`,
    `    catalog: ${capacity.catalog}`,
    `    cart: ${capacity.cart}`,
    '',
  ].join('\n');
}

/** @param {string} path @param {'Helm values' | 'generated values'} description @returns {YamlMapping} */
function readValuesFile(path, description) {
  if (!existsSync(path)) {
    if (description === 'generated values') {
      throw new Error(`Missing generated values: ${path}. Run npm run k8s:config:sync first.`);
    }
    throw new Error(`Missing Helm values: ${path}`);
  }
  return parseValuesYaml(readFileSync(path, 'utf8'));
}

/** @param {string} output @param {string} content @returns {void} */
function writeRuntimeValues(output, content) {
  mkdirSync(dirname(output), { recursive: true });
  const temporary = `${output}.tmp`;
  try {
    writeFileSync(temporary, content);
    renameSync(temporary, output);
  } finally {
    rmSync(temporary, { force: true });
  }
}

/**
 * Read calculation inputs and atomically replace the runtime values file.
 * @param {{ chartValuesPath: string, generatedValuesPath: string, outputPath: string }} paths
 * @returns {string} Absolute path of the newly generated runtime values file.
 */
export function writePostgresCapacityValues({ chartValuesPath, generatedValuesPath, outputPath }) {
  const output = resolve(outputPath);
  // A failed calculation must never leave a previous result available to Helm.
  rmSync(output, { force: true });

  const chartValues = readValuesFile(chartValuesPath, 'Helm values');
  const generatedValues = readValuesFile(generatedValuesPath, 'generated values');
  const capacity = calculatePostgresCapacity(chartValues, generatedValues);
  writeRuntimeValues(output, renderRuntimeValues(capacity));
  return output;
}

/** @param {string[]} args @returns {{ chartValuesPath: string, generatedValuesPath: string, outputPath: string }} */
function parseArguments(args) {
  if (args.length !== 6 || args[0] !== '--chart-values' || args[2] !== '--generated-values' || args[4] !== '--output') {
    throw new Error('Usage: node scripts/k8s-postgres-capacity.mjs --chart-values <path> --generated-values <path> --output <path>');
  }
  return { chartValuesPath: args[1], generatedValuesPath: args[3], outputPath: args[5] };
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  try {
    const path = writePostgresCapacityValues(parseArguments(process.argv.slice(2)));
    console.log(`Generated ${path}`);
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
