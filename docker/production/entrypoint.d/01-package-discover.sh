#!/bin/sh
# Run package:discover at container start (skipped during Docker build
# because there is no database connection). At this point the DB container
# is already running, so Laravel can boot safely.
php artisan package:discover --ansi 2>/dev/null || true
php artisan event:cache --ansi 2>/dev/null || true

# Ensure public storage is world-writable (volume may be owned by a different UID on the host)
chmod -R 777 /var/www/html/storage/app/public 2>/dev/null || true
mkdir -p /var/www/html/storage/app/public/avatars 2>/dev/null || true
mkdir -p /var/www/html/storage/app/public/logos 2>/dev/null || true
