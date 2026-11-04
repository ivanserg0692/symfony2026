import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { calculatePostgresCapacity, parseValuesYaml, writePostgresCapacityValues } from './k8s-postgres-capacity.mjs';

const chartValues = parseValuesYaml(readFileSync(new URL('../kubernetes/helm/symfony2026/values.yaml', import.meta.url), 'utf8'));
const generatedValues = {
  generated: {
    postgresConnectionReserve: '20',
    config: {
      auth: { PHP_FPM_MAX_CHILDREN: '10' },
      catalog: { ROADRUNNER_NUM_WORKERS: '12' },
    },
  },
};

test('budgets PostgreSQL for each HTTP HPA upper bound', () => {
  assert.deepEqual(calculatePostgresCapacity(chartValues, generatedValues), { auth: 234, catalog: 262, cart: 230 });
});

test('uses generated overrides and maxReplicas overrides', () => {
  const result = calculatePostgresCapacity(chartValues, {
    generated: {
      postgresConnectionReserve: '0',
      config: { auth: { PHP_FPM_MAX_CHILDREN: '12' } },
    },
    maxReplicas: { 'symfony-web': 4 },
  });
  assert.deepEqual(result, { auth: 64, catalog: 262, cart: 252 });
});

test('uses replicas when they exceed maxReplicas', () => {
  const result = calculatePostgresCapacity(chartValues, {
    ...generatedValues,
    replicas: { 'symfony-web': 21 },
    maxReplicas: { 'symfony-web': 20 },
  });
  assert.deepEqual(result, { auth: 244, catalog: 262, cart: 230 });
});

test('rejects zero and invalid calculation inputs', () => {
  for (const generated of [
    { ...generatedValues, generated: { ...generatedValues.generated, postgresConnectionReserve: '-1' } },
    { ...generatedValues, generated: { ...generatedValues.generated, config: { auth: { PHP_FPM_MAX_CHILDREN: '0' } } } },
  ]) {
    assert.throws(() => calculatePostgresCapacity(chartValues, generated), /must be/);
  }
  assert.throws(() => calculatePostgresCapacity(chartValues, {
    ...generatedValues,
    maxReplicas: { 'symfony-web': 0 },
  }), /maxReplicas\.symfony-web must be a positive integer/);
  assert.throws(() => calculatePostgresCapacity(chartValues, {
    ...generatedValues,
    replicas: { 'symfony-web': 0 },
  }), /replicas\.symfony-web must be a positive integer/);
});

test('rejects YAML syntax it cannot safely parse', () => {
  assert.throws(() => parseValuesYaml('values:\n  - unexpected-list-item\n'), /Unsupported YAML syntax near line 2/);
  assert.throws(() => parseValuesYaml('values:\n  name: "unterminated\n'), /Invalid quoted YAML scalar near line 2/);
  assert.throws(() => parseValuesYaml('values: scalar\n  nested: value\n'), /Unexpected YAML indentation near line 2/);
});

test('removes a stale runtime file when calculation fails', async (context) => {
  const { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync } = await import('node:fs');
  const { tmpdir } = await import('node:os');
  const { join } = await import('node:path');
  const directory = mkdtempSync(join(tmpdir(), 'postgres-capacity-'));
  context.after(() => import('node:fs').then(({ rmSync }) => rmSync(directory, { recursive: true, force: true })));
  const chart = join(directory, 'values.yaml');
  const generated = join(directory, 'generated.yaml');
  const output = join(directory, 'runtime/postgres-capacity-values.yaml');
  mkdirSync(join(directory, 'runtime'));
  writeFileSync(chart, readFileSync(new URL('../kubernetes/helm/symfony2026/values.yaml', import.meta.url)));
  writeFileSync(generated, 'generated:\n  postgresConnectionReserve: "bad"\n');
  writeFileSync(output, 'generated:\n  postgresCapacity:\n    auth: 64\n');
  assert.throws(() => writePostgresCapacityValues({ chartValuesPath: chart, generatedValuesPath: generated, outputPath: output }));
  assert.equal(existsSync(output), false);
});
