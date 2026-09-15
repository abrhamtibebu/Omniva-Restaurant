#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

echo "==> Ensuring storage directories exist"
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true

if [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: APP_KEY is not set. Generate one with: php artisan key:generate --show"
  exit 1
fi

# Vendor is baked into the image; refresh only if missing (safety net).
if [ ! -f vendor/autoload.php ]; then
  echo "==> vendor missing — running composer install"
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --working-dir=/var/www/html
fi

echo "==> PHP extensions"
php -m | grep -i pgsql || echo "WARNING: pgsql extension not loaded"

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

echo "==> Deploy scripts finished"
