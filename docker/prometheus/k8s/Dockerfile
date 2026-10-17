FROM prom/prometheus:latest

COPY docker/prometheus/k8s/prometheus.yml /etc/prometheus/prometheus.yml.template
COPY docker/prometheus/k8s/entrypoint.sh /etc/prometheus/entrypoint.sh

ENTRYPOINT ["/bin/sh", "/etc/prometheus/entrypoint.sh"]
