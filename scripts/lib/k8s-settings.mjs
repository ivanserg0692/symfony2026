import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const commonScript = fileURLToPath(new URL('./k8s-common.sh', import.meta.url));
const repoRoot = fileURLToPath(new URL('../../', import.meta.url));

export function loadK8sSettings() {
  const result = spawnSync('bash', [
    '-c',
    'set -euo pipefail; source "$1"; ensure_k8s_settings; printf "%s\\n%s\\n" "$runtime" "$namespace"',
    'bash',
    commonScript,
  ], {
    cwd: repoRoot,
    encoding: 'utf8',
    env: process.env,
  });

  if (result.status !== 0) {
    throw new Error(result.stderr?.trim() || 'Cannot load Kubernetes environment settings.');
  }

  const [runtime, namespace] = result.stdout.trimEnd().split('\n');
  return { runtime, namespace };
}
