#!/usr/bin/env node

// Explicit secret synchronization. Values travel through stdin to kubectl and
// persist only as Kubernetes Secrets; no secret YAML is written to disk.
import { buildResources, loadConfiguration, run, validateNamespace } from './lib/k8s-config-resources.mjs';

function parseNamespaceOverride(args) {
  if (args.length > 1 || args.some((arg) => arg.startsWith('--'))) {
    throw new Error('Usage: npm run k8s:secrets:sync -- [namespace]');
  }
  if (args[0] && !validateNamespace(args[0])) {
    throw new Error('Invalid Kubernetes namespace: use 1-63 lowercase letters, digits or hyphens.');
  }
  return args[0];
}

function resolveNamespace(namespaceOverride, config) {
  const namespace = namespaceOverride ?? config.projectName;
  if (!validateNamespace(namespace)) {
    throw new Error('Invalid Kubernetes namespace: use 1-63 lowercase letters, digits or hyphens.');
  }
  return namespace;
}

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
  const namespaceOverride = parseNamespaceOverride(process.argv.slice(2));
  const config = loadConfiguration();
  const namespace = resolveNamespace(namespaceOverride, config);

  ensureNamespaces([namespace, 'monitoring']);
  syncSecrets(config, namespace);
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}
