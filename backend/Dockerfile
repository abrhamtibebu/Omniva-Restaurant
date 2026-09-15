FROM richarvey/nginx-php-fpm:3.1.6

# Use this Dockerfile only when Render Root Directory = backend.
# Prefer the monorepo-root Dockerfile (../Dockerfile) when context is the repo root.

RUN apk add --no-cache postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) pdo_pgsql \
    && apk del --no-network $PHPIZE_DEPS postgresql-dev \
    && apk add --no-cache libpq

WORKDIR /var/www/html

COPY . /var/www/html

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
    && chmod +x scripts/*.sh \
    && test -f /var/www/html/vendor/autoload.php

ENV PORT=80
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 80

CMD ["/start.sh"]
