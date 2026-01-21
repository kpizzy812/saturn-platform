#!/bin/bash
set -e

echo "=== Railway Entrypoint ==="

# Go to app directory
cd /var/www/html

# Verify frontend assets were built
echo "Verifying frontend assets..."
if [ -f "public/build/manifest.json" ]; then
    echo "✓ Frontend assets found (manifest.json exists)"
else
    echo "✗ WARNING: Frontend assets not found! Build may have failed."
    echo "  Expected: public/build/manifest.json"
    ls -la public/build/ 2>/dev/null || echo "  public/build/ directory does not exist"
fi

# Show critical env vars for debugging
echo "CACHE_DRIVER=${CACHE_DRIVER}"
echo "SESSION_DRIVER=${SESSION_DRIVER}"
echo "QUEUE_CONNECTION=${QUEUE_CONNECTION}"

# Clear ALL cached config first
echo "Clearing all caches..."
rm -rf bootstrap/cache/*.php 2>/dev/null || true
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Run migrations
echo "Running migrations..."
php artisan migrate --force || echo "Migration completed or skipped"

# Run seeder
echo "Running RailwaySeeder..."
php artisan db:seed --class=RailwaySeeder --force || echo "Seeder completed or skipped"

# NOW cache config with correct env vars
echo "Caching configuration..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "=== Starting services ==="

# Start supervisor (manages nginx + php-fpm)
exec /usr/bin/supervisord -c /etc/supervisord.conf
