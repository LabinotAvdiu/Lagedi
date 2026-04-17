#!/bin/bash

composer install --no-interaction --optimize-autoloader

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

until php artisan migrate; do
    echo "DB not ready, retrying in 3s..."
    sleep 3
done

# Seed data if the users table is empty (first boot or DB was reset)
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tr -d '[:space:]')
if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
    echo "DB is empty — running seeders..."
    php artisan db:seed --class=TestUserSeeder --force
    php artisan db:seed --class=CompanySeeder --force
    php artisan db:seed --class=AppointmentSeeder --force
    echo "Seeders done."
fi

php artisan optimize

exec "$@"
