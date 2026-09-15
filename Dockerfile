# Build from the monorepo root on Render:
#   Root Directory:   (leave EMPTY)
#   Dockerfile Path:  ./Dockerfile
#   Docker Context:   .
#   PORT:             80
#
# Copies only /backend into the image so vendor/ is at /var/www/html/vendor.

FROM richarvey/nginx-php-fpm:3.1.6

RUN apk add --no-cache postgresql-dev $PHPIZE_DEPS libpq \
    && docker-php-ext-install -j$(nproc) pdo_pgsql \
    && apk del --no-network $PHPIZE_DEPS postgresql-dev \
    && apk add --no-cache libpq

WORKDIR /var/www/html

COPY backend/ /var/www/html/

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
    && test -f /var/www/html/vendor/autoload.php \
    && echo "vendor/autoload.php OK"

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
