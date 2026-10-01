ARG PHP_BASE_IMAGE=symfony-php:prod

FROM ${PHP_BASE_IMAGE} AS k8s-composer-base

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /workspace
# Symfony Runtime needs this file when build-time console commands boot the kernel.
# Runtime values come from Kubernetes ConfigMaps and Secrets; source .env files
# remain excluded from the Docker build context.
RUN printf 'APP_ENV=prod\n' > /workspace/.env


FROM k8s-composer-base AS k8s-auth-build

COPY symfony/ /workspace/
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader \
    && APP_SECRET=build-only php bin/console assets:install public --env=prod --no-interaction \
    && APP_SECRET=build-only php bin/console importmap:install --env=prod --no-interaction \
    && APP_SECRET=build-only php bin/console asset-map:compile --env=prod --no-interaction \
    && mkdir -p var/cache var/log var/sessions \
    && rm -rf var/cache/*


FROM k8s-composer-base AS k8s-catalog-build

COPY catalog-service/ /workspace/
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader \
    && APP_SECRET=build-only php bin/console assets:install public --env=prod --no-interaction \
    && mkdir -p var/cache var/log \
    && rm -rf var/cache/*


FROM k8s-composer-base AS k8s-cart-build

COPY cart-service/ /workspace/
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader \
    && APP_SECRET=build-only php bin/console assets:install public --env=prod --no-interaction \
    && mkdir -p var/cache var/log \
    && rm -rf var/cache/*


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
