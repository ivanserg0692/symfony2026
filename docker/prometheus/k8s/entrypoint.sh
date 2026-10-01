#!/bin/sh
set -eu

case "${APP_NAMESPACE:-}" in
    ''|*[!a-z0-9-]*|-*|*-)
        echo 'APP_NAMESPACE must be a valid Kubernetes namespace.' >&2
        exit 1
        ;;
esac
if [ "${#APP_NAMESPACE}" -gt 63 ]; then
    echo 'APP_NAMESPACE must be at most 63 characters.' >&2
    exit 1
fi

sed "s/__APP_NAMESPACE__/${APP_NAMESPACE}/g" \
    /etc/prometheus/prometheus.yml.template > /tmp/prometheus.yml

exec /bin/prometheus "$@"
