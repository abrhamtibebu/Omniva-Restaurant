# Use this only when Render Root Directory = backend.
# Prefer the monorepo-root Dockerfile (PHP 8.4).

FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        bash curl git icu-dev libzip-dev nginx oniguruma-dev postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) bcmath intl mbstring opcache pcntl pdo_pgsql zip \
    && apk del --no-network $PHPIZE_DEPS \
    && apk add --no-cache icu-libs libpq libzip \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html/

# When building from backend/, nginx/start live one level up in the monorepo.
# Prefer building from repo root instead.
COPY conf/nginx/nginx-site.conf /etc/nginx/http.d/default.conf

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x scripts/*.sh \
    && chown -R www-data:www-data /var/www/html \
    && test -f vendor/autoload.php

ENV PORT=80
ENV COMPOSER_ALLOW_SUPERUSER=1
EXPOSE 80

CMD ["bash", "-lc", "sed -i \"s/listen 80;/listen ${PORT:-80};/\" /etc/nginx/http.d/default.conf && ./scripts/00-laravel-deploy.sh && php-fpm -D && exec nginx -g 'daemon off;'"]
