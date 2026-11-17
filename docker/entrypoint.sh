#!/bin/sh
set -eu

# Platforms such as Railway/Render inject PORT. Apache's default config listens
# on 80, so rewrite both the listener and the vhost before starting it.
PORT="${PORT:-80}"
sed -ri "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

ROLE="${ROLE:-web}"

if [ "$ROLE" = "worker" ]; then
    exec php artisan queue:work --sleep=2 --tries=8 --timeout=60
fi

if [ "$ROLE" = "scheduler" ]; then
    exec php artisan schedule:work
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force

    if [ "${RUN_SEEDER:-false}" = "true" ]; then
        php artisan db:seed --force
    fi

    if [ "${RUN_DEMO_GENERATIONS:-false}" = "true" ]; then
        php artisan pixelforge:demo --force
    fi
fi

exec "$@"
