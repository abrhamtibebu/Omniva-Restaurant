FROM richarvey/nginx-php-fpm:3.1.6

# PostgreSQL PDO (required for Render Postgres)
RUN apk add --no-cache postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) pdo_pgsql \
    && apk del $PHPIZE_DEPS postgresql-dev \
    && apk add --no-cache libpq

WORKDIR /var/www/html

COPY . /var/www/html

# Install PHP deps into the image so requests never hit a missing vendor/
RUN composer install \
      --no-dev \
      --no-interaction \
      --prefer-dist \
      --optimize-autoloader \
      --working-dir=/var/www/html \
    && mkdir -p \
      storage/framework/cache \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x scripts/*.sh

# Image config — document root MUST be Laravel's public/
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV COMPOSER_ALLOW_SUPERUSER=1

CMD ["/start.sh"]
