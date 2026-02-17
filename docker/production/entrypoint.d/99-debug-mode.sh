# Debug mode — install dev dependencies without running artisan scripts.
# Laravel cannot fully bootstrap inside the entrypoint context (no DB yet,
# $this->laravel is null), so --no-scripts is required to skip package:discover.
if [ "$APP_DEBUG" = "true" ]; then
    echo "Debug mode is enabled"
    echo "Installing development dependencies..."
    composer install --dev --no-interaction --no-progress --no-scripts
fi
