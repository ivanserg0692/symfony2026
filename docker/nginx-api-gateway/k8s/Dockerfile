ARG NGINX_BASE_IMAGE=symfony-api-gateway-nginx:1.27-vts
FROM ${NGINX_BASE_IMAGE}

COPY api-gateway/nginx/nginx.conf /etc/nginx/nginx.conf
COPY api-gateway/nginx/api-gateway.conf /etc/nginx/conf.d/default.conf
COPY api-gateway/nginx/snippets/ /etc/nginx/snippets/

# The exporter shares this Pod's loopback interface with Nginx. Prometheus
# scrapes VTS through the Pod IP; keep the Compose source config unchanged.
RUN sed -i '/allow 10\.0\.0\.0\/8;/s//allow 127.0.0.0\/8;\n        allow 10.0.0.0\/8;/' /etc/nginx/conf.d/default.conf \
    && sed -i '/location = \/vts\/metrics {/,/^[[:space:]]*}/ { /allow [^;]*;/d; s/deny all;/allow all;/; }' /etc/nginx/conf.d/default.conf
