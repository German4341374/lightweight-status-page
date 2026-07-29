FROM composer:2.10.2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

FROM php:8.5.8-fpm-alpine3.23 AS runtime

RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && apk del .build-deps

WORKDIR /var/www/html
COPY --from=dependencies /app/vendor ./vendor
COPY bin ./bin
COPY bootstrap ./bootstrap
COPY migrations ./migrations
COPY public ./public
COPY src ./src
COPY templates ./templates
COPY composer.json composer.lock ./
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-status-page.ini
COPY docker/php/entrypoint.sh /usr/local/bin/status-page-entrypoint

RUN chmod +x /usr/local/bin/status-page-entrypoint \
    && mkdir -p storage \
    && chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000

HEALTHCHECK --interval=10s --timeout=3s --start-period=20s --retries=6 \
  CMD php -r "exit(@fsockopen('127.0.0.1', 9000) ? 0 : 1);"

ENTRYPOINT ["status-page-entrypoint"]
CMD ["php-fpm", "-F"]
