#!/usr/bin/env node

import { spawnSync } from 'node:child_process';

const args = process.argv.slice(2);
const runtime = args.length === 0 ? 'local'
  : args.length === 2 && args[0] === '--runtime' ? args[1]
    : args.length === 1 && args[0].startsWith('--runtime=') ? args[0].slice('--runtime='.length)
      : null;

if (!['local', 'k3s', 'kubernetes'].includes(runtime)) {
  console.error('Usage: npm run prerequisites:check [-- --runtime k3s|kubernetes]');
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
