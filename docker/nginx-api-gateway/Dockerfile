ARG NGINX_VERSION=1.27.5
ARG NGINX_IMAGE=nginx:${NGINX_VERSION}-alpine
ARG NGINX_MODULE_VTS_VERSION=v0.2.5

FROM ${NGINX_IMAGE} AS vts-builder

ARG NGINX_VERSION
ARG NGINX_MODULE_VTS_VERSION

RUN apk add --no-cache --virtual .build-deps \
        build-base \
        linux-headers \
        openssl-dev \
        pcre2-dev \
        wget \
        zlib-dev

WORKDIR /usr/src

RUN wget -O nginx.tar.gz "https://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz" \
    && tar -zxf nginx.tar.gz \
    && wget -O nginx-module-vts.tar.gz "https://github.com/vozlt/nginx-module-vts/archive/refs/tags/${NGINX_MODULE_VTS_VERSION}.tar.gz" \
    && mkdir nginx-module-vts \
    && tar -zxf nginx-module-vts.tar.gz -C nginx-module-vts --strip-components=1 \
    && cd "nginx-${NGINX_VERSION}" \
    && ./configure --with-compat --add-dynamic-module=../nginx-module-vts \
    && make modules

FROM ${NGINX_IMAGE}

ARG NGINX_VERSION

COPY --from=vts-builder /usr/src/nginx-${NGINX_VERSION}/objs/ngx_http_vhost_traffic_status_module.so /usr/lib/nginx/modules/ngx_http_vhost_traffic_status_module.so
