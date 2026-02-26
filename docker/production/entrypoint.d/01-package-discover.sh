#!/bin/sh
# Run package:discover at container start (skipped during Docker build
# because there is no database connection). At this point the DB container
# is already running, so Laravel can boot safely.
php artisan package:discover --ansi 2>/dev/null || true
php artisan event:cache --ansi 2>/dev/null || true

# Ensure required storage directories exist with correct permissions
mkdir -p /var/www/html/storage/app/public/avatars
chown -R www-data:www-data /var/www/html/storage 2>/dev/null || true
