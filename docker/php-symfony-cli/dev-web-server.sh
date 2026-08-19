#!/usr/bin/env bash
set -euo pipefail

export PATH="/root/.symfony5/bin:${PATH}"

symfony server:stop || true
symfony serve --allow-http --no-tls --listen-ip=0.0.0.0 --port=8000 --daemon
exec tail -f /dev/null
