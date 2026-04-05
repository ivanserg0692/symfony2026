#!/usr/bin/env bash
set -euo pipefail

export PATH="/root/.symfony5/bin:${PATH}"

if ! command -v symfony >/dev/null 2>&1; then
  echo "Symfony CLI is not available in PATH." >&2
  exit 1
fi

if [[ -n "${GIT_AUTHOR_NAME:-}" ]]; then
  git config --global user.name "${GIT_AUTHOR_NAME}"
fi

if [[ -n "${GIT_AUTHOR_EMAIL:-}" ]]; then
  git config --global user.email "${GIT_AUTHOR_EMAIL}"
fi

if [[ -n "${GIT_COMMITTER_NAME:-}" ]]; then
  export GIT_COMMITTER_NAME
fi

if [[ -n "${GIT_COMMITTER_EMAIL:-}" ]]; then
  export GIT_COMMITTER_EMAIL
fi

exec "$@"
