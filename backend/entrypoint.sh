#!/bin/bash

composer install --no-interaction --optimize-autoloader

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan config:clear

until php artisan migrate; do
    echo "DB not ready, retrying in 3s..."
    sleep 3
done

exec "$@"
