#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

PORT="${PORT:-80}"
echo "==> Binding nginx to port ${PORT}"
sed -i "s/listen 80;/listen ${PORT};/" /etc/nginx/http.d/default.conf

# Ensure writable dirs on ephemeral filesystem
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache || true
chown -R www-data:www-data storage bootstrap/cache || true

echo "==> Running deploy script"
./scripts/00-laravel-deploy.sh

echo "==> Starting php-fpm"
php-fpm -D

echo "==> Starting nginx on :${PORT}"
exec nginx -g 'daemon off;'
