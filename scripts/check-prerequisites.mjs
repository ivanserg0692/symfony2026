#!/usr/bin/env node

import { spawnSync } from 'node:child_process';

const prerequisites = [
  { name: 'Docker', command: 'docker', args: ['--version'] },
  { name: 'Docker Compose', command: 'docker', args: ['compose', 'version'] },
  { name: 'kubectl', command: 'kubectl', args: ['version', '--client'] },
  { name: 'OpenSSL', command: 'openssl', args: ['version'] },
];

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
