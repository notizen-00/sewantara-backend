#!/bin/sh

set -eu

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

if [ "${LARAVEL_CACHE_CONFIG:-true}" = "true" ]; then
    gosu www-data php artisan config:cache --no-interaction
    gosu www-data php artisan view:cache --no-interaction
fi

if [ "${1:-}" = "php-fpm" ]; then
    exec "$@"
fi

exec gosu www-data "$@"
