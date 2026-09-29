ARG PHP_BASE_IMAGE=symfony-php:prod

FROM ${PHP_BASE_IMAGE} AS k8s-composer-base

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /workspace


FROM k8s-composer-base AS k8s-auth-build

COPY symfony/ /workspace/
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p var/cache var/log var/sessions


FROM k8s-composer-base AS k8s-catalog-build

COPY catalog-service/ /workspace/
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p var/cache var/log


FROM k8s-composer-base AS k8s-cart-build

COPY cart-service/ /workspace/
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p var/cache var/log


FROM ${PHP_BASE_IMAGE} AS k8s-auth

COPY --from=k8s-auth-build /workspace/ /workspace/
RUN chown -R www-data:www-data /workspace/var
CMD ["symfony-prod-server"]


FROM ${PHP_BASE_IMAGE} AS k8s-catalog

COPY --from=k8s-catalog-build /workspace/ /workspace/
COPY grpc-contracts/catalog/v1/inventory.proto /grpc-contracts/catalog/v1/inventory.proto
RUN chown -R www-data:www-data /workspace/var
CMD ["symfony-prod-server"]


FROM ${PHP_BASE_IMAGE} AS k8s-cart

COPY --from=k8s-cart-build /workspace/ /workspace/
RUN chown -R www-data:www-data /workspace/var
CMD ["symfony-prod-server"]
