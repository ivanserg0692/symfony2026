#!/usr/bin/env node

import { spawnSync } from 'node:child_process';
import { loadK8sSettings } from './lib/k8s-settings.mjs';

if (process.argv.length > 2) {
  console.error('Usage: npm run prerequisites:check');
  process.exit(2);
}

let runtime;
try {
  ({ runtime } = loadK8sSettings());
} catch (error) {
  console.error(error.message);
  process.exit(2);
}

const prerequisites = [
  { name: 'Docker', command: 'docker', args: ['--version'] },
  { name: 'Docker Compose', command: 'docker', args: ['compose', 'version'] },
  { name: 'kubectl', command: 'kubectl', args: ['version', '--client'] },
  { name: 'OpenSSL', command: 'openssl', args: ['version'] },
];

if (runtime === 'local') {
  prerequisites.push({ name: 'kind (local runtime)', command: 'kind', args: ['version'] });
} else if (runtime === 'k3s') {
  prerequisites.push({ name: 'k3s (selected runtime)', command: 'k3s', args: ['--version'] });
}

console.log(`Kubernetes runtime: ${runtime}`);

let missing = false;

for (const { name, command, args } of prerequisites) {
  const result = spawnSync(command, args, {
    encoding: 'utf8',
    timeout: 10_000,
    windowsHide: true,
  });

  if (result.status === 0) {
    console.log(`[OK] ${name}`);
    continue;
  }

  missing = true;
  const reason = result.error?.message || result.stderr?.trim() || `exit code ${result.status}`;
  console.error(`[MISSING] ${name}: ${reason}`);
}

if (missing) {
  process.exitCode = 1;
}
