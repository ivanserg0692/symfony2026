#!/usr/bin/env node

// Explicit secret synchronization. Values travel through stdin to kubectl and
// persist only as Kubernetes Secrets; no secret YAML is written to disk.
import { buildResources, loadConfiguration, run } from './lib/k8s-config-resources.mjs';
import { loadK8sSettings } from './lib/k8s-settings.mjs';

function ensureNamespace(name) {
  if (!run('kubectl', ['get', 'namespace', name, '--ignore-not-found', '-o', 'name']).trim()) {
    run('kubectl', ['create', 'namespace', name]);
  }
}

function ensureNamespaces(names) {
  for (const name of new Set(names)) {
    ensureNamespace(name);
  }
}

function syncSecrets(config, namespace) {
  for (const secret of buildResources(config, namespace, { includeSecrets: true })) {
    run('kubectl', ['-n', secret.metadata.namespace, 'apply', '-f', '-'], JSON.stringify(secret));
    console.log(`Synchronized Secret/${secret.metadata.name} in ${secret.metadata.namespace}`);
  }
}

try {
  if (process.argv.length > 2) {
    throw new Error('Usage: npm run k8s:secrets:sync');
  }
  const { namespace, imageProfile } = loadK8sSettings();
  const config = loadConfiguration(imageProfile);

  ensureNamespaces([namespace, 'monitoring']);
  syncSecrets(config, namespace);
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}
