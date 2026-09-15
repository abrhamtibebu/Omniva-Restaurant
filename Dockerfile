# Monorepo root Dockerfile for Render (PHP 8.4 — required by composer.lock).
#
# Render settings:
#   Root Directory:  (empty)
#   Dockerfile Path: ./Dockerfile
#   Docker Context:  .
#   PORT:            80

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        bash \
        curl \
        git \
        icu-dev \
        libzip-dev \
        nginx \
        oniguruma-dev \
        postgresql-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && apk del --no-network $PHPIZE_DEPS \
    && apk add --no-cache icu-libs libpq libzip \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY backend/ /var/www/html/
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/start.sh /usr/local/bin/start-omniva.sh

RUN test -f /var/www/html/composer.json \
    && composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts \
        --working-dir=/var/www/html \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x scripts/*.sh /usr/local/bin/start-omniva.sh \
    && chown -R www-data:www-data /var/www/html \
    && test -f /var/www/html/vendor/autoload.php \
    && php -v \
    && php -m | grep -i pdo_pgsql \
    && echo "vendor/autoload.php OK (PHP $(php -r 'echo PHP_VERSION;'))"

ENV PORT=80
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 80

CMD ["/usr/local/bin/start-omniva.sh"]
