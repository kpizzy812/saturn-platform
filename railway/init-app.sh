#!/bin/bash
# Railway Init Script for Saturn Platform
# Don't use set -e - we want to continue even if some commands fail

echo "=== Railway Init Script ==="

# Run migrations first
echo "Running migrations..."
php artisan migrate --force || echo "Migration completed or skipped"

# Create sessions table if not exists
php artisan session:table 2>/dev/null || true
php artisan migrate --force 2>/dev/null || true

# Run Railway seeder to create required records
echo "Running RailwaySeeder..."
php artisan db:seed --class=RailwaySeeder --force || echo "Seeder completed or skipped"

# Clear and rebuild caches
echo "Optimizing application..."
php artisan optimize:clear 2>/dev/null || true
php artisan config:cache || true
php artisan event:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "=== Init complete ==="
