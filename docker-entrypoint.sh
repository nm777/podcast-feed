#!/bin/sh

# Run database migrations
php artisan migrate --force

# Start PHP-FPM
php-fpm
