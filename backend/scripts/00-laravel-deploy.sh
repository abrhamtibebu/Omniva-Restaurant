#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

echo "==> Ensuring storage directories exist"
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

echo "==> Composer install (must create vendor/autoload.php)"
composer install \
  --no-dev \
  --no-interaction \
  --prefer-dist \
  --optimize-autoloader \
  --working-dir=/var/www/html

if [ ! -f vendor/autoload.php ]; then
  echo "ERROR: vendor/autoload.php still missing after composer install"
  ls -la
  exit 1
fi

if [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: APP_KEY is not set. Generate one with: php artisan key:generate --show"
  exit 1
fi

echo "==> PHP pgsql extension check"
php -m | grep -i pgsql || echo "WARNING: pgsql extension not loaded"

echo "==> Database driver check"
echo "DB_CONNECTION=${DB_CONNECTION:-<unset>}"
if [ -n "${DB_URL:-}${DATABASE_URL:-}" ]; then
  echo "DB_URL is set"
fi
if [ "${DB_CONNECTION:-}" != "pgsql" ] && [[ "${DB_URL:-${DATABASE_URL:-}}" == postgres* ]]; then
  echo "WARNING: DB_URL is Postgres but DB_CONNECTION is '${DB_CONNECTION:-unset}'. Forcing pgsql for this boot."
  export DB_CONNECTION=pgsql
fi

echo "==> Caching configuration"
php artisan config:cache

echo "==> Caching routes"
php artisan route:cache

echo "==> Caching views"
php artisan view:cache || true

echo "==> Linking public storage"
php artisan storage:link || true

echo "==> Running migrations"
php artisan migrate --force

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
  echo "==> Seeding database (RUN_SEEDERS=true)"
  php artisan db:seed --force
fi

echo "==> Deploy scripts finished — vendor OK"
