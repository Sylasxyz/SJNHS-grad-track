#!/bin/sh
set -e

PORT="${PORT:-8080}"
cd /var/www/html/laravel

if [ -z "${APP_KEY}" ]; then
  echo "APP_KEY is not set. Generate one with: php artisan key:generate --show" >&2
  exit 1
fi

# A freshly mounted uploads volume starts empty.
mkdir -p ../uploads/announcements ../uploads/profiles

# Railway variables only exist at runtime, so the caches are built on start.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Laravel's router treats the working directory as the public folder.
cd public
exec php -S "0.0.0.0:${PORT}" -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
