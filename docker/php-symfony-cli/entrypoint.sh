#!/usr/bin/env bash
set -euo pipefail

export PATH="/root/.symfony5/bin:${PATH}"

if command -v git >/dev/null 2>&1; then
  if [[ -n "${GIT_AUTHOR_NAME:-}" ]]; then
    git config --global user.name "${GIT_AUTHOR_NAME}"
  fi

  if [[ -n "${GIT_AUTHOR_EMAIL:-}" ]]; then
    git config --global user.email "${GIT_AUTHOR_EMAIL}"
  fi
fi

if [[ -n "${GIT_COMMITTER_NAME:-}" ]]; then
  export GIT_COMMITTER_NAME
fi

if [[ -n "${GIT_COMMITTER_EMAIL:-}" ]]; then
  export GIT_COMMITTER_EMAIL
fi

exec "$@"
