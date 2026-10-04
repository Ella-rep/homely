FROM php:8.4-fpm

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json ./
ENV SYMFONY_ENV=prod
RUN mkdir -p vendor

RUN set -eux; \
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates nginx netcat-openbsd postgresql-client \
        libpq-dev libzip-dev libicu-dev libxml2-dev pkg-config \
        g++ make autoconf unzip git; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip intl xml opcache; \
    docker-php-ext-enable xml opcache; \
    apt-get purge -y --auto-remove g++ make autoconf pkg-config; \
    rm -rf /var/lib/apt/lists/* /tmp/*

COPY . .

RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=1'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.file_update_protection=0'; \
} > /usr/local/etc/php/conf.d/opcache.ini

COPY docker/php-upload.ini /usr/local/etc/php/conf.d/zz-upload.ini
COPY docker/php-fpm-socket.conf /usr/local/etc/php-fpm.d/zz-socket.conf

RUN mkdir -p /run/nginx
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
RUN rm -f /etc/nginx/sites-enabled/default

RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var/ \
    && chown -R www-data:www-data public/

COPY docker/entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
