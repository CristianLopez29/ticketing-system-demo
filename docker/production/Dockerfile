# Production image. The Sail runtime in compose.yaml is for local development only:
# it bind-mounts the source tree, runs as a dev user and ships Xdebug.
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install     --no-dev     --no-scripts     --no-autoloader     --prefer-dist     --no-interaction

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative


# Pinned to the patch level: a floating tag lets the build cache keep serving a base that
# predates a security release (8.4.27 fixes CVE-2026-109618 and CVE-2026-109619).
FROM php:8.4.27-fpm-alpine AS runtime

RUN apk add --no-cache $PHPIZE_DEPS linux-headers icu-dev oniguruma-dev     && docker-php-ext-install -j"$(nproc)" bcmath pdo_mysql opcache     && pecl install redis     && docker-php-ext-enable redis     && apk del $PHPIZE_DEPS     && rm -rf /tmp/pear

COPY docker/production/php.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app .

RUN chown -R www-data:www-data storage bootstrap/cache     && chmod -R 775 storage bootstrap/cache

USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]


# Nginx image carrying its own copy of public/. A shared volume would be seeded only the
# first time and then go stale on every subsequent deploy; baking it in makes the image
# the single unit of deployment for both containers.
FROM nginx:1.27-alpine AS web

COPY docker/production/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/production/nginx-http.conf /etc/nginx/conf.d/00-limits.conf
COPY public /var/www/html/public
