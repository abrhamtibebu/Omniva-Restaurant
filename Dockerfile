# Monorepo entrypoint for Render Docker builds from the repo root.
# If Root Directory is set to `backend`, Render uses backend/Dockerfile instead.
FROM richarvey/nginx-php-fpm:3.1.6

WORKDIR /var/www/html

COPY backend/ .

RUN chmod +x /var/www/html/scripts/*.sh || true

# Image config
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1

# Laravel defaults (overridden by Render env vars)
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

# Allow composer to run as root during deploy scripts
ENV COMPOSER_ALLOW_SUPERUSER=1

CMD ["/start.sh"]
