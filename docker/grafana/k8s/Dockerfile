FROM grafana/grafana:latest

COPY docker/grafana/k8s/grafana-datasource.yml /etc/grafana/provisioning/datasources/datasource.yml
COPY docker/grafana/k8s/grafana-provider.yml /etc/grafana/provisioning/dashboards/provider.yml
COPY docker/grafana/k8s/grafana-dashboard.json /etc/grafana/dashboards/kubernetes.json
